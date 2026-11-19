<?php

namespace App\Services;

use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Factories\RecordStoreFactory;
use App\Repositories\BoardPositionRepository;
use App\Repositories\BoardReadRepository;

/** TT-428：看板欄位內的手動排序, 排序以看板為範圍, 不改卡片內容、狀態或 revision */
class BoardPositionService
{
    public const MAX_CARDS = 500;

    /** 注入授權、看板與排序資料入口
     * @param  ProjectReadService  $projects  Project 可見性與權限
     * @param  BoardReadRepository  $boards  同 Project 未封存看板
     * @param  BoardPositionRepository  $positions  排序資料
     * @param  RecordStoreFactory  $records  Workspace receipt 儲存入口
     * @param  RequestReplay  $replay  同 requestId 重送回傳第一次結果
     */
    public function __construct(private ProjectReadService $projects, private BoardReadRepository $boards, private BoardPositionRepository $positions, private RecordStoreFactory $records, private RequestReplay $replay) {}

    /** 依前端送來的欄位完整順序改寫位置；卡片必須都在這個看板內
     * @param  WorkspaceContext  $context  交易內重驗的身分與工作區
     * @param  array<string,mixed>  $input  projectId、boardId、issueIds、requestId
     * @return array{projectId:string,boardId:string,positions:list<array{id:string,position:int}>} 新位置
     */
    public function reorder(WorkspaceContext $context, array $input): array
    {
        $access = $this->projects->authorized($context, Fields::text($input['projectId'] ?? null, '專案', 100, true), 'issue.write');
        $project = $access->project;
        $boardId = Fields::text($input['boardId'] ?? null, '看板', 100, true);
        $board = null;
        if ($boardId !== 'default') {
            $board = $this->boards->find($project, $boardId) ?? DomainError::fail('NOT_FOUND', '找不到這個專案內的看板');
            if ($board->archived_at !== null) {
                DomainError::fail('NOT_FOUND', '看板已封存, 請先還原');
            }
        }
        $ids = $input['issueIds'] ?? null;
        if (! is_array($ids) || ! array_is_list($ids) || $ids === [] || count($ids) > self::MAX_CARDS) {
            DomainError::fail('INVALID_INPUT', '一次最多排序 500 張卡片');
        }
        $ids = array_map(fn ($id) => Fields::text($id, '卡片', 100, true), $ids);
        if (count(array_unique($ids)) !== count($ids)) {
            DomainError::fail('INVALID_INPUT', '排序的卡片不可重複');
        }

        return $this->replay->execute($this->records->forWorkspace($context->workspaceId), 'requests', $context->userId, 'reorderBoardCards', $input, function () use ($project, $board, $ids) {
            $found = $this->positions->boardIssueIds($project, $board, $ids);
            if (count($found) !== count($ids)) {
                DomainError::fail('DATA_CHANGED', '有卡片已不在這個看板, 請重新整理後再排序');
            }
            $this->positions->save($project, $board, array_map(fn ($id) => $found[$id], $ids));

            return ['projectId' => $project->public_id, 'boardId' => $board?->public_id ?? 'default',
                'positions' => array_map(fn ($id, $index) => ['id' => $id, 'position' => ($index + 1) * 1024], $ids, array_keys($ids))];
        });
    }
}
