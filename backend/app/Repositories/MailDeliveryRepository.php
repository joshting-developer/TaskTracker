<?php

namespace App\Repositories;

use App\Models\EmailVerification;
use App\Models\MailDelivery;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;

/** 信件唯一事件、有限派送查詢及狀態保存, 不決定寄送資格 */
class MailDeliveryRepository
{
    /** 鎖定同帳號以序列化寄送與帳號變更
     * @param  string  $id  帳號UUID
     * @return ?User 當次帳號
     */
    public function user(string $id): ?User
    {
        return User::whereKey($id)->lockForUpdate()->first();
    }

    /** 是否仍有有效空間資格
     * @param  string  $id  帳號UUID
     * @return bool 至少一筆有效成員
     */
    public function hasMembership(string $id): bool
    {
        return WorkspaceMember::where('user_id', $id)->whereNull('disabled_at')->exists();
    }

    /**
     * 取得指定邀請的寄送資格欄位, 不載入token或其他邀請
     *
     * @param  ?int  $id  信件保存的邀請內部鍵
     * @return ?WorkspaceInvitation 當次邀請或null
     */
    public function invitation(?int $id): ?WorkspaceInvitation
    {
        return $id ? WorkspaceInvitation::whereKey($id)->first(['id', 'workspace_id', 'email', 'expires_at', 'consumed_at']) : null;
    }

    /**
     * 只取得邀請者在該空間仍有效的角色, permission由Service判斷
     *
     * @param  int  $workspaceId  邀請所屬空間內部鍵
     * @param  string  $userId  信件所記邀請者
     * @return ?string 有效角色或null
     */
    public function inviterRole(int $workspaceId, string $userId): ?string
    {
        return WorkspaceMember::where('workspace_id', $workspaceId)->where('user_id', $userId)->whereNull('disabled_at')->value('role');
    }

    /** 找既有事件, 呼叫前已持有帳號鎖
     * @param  string  $key  事件SHA256
     * @return ?MailDelivery 既有outbox
     */
    public function event(string $key): ?MailDelivery
    {
        return MailDelivery::where('event_key', $key)->first();
    }

    /** 只讀指定outbox
     * @param  string  $id  信件UUID
     * @return ?MailDelivery 狀態或null
     */
    public function find(string $id): ?MailDelivery
    {
        return MailDelivery::find($id);
    }

    /** 管理命令只讀指定事件的安全狀態欄位
     * @param  string  $id  信件UUID
     * @return ?MailDelivery 最小狀態投影
     */
    public function state(string $id): ?MailDelivery
    {
        return MailDelivery::whereKey($id)->first(['id', 'status', 'attempts', 'error_code', 'sent_at']);
    }

    /** 已持有帳號鎖後再鎖信件, 不跨其他事件
     * @param  string  $id  信件UUID
     * @return ?MailDelivery 同一outbox
     */
    public function lock(string $id): ?MailDelivery
    {
        return MailDelivery::whereKey($id)->lockForUpdate()->first();
    }

    /** 保存已由Service選定的加密屬性
     * @param  array<string,mixed>  $attributes  初始欄位
     * @return MailDelivery 新信件
     */
    public function create(array $attributes): MailDelivery
    {
        return MailDelivery::create($attributes);
    }

    /** 更新同一信件狀態
     * @param  MailDelivery  $row  已鎖定信件
     * @param  array<string,mixed>  $attributes  狀態欄位
     * @return MailDelivery 更新後實體
     */
    public function update(MailDelivery $row, array $attributes): MailDelivery
    {
        $row->forceFill($attributes)->save();

        return $row;
    }

    /** 找出過期sending租約, 僅投影最多100個ID
     * @param  DateTimeInterface  $cutoff  Worker中斷判定時間
     * @return list<string> 候選事件ID
     */
    public function staleSendingIds(DateTimeInterface $cutoff): array
    {
        return MailDelivery::where('status', 'sending')->where('started_at', '<=', $cutoff)->orderBy('started_at')->orderBy('id')->limit(100)->pluck('id')->all();
    }

    /** 重新檢查狀態與期限後有界批次更新, 不覆蓋已完成的事件
     * @param  list<string>  $ids  候選ID
     * @param  DateTimeInterface  $cutoff  原本選取期限
     * @param  array<string,mixed>  $attributes  Service選定終態
     * @return int 更新件數
     */
    public function updateStaleSending(array $ids, DateTimeInterface $cutoff, array $attributes): int
    {
        return $ids ? MailDelivery::whereIn('id', $ids)->where('status', 'sending')->where('started_at', '<=', $cutoff)->update($attributes) : 0;
    }

    /** 只檢查指定帳號及當次驗證的寄送資格
     * @param  string  $userId  已鎖帳號
     * @param  string  $id  當次驗證ID
     * @return bool 是否仍有效
     */
    public function verificationValid(string $userId, string $id): bool
    {
        return EmailVerification::whereKey($id)->where('user_id', $userId)->whereNull('consumed_at')->where('expires_at', '>', now())->where('attempts', '<', 5)->exists();
    }

    /** 排程只取到期pending, 不解密內容或讀全表到PHP
     * @return Collection<int,MailDelivery> 最多100筆
     */
    public function pending(): Collection
    {
        return MailDelivery::where('status', 'pending')->where('next_attempt_at', '<=', now())->orderBy('next_attempt_at')->orderBy('id')->limit(100)->get(['id']);
    }
}
