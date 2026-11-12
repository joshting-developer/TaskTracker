<?php

namespace App\Services\Integrations;

use App\Data\IssueChangesQueryData;
use App\Data\WorkspaceContext;
use App\Repositories\IssueReadRepository;
use App\Services\ProjectReadService;

/** 同Project的有界live增量摘要；每批先驗當前權限，不保存授權快照。 */
class IssueChangeService
{
    /**
     * @param ProjectReadService $projects 既有Project授權用例
     * @param IssueReadRepository $issues 共用篩選與最小投影
     * @param IssueCursorCodec $cursors 認證加密接續識別
     */
    public function __construct(private ProjectReadService $projects, private IssueReadRepository $issues, private IssueCursorCodec $cursors) {}

    /**
     * @param WorkspaceContext $context 當次資格
     * @param string $projectId Project公開ID
     * @param IssueChangesQueryData $query 已驗證的查詢
     * @return array{items: \Illuminate\Database\Eloquent\Collection,perPage:int,nextCursor:?string,syncUntil:string} 有界摘要及水位，沒有COUNT
     */
    public function read(WorkspaceContext $context, string $projectId, IssueChangesQueryData $query): array
    {
        $project = $this->projects->require($context, $projectId, 'issue.read');
        $binding = hash('sha256', json_encode([$context->userId, $context->workspaceId, $projectId, $query->filters, $query->updatedSince], JSON_THROW_ON_ERROR));
        $position = $query->cursor === '' ? null : $this->cursors->decode($query->cursor, $binding);
        $until = $position['until'] ?? now('UTC')->toISOString();
        $size = $query->filters->pagination->perPage;
        $rows = $this->issues->changes($project, $context->userId, $query->filters, $query->updatedSince, $until, $position);
        $hasMore = $rows->count() > $size;
        $rows = $rows->take($size);
        $last = $rows->last();
        $cursor = $hasMore ? $this->cursors->encode(['version' => 1, 'binding' => $binding, 'until' => $until, 'time' => $last->updated_at, 'id' => (int) $last->id]) : null;

        return ['items' => $rows, 'perPage' => $size, 'nextCursor' => $cursor, 'syncUntil' => $until];
    }
}
