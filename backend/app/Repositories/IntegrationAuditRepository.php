<?php

namespace App\Repositories;

use App\Data\WorkspaceContext;
use App\Models\IntegrationAudit;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

/** 整合來源的追加及本人限定分頁，不判斷寫入業務或Token Scope。 */
class IntegrationAuditRepository
{
    /**
     * 在既有業務交易內保存安全來源metadata。
     *
     * @param  WorkspaceContext  $context  當次資格
     * @param  array  $attributes  安全來源欄位
     * @return void
     */
    public function append(WorkspaceContext $context, array $attributes): void
    {
        IntegrationAudit::create([...$attributes, 'workspace_id' => Workspace::where('public_id', $context->workspaceId)->sole(['id'])->id]);
    }

    /**
     * 只讀本人目前Workspace來源，每頁20且穩定倒序。
     *
     * @param  WorkspaceContext  $context  當次資格
     * @param  int  $page  頁碼
     * @return array{items:Collection,total:int} 有界來源metadata
     */
    public function page(WorkspaceContext $context, int $page): array
    {
        $query = IntegrationAudit::where('actor_user_id', $context->userId)->whereIn('workspace_id', Workspace::where('public_id', $context->workspaceId)->select('id'));
        $total = $query->count();
        $items = $query->orderByDesc('id')->forPage($page, 20)->get(['id', 'actor_user_id', 'source', 'token_id', 'token_name', 'abilities', 'operation', 'resource_id', 'resource_revision', 'client_reference', 'occurred_at']);

        return compact('items', 'total');
    }
}
