<?php

namespace Tests\Concerns;

use App\Database\RelationalDataMigration;

/** 在真實外鍵順序下驗證舊關聯 migration, 不略過後續資料表。 */
trait RelationalMigrationRoundTrip
{
    /**
     * 取出依序執行的後續 migration
     *
     * @return array<string> migration 路徑
     */
    private function laterMigrations(): array
    {
        return array_values(array_filter(glob(database_path('migrations/*.php')), fn ($file) => basename($file) > '2026_11_04_000000_create_relational_business_tables.php'));
    }

    /**
     * 後續表先撤下, 保留歷史資料匯出
     *
     * @return void
     */
    private function rollbackLaterMigrations(): void
    {
        RelationalDataMigration::export();
        foreach (array_reverse($this->laterMigrations()) as $file) {
            (require $file)->down();
        }
    }

    /**
     * 依真實順序恢復後續欄位與資料表
     *
     * @return void
     */
    private function restoreLaterMigrations(): void
    {
        foreach ($this->laterMigrations() as $file) {
            (require $file)->up();
        }
    }
}
