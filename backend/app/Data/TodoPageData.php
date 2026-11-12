<?php

namespace App\Data;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 待辦分頁與同一次授權後的連結／操作能力，不附其他資源全集。 */
readonly class TodoPageData
{
    /**
     * 保存分頁與當次業務授權投影。
     *
     * @param  LengthAwarePaginator  $page  已限定參與者的待辦
     * @param  array<int,array<string,string>>  $links  按內部Issue ID索引的可讀轉卡連結
     * @param  array<string,array<string,bool>>  $capabilities  按Todo公開ID索引的操作能力
     */
    public function __construct(public LengthAwarePaginator $page, public array $links, public array $capabilities) {}
}
