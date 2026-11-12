<?php

namespace App\Services\Integrations;

use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Models\Issue;
use App\Repositories\IssueExternalRefRepository;
use App\Services\IssueReadService;
use App\Services\ProjectReadService;
use App\Services\WorkspaceService;

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
        $issueId = $this->refs->issueId($access->project, $source, $externalId);
        $identity = $context->legacyIdentity();
        if ($issueId === null) {
            unset($fields['expectedRevision']);
            $result = $this->workspaces->executePublicIssue($identity, 'createIssue', [...$fields, 'projectId' => $projectId]);
            $this->refs->link($access->project, $result['id'], $source, $externalId);
        } else {
            if (! isset($fields['expectedRevision'])) {
                DomainError::fail('REVISION_CONFLICT', '此外部識別已有任務，更新時必須提供expectedRevision');
            }
            $result = $this->workspaces->executePublicIssue($identity, 'updateIssue', [...$fields, 'id' => $issueId, 'projectId' => $projectId]);
        }

        return [...$result, 'externalSource' => $source, 'externalId' => $externalId, 'created' => $issueId === null];
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
