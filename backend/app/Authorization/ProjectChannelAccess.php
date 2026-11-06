<?php

namespace App\Authorization;

use App\Domain\DomainError;
use App\Repositories\RecordStore;

/** 私有看板頻道的領域授權，不負責HTTP或Broadcast簽章。 */
class ProjectChannelAccess
{
    /**
     * @param  array  $context  從Session重新驗證的工作空間資格
     * @param  string  $channel  固定格式的私有頻道
     * @param  string  $socket  Pusher socket識別碼
     * @return void
     */
    public function authorize(array $context, string $channel, string $socket): void
    {
        if (! preg_match('/^private-workspaces\.([A-Za-z0-9_-]{1,100})\.projects\.([A-Za-z0-9_-]{1,100})$/D', $channel, $parts) || ! preg_match('/^\d+\.\d+$/D', $socket)) {
            DomainError::fail('INVALID_INPUT', '無效的即時頻道或連線');
        }
        if ($parts[1] !== $context['workspace']['id']) {
            DomainError::fail('FORBIDDEN', '沒有訂閱此專案的權限');
        }
        $access = new ProjectAccess((new RecordStore)->forWorkspace($parts[1]), $context);
        $access->require($parts[2], 'project.read');
        $access->require($parts[2], 'issue.read');
    }
}
