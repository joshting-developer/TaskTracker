<?php

namespace App\Console\Commands;

use App\Jobs\InfrastructureProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * 驗證Redis、Queue worker消費與Reverb WebSocket握手的維運檢查指令；不寄信、不發業務事件。
 */
class CheckInfrastructure extends Command
{
    protected $signature = 'tasktracker:check-infrastructure';
    protected $description = '驗證 Redis、Queue worker 與 Reverb WebSocket 握手, 不寄信或發布業務事件';

    /**
     * 依序檢查Redis連線、Queue實際消費與Reverb握手，任一失敗即回報。
     *
     * @return int 指令結束碼
     */
    public function handle(): int
    {
        try {
            Redis::connection()->ping();
            $this->info('Redis: connected');
            if (config('queue.default') !== 'redis') throw new \RuntimeException('此檢查要求 Redis queue, 不接受 sync 假成功');
            $key = 'infrastructure-probe:'.Str::uuid();
            InfrastructureProbe::dispatch($key)->onConnection('redis');
            $deadline = microtime(true) + 12;
            do { usleep(100000); $consumed = Cache::get($key) === 'consumed'; } while (!$consumed && microtime(true) < $deadline);
            Cache::forget($key);
            if (!$consumed) throw new \RuntimeException('Queue worker 未在期限內消費工作');
            $this->info('Queue: dispatched and consumed');
            $socket = @fsockopen(config('reverb.apps.apps.0.options.host', 'websocket'), (int) config('reverb.apps.apps.0.options.port', 8080), $errno, $message, 5);
            if (!$socket) throw new \RuntimeException('Reverb 無法連線');
            stream_set_timeout($socket, 5);
            $key = config('reverb.apps.apps.0.key');
            $origin = config('app.url');
            $handshakeKey = base64_encode(random_bytes(16));
            fwrite($socket, "GET /app/{$key}?protocol=7&client=tasktracker&version=1 HTTP/1.1\r\nHost: websocket:8080\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {$handshakeKey}\r\nSec-WebSocket-Version: 13\r\nOrigin: {$origin}\r\n\r\n");
            $header = '';
            while (!str_ends_with($header, "\r\n\r\n") && !feof($socket) && strlen($header) < 8192) {
                $byte = fread($socket, 1);
                if ($byte === '' || $byte === false) break;
                $header .= $byte;
            }
            $frame = fread($socket, 4096);
            fclose($socket);
            if (!str_contains($header, '101 Switching Protocols') || !str_contains($frame, 'pusher:connection_established')) throw new \RuntimeException('WebSocket 握手或協定確認失敗');
            $this->info('WebSocket: upgraded and connected');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
