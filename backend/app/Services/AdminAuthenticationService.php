<?php

namespace App\Services;

use App\Data\AdminLoginData;
use App\Domain\DomainError;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;

/** 單一平台管理帳號, 與一般使用者／Owner／PAT資格完全分開 */
class AdminAuthenticationService
{
    /** 注入私有配置與框架雜湊器, 建構時不保存當次登入狀態
     *
     * @param  Repository  $config  私有管理配置
     * @param  Hasher  $hash  框架密碼驗證
     */
    public function __construct(private Repository $config, private Hasher $hash) {}

    /**
     * 驗證私有管理帳密, 未配置與錯誤帳密使用同一拒絕結果
     *
     * @param  AdminLoginData  $input  當次輸入
     * @return array{fingerprint:string,expiresAt:int} 30分鐘固定有效期的Session資格
     */
    public function authenticate(AdminLoginData $input): array
    {
        $username = (string) $this->config->get('admin.username', '');
        $passwordHash = (string) $this->config->get('admin.password_hash', '');
        // 即使名稱錯誤也執行同成本雜湊驗證, 不洩漏有效名稱
        $dummy = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        try {
            $matches = $this->hash->check($input->password, $passwordHash !== '' ? $passwordHash : $dummy);
        } catch (\RuntimeException) {
            $matches = false;
        }
        if ($username === '' || $passwordHash === '' || ! hash_equals($username, $input->username) || ! $matches) {
            DomainError::fail('UNAUTHENTICATED', '管理帳號或密碼不正確');
        }

        return ['fingerprint' => $this->fingerprint(), 'expiresAt' => now()->timestamp + 1800];
    }

    /**
     * 每次讀取前重驗Session與目前配置, 換名稱／密碼即撤銷舊資格
     *
     * @param  mixed  $claim  從伺服器Session取得, 不接受HTTP body資格
     * @return void
     */
    public function assertCurrent(mixed $claim): void
    {
        if (! is_array($claim) || ! is_string($claim['fingerprint'] ?? null) || ! is_int($claim['expiresAt'] ?? null)
            || $claim['expiresAt'] <= now()->timestamp || $this->config->get('admin.username', '') === ''
            || $this->config->get('admin.password_hash', '') === '' || ! hash_equals($this->fingerprint(), $claim['fingerprint'])) {
            DomainError::fail('UNAUTHENTICATED', '請登入管理後台');
        }
    }

    /** 僅供已登入管理者顯示的名稱
     *
     * @return string 僅供已登入管理者顯示的名稱
     */
    public function username(): string
    {
        return (string) $this->config->get('admin.username', '');
    }

    /** 私有配置的HMAC, 不將密碼雜湊放入Session或JSON
     *
     * @return string 私有配置的HMAC, 不將密碼雜湊放入Session或JSON
     */
    private function fingerprint(): string
    {
        return hash_hmac('sha256', json_encode([$this->config->get('admin.username'), $this->config->get('admin.password_hash')]), (string) $this->config->get('app.key'));
    }
}
