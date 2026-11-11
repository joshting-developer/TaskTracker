<?php

namespace App\Factories;

use App\Repositories\RecordStore;

/** 集中建立明確Workspace的相容Store，不保存actor或跨請求租戶狀態。 */
class RecordStoreFactory
{
    /**
     * 建立只屬於本次可信Workspace的資料入口。
     *
     * @param  string  $workspaceId  伺服器驗證的Workspace公開ID
     * @return RecordStore 限定租戶的資料入口
     */
    public function forWorkspace(string $workspaceId): RecordStore
    {
        return new RecordStore($workspaceId);
    }
}
