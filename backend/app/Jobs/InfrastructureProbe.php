<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * 維運檢查用的Queue工作：被worker消費時寫入cache標記，證明Queue確實運作。
 */
class InfrastructureProbe implements ShouldQueue
{
    use Queueable;

    /**
     * 指定要寫入的cache鍵。
     *
     * @param  string  $key  檢查用cache鍵
     */
    public function __construct(public string $key) {}

    /**
     * 寫入已消費標記。
     *
     * @return void
     */
    public function handle(): void
    {
        Cache::put($this->key, 'consumed', 30);
    }
}
