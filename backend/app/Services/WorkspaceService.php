<?php

namespace App\Services;

use App\Data\Writes\WriteResults;
use App\Domain\DomainError;
use App\Factories\WorkspaceOperationServices;
use App\Factories\WorkspaceServicesFactory;
use Database\Seeders\DemoSeeder;

/**
 * 維持既有操作契約，協調逐筆授權、重試與各領域寫入。
 */
class WorkspaceService
{
    /**
     * 注入無租戶狀態的穩定協作者；可由Container自動解析，操作者與工作區改為每次執行的參數。
     *
     * @param  WorkspaceServicesFactory  $services  依可信Session建立單次操作的服務組
     * @param  RequestReplay  $replay  防重複提交與原結果重播
     * @param  DemoSeeder  $demo  示範專案載入（只限local／testing）
     */
    public function __construct(private WorkspaceServicesFactory $services, private RequestReplay $replay, private DemoSeeder $demo) {}

    /**
     * 授權後執行RPC操作：讀取直接回傳，寫入經RequestReplay保存原結果，並在commit後送出Project變更訊號。
     *
     * @param  array<string,mixed>  $workspaceSession  交易內由Session重驗的身分、工作區與權限
     * @param  string  $operation  已允許的操作名稱
     * @param  array<string,mixed>  $input  此次操作的原始輸入欄位
     * @return array<string,mixed> 操作結果
     *
     * @throws DomainError 權限不足、資料不存在、版本衝突或識別碼衝突
     */
    public function execute(array $workspaceSession, string $operation, array $input): array
    {
        if ($operation === 'loadDemo') {
            $this->demo->assertEnvironment();
        }
        $services = $this->services->forSession($workspaceSession);
        $services->operationAccess()->authorize($operation, $input);
        if ($operation === 'getTimeReport') {
            return $services->timeReport()->get($workspaceSession, $input);
        }
        if ($operation === 'getWorkspace') {
            return $services->query()->getWorkspace();
        }
        if ($operation === 'getProjectMembers') {
            return $services->members()->listing($input['projectId']);
        }

        return $this->replay->execute($services->store, 'requests', $workspaceSession['user']['id'], $operation, $input, fn () => $this->write($services, $operation, $input));
    }

    /**
     * 公開Issue寫入共用同一業務／關聯／通知流程，receipt由外層Bearer writeOnce擁有。
     *
     * @param  array  $identity  交易內重驗的可信資格
     * @param  string  $operation  僅createIssue／updateIssue／addComment
     * @param  array  $input  已驗輸入與路徑目標
     * @return array{id:string,projectId:string,identifier:string,revision:int} 有界首次寫入結果
     */
    public function executePublicIssue(array $identity, string $operation, array $input): array
    {
        if (! in_array($operation, ['createIssue', 'updateIssue', 'addComment', 'deleteIssue'], true)) {
            DomainError::fail('NOT_FOUND', '找不到這個操作');
        }
        $services = $this->services->forSession($identity);
        $services->operationAccess()->authorize($operation, $input);
        $result = $this->write($services, $operation, $input);

        return ['id' => $result['id'], 'projectId' => $result['projectId'], 'identifier' => $result['identifier'], 'revision' => $result['revision']];
    }

    /**
     * 依操作只取得需要的領域服務並寫入，完成後依結果決定要通知的Project。
     *
     * @param  WorkspaceOperationServices  $services  本次操作的服務組
     * @param  string  $operation  已授權的寫入操作
     * @param  array<string,mixed>  $input  此次操作的原始輸入欄位
     * @return array<string,mixed> 寫入結果
     *
     * @throws DomainError 業務規則或資料驗證失敗
     */
    private function write(WorkspaceOperationServices $services, string $operation, array $input): array
    {
        $result = match ($operation) {
            'createProject' => $services->projects()->createProject($input),
            'updateProject' => $services->projects()->updateProject($input),
            'updateStatuses' => $services->projects()->updateStatuses($input),
            'createIssue' => $services->issues()->createIssue($input),
            'updateIssue' => $services->issues()->updateIssue($input),
            'addComment' => $services->issues()->addComment($input),
            'deleteIssue' => $services->issues()->deleteIssue($input),
            'logTime' => $services->time()->logTime($input),
            'updateTime' => $services->time()->updateTime($input),
            'voidTime' => $services->time()->voidTime($input),
            'createBoard' => $services->boards()->create($input),
            'updateBoard' => $services->boards()->update($input),
            'archiveBoard' => $services->boards()->archive($input),
            'setProjectMember' => $services->members()->change($input, false),
            'removeProjectMember' => $services->members()->change($input, true),
            'loadDemo' => $this->demo->loadProject($services->workspaceSession),
            default => DomainError::fail('NOT_FOUND', '找不到這個操作'),
        };
        // 專案／看板／卡片／工時寫入只回有界結果（同時是receipt保存的內容），最新內容由GET按需讀取。
        $result = match (true) {
            in_array($operation, WriteResults::PROJECT_OPERATIONS, true) => WriteResults::resource($result),
            in_array($operation, WriteResults::BOARD_OPERATIONS, true) => WriteResults::board($result),
            in_array($operation, WriteResults::ISSUE_OPERATIONS, true) => WriteResults::issue($result),
            in_array($operation, WriteResults::TIME_OPERATIONS, true) => WriteResults::timeEntry($result),
            default => $result,
        };
        $projectId = match ($operation) {
            'createProject', 'updateProject', 'updateStatuses' => $result['id'],
            'archiveBoard' => $result['projectId'],
            default => $result['projectId'] ?? '',
        };
        $services->signals()->changed($projectId);

        return $result;
    }
}
