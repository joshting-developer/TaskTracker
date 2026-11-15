<?php

namespace App\Services;

use App\Data\PageQueryData;
use App\Data\TagWriteScope;
use App\Data\WorkspaceContext;
use App\Database\DatabaseTimes;
use App\Domain\DomainError;
use App\Factories\ProjectSignalsFactory;
use App\Factories\RecordStoreFactory;
use App\Models\ProjectTag;
use App\Repositories\TagRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/** 專案標籤規則與卡片差量套用, Session／PAT 共用且先授權再重播 */
class TagService
{
    public const MAX_PROJECT_TAGS = 200;

    public const MAX_ISSUE_TAGS = 20;

    /** 注入專案授權、標籤存取、重播與訊號工廠
     *
     * @param  ProjectReadService  $projects  當次專案資格
     * @param  TagRepository  $tags  有界標籤資料入口
     * @param  RecordStoreFactory  $stores  可信租戶 receipt 入口
     * @param  RequestReplay  $replay  同 requestId 重播
     * @param  ProjectSignalsFactory  $signals  當次租戶廣播
     */
    public function __construct(private ProjectReadService $projects, private TagRepository $tags, private RecordStoreFactory $stores, private RequestReplay $replay, private ProjectSignalsFactory $signals) {}

    /** 分頁取得有權限專案的有效標籤
     *
     * @param  WorkspaceContext  $context  可信身分
     * @param  string  $projectId  專案公開 ID
     * @param  PageQueryData  $filters  有界搜尋與分頁
     * @return LengthAwarePaginator<ProjectTag> 有效定義
     */
    public function index(WorkspaceContext $context, string $projectId, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->tags->page($this->projects->require($context, $projectId, 'issue.read'), $filters);
    }

    /** 取得有權限專案的單一有效定義
     *
     * @param  WorkspaceContext  $context  可信身分
     * @param  string  $projectId  專案公開 ID
     * @param  string  $id  定義公開 ID
     * @return ProjectTag 單一定義, 不讀卡片全集
     */
    public function show(WorkspaceContext $context, string $projectId, string $id): ProjectTag
    {
        $project = $this->projects->require($context, $projectId, 'issue.read');

        return $this->tags->find($project, $id) ?? DomainError::fail('NOT_FOUND', '找不到這個專案的標籤');
    }

    /** 重播前仍驗當次權限與目標資源, 不先讀 receipt
     * @param  WorkspaceContext  $context  可信身分
     * @param  string  $projectId  專案公開 ID
     * @param  string  $action  伺服器指定 create／update／delete／apply
     * @param  ?string  $target  標籤或卡片公開 ID
     * @return TagWriteScope 僅同一交易有效的資源
     */
    public function authorize(WorkspaceContext $context, string $projectId, string $action, ?string $target): TagWriteScope
    {
        if (! in_array($action, ['create', 'update', 'delete', 'apply'], true)) {
            DomainError::fail('NOT_FOUND', '找不到標籤操作');
        }
        $project = $this->projects->require($context, $projectId, $action === 'apply' ? 'issue.write' : 'project.tags.manage');
        $tag = $issue = null;
        if ($action === 'apply') {
            $issue = $this->tags->issue($project, $target ?? '') ?? DomainError::fail('NOT_FOUND', '找不到這個專案的任務');
        } elseif ($action !== 'create') {
            $tag = $this->tags->find($project, $target ?? '', true) ?? DomainError::fail('NOT_FOUND', '找不到這個專案的標籤');
        }

        return new TagWriteScope($project, $tag, $issue);
    }

    /** Session 入口自行擁有 receipt, 公開 PAT 的 receipt 由 Bearer 邊界擁有
     * @param  WorkspaceContext  $context  可信身分
     * @param  string  $projectId  專案公開 ID
     * @param  string  $action  伺服器操作
     * @param  ?string  $target  目標公開 ID
     * @param  array<string,mixed>  $input  已驗證輸入
     * @return array<string,mixed> 有界寫入結果
     */
    public function sessionWrite(WorkspaceContext $context, string $projectId, string $action, ?string $target, array $input): array
    {
        $scope = $this->authorize($context, $projectId, $action, $target);

        return $this->replay->execute($this->stores->forContext($context), 'requests', $context->userId, 'session.tags.'.$action, ['projectId' => $projectId, 'target' => $target, ...$input], fn () => $this->execute($context, $scope, $action, $input));
    }

