<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions;
use App\Data\MailDeliveryData;
use App\Data\PageQueryData;
use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Factories\RecordStoreFactory;
use App\Repositories\WorkspaceInvitationRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** 邀請建立、加密重試結果與待接受目錄, 不提前授予成員資格 */
class WorkspaceInvitationService
{
    /**
     * 注入資料、寄信、原有receipt及加密邊界, 不在建構時讀資料
     *
     * @param  WorkspaceInvitationRepository  $invitations  單工作區邀請資料
     * @param  MailDeliveryService  $mail  交易提交後寄信
     * @param  RequestReplay  $replay  授權後的防重複入口
     * @param  RecordStoreFactory  $stores  既有receipt資料入口
     * @param  Encrypter  $encryption  邀請結果含token的加密保存
     */
    public function __construct(private WorkspaceInvitationRepository $invitations, private MailDeliveryService $mail, private RequestReplay $replay, private RecordStoreFactory $stores, private Encrypter $encryption) {}

    /**
     * 建立單次邀請並寄信, 每次重播前仍先驗當前資格
     *
     * @param  WorkspaceContext  $context  交易內可信身分與工作區
     * @param  array<string,mixed>  $input  邀請原始輸入及可選舊版requestId
     * @return array{token:string,email:string,expiresAt:string} 原邀請結果
     *
     * @throws DomainError 權限、地址、既有成員或重試簽章不符
     */
    public function create(WorkspaceContext $context, array $input): array
    {
        WorkspacePermissions::authorize($context->legacyIdentity(), 'workspace.invite');
        $email = strtolower(Fields::text($input['email'] ?? null, 'Email', 254, true));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            DomainError::fail('INVALID_INPUT', 'Email格式不正確');
        }
        // 舊呼叫端未提供key時維持單次建立, 新網頁固定保存並沿用同一requestId
        $input['requestId'] ??= (string) Str::uuid();
        $workspace = $this->invitations->workspace($context->workspaceId);
        $result = $this->replay->execute($this->stores->forWorkspace($context->workspaceId), 'requests', $context->userId, 'inviteMember', $input, function () use ($workspace, $email, $context) {
            if ($this->invitations->hasMember($workspace, $email)) {
                DomainError::fail('CONFLICT', '此帳號已是工作空間成員');
            }
            $token = bin2hex(random_bytes(32));
            $expires = now()->addDays(7);
            $this->invitations->expirePrevious($workspace, $email);
            $invite = $this->invitations->create(['public_id' => hash('sha256', $token), 'workspace_id' => $workspace->id, 'email' => $email, 'created_at' => now(), 'expires_at' => $expires]);
            $base = rtrim((string) config('app.url'), '/');
            if (! filter_var($base, FILTER_VALIDATE_URL) || (! in_array(config('app.env'), ['local', 'testing'], true) && ! str_starts_with($base, 'https://'))) {
                DomainError::fail('INVALID_INPUT', '邀請連結網址尚未配置');
            }
            $body = "你受邀加入{$workspace->name}\n\n{$base}/app/#/invite?token={$token}\n\n連結7天內有效, 請使用{$email}接受邀請";
            $this->mail->enqueue(new MailDeliveryData($context->userId, 'invitation:'.$invite->public_id, 'invitation', 'TaskTracker 工作空間邀請', $body, $expires, invitationId: $invite->id));

            return ['ciphertext' => $this->encryption->encrypt(json_encode(['token' => $token, 'email' => $email, 'expiresAt' => $expires->utc()->toISOString()], JSON_THROW_ON_ERROR), false)];
        });

        return json_decode($this->encryption->decrypt($result['ciphertext'], false), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * 管理者按需取得待接受邀請, 不讀已接受或到期紀錄
     *
     * @param  WorkspaceContext  $context  交易內可信身分
     * @param  PageQueryData  $filters  有界地址搜尋及頁面
     * @return LengthAwarePaginator<array<string,mixed>> 安全狀態投影
     *
     * @throws DomainError 沒有邀請管理資格
     */
    public function pending(WorkspaceContext $context, PageQueryData $filters): LengthAwarePaginator
    {
        WorkspacePermissions::authorize($context->legacyIdentity(), 'workspace.invite');

        return $this->invitations->pending($this->invitations->workspace($context->workspaceId), $filters)->through(fn ($invite) => ['id' => $invite->public_id, 'email' => $invite->email, 'role' => 'user', 'createdAt' => Carbon::parse($invite->created_at)->toISOString(), 'expiresAt' => Carbon::parse($invite->expires_at)->toISOString(), 'deliveryStatus' => $invite->delivery_status ?? 'unavailable']);
    }
}
