<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class InfrastructureProbe implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $key) {}

    public function handle(): void
    {
        Cache::put($this->key, 'consumed', 30);
    }
}
