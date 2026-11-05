<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** 私有專案變更提示；不廣播任務、成員或授權內容。 */
class ProjectChanged implements ShouldBroadcast
{
    public int $tries = 3;

    public array $backoff = [1, 5, 10];

    /** 建立由伺服器決定範圍的事件。
     * @param  string  $workspaceId  可信空間公開ID
     * @param  string  $projectId  可信專案公開ID
     * @return void
     */
    public function __construct(private string $workspaceId, private string $projectId) {}

    /** 取得需要HTTP授權的私有專案頻道。
     * @return array
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('workspaces.'.$this->workspaceId.'.projects.'.$this->projectId)];
    }

    /** 使用穩定的客戶端事件名稱。
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'project.changed';
    }

    /** 只發無內容訊號，資料須重新經API授權讀取。
     * @return array
     */
    public function broadcastWith(): array
    {
        return ['changed' => true];
    }
}
