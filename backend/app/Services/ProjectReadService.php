<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Data\PageQueryData;
use App\Data\ProjectPageData;
use App\Domain\DomainError;
use App\Models\Project;
use App\Repositories\ProjectReadRepository;
use App\Repositories\RecordStore;
use Illuminate\Database\Eloquent\Collection;

/** 唯讀專案授權及查詢，可供Session或後續Token適配器共用。 */
class ProjectReadService
{
    /** 建立此用例的相依與唯讀資料，不從HTTP取得身分。 */
    public function __construct(private ProjectReadRepository $projects) {}

    /** 當次查詢的最小授權集合；不持久快取，也不載Project歷史。
     * @return array{ProjectAccess, Collection}
     */
    private function visibility(array $actor, array $projectIds): array
    {
        $store = (new RecordStore)->forWorkspace($actor['workspace']['id']);
        $projects = $this->projects->references($actor['workspace']['id'], $projectIds);
        // actor已由同一交易內的AccountService重驗；不從前端或原始Session信任此資格。
        $members = [['id' => $actor['user']['id'], 'role' => $actor['role']]];
        $assignments = $this->projects->assignments($actor['workspace']['id'], $actor['user']['id'], $projectIds)->map(fn ($assignment) => [
            'id' => $assignment->public_id, 'projectId' => $assignment->getRelation('project')->public_id,
            'userId' => $assignment->getRelation('member')->user_id, 'roleId' => $assignment->role_id,
            'removedAt' => $assignment->removed_at,
        ])->all();
        $access = ProjectAccess::forRead($store, $actor, $projects->map(fn ($p) => ['id' => $p->public_id])->all(), $members, $assignments);

        return [$access, $projects];
    }

    /** 讓分頁與能力取自同一資格快照，禁止在Resource逐項查授權。 */
    public function index(array $actor, PageQueryData $filters): ProjectPageData
    {
        // roleId只用來查permission映射，再把符合project.read的指派條件送入SQL。
        $readRoleIds = array_column(array_filter(ProjectAccess::roles(), fn ($role) => in_array('project.read', $role['permissions'], true)), 'id');
        $page = $this->projects->page($actor['workspace']['id'], $actor['user']['id'], in_array('project.readAny', $actor['permissions'], true), $readRoleIds, $filters);
        [$access] = $this->visibility($actor, array_map(fn ($p) => $p->public_id, $page->items()));
        $permissions = [];
        foreach ($page->items() as $project) {
            $permissions[$project->public_id] = $access->permissions($project->public_id);
        }

        return new ProjectPageData($page, $permissions);
    }

    /** 限定資源前先驗當次有效資格，對外隱藏不可見Project存在性。 */
    public function require(array $actor, string $id, string $permission = 'project.read'): Project
    {
        [$access, $projects] = $this->visibility($actor, [$id]);
        $this->authorize($access, $id, $permission);

        return $projects->firstWhere('public_id', $id) ?? DomainError::fail('NOT_FOUND', '找不到專案');
    }

    /** 專案單頁需要的屬性與能力資訊，不輸出設定／ACL歷史。 */
    public function show(array $actor, string $id): array
    {
        [$access] = $this->visibility($actor, [$id]);
        $this->authorize($access, $id, 'project.read');

        return ['project' => $this->projects->find($actor['workspace']['id'], $id), 'capabilities' => [
            'permissions' => $access->permissions($id), 'accessSource' => $access->source($id),
            // 成員選項改由獨立有界查詢提供，不在專案屬性附帶全員名單。
        ]];
    }

    /** 維持不可見資源404與缺少操作權403的差異。 */
    private function authorize(ProjectAccess $access, string $id, string $permission): void
    {
        if (! $access->can($id, 'project.read')) {
            DomainError::fail('NOT_FOUND', '找不到或無權存取這個專案');
        }
        if (! $access->can($id, $permission)) {
            DomainError::fail('FORBIDDEN', '沒有執行此操作的權限');
        }
    }
}
