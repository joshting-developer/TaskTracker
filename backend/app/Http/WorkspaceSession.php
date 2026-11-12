<?php

namespace App\Http;

use App\Data\WorkspaceContext;
use App\Factories\WorkspaceContextFactory;
use Illuminate\Http\Request;

/** HTTP與領域服務的身分邊界，只從Laravel Session取得操作者與工作空間。 */
class WorkspaceSession
{
    /** 注入每次重驗資格的Context組裝邊界，避免領域服務反向依賴HTTP。 */
    public function __construct(private WorkspaceContextFactory $contexts) {}

    /**
     * @param  Request  $request  目前HTTP與Session
     * @return array 經資料庫重驗的身分與有效權限
     */
    public function context(Request $request): array
    {
        return $this->verified($request)->legacyIdentity();
    }

    /**
     * 僅取Laravel Session／Auth來源，忽略JSON內偽造的Actor、Workspace與permissions。
     *
     * @param  Request  $request  當次HTTP，呼叫端負責交易與Session鎖生命週期
     * @return WorkspaceContext 每次重驗的新快照，不跨請求保存
     */
    public function verified(Request $request): WorkspaceContext
    {
        return $this->contexts->verify($request->user()?->fresh(), $request->session()->get('workspace_id'));
    }
}
