<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * 登入帳號；工作區資格與角色另由WorkspaceMember保存。
 */
#[Fillable(['id', 'name', 'email', 'password', 'email_verification_required'])]
#[Hidden(['password', 'remember_token', 'legacy_salt', 'legacy_hash'])]
class User extends Authenticatable
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** @use HasFactory<UserFactory> */
    use HasFactory, \Laravel\Sanctum\HasApiTokens, Notifiable;

    /**
     * 取得帳號在各工作空間的成員資格。
     *
     * @return HasMany
     */
    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * 取得加入的工作空間；實際操作仍須經集中權限檢查。
     *
     * @return BelongsToMany
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')->withPivot(['role', 'disabled_at']);
    }

    /**
     * 設定驗證時間及密碼雜湊型別。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_required' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
