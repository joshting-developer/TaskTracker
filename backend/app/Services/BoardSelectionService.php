<?php

namespace App\Services;

use App\Data\BoardRuleData;
use App\Data\BoardSelectionData;
use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Models\Issue;
use App\Models\Project;
use App\Repositories\BoardSelectionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 看板規則與手動排除的驗證用例, 交易由既有 Workspace 邊界擁有 */
class BoardSelectionService
{
    /** 注入穩定資料入口及預覽資格檢查
     * @param  BoardSelectionRepository  $selections  同專案有界存取
     * @param  ProjectReadService  $projects  同專案有效 permission
     */
    public function __construct(private BoardSelectionRepository $selections, private ProjectReadService $projects) {}

    /** 保留既有 RPC array 相容入口, 新規則以 typed Data 進入資料入口
     * @param  string  $workspaceId  交易內重驗的工作區 ID
     * @param  string  $projectId  已授權專案 ID
     * @param  string  $boardId  已確認版本的看板 ID
     * @param  array<string,mixed>  $input  既有寫入邊界的原始欄位
     * @return void
     */
    public function apply(string $workspaceId, string $projectId, string $boardId, array $input): void
    {
        if (! array_intersect(array_keys($input), ['autoInclude', 'excludedIssueIds', 'excludeIssueIds', 'unexcludeIssueIds'])) {
            return;
        }
        $project = $this->selections->project($workspaceId, $projectId) ?? DomainError::fail('NOT_FOUND', '找不到目前專案');
        $board = $this->selections->board($project, $boardId) ?? DomainError::fail('NOT_FOUND', '找不到這個專案內的看板');
        $current = $this->selections->selection($project, $board);
        $rules = array_key_exists('autoInclude', $input) ? $this->rules($input['autoInclude']) : $current->autoInclude;
        $excluded = array_key_exists('excludedIssueIds', $input) ? $this->ids($input['excludedIssueIds'], 1000) : $current->excludedIssueIds;
        $add = $this->ids($input['excludeIssueIds'] ?? [], 200);
        $remove = $this->ids($input['unexcludeIssueIds'] ?? [], 200);
        if (array_intersect($add, $remove)) {
            DomainError::fail('INVALID_INPUT', '同一項目不可同時排除與取消排除');
        }
        if ($this->selections->issueCount($project, array_values(array_unique([...$add, ...$remove]))) !== count(array_unique([...$add, ...$remove]))) {
            DomainError::fail('INVALID_INPUT', '只能調整目前專案的卡片');
        }
        $excluded = $this->ids(array_values(array_unique([...array_diff($excluded, $remove), ...$add])), 1000);
        $selection = new BoardSelectionData($rules, $current->selectedIssueIds, $excluded);
        $this->validate($project, $selection, array_key_exists('autoInclude', $input));
        $this->selections->save($project, $board, $selection);
    }

    /** 預覽只讀當前有效管理資格可設定的同 Project 內容
     * @param  WorkspaceContext  $context  交易內重驗的身分
     * @param  string  $projectId  Project 公開 ID
     * @param  BoardSelectionData  $selection  已通過傳輸驗證的純值
     * @return LengthAwarePaginator<Issue> 首頁最多 20 筆與總數
     */
    public function preview(WorkspaceContext $context, string $projectId, BoardSelectionData $selection): LengthAwarePaginator
    {
        $project = $this->projects->require($context, $projectId, 'board.manage');
        $this->validate($project, $selection);

        return $this->selections->preview($project, $selection);
    }

    /** 驗證同 Project 的真實參照, 不把前端 ID 當資格
     * @param  Project  $project  已授權專案
     * @param  BoardSelectionData  $selection  收錄快照
     * @param  bool  $validateRules  新規則需驗有效定義, 未改動舊規則可保留已移除 Tag 而不放寬
     * @return void
     */
    private function validate(Project $project, BoardSelectionData $selection, bool $validateRules = true): void
    {
        $ids = array_values(array_unique([...$selection->selectedIssueIds, ...$selection->excludedIssueIds]));
        if ($this->selections->issueCount($project, $ids) !== count($ids)) {
            DomainError::fail('INVALID_INPUT', '只能調整目前專案的卡片');
        }
        $rules = $selection->autoInclude;
        if ($validateRules && $rules !== null && ($this->selections->tagCount($project, $rules->tagIds) !== count($rules->tagIds) || $this->selections->stateCount($project, $rules->states) !== count($rules->states))) {
            DomainError::fail('INVALID_INPUT', '自動收錄條件包含已移除或其他專案的標籤或狀態');
        }
    }

    /** RPC 相容邊界驗證規則格式, null 明確停用自動收錄
     * @param  mixed  $value  原始條件
     * @return BoardRuleData|null 有界且型別明確的條件
     */
    private function rules(mixed $value): ?BoardRuleData
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value) || array_diff(array_keys($value), ['tagIds', 'types', 'states'])) {
            DomainError::fail('INVALID_INPUT', '自動收錄條件格式不正確');
        }
        $tags = $this->ids($value['tagIds'] ?? [], 20);
        $types = $this->ids($value['types'] ?? [], 3);
        $states = $this->ids($value['states'] ?? [], 30);
        if (array_diff($types, ['epic', 'task', 'bug'])) {
            DomainError::fail('INVALID_INPUT', '自動收錄卡片類型不正確');
        }

        return new BoardRuleData($tags, $types, $states);
    }

    /** 保留字串 ID 契約並排序去歧義, 超額或重複拒絕整筆寫入
     * @param  mixed  $value  原始集合
     * @param  int  $limit  本欄位上限
     * @return list<string> 已驗證且排序的 ID
     */
    private function ids(mixed $value, int $limit): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > $limit) {
            DomainError::fail('INVALID_INPUT', '看板條件項目超過上限或格式不正確');
        }
        $ids = array_map(fn ($id) => Fields::text($id, '看板條件項目', 100, true), $value);
        if (count(array_unique($ids)) !== count($ids)) {
            DomainError::fail('INVALID_INPUT', '看板條件項目不可重複');
        }
        sort($ids);

        return $ids;
    }
}
