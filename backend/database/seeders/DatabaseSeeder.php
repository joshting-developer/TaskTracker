<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** 標準seed入口，示範資料與環境限制統一由DemoSeeder負責。 */
class DatabaseSeeder extends Seeder
{
    /** 透過Laravel Seeder呼叫示範資料建立流程。
     * @return void
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
