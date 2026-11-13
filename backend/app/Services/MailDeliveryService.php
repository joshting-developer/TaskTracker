<?php

namespace App\Services;

use App\Data\MailDeliveryData;
use App\Domain\DomainError;
use App\Jobs\SendTransactionalMail;
use App\Mail\TransactionalMessage;
use App\Models\MailDelivery;
use App\Models\User;
use App\Repositories\MailDeliveryRepository;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** 交易式outbox與寄信狀態機, SMTP IO在資料庫交易外執行 */
class MailDeliveryService
{
    /** 注入資料、Queue與Mail邊界, 不在建構時查資格或連SMTP
     * @param  MailDeliveryRepository  $deliveries  事件與狀態資料入口
     * @param  Dispatcher  $bus  Job派送入口
     * @param  Factory  $mail  Laravel Mail唯一寄送入口
     * @param  MailConnectionService  $connection  可替換的SMTP預連線
     */
    public function __construct(private MailDeliveryRepository $deliveries, private Dispatcher $bus, private Factory $mail, private MailConnectionService $connection) {}

    /** 保存唯一業務事件, commit後才排Job；Queue不可用不回滾業務或等SMTP
     * @param  MailDeliveryData  $data  可信範本及收件帳號
     * @return MailDelivery 唯一事件的狀態
     */
    public function enqueue(MailDeliveryData $data): MailDelivery
    {
        if (! in_array($data->kind, ['diagnostic', 'verification'], true) || $data->eventKey === '' || strlen($data->eventKey) > 200 || mb_strlen($data->subject) > 200 || preg_match('/[\r\n]/', $data->subject) || mb_strlen($data->body) > 10000) {
            DomainError::fail('INVALID_INPUT', '信件範本不符合限制');
        }

        return DB::transaction(function () use ($data) {
            $user = $this->deliveries->user($data->userId) ?? DomainError::fail('NOT_FOUND', '找不到收件帳號');
            $key = hash('sha256', $data->userId.'|'.$data->eventKey);
            $existing = $this->deliveries->event($key);
            if ($existing) {
                return $existing;
            }
            $row = $this->deliveries->create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'event_key' => $key, 'kind' => $data->kind,
                'recipient' => $user->email, 'payload' => ['subject' => $data->subject, 'body' => $data->body], 'status' => 'pending', 'next_attempt_at' => now(), 'expires_at' => $data->expiresAt]);
            DB::afterCommit(fn () => $this->dispatch($row->id));

            return $row;
        });
    }

    /** 非同步派送, broker失敗只留安全代碼供有界重派恢復
     * @param  string  $id  信件UUID
     * @return void
     */
    private function dispatch(string $id): void
    {
        try {
            if (in_array(config('delivery.connection'), ['sync', 'null'], true)) {
                throw new RuntimeException('ASYNC_QUEUE_REQUIRED');
            }
            $this->bus->dispatch((new SendTransactionalMail($id))->onConnection(config('delivery.connection'))->onQueue(config('delivery.queue')));
        } catch (\Throwable) {
            try {
                $this->locked($id, function ($row) {
                    if ($row->status === 'pending') {
                        $this->deliveries->update($row, ['error_code' => 'QUEUE_UNAVAILABLE', 'next_attempt_at' => now()->addMinute()]);
                    }
                });
            } catch (\Throwable) {
                // outbox已commit, 即使狀態更新失敗仍由既有pending事件恢復, 不使業務請求變500。
            }
        }
    }

    /** 只讀本輪100筆到期事件, 重複Job會由資料庫狀態去重
     * @return int 本輪派送數量
     */
    public function dispatchPending(): int
    {
        $cutoff = now()->subMinute();
        $stale = $this->deliveries->staleSendingIds($cutoff);
        $this->deliveries->updateStaleSending($stale, $cutoff, ['status' => 'uncertain', 'error_code' => 'WORKER_INTERRUPTED', 'payload' => null]);
        $rows = $this->deliveries->pending();
        foreach ($rows as $row) {
            $this->dispatch($row->id);
        }

        return $rows->count();
    }

    /** Worker用例：重新驗收件人、連線、再驗一次才交付信件
     * @param  string  $id  信件UUID
     * @return void
     */
    public function deliver(string $id): void
    {
        $row = $this->locked($id, function ($row, $user) {
            if ($row->status === 'sending' && $row->started_at?->lt(now()->subSeconds(60))) {
                $this->deliveries->update($row, ['status' => 'uncertain', 'error_code' => 'WORKER_INTERRUPTED', 'payload' => null]);

                return null;
            }
            if ($row->status !== 'pending' || $row->next_attempt_at?->isFuture()) {
                return null;
            }
            if (! $this->eligible($row, $user)) {
                $this->deliveries->update($row, ['status' => 'skipped', 'error_code' => 'RECIPIENT_UNAVAILABLE', 'payload' => null]);

                return null;
            }
            if ($row->attempts >= config('delivery.tries')) {
                return null;
            }

            return $this->deliveries->update($row, ['status' => 'sending', 'attempts' => $row->attempts + 1, 'started_at' => now(), 'error_code' => null]);
        });
        if (! $row) {
            return;
        }
        try {
            $this->connection->connect();
        } catch (\Throwable) {
            $this->locked($id, function ($current) {
                $last = $current->attempts >= config('delivery.tries');
                $delay = config('delivery.backoff')[min($current->attempts - 1, 1)];
                $this->deliveries->update($current, ['status' => $last ? 'failed' : 'pending', 'error_code' => 'SMTP_CONNECTION_FAILED', 'started_at' => null, 'next_attempt_at' => now()->addSeconds($delay), ...($last ? ['payload' => null] : [])]);
            });
            throw new RuntimeException('SMTP_CONNECTION_FAILED');
        }
        $valid = $this->locked($id, function ($current, $user) {
            if ($current->status !== 'sending') {
                return false;
            }
            if ($this->eligible($current, $user)) {
                return true;
            }
            $this->deliveries->update($current, ['status' => 'skipped', 'error_code' => 'RECIPIENT_UNAVAILABLE', 'payload' => null]);

            return false;
        });
        if (! $valid) {
            return;
        }
        try {
            $payload = $row->payload;
            $this->mail->mailer(config('mail.default'))->to($row->recipient)->send(new TransactionalMessage($payload['subject'], $payload['body']));
        } catch (\Throwable) {
            // SMTP可能已接受但回應遺失；不自動補寄, 不把含認證／信件內容的原例外丟給Queue。
            $this->locked($id, fn ($current) => $this->deliveries->update($current, ['status' => 'uncertain', 'error_code' => 'SMTP_DELIVERY_UNCERTAIN', 'payload' => null]));

            return;
        }
        $this->locked($id, fn ($current) => $this->deliveries->update($current, ['status' => 'sent', 'sent_at' => now(), 'error_code' => null, 'payload' => null]));
    }

    /** Queue重試耗盡後記終態, sending代表不能確認是否已交付
     * @param  string  $id  信件UUID
     * @return void
     */
    public function failed(string $id): void
    {
        $this->locked($id, function ($row) {
            if (! in_array($row->status, ['sending', 'pending'], true)) {
                return;
            }
            $this->deliveries->update($row, ['status' => $row->status === 'sending' ? 'uncertain' : 'failed', 'error_code' => 'QUEUE_RETRIES_EXHAUSTED', 'payload' => null]);
        });
    }

    /** 帳號、地址、資格與驗證狀態以寄送當下為準
     * @param  MailDelivery  $row  當前事件
     * @param  ?User  $user  新鮮帳號
     * @return bool 是否仍可寄送
     */
    private function eligible(MailDelivery $row, ?User $user): bool
    {
        return $user && $row->payload !== null && $row->expires_at->isFuture() && $user->email === $row->recipient && $this->deliveries->hasMembership($user->id)
            && ($row->kind !== 'verification' || $user->email_verified_at === null);
    }

    /** 固定User→MailDelivery鎖序, 只處理一筆事件且不在交易內連外
     * @param  string  $id  信件UUID
     * @param  callable(MailDelivery,?User):mixed  $operation  狀態操作
     * @return mixed 用例結果或null
     */
    private function locked(string $id, callable $operation): mixed
    {
        return DB::transaction(function () use ($id, $operation) {
            $snapshot = $this->deliveries->find($id);
            if (! $snapshot) {
                return null;
            }
            $user = $snapshot->user_id ? $this->deliveries->user($snapshot->user_id) : null;
            $row = $this->deliveries->lock($id);

            return $row ? $operation($row, $user) : null;
        });
    }
}
