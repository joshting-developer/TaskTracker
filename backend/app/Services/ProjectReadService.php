<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Authorization\WorkspacePermissions;
use App\Data\AuthorizedProjectData;
use App\Data\PageQueryData;
use App\Data\ProjectPageData;
use App\Domain\DomainError;
use App\Factories\RecordStoreFactory;
use App\Models\Project;
use App\Repositories\ProjectMemberReadRepository;
use App\Repositories\ProjectReadRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/** 唯讀專案授權及查詢，可供Session或後續Token適配器共用。 */
class ProjectReadService
{
    /**
     * 注入限定資料投影、Store組裝及成員選項協作者。
     *
     * @param  ProjectReadRepository  $projects  Project有界資料入口
     * @param  RecordStoreFactory  $stores  可信Workspace組裝邊界
     * @param  ProjectMemberReadRepository  $members  最小成員選項投影
     * @return void
     */
    public function __construct(private ProjectReadRepository $projects, private RecordStoreFactory $stores, private ProjectMemberReadRepository $members) {}

    /**
     * 建立指定ID集合的當次資格快照，不持久快取或讀取歷史。
     *
     * @param  array<string,mixed>  $actor  交易內驗證的操作者
     * @param  array<int,string>  $projectIds  至多100個Project公開ID
     * @return array{ProjectAccess, Collection<int,Project>} 授權與Project參照
     */
    private function visibility(array $actor, array $projectIds): array
    {
        $store = $this->stores->forWorkspace($actor['workspace']['id']);
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

    /**
     * 在SQL分頁後以同一快照計算當頁能力。
     *
     * @param  array<string,mixed>  $actor  可信操作者
     * @param  PageQueryData  $filters  有界分頁及搜尋
     * @return ProjectPageData 當頁摘要與權限
     */
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

    /**
     * 先驗建立或單Project編輯資格，再查有效負責人候選。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  PageQueryData  $filters  有界搜尋與分頁
     * @param  string|null  $projectId  null代表建立Project用途
     * @return LengthAwarePaginator 有效Workspace成員最小選項
     */
    public function leadOptions(array $actor, PageQueryData $filters, ?string $projectId): LengthAwarePaginator
    {
        if ($projectId !== null) {
            $project = $this->require($actor, $projectId, 'project.update');

            return $this->members->page($project, $filters)->through(fn ($member) => (object) ['id' => $member->user_id, 'name' => $member->getRelation('user')->name]);
        }
        WorkspacePermissions::authorize($actor, 'project.create');
        $roles = array_values(array_filter(['owner', 'manager', 'admin', 'user', 'member', 'maintainer', 'developer'], fn ($role) => in_array('project.readAny', WorkspacePermissions::resolve($role), true)));

        return $this->members->leads($actor['workspace']['id'], $roles, $filters);
    }

    /**
     * 用有效issue.write映射限定轉卡Project候選，不把讀者當可寫者。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  PageQueryData  $filters  有界搜尋與分頁
     * @return LengthAwarePaginator<Project> 可寫卡片的Project候選
     */
    public function options(array $actor, PageQueryData $filters): LengthAwarePaginator
    {
        $roles = array_column(array_filter(ProjectAccess::roles(), fn ($role) => in_array('issue.write', $role['permissions'], true)), 'id');

        return $this->projects->options($actor['workspace']['id'], $actor['user']['id'], in_array('issue.writeAny', $actor['permissions'], true), $roles, $filters);
    }

    /**
     * 依本次最多100個Project參照批次重建權限，不查逐卡完整Project。
     *
     * @param  array<string,mixed>  $actor  可信操作者
     * @param  array<int,string>  $projectIds  當頁Project公開ID
     * @return array<string,array<int,string>> Project有效permission對照
     */
    public function permissionsFor(array $actor, array $projectIds): array
    {
        $ids = array_values(array_unique($projectIds));
        [$access] = $this->visibility($actor, $ids);
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = $access->permissions($id);
        }

        return $result;
    }

    /**
     * 取得通過指定permission驗證的Project，隱藏不可見資源存在性。
     *
     * @param  array<string,mixed>  $actor  可信操作者
     * @param  string  $id  Project公開ID
     * @param  string  $permission  必要權限，預設project.read
     * @return Project 已授權Project
     */
    public function require(array $actor, string $id, string $permission = 'project.read'): Project
    {
        return $this->authorized($actor, $id, $permission)->project;
    }

    /**
     * 以同一授權快照提供Project及permission，避免用例再次建立資格。
     *
     * @param  array<string,mixed>  $actor  交易內驗證的操作者
     * @param  string  $id  Project公開ID
     * @param  string  $permission  必要permission
     * @return AuthorizedProjectData 當次Project及有效權限
     *
     * @throws DomainError 無權或Project不存在
     */
    public function authorized(array $actor, string $id, string $permission = 'project.read'): AuthorizedProjectData
    {
        [$access, $projects] = $this->visibility($actor, [$id]);
        $this->authorize($access, $id, $permission);
        $project = $projects->firstWhere('public_id', $id) ?? DomainError::fail('NOT_FOUND', '找不到專案');

        return new AuthorizedProjectData($project, $access->permissions($id));
    }

    /**
     * 讀取單Project屬性、狀態與本人的能力，不附全員目錄。
     *
     * @param  array<string,mixed>  $actor  可信操作者
     * @param  string  $id  Project公開ID
     * @return array<string,mixed> Project屬性及能力
     */
    public function show(array $actor, string $id): array
    {
        [$access] = $this->visibility($actor, [$id]);
        $this->authorize($access, $id, 'project.read');

        return ['project' => $this->projects->find($actor['workspace']['id'], $id), 'capabilities' => [
            'permissions' => $access->permissions($id), 'accessSource' => $access->source($id),
            // 成員選項改由獨立有界查詢提供，不在專案屬性附帶全員名單。
        ]];
    }

    /**
     * 取得同Project的最小成員選項，不公開工作區目錄或email。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  string  $id  Project公開ID
     * @param  PageQueryData  $filters  有界條件
     * @return LengthAwarePaginator 有效成員選項
     */
    public function memberOptions(array $actor, string $id, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->members->page($this->require($actor, $id, 'issue.read'), $filters);
    }

    /**
     * 區分不可見Project及缺少操作權，依目前快照拒絕請求。
     *
     * @param  ProjectAccess  $access  當次授權快照
     * @param  string  $id  Project公開ID
     * @param  string  $permission  必要權限
     * @return void
     */
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
