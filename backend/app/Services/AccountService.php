<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 處理帳號、Session工作空間資格與邀請，避免信任前端身分。
 */
class AccountService
{
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
     * 從Session及目前成員資格建立可信任工作空間身分。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @return array
     */
    public function context(Request $request): array
    {
        $user = $request->user()?->fresh();
        $workspaceId = $request->session()->get('workspace_id');
        if (! $user || ! is_string($workspaceId)) {
            DomainError::fail('UNAUTHENTICATED', '請先登入');
        }
        $store = new RecordStore;
        $workspace = $store->get('workspaces', $workspaceId);
        $member = $store->forWorkspace($workspaceId)->get('members', $user->id);
        if (! $workspace || ! $member || ! empty($member['disabledAt'])) {
            DomainError::fail('UNAUTHENTICATED', '工作空間資格已失效');
        }

        return [
            'user' => $this->publicUser($user),
            'workspace' => [
                'id' => $workspaceId,
                'name' => $workspace['name'],
            ],
            'role' => $member['role'],
            'permissions' => WorkspacePermissions::resolve($member['role']),
        ];
    }

    /**
     * 驗證註冊或邀請後建立帳號並更新Session。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function register(Request $request, array $input): array
    {
        $email = $this->email($input['email'] ?? null);
        $name = Fields::text($input['name'] ?? null, '姓名', 60, true);
        $password = Fields::text($input['password'] ?? null, '密碼', 128, true);
        if (mb_strlen($password) < 10) {
            DomainError::fail('INVALID_INPUT', '密碼至少 10 字');
        }
        if (User::where('email', $email)->exists()) {
            DomainError::fail('CONFLICT', '此 Email 已註冊, 請登入');
        }
        $store = new RecordStore;
        $role = 'owner';
        if (! empty($input['inviteToken'])) {
            $token = Fields::text($input['inviteToken'], '邀請碼', 100, true);
            $invite = $store->get('invites', hash('sha256', $token));
            if (! $invite || ! empty($invite['consumedAt']) || $invite['expiresAt'] <= now()->utc()->toISOString() || $invite['email'] !== $email) {
                DomainError::fail('INVALID_INPUT', '邀請已失效或 Email 不符');
            }
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
            $first = ! $store->all('workspaces');
            $store->put('workspaces', $workspace['id'], $workspace);
        }
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
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
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('workspace_id', $workspace['id']);

        return $this->context($request);
    }

    /**
     * 驗證帳號並在必要時升級舊密碼雜湊。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function login(Request $request, array $input): array
    {
        $email = $this->email($input['email'] ?? null);
        $password = Fields::text($input['password'] ?? null, '密碼', 128, true);
        $user = User::where('email', $email)->first();
        $valid = false;
        if ($user && $user->legacy_salt) {
            // Node scrypt 預設 N=16384,r=8,p=1；舊 salt 是 32 字元 hex 字串
            $derived = sodium_crypto_pwhash_scryptsalsa208sha256(64, $password, $user->legacy_salt, 524288, 16777216);
            $valid = hash_equals($user->legacy_hash, bin2hex($derived));
            if ($valid) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'legacy_salt' => null,
                    'legacy_hash' => null,
                ])->save();
            }
        } elseif ($user) {
            $valid = Hash::check($password, $user->password);
        }
        if (! $valid) {
            DomainError::fail('UNAUTHENTICATED', 'Email 或密碼不正確');
        }
        $workspaceId = null;
        $store = new RecordStore;
        foreach ($store->all('workspaces') as $workspace) {
            $member = $store->forWorkspace($workspace['id'])->get('members', $user->id);
            if ($member && empty($member['disabledAt'])) {
                $workspaceId = $workspace['id'];
                break;
            }
        }
        if (! $workspaceId) {
            DomainError::fail('UNAUTHENTICATED', '找不到工作空間資格');
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('workspace_id', $workspaceId);

        return $this->context($request);
    }

    /**
     * 確認邀請權限後建立具有期限的單次邀請。
     *
     * @param  array  $context  此步驟使用的context資料
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function invite(array $context, array $input): array
    {
        WorkspacePermissions::authorize($context, 'workspace.invite');
        $email = $this->email($input['email'] ?? null);
        if (User::where('email', $email)->exists()) {
            DomainError::fail('CONFLICT', '目前邀請限未註冊的新帳號');
        }
        $token = bin2hex(random_bytes(32));
        $id = hash('sha256', $token);
        $invite = [
            'id' => $id,
            'workspaceId' => $context['workspace']['id'],
            'email' => $email,
            'expiresAt' => now()->addDays(7)->utc()->toISOString(),
            'consumedAt' => null,
            'createdAt' => now()->utc()->toISOString(),
        ];
        (new RecordStore)->put('invites', $id, $invite);

        return [
            'token' => $token,
            'email' => $email,
            'expiresAt' => $invite['expiresAt'],
        ];
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
