<?php

namespace App\Repositories;

use App\Models\Workspace;
use App\Models\WorkspaceCreationRequest;
use App\Models\WorkspaceMember;

/** 新工作空間、成員與建立receipt的限定資料存取 */
class WorkspaceCreationRepository
{
    /**
     * 查本人及單一requestId, 不讀其他帳號的建立紀錄
     *
     * @param  string  $userId  已鎖操作者
     * @param  string  $requestId  UUID請求識別碼
     * @return ?WorkspaceCreationRequest 當次建立結果
     */
    public function receipt(string $userId, string $requestId): ?WorkspaceCreationRequest
    {
        return WorkspaceCreationRequest::where('user_id', $userId)->where('request_id', $requestId)->first();
    }

    /**
     * 讀取receipt對應的最小工作空間資料
     *
     * @param  ?int  $id  資料庫內部鍵, null表示已刪除
     * @return ?Workspace 單一結果或null
     */
    public function workspace(?int $id): ?Workspace
    {
        return $id ? Workspace::whereKey($id)->first(['id', 'public_id', 'name']) : null;
    }

    /**
     * 重播前重驗操作者仍有該工作空間的有效資格
     *
     * @param  int  $workspaceId  目標內部鍵
     * @param  string  $userId  本人帳號
     * @return bool 是否仍可存取
     */
    public function active(int $workspaceId, string $userId): bool
    {
        return WorkspaceMember::where('workspace_id', $workspaceId)->where('user_id', $userId)->whereNull('disabled_at')->exists();
    }

    /**
     * 保存用例已確認的新空間資料
     *
     * @param  array<string,mixed>  $attributes  明確的新空間欄位
     * @return Workspace 已建立空間
     */
    public function create(array $attributes): Workspace
    {
        return Workspace::create($attributes);
    }

    /**
     * 保存建立者的Owner資格及請求結果, 與外層交易一起提交
     *
     * @param  array<string,mixed>  $member  已由用例設定的本人角色欄位
     * @param  array<string,mixed>  $receipt  本人請求與新空間指標
     * @return void
     */
    public function saveCreation(array $member, array $receipt): void
    {
        WorkspaceMember::create($member);
        WorkspaceCreationRequest::create($receipt);
    }
}
