<?php

namespace App\Services\Integrations;

use App\Data\IssueChangesQueryData;
use App\Data\WorkspaceContext;
use App\Repositories\IntegrationAuditReadRepository;
use App\Repositories\IssueDeletionReadRepository;
use App\Repositories\IssueReadRepository;
use App\Services\ProjectReadService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/** 同Project的有界live增量摘要；每批先驗當前權限，不保存授權快照。 */
class IssueChangeService
{
    /**
     * 組合當次Project授權、共用摘要查詢與cursor接續，不保存權限快照。
     *
     * @param  ProjectReadService  $projects  既有Project授權用例
     * @param  IssueReadRepository  $issues  共用篩選與最小投影
     * @param  IssueCursorCodec  $cursors  認證加密接續識別
     * @param  IntegrationAuditReadRepository  $audits  來源稽核查詢（回音辨識）
     * @param  IssueDeletionReadRepository  $deletions  刪除墓碑事件讀取
     */
    public function __construct(private ProjectReadService $projects, private IssueReadRepository $issues, private IssueCursorCodec $cursors, private IntegrationAuditReadRepository $audits, private IssueDeletionReadRepository $deletions) {}

    /**
     * 組合當次Project授權、共用摘要查詢與cursor接續，不保存權限快照。
     *
     * @param  WorkspaceContext  $context  當次資格
     * @param  string  $projectId  Project公開ID
     * @param  IssueChangesQueryData  $query  已驗證的查詢
     * @param  string|null  $tokenId  目前Bearer Token ID，用來判斷sameToken
     * @return array{items: Collection,perPage:int,nextCursor:?string,syncUntil:string,lastWrites:array<string,array{sameToken:bool,sameUser:bool,clientReference:?string}>} 有界摘要、水位與回音資訊，沒有COUNT
     */
    public function read(WorkspaceContext $context, string $projectId, IssueChangesQueryData $query, ?string $tokenId = null): array
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

        // 本輪項目仍到until為止；下一輪水位往前退settle秒，重讀邊界內較晚提交的寫入（以id／revision去重）。
        $settled = CarbonImmutable::parse($until)->subSeconds(max(0, (int) config('tasktracker.sync.settle_seconds', 10)));
        if ($query->updatedSince !== null && $settled->lessThan(CarbonImmutable::parse($query->updatedSince))) {
            $settled = CarbonImmutable::parse($query->updatedSince);
        }

        // TT-176：只有目前revision由公開API寫入產生時才回lastWrite；不揭露他人Token ID或參照。
        $writes = $this->audits->tokenWrites($project, $rows->mapWithKeys(fn ($row) => [$row->public_id => (int) $row->revision])->all());
        $lastWrites = array_map(fn ($write) => ['sameToken' => $tokenId !== null && $write['tokenId'] === $tokenId, 'sameUser' => $write['userId'] === $context->userId,
            'clientReference' => $write['userId'] === $context->userId ? $write['clientReference'] : null], $writes);

        return ['items' => $rows, 'perPage' => $size, 'nextCursor' => $cursor, 'syncUntil' => $settled->utc()->toISOString(), 'lastWrites' => $lastWrites];
    }

    /**
     * TT-250：Project的卡片刪除墓碑，供鏡像同步移除本地資料；需issue.read，依事件id遞增分頁。
     *
     * @param  WorkspaceContext  $context  Token重驗後的身分與有效權限
     * @param  string  $projectId  Project公開ID
     * @param  int|null  $after  上一頁回傳的nextAfter
     * @param  int  $limit  每頁筆數（1–100）
     * @return array{items:array<int,array{issueId:string,identifier:string,deletedAt:string}>,nextAfter:string|null} 墓碑摘要，不含標題外內容
     */
    public function deletions(WorkspaceContext $context, string $projectId, ?int $after, int $limit): array
    {
        $project = $this->projects->require($context, $projectId, 'issue.read');
        $rows = $this->deletions->page($project, $after, $limit);
        $more = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return ['items' => $rows->map(function ($row) {
            $event = json_decode($row->event_data, true, 8, JSON_THROW_ON_ERROR);

            return ['issueId' => $event['issueId'], 'identifier' => $event['identifier'], 'deletedAt' => $event['at']];
        })->values()->all(), 'nextAfter' => $more ? (string) $rows->last()->id : null];
    }
}
