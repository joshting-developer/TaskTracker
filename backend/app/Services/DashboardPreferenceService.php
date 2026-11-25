<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions;
use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Models\WorkspaceMember;
use App\Repositories\DashboardPreferenceRepository;

/**
 * TT-186：首頁小工具只存順序與顯示, 不存資料；白名單外與無權限的小工具不回傳也不接受。
 * 沒有偏好時回預設全部顯示, revision 0。
 */
class DashboardPreferenceService
{
    /** 可用小工具與預設順序 */
    public const WIDGETS = ['mine', 'due', 'starting', 'recent', 'notes', 'tasks'];

    /** 注入偏好資料入口
     * @param  DashboardPreferenceRepository  $preferences  偏好SQL
     */
    public function __construct(private DashboardPreferenceRepository $preferences) {}

    /** 目前偏好；過時或無權限的項目過濾後回傳
     * @param  WorkspaceContext  $context  可信資格
     * @return array{widgets:array<int,string|array>,available:array<int,string>,revision:int} 偏好
     */
    public function show(WorkspaceContext $context): array
    {
        $member = $this->member($context);
        $current = $this->preferences->find($member);

        return $this->present($context, $current?->widgets ?? array_values(array_diff(self::WIDGETS, ['tasks'])), $current?->revision ?? 0);
    }

    /** 整份覆寫順序與顯示；同內容重送視為成功, 版本不符且內容不同回衝突
     * @param  WorkspaceContext  $context  可信資格
     * @param  array<int,string|array>  $widgets  已驗證白名單內的順序
     * @param  int  $expectedRevision  讀到的版本
     * @return array{widgets:array<int,string|array>,available:array<int,string>,revision:int} 保存後偏好
     */
    public function update(WorkspaceContext $context, array $widgets, int $expectedRevision): array
    {
        $member = $this->member($context);
        $widgets = $this->filterWidgets($widgets, $this->available($context));
        $current = $this->preferences->find($member, true);
        $revision = $current?->revision ?? 0;
        if ($revision !== $expectedRevision) {
            if ($current && $current->widgets === $widgets) {
                return $this->present($context, $current->widgets, $revision);
            }
            DomainError::fail('REVISION_CONFLICT', '首頁設定已在其他地方更新, 請重新載入');
        }
        $saved = $this->preferences->save($member, $current, $widgets);

        return $this->present($context, $saved->widgets, $saved->revision);
    }

    /** 依權限過濾並附上可加入的項目
     * @param  WorkspaceContext  $context  可信資格
     * @param  array<int,string|array>  $widgets  儲存的順序
     * @param  int  $revision  版本
     * @return array{widgets:array<int,string|array>,available:array<int,string>,revision:int} 回應
     */
    private function present(WorkspaceContext $context, array $widgets, int $revision): array
    {
        $available = $this->available($context);

        return ['widgets' => $this->filterWidgets($widgets, $available), 'available' => $available, 'revision' => $revision];
    }

    /**
     * 依種類與實例識別過濾偏好, 相容舊版字串設定。
     *
     * @param  array  $widgets  已保存或送入的小工具設定
     * @param  array  $available  目前權限可用的種類
     * @return array<int,mixed> 過濾後的小工具設定
     */
    private function filterWidgets(array $widgets, array $available): array
    {
        $seen = [];

        return array_values(array_filter($widgets, function ($widget) use ($available, &$seen) {
            $kind = is_string($widget) ? $widget : ($widget['kind'] ?? '');
            $id = is_string($widget) ? $widget : ($widget['id'] ?? '');
            if (! in_array($kind, $available, true) || isset($seen[$id])) {
                return false;
            }
            $seen[$id] = true;

            return true;
        }));
    }

    /** 目前身分可用的小工具；紙條需 todo.read
     * @param  WorkspaceContext  $context  可信資格
     * @return array<int,string> 可用項目
     */
    private function available(WorkspaceContext $context): array
    {
        return array_values(array_filter(self::WIDGETS, fn ($widget) => $widget !== 'notes' || in_array('todo.read', $context->permissions, true)));
    }

    /** 重驗工作區讀權與有效成員
     * @param  WorkspaceContext  $context  可信資格
     * @return WorkspaceMember 有效成員
     */
    private function member(WorkspaceContext $context): WorkspaceMember
    {
        WorkspacePermissions::authorize($context->legacyIdentity(), 'workspace.read');

        return $this->preferences->member($context->workspaceId, $context->userId) ?? DomainError::fail('UNAUTHENTICATED', '工作空間資格已失效');
    }
}
