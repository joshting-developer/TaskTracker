<?php

namespace App\Factories;

use App\Repositories\ProjectReadRepository;
use App\Repositories\SettingsRepository;
use App\Services\AccountSettings;
use App\Services\ProjectSignals;
use App\Services\RequestReplay;

/** 將穩定設定資料入口與當次可信actor於明確邊界組裝，不跨請求保存身分。 */
class AccountSettingsFactory
{
    /**
     * 注入穩定且無租戶狀態的設定資料入口。
     *
     * @param  SettingsRepository  $settings  設定資料入口
     * @param  RecordStoreFactory  $stores  Workspace資料入口組裝
     * @param  RequestReplay  $replay  原子防重複協作者
     * @param  ProjectReadRepository  $projects  訊號目標專案資料入口
     */
    public function __construct(private SettingsRepository $settings, private RecordStoreFactory $stores, private RequestReplay $replay, private ProjectReadRepository $projects) {}

    /**
     * 建立當次設定寫入用例，不使用服務定位器。
     *
     * @param  array<string,mixed>  $actor  交易內重新驗證的身分
     * @return AccountSettings 當次actor設定服務
     */
    public function forActor(array $actor): AccountSettings
    {
        return new AccountSettings($actor, $this->settings, $this->stores, $this->replay, new ProjectSignals($actor, $this->projects));
    }
}
