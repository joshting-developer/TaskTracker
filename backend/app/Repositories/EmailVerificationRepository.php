<?php

namespace App\Repositories;

use App\Models\EmailVerification;
use App\Models\MailDelivery;
use App\Models\User;

/** 單一帳號與當次驗證資料入口, 不判斷驗證碼或登入規則 */
class EmailVerificationRepository
{
    /** 依可信ID取得單一帳號鎖
     * @param  string  $id  帳號UUID
     * @return ?User 鎖定帳號
     */
    public function user(string $id): ?User
    {
        return User::whereKey($id)->lockForUpdate()->first();
    }

    /** 只讀指定帳號的當次驗證
     * @param  string  $id  帳號UUID
     * @return ?EmailVerification 當次驗證
     */
    public function current(string $id): ?EmailVerification
    {
        return EmailVerification::where('user_id', $id)->first();
    }

    /** 以唯一連結雜湊取得帳號識別
     * @param  string  $hash  連結雜湊
     * @return ?string 帳號UUID
     */
    public function tokenUser(string $hash): ?string
    {
        return EmailVerification::where('token_hash', $hash)->value('user_id');
    }

    /** 保存Service選定的帳號欄位
     * @param  User  $user  已鎖帳號
     * @param  array<string,mixed>  $attributes  已驗證屬性
     * @return void
     */
    public function updateUser(User $user, array $attributes): void
    {
        $user->forceFill($attributes)->save();
    }

    /** 檢查地址是否被其他帳號使用
     * @param  string  $email  新Email
     * @param  string  $except  本人UUID
     * @return bool 是否被其他帳號使用
     */
    public function emailExists(string $email, string $except): bool
    {
        return User::where('email', $email)->where('id', '!=', $except)->exists();
    }

    /** 建立Service驗證過的challenge
     * @param  array<string,mixed>  $attributes  新challenge
     * @return EmailVerification 新驗證
     */
    public function create(array $attributes): EmailVerification
    {
        return EmailVerification::create($attributes);
    }

    /** 移除已撤銷的舊challenge
     * @param  EmailVerification  $row  舊驗證
     * @return void
     */
    public function remove(EmailVerification $row): void
    {
        $row->delete();
    }

    /** 保存單一challenge的狀態
     * @param  EmailVerification  $row  已鎖帳號的驗證
     * @param  array<string,mixed>  $attributes  狀態變動
     * @return void
     */
    public function update(EmailVerification $row, array $attributes): void
    {
        $row->forceFill($attributes)->save();
    }

    /** 只讀本人當次驗證的安全寄送狀態, 不解密payload
     * @param  string  $userId  帳號UUID
     * @param  string  $id  當次challenge UUID
     * @return string 狀態或pending
     */
    public function deliveryStatus(string $userId, string $id): string
    {
        return MailDelivery::where('user_id', $userId)->where('verification_id', $id)->value('status') ?? 'pending';
    }

    /** 撤銷指定舊challenge尚未寄出的信件
     * @param  string  $id  舊challenge UUID
     * @return void
     */
    public function cancelMail(string $id): void
    {
        MailDelivery::where('verification_id', $id)->whereIn('status', ['pending', 'sending'])->update(['status' => 'skipped', 'error_code' => 'VERIFICATION_REPLACED', 'payload' => null]);
    }
}
