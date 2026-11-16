<?php

namespace App\Services;

use App\Data\MailDeliveryData;
use App\Domain\DomainError;
use App\Repositories\PasswordResetRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;

/** 單次重設與恢復後撤銷, 不取得任何Workspace資料或自動登入 */
class PasswordResetService
{
    /**
     * 注入帳號恢復、信件交付與最低處理時間邊界
     *
     * @param  PasswordResetRepository  $resets  帳號鎖與重設入口
     * @param  MailDeliveryService  $mail  既有加密outbox
     * @param  Timebox  $timebox  匿名申請最低處理時間
     */
    public function __construct(private PasswordResetRepository $resets, private MailDeliveryService $mail, private Timebox $timebox) {}

    /**
     * 保存或替換一次性重設憑證, 對外始終回覆相同申請結果
     *
     * @param  string  $email  正規化後的Email
     * @return array{accepted:bool} 不依帳號存在、冷卻或交付狀態改變回覆
     */
    public function request(string $email): array
    {
        $base = rtrim((string) config('app.url'), '/');
        if (! filter_var($base, FILTER_VALIDATE_URL) || (! in_array(config('app.env'), ['local', 'testing'], true) && ! str_starts_with($base, 'https://'))) {
            DomainError::fail('INVALID_INPUT', '重設連結網址尚未配置');
        }

        return $this->timebox->call(function () use ($email, $base) {
            $token = bin2hex(random_bytes(32));
            try {
                DB::transaction(function () use ($email, $token, $base) {
                    $user = $this->resets->user($email);
                    if (! $user) {
                        return;
                    }
                    $old = $this->resets->current($email);
                    if ($old && $old->created_at->gt(now()->subMinute())) {
                        return;
                    }
                    $hash = hash('sha256', $token);
                    $this->resets->cancelMail($user->id);
                    $this->resets->replace($email, $hash);
                    $url = $base.'/login#/reset-password/'.$token.'?email='.rawurlencode($email);
                    $this->mail->enqueue(new MailDeliveryData($user->id, 'password-reset:'.$hash, 'password_reset', 'Pavenro 重設密碼', "重設密碼：{$url}\n\n30分鐘內有效, 只能使用一次。若不是你提出的申請, 可以忽略這封信", now()->addMinutes(30), passwordResetHash: $hash));
                });
            } catch (\Throwable $error) {
                // Do not expose account existence through an outbox or database failure.
                Log::warning('Password recovery request unavailable', ['error' => $error::class]);
            }

            return ['accepted' => true];
        }, 200000);
    }

    /**
     * 驗證單次連結後更新密碼並撤銷舊登入與Token
     *
     * @param  string  $email  正規化Email
     * @param  string  $token  高熵憑證原文
     * @param  string  $password  已通過長度及確認欄位的新密碼
     * @return array{reset:bool} 成功後仍須正常登入
     */
    public function reset(string $email, string $token, string $password): array
    {
        return DB::transaction(function () use ($email, $token, $password) {
            $user = $this->resets->user($email);
            $row = $user ? $this->resets->current($email) : null;
            if (! $row || ! $row->created_at || $row->created_at->lte(now()->subMinutes(30)) || ! hash_equals($row->token, hash('sha256', $token))) {
                DomainError::fail('INVALID_INPUT', '重設連結已失效或過期, 請重新申請');
            }
            $this->resets->reset($user, Hash::make($password), Str::random(60));
            $this->resets->cancelMail($user->id);
            $this->mail->enqueue(new MailDeliveryData($user->id, 'password-changed:'.Str::uuid(), 'password_changed', 'Pavenro 密碼已變更', '你的密碼已變更, 原有登入與個人API Token已撤銷。若不是你本人操作, 請立即重新申請重設密碼', now()->addHours(24)));

            return ['reset' => true];
        });
    }
}