    /** 執行已在同交易授權的寫入, 不能從外部 payload 建立 scope
     * @param  WorkspaceContext  $context  可信身分
     * @param  TagWriteScope  $scope  同交易已授權資源
     * @param  string  $action  伺服器操作
     * @param  array<string,mixed>  $input  已驗證輸入
     * @return array<string,mixed> ID 與版本
     */
    public function execute(WorkspaceContext $context, TagWriteScope $scope, string $action, array $input): array
    {
        if ($action === 'apply') {
            return $this->apply($context, $scope, $input);
        }
        $tag = $scope->tag;
        if ($tag && (int) $tag->revision !== $input['expectedRevision']) {
            DomainError::fail('REVISION_CONFLICT', '標籤已更新, 請重新載入');
        }
        if ($tag?->deleted_at !== null) {
            DomainError::fail('NOT_FOUND', '找不到這個專案的標籤');
        }
        $at = DatabaseTimes::store(now()->toISOString());
        if ($action === 'delete') {
            $this->tags->save($tag, ['deleted_at' => $at, 'updated_at' => $at, 'name_key' => 'deleted:'.$tag->public_id, 'revision' => $tag->revision + 1]);
            $this->tags->detachTag($tag);
        } else {
            $name = trim($input['name'] ?? $tag?->name ?? '');
            if ($name === '') {
                DomainError::fail('INVALID_INPUT', '請輸入標籤名稱');
            }
            $data = ['name' => $name, 'name_key' => mb_strtolower($name), 'color' => strtolower($input['color'] ?? $tag?->color ?? '#6653bd'), 'description' => trim($input['description'] ?? $tag?->description ?? '')];
            if ($this->tags->nameExists($scope->project, $data['name_key'], $tag?->id)) {
                DomainError::fail('INVALID_INPUT', '這個專案已有同名標籤');
            }
            if (! $tag) {
                if ($this->tags->count($scope->project) >= self::MAX_PROJECT_TAGS) {
                    DomainError::fail('INVALID_INPUT', '每個專案最多 200 個標籤');
                }
                $tag = $this->tags->create($scope->project, [...$data, 'public_id' => (string) Str::uuid(), 'revision' => 1, 'created_at' => $at, 'updated_at' => $at]);
            } else {
                $this->tags->save($tag, [...$data, 'updated_at' => $at, 'revision' => $tag->revision + 1]);
            }
        }
        $this->tags->touchProject($scope->project);
        $this->signals->forContext($context)->changed($scope->project->public_id);

        return ['id' => $tag->public_id, 'projectId' => $scope->project->public_id, 'revision' => (int) $tag->revision];
    }

    /** 檢查卡片目前版本後套用明確差量, 空操作不增加版本或事件
     * @param  WorkspaceContext  $context  可信身分
     * @param  TagWriteScope  $scope  已授權單一卡片
     * @param  array<string,mixed>  $input  差量與版本
     * @return array{id:string,projectId:string,identifier:string,revision:int} 有界卡片結果
     */
    private function apply(WorkspaceContext $context, TagWriteScope $scope, array $input): array
    {
        $issue = $scope->issue;
        if ((int) $issue->revision !== $input['expectedRevision']) {
            DomainError::fail('REVISION_CONFLICT', '任務已更新, 請重新載入');
        }
        $add = $input['addTagIds'] ?? [];
        $remove = $input['removeTagIds'] ?? [];
        if (array_intersect($add, $remove)) {
            DomainError::fail('INVALID_INPUT', '相同標籤不能同時新增與移除');
        }
        $references = $this->tags->references($scope->project, $add);
        if ($references->count() !== count($add)) {
            DomainError::fail('NOT_FOUND', '找不到這個專案的標籤');
        }
        $before = $issue->getRelation('tags')->pluck('public_id')->sort()->values()->all();
        $after = array_values(array_unique([...array_diff($before, $remove), ...$add]));
        sort($after);
        if (count($after) > self::MAX_ISSUE_TAGS) {
            DomainError::fail('INVALID_INPUT', '每張任務最多 20 個標籤');
        }
        if ($before !== $after) {
            $brief = fn ($tag) => ['id' => $tag->public_id, 'name' => $tag->name, 'color' => $tag->color];
            $previousTags = $issue->getRelation('tags')->map($brief)->sortBy('id')->values()->all();
            $all = $issue->getRelation('tags')->concat($references)->unique('public_id');
            $nextTags = $all->filter(fn ($tag) => in_array($tag->public_id, $after, true))->map($brief)->sortBy('id')->values()->all();
            $this->tags->apply($issue, $references, $remove, ['id' => (string) Str::uuid(), 'kind' => 'updated', 'actor' => $context->legacyIdentity()['user']['name'], 'at' => now()->toISOString(), 'changes' => [['field' => 'tags', 'before' => $previousTags, 'after' => $nextTags]]]);
            $this->signals->forContext($context)->changed($scope->project->public_id);
        }

        return ['id' => $issue->public_id, 'projectId' => $scope->project->public_id, 'identifier' => $issue->identifier, 'revision' => (int) $issue->revision];
    }
}
