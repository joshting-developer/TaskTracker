<?php

namespace App\Services;

use App\Domain\DomainError;
use App\Repositories\MembershipRepository;

/** 新增有效資格前的共同人數規則, 由呼叫端交易持有工作空間鎖 */
class WorkspaceMemberCapacity
{
    /** 注入限定範圍的成員計數入口
     * @param  MembershipRepository  $members  工作空間成員查詢
     */
    public function __construct(private MembershipRepository $members) {}

    /** 只在增加有效資格時檢查, 不限制既有成員管理或重播
     * @param  string  $workspaceId  已鎖定的工作空間公開ID
     * @return void
     */
    public function assertAvailable(string $workspaceId): void
    {
        if ($this->members->activeCount($workspaceId) >= 5) {
            DomainError::fail('CONFLICT', '工作空間最多5位有效成員（包含擁有者）, 請先停用或移除成員再加入');
        }
    }
}
