<?php

namespace App\Repositories;

use App\Models\MailDelivery;
use App\Models\PasswordReset;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** 帳號與單筆重設資料存取, 權限及token有效性由Service決定 */
class PasswordResetRepository
{
    /**
     * 依Email取得並鎖定單一帳號
     *
     * @param  string  $email  正規化Email
     * @return ?User 先取得穩定帳號鎖
     */
    public function user(string $email): ?User
    {
        return User::where('email', $email)->lockForUpdate()->first();
    }

    /**
     * 取得指定信箱目前的一筆重設資料
     *
     * @param  string  $email  已鎖帳號Email
     * @return ?PasswordReset 當次重設
     */
    public function current(string $email): ?PasswordReset
    {
        return PasswordReset::whereKey($email)->first();
    }

    /**
     * 以新雜湊及時間替換該信箱的重設資料
     *
     * @param  string  $email  已驗證Email
     * @param  string  $hash  新token雜湊
     * @return void
     */
    public function replace(string $email, string $hash): void
    {
        PasswordReset::updateOrCreate(['email' => $email], ['token' => $hash, 'created_at' => now()]);
    }

    /**
     * 保存新密碼與登入版本, 刪除重設資料並撤銷該帳號Session及PAT
     *
     * @param  User  $user  已鎖帳號
     * @param  string  $password  新密碼雜湊
     * @param  string  $remember  新remember token
     * @return void
     */
    public function reset(User $user, string $password, string $remember): void
    {
        $user->forceFill(['password' => $password, 'legacy_salt' => null, 'legacy_hash' => null, 'remember_token' => $remember, 'session_epoch' => (int) $user->session_epoch + 1])->save();
        PasswordReset::whereKey($user->email)->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        PersonalAccessToken::where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    /**
     * 清除該帳號尚未交付的舊重設信
     *
     * @param  string  $userId  已鎖帳號ID
     * @return void 撤銷尚未交付的舊重設信
     */
    public function cancelMail(string $userId): void
    {
        MailDelivery::where('user_id', $userId)->where('kind', 'password_reset')->whereIn('status', ['pending', 'sending'])->update(['status' => 'skipped', 'error_code' => 'RESET_REPLACED', 'payload' => null]);
    }
}
