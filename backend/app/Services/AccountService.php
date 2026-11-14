<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions;
use App\Data\AuthenticatedAccountData;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Factories\RecordStoreFactory;
use App\Models\User;
use App\Repositories\AccountRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 處理帳號、工作空間資格與邀請；不依賴HTTP、Session或登入狀態寫入。
 */
class AccountService
{
    /**
     * 注入資料入口工廠與工作區資格服務。
     *
     * @param  RecordStoreFactory  $stores  依範圍建立資料入口
     * @param  WorkspaceMemberships  $memberships  工作區清單、切換與邀請
     * @param  AccountRepository  $accounts  帳號與最小資格資料入口
     * @param  EmailVerificationService  $verification  註冊驗證
     */
    public function __construct(private RecordStoreFactory $stores, private WorkspaceMemberships $memberships, private AccountRepository $accounts, private EmailVerificationService $verification) {}

    /**
     * 重新取得HTTP伺服器所指定的帳號，不接受前端actor。
     *
     * @param  ?string  $id  可信User ID
     * @return ?User 當次帳號
     */
    public function freshUser(?string $id): ?User
    {
        return $this->accounts->find($id);
    }

    /**
     * 回傳不含密碼及驗證秘密的帳號資訊。
     *
     * @param  User  $user  待輸出的帳號模型
     * @return array
     */
    public function publicUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * 從伺服器提供的帳號及空間ID重新驗證成員資格。
     *
     * @param  User|null  $user  HTTP邊界取得的新鮮帳號模型
     * @param  mixed  $workspaceId  HTTP邊界取得的Session空間ID
     * @return array
     */
    public function context(?User $user, mixed $workspaceId): array
    {
        if (! $user || ($user->email_verification_required && ! $user->email_verified_at) || ! is_string($workspaceId)) {
            DomainError::fail('UNAUTHENTICATED', '請先登入');
        }
        // 此處只驗當次資格，不透過相容RecordStore附帶讀取歷史／全部欄位。
        $workspace = $this->accounts->workspace($workspaceId);
        $member = $workspace ? $this->accounts->membership($workspace->id, $user->id) : null;
        if (! $workspace || ! $member) {
            DomainError::fail('UNAUTHENTICATED', '工作空間資格已失效');
        }

        return [
            'user' => $this->publicUser($user),
            'workspace' => [
                'id' => $workspaceId,
                'name' => $workspace->name,
            ],
            'role' => $member->role,
            'permissions' => WorkspacePermissions::resolve($member->role),
        ];
    }

    /** 公開註冊是否開放, 只讀部署設定且不查帳號或工作區
     * @return array{publicRegistration:bool} 匿名畫面需要的最小能力
     */
    public function registrationPolicy(): array
    {
        return ['publicRegistration' => (bool) config('tasktracker.registration.public', false)];
    }

    /**
     * 驗證註冊或邀請後建立帳號，回傳待建立Session的資格。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return AuthenticatedAccountData 已確認的帳號及空間
     */
    public function register(array $input): AuthenticatedAccountData
    {
        if (empty($input['inviteToken']) && ! $this->registrationPolicy()['publicRegistration']) {
            DomainError::fail('FORBIDDEN', '目前僅接受邀請註冊');
        }
        $email = $this->email($input['email'] ?? null);
        $name = Fields::text($input['name'] ?? null, '姓名', 60, true);
        $password = Fields::text($input['password'] ?? null, '密碼', 128, true);
        if (mb_strlen($password) < 10) {
            DomainError::fail('INVALID_INPUT', '密碼至少 10 字');
        }
        $store = $this->stores->global();
        $role = 'owner';
        $invite = null;
        if (! empty($input['inviteToken'])) {
            $token = Fields::text($input['inviteToken'], '邀請碼', 100, true);
            $invite = $store->get('invites', hash('sha256', $token));
            if (! $invite || ! empty($invite['consumedAt']) || $invite['expiresAt'] <= now()->utc()->toISOString() || $invite['email'] !== $email) {
                DomainError::fail('INVALID_INPUT', '邀請已失效或 Email 不符');
            }
        }
        if ($this->accounts->emailExists($email)) {
            DomainError::fail('CONFLICT', '此 Email 已註冊, 請登入');
        }
        if ($invite !== null) {
            $workspace = $store->require('workspaces', $invite['workspaceId']);
            $role = 'user';
            $store->put('invites', $invite['id'], [
                ...$invite,
                'consumedAt' => now()->utc()->toISOString(),
            ]);
        } else {
            $workspace = [
                'id' => (string) Str::uuid(),
                'name' => Fields::text($input['workspaceName'] ?? null, '工作空間名稱', 60, true),
                'createdAt' => now()->utc()->toISOString(),
            ];
            $first = ! $this->accounts->hasWorkspaces();
            $store->put('workspaces', $workspace['id'], $workspace);
        }
        $user = $this->accounts->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verification_required' => true,
        ]);
        $tenant = $store->forWorkspace($workspace['id']);
        $tenant->put('members', $user->id, [
            ...$this->publicUser($user),
            'role' => $role,
            'joinedAt' => now()->utc()->toISOString(),
        ]);
        if (! empty($first)) {
            foreach (['projects', 'issues'] as $kind) {
                foreach ($store->all($kind) as $record) {
                    if ($kind === 'issues' && ($record['assignee'] ?? '') === '我') {
                        $record['assignee'] = $user->id;
                    }
                    $tenant->put($kind, $record['id'], $record);
                }
            }
        }

        $this->verification->begin($user, $workspace['id']);

        return new AuthenticatedAccountData($user, $workspace['id']);
    }

    /**
     * 驗證帳號並在必要時升級舊密碼雜湊。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return AuthenticatedAccountData 已確認的帳號及空間
     */
    public function login(array $input): AuthenticatedAccountData
    {
        $email = $this->email($input['email'] ?? null);
        $password = Fields::text($input['password'] ?? null, '密碼', 128, true);
        $user = $this->accounts->byEmail($email);
        $valid = false;
        if ($user && $user->legacy_salt) {
            // Node scrypt 預設 N=16384,r=8,p=1；舊 salt 是 32 字元 hex 字串
            $derived = sodium_crypto_pwhash_scryptsalsa208sha256(64, $password, $user->legacy_salt, 524288, 16777216);
            $valid = hash_equals($user->legacy_hash, bin2hex($derived));
            if ($valid) {
                $this->accounts->update($user, [
                    'password' => Hash::make($password),
                    'legacy_salt' => null,
                    'legacy_hash' => null,
                ]);
            }
        } elseif ($user) {
            $valid = Hash::check($password, $user->password);
        }
        if (! $valid) {
            DomainError::fail('UNAUTHENTICATED', 'Email 或密碼不正確');
        }
        $memberships = $this->memberships;
        if (! empty($input['inviteToken'])) {
            $workspaceId = $memberships->accept($user, $input);
        } else {
            $workspaceId = $memberships->listing($user)[0]['id'] ?? null;
        }
        if (! $workspaceId) {
            DomainError::fail('UNAUTHENTICATED', '找不到工作空間資格');
        }

        return new AuthenticatedAccountData($user, $workspaceId);
    }

    /**
     * 驗證並正規化帳號Email。
     *
     * @param  mixed  $value  待驗證或正規化的值
     * @return string
     */
    private function email(mixed $value): string
    {
        $email = strtolower(Fields::text($value, 'Email', 254, true));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            DomainError::fail('INVALID_INPUT', 'Email 格式不正確');
        }

        return $email;
    }
}
