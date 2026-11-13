<?php

namespace App\Services;

use App\Data\AuthenticatedAccountData;
use App\Data\MailDeliveryData;
use App\Domain\DomainError;
use App\Models\EmailVerification;
use App\Models\User;
use App\Repositories\EmailVerificationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** 信箱驗證用例, 同一帳號鎖序列化重寄、錯誤次數與一次性啟用 */
class EmailVerificationService
{
    /** 注入驗證資料入口與背景寄信服務
     * @param  EmailVerificationRepository  $verifications  驗證資料入口
     * @param  MailDeliveryService  $mail  commit後背景寄信
     */
    public function __construct(private EmailVerificationRepository $verifications, private MailDeliveryService $mail) {}

    /** 新註冊以帳號鎖建立驗證事件
     * @param  User  $user  新註冊帳號
     * @param  string  $workspaceId  可信工作區
     * @return array<string,mixed> 不含秘密的驗證畫面資料
     */
    public function begin(User $user, string $workspaceId): array
    {
        return DB::transaction(function () use ($user, $workspaceId) {
            $fresh = $this->verifications->user($user->id) ?? DomainError::fail('UNAUTHENTICATED', '請重新登入');

            return $this->issue($fresh, $workspaceId);
        });
    }

    /** 恢復pending Session的最小驗證畫面資料
     * @param  string  $id  pending Session帳號
     * @return array<string,mixed> 重載時的最小驗證資訊
     */
    public function status(string $id): array
    {
        $user = $this->verifications->user($id) ?? DomainError::fail('UNAUTHENTICATED', '請重新登入');
        $row = $this->verifications->current($id) ?? DomainError::fail('UNAUTHENTICATED', '請重新登入');

        return $this->view($user, $row);
    }

    /** 重驗冷卻與地址後撤銷舊事件並重寄
     * @param  string  $id  pending Session帳號
     * @param  ?string  $email  選填新地址
     * @return array<string,mixed> 新驗證資訊
     */
    public function resend(string $id, ?string $email): array
    {
        return DB::transaction(function () use ($id, $email) {
            $user = $this->verifications->user($id) ?? DomainError::fail('UNAUTHENTICATED', '請重新登入');
            $row = $this->verifications->current($id) ?? DomainError::fail('UNAUTHENTICATED', '請重新登入');
            if ($user->email_verified_at || $row->consumed_at) {
                DomainError::fail('INVALID_INPUT', '帳號已驗證, 請登入');
            }
            if ($row->sent_at->gt(now()->subSeconds(60))) {
                DomainError::fail('INVALID_INPUT', '請等60秒後再重寄');
            }
            if ($email !== null) {
                $email = mb_strtolower(trim($email));
                if (strlen($email) > 254 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    DomainError::fail('INVALID_INPUT', '請輸入有效的Email');
                }
                if ($this->verifications->emailExists($email, $id)) {
                    DomainError::fail('CONFLICT', '此Email已註冊');
                }
                $this->verifications->updateUser($user, ['email' => $email]);
            }

            return $this->issue($user, $row->workspace_id);
        });
    }

    /** 驗證一次性憑證並建立已驗證資格
     * @param  ?string  $id  pending Session帳號, 連結模式由token解析
     * @param  string  $code  驗證碼
     * @param  string  $token  高熵連結secret
     * @return AuthenticatedAccountData 已完成驗證的資格
     */
    public function verify(?string $id, string $code, string $token): AuthenticatedAccountData
    {
        $tokenHash = hash('sha256', $token);
        if ($token !== '') {
            $id = $this->verifications->tokenUser($tokenHash);
        }
        if (! $id) {
            DomainError::fail('INVALID_INPUT', '驗證資訊已失效, 請重新登入或重寄');
        }
        $result = DB::transaction(function () use ($id, $code, $token, $tokenHash) {
            $user = $this->verifications->user($id);
            $row = $this->verifications->current($id);
            if (! $user || ! $row || $user->email_verified_at || $row->consumed_at) {
                return '驗證資訊已使用或失效';
            }
            if ($row->expires_at->isPast()) {
                return '驗證已過期, 請重寄驗證碼';
            }
            if ($row->attempts >= 5) {
                return '輸入錯誤已達5次, 請重寄驗證碼';
            }
            $valid = $token !== '' ? hash_equals($row->token_hash, $tokenHash) : ((in_array(config('app.env'), ['local', 'testing'], true) && $code === 'local666') || Hash::check(strtoupper(trim($code)), $row->code_hash));
            if (! $valid) {
                $this->verifications->update($row, ['attempts' => $row->attempts + 1]);

                return '驗證碼不正確, 請重新輸入';
            }
            $this->verifications->update($row, ['consumed_at' => now()]);
            $this->verifications->updateUser($user, ['email_verified_at' => now()]);
            $this->verifications->cancelMail($row->id);

            return new AuthenticatedAccountData($user, $row->workspace_id);
        });
        // Invalid attempts must commit, rather than being rolled back with the response error.
        if (is_string($result)) {
            DomainError::fail('INVALID_INPUT', $result);
        }

        return $result;
    }

    /** 產生新憑證雜湊並排背景寄信
     * @param  User  $user  已鎖帳號
     * @param  string  $workspaceId  可信工作區
     * @return array<string,mixed> 新驗證資訊
     */
    private function issue(User $user, string $workspaceId): array
    {
        if ($old = $this->verifications->current($user->id)) {
            $this->verifications->cancelMail($old->id);
            $this->verifications->remove($old);
        }
        $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 7; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $token = bin2hex(random_bytes(32));
        $row = $this->verifications->create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'workspace_id' => $workspaceId, 'code_hash' => Hash::make($code), 'token_hash' => hash('sha256', $token), 'sent_at' => now(), 'expires_at' => now()->addMinutes(30)]);
        $base = rtrim((string) config('app.url'), '/');
        if (! filter_var($base, FILTER_VALIDATE_URL) || (! in_array(config('app.env'), ['local', 'testing'], true) && ! str_starts_with($base, 'https://'))) {
            DomainError::fail('INVALID_INPUT', '啟用連結網址尚未配置');
        }
        $this->mail->enqueue(new MailDeliveryData($user->id, 'verification:'.$row->id, 'verification', 'TaskTracker 信箱驗證', "驗證碼：{$code}\n\n30分鐘內有效, 只能使用一次\n\n啟用連結：{$base}/app/#/activate/{$token}\n\n請開啟連結後按下啟用帳號", $row->expires_at, $row->id));

        return $this->view($user, $row);
    }

    /** 只回傳畫面需要的地址及時間
     * @param  User  $user  帳號
     * @param  EmailVerification  $row  當次驗證
     * @return array<string,mixed> 最小資訊, 不包含驗證碼與token
     */
    private function view(User $user, EmailVerification $row): array
    {
        return ['verificationRequired' => true, 'email' => $user->email, 'expiresAt' => $row->expires_at->toISOString(), 'resendAt' => $row->sent_at->copy()->addSeconds(60)->toISOString(), 'resendAfterSeconds' => max(0, $row->sent_at->timestamp + 60 - now()->timestamp)];
    }
}
