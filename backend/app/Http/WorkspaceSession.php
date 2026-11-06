<?php

namespace App\Http;

use App\Services\AccountService;
use Illuminate\Http\Request;

/** HTTP與領域服務的身分邊界，只從Laravel Session取得操作者與工作空間。 */
class WorkspaceSession
{
    /** 注入帳號資格服務，避免領域服務反向依賴HTTP。 */
    public function __construct(private AccountService $accounts) {}

    /**
     * @param  Request  $request  目前HTTP與Session
     * @return array 經資料庫重驗的身分與有效權限
     */
    public function context(Request $request): array
    {
        return $this->accounts->context($request->user()?->fresh(), $request->session()->get('workspace_id'));
    }
}
