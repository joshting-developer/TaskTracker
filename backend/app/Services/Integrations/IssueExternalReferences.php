<?php

namespace App\Services\Integrations;

use App\Data\AuthorizedProjectData;
use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Models\Issue;
use App\Repositories\IssueExternalRefRepository;
use App\Services\IssueReadService;
use App\Services\ProjectReadService;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\DB;

/** TT-171：以Project範圍外部識別查詢或upsert卡片；授權、revision與卡片規則沿既有用例。 */
class IssueExternalReferences
{
    public const SOURCE_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,39}$/';

    public const EXTERNAL_ID_PATTERN = '/^[A-Za-z0-9._~:@-]{1,200}$/';

    /**
     * 注入對照資料入口與共用卡片讀寫用例。
     *
     * @param  IssueExternalRefRepository  $refs  外部識別對照
     * @param  ProjectReadService  $projects  Project可見與permission檢查
     * @param  IssueReadService  $issues  單卡讀取
     * @param  WorkspaceService  $workspaces  共用卡片寫入
     */
    public function __construct(private IssueExternalRefRepository $refs, private ProjectReadService $projects, private IssueReadService $issues, private WorkspaceService $workspaces) {}

    /**
     * 依外部識別讀完整單卡；格式不符、未綁定或無權一律404，不洩漏存在與否。
     *
     * @param  WorkspaceContext  $context  交易內重驗的資格
     * @param  string  $projectId  Project公開ID
     * @param  string  $source  外部來源代號
     * @param  string  $externalId  外部識別
     * @return Issue 完整單卡
     */
    public function show(WorkspaceContext $context, string $projectId, string $source, string $externalId): Issue
    {
        $access = $this->projects->authorized($context, $projectId, 'issue.read');
        $issueId = self::valid($source, $externalId) ? $this->refs->issueId($access->project, $source, $externalId) : null;

        return $this->issues->show($context, $projectId, $issueId ?? DomainError::fail('NOT_FOUND', '找不到這個外部識別對應的任務'));
    }

    /**
     * 未綁定時建立並綁定；已綁定時要求expectedRevision後更新同一張卡。須在Bearer writeOnce交易／鎖內呼叫。
     *
     * @param  WorkspaceContext  $context  交易內重驗的資格
     * @param  string  $projectId  Project公開ID
     * @param  string  $source  外部來源代號
     * @param  string  $externalId  外部識別
     * @param  array<string,mixed>  $fields  已驗證卡片欄位（可含expectedRevision）
     * @return array{id:string,projectId:string,identifier:string,revision:int,externalSource:string,externalId:string,created:bool} 有界結果
     */
    public function upsert(WorkspaceContext $context, string $projectId, string $source, string $externalId, array $fields): array
    {
        $access = $this->projects->authorized($context, $projectId, 'issue.write');
        $result = $this->apply($access, $context->legacyIdentity(), $projectId, $source, $externalId, $fields, false);

        return [...array_diff_key($result, ['status' => true]), 'externalSource' => $source, 'externalId' => $externalId, 'created' => $result['status'] === 'created'];
    }

    /**
     * TT-172：同一交易內依序處理多項upsert；每項各自savepoint，失敗只回滾該項並回報，其他項照常。
     * 整批結果由外層writeOnce保存為同一receipt，逾時以同requestId重送取回原逐項結果。
     *
     * @param  WorkspaceContext  $context  交易內重驗的資格
     * @param  string  $projectId  Project公開ID
     * @param  list<array<string,mixed>>  $items  已驗證項目（source、externalId、欄位、epicRef／parentRef）
     * @param  bool  $createOnly  true時已綁定項目回exists、不更新
     * @return array{items:list<array<string,mixed>>,summary:array<string,int>} 逐項有界結果與統計
     */
    public function batch(WorkspaceContext $context, string $projectId, array $items, bool $createOnly): array
    {
        $access = $this->projects->authorized($context, $projectId, 'issue.write');
        $identity = $context->legacyIdentity();
        $results = [];
        $summary = ['created' => 0, 'updated' => 0, 'exists' => 0, 'failed' => 0];
        foreach ($items as $index => $item) {
            $key = ['index' => $index, 'externalSource' => $item['source'], 'externalId' => $item['externalId']];
            try {
                $row = DB::transaction(function () use ($access, $identity, $projectId, $item, $createOnly) {
                    $fields = array_diff_key($item, ['source' => true, 'externalId' => true, 'epicRef' => true, 'parentRef' => true]);
                    foreach (['epicRef' => 'epicId', 'parentRef' => 'parentIssueId'] as $ref => $field) {
                        if (isset($item[$ref])) {
                            $fields[$field] = $this->refs->issueId($access->project, $item[$ref]['source'], $item[$ref]['externalId'])
                                ?? DomainError::fail('NOT_FOUND', '找不到'.($ref === 'epicRef' ? 'Epic' : '父任務').'的外部識別，請先匯入該卡');
                        }
                    }

                    return $this->apply($access, $identity, $projectId, $item['source'], $item['externalId'], $fields, $createOnly);
                });
                $results[] = [...$key, ...$row];
                $summary[$row['status']]++;
            } catch (DomainError $error) {
                $results[] = [...$key, 'status' => 'failed', 'error' => ['code' => $error->errorCode, 'message' => $error->getMessage()]];
                $summary['failed']++;
            }
        }

        return ['items' => $results, 'summary' => $summary];
    }

    /**
     * 單項建立／更新／略過，呼叫端已授權Project且持有交易。
     *
     * @param  AuthorizedProjectData  $access  已授權Project
     * @param  array  $identity  交易內重驗的可信資格
     * @param  string  $projectId  Project公開ID
     * @param  string  $source  外部來源代號
     * @param  string  $externalId  外部識別
     * @param  array<string,mixed>  $fields  卡片欄位（可含expectedRevision）
     * @param  bool  $createOnly  已綁定時是否略過
     * @return array{status:string,id:string,projectId:string,identifier:string,revision:int} 有界結果
     */
    private function apply(AuthorizedProjectData $access, array $identity, string $projectId, string $source, string $externalId, array $fields, bool $createOnly): array
    {
        $issueId = $this->refs->issueId($access->project, $source, $externalId);
        if ($issueId === null) {
            unset($fields['expectedRevision']);
            $result = $this->workspaces->executePublicIssue($identity, 'createIssue', [...$fields, 'projectId' => $projectId]);
            $this->refs->link($access->project, $result['id'], $source, $externalId);

            return ['status' => 'created', ...$result];
        }
        if ($createOnly) {
            return ['status' => 'exists', ...$this->refs->summary($access->project, $issueId)];
        }
        if (! isset($fields['expectedRevision'])) {
            DomainError::fail('REVISION_CONFLICT', '此外部識別已有任務，更新時必須提供expectedRevision');
        }

        return ['status' => 'updated', ...$this->workspaces->executePublicIssue($identity, 'updateIssue', [...$fields, 'id' => $issueId, 'projectId' => $projectId])];
    }

    /**
     * 檢查路徑上的外部識別格式。
     *
     * @param  string  $source  外部來源代號
     * @param  string  $externalId  外部識別
     * @return bool 是否符合公開契約
     */
    public static function valid(string $source, string $externalId): bool
    {
        return preg_match(self::SOURCE_PATTERN, $source) === 1 && preg_match(self::EXTERNAL_ID_PATTERN, $externalId) === 1;
    }
}
