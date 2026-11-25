<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Data\PageQueryData;
use App\Data\WorkspaceContext;
use App\Models\Issue;
use App\Repositories\PersonalDashboardRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/** 依當次權限與台北工作日協調本人工作摘要。 */
class PersonalDashboardService
{
    /**
     * 注入首頁專用有界投影, 不在建構時取得身分或查詢。
     *
     * @param  PersonalDashboardRepository  $dashboard  首頁摘要查詢
     */
    public function __construct(private PersonalDashboardRepository $dashboard) {}

    /**
     * 將有效permission映射為SQL資格條件, 每次讀取重新計算。
     *
     * @param  WorkspaceContext  $context  交易內重驗的工作區身分
     * @param  string  $kind  已驗證的摘要種類
     * @param  PageQueryData  $page  有界分頁
     * @param  array{projectId?:string,state?:string,priority?:string,assignee?:string,tagId?:string,dueFrom?:string,dueTo?:string}  $filters  已驗證的小工具條件
     * @return LengthAwarePaginator<Issue> 本人且有權讀取的任務摘要
     */
    public function index(WorkspaceContext $context, string $kind, PageQueryData $page, array $filters = []): LengthAwarePaginator
    {
        $roles = array_column(array_filter(ProjectAccess::roles(), fn ($role) => in_array('issue.read', $role['permissions'], true)), 'id');
        $today = Carbon::now('Asia/Taipei')->startOfDay();

        return $this->dashboard->page($context->workspaceId, $context->userId, in_array('issue.readAny', $context->permissions, true),
            $roles, $kind, $today->toDateString(), $today->copy()->addDays(7)->toDateString(), $page, $filters);
    }
}
