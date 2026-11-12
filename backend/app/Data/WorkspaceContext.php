<?php

namespace App\Data;

/** 當次已驗證工作空間資格的不可變快照；不是可持久化或免重驗的授權憑證。 */
final readonly class WorkspaceContext
{
    public string $userId;

    public string $workspaceId;

    /** @var list<string> 依工作區角色對照出的有效permission ID */
    public array $permissions;

    /** 工作區角色ID；只用來查權限對照，放行判斷一律看permissions。 */
    public string $role;

    /**
     * 只能由WorkspaceContextFactory傳入AccountService重驗結果，不接受HTTP payload。
     *
     * @internal 建構子是純值組裝，信任來源由Factory及HTTP Session邊界負責。
     *
     * @param  array{user:array{id:string,name:string,email:string},workspace:array{id:string,name:string},role:string,permissions:array}  $identity  伺服器重新驗證的資格
     */
    public function __construct(private array $identity)
    {
        $this->userId = $identity['user']['id'];
        $this->workspaceId = $identity['workspace']['id'];
        $this->permissions = array_values($identity['permissions'] ?? []);
        $this->role = (string) ($identity['role'] ?? '');
    }

    /**
     * 明確轉接尚未遷移的array用例，不公開Request／Session／Model。
     *
     * @return array{user:array{id:string,name:string,email:string},workspace:array{id:string,name:string},role:string,permissions:array} 原資格契約的值副本
     */
    public function legacyIdentity(): array
    {
        return $this->identity;
    }
}
