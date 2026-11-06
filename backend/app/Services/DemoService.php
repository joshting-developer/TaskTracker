<?php

namespace App\Services;

use App\Domain\DomainError;
use App\Repositories\RecordStore;

/**
 * 透過既有專案及任務服務建立可重複辨識的示範工作。
 */
class DemoService
{
    /**
     * 設定此服務使用的資料入口與可信任相依。
     *
     * @param  RecordStore  $store  限定工作空間的資料存取入口
     * @param  array  $workspaceSession  從後端Session重新驗證的工作空間身分與權限
     * @param  ProjectService  $projects  專案資訊與狀態管理服務
     * @param  IssueService  $issues  任務及關聯管理服務
     * @return void
     */
    public function __construct(private RecordStore $store, private array $workspaceSession, private ProjectService $projects, private IssueService $issues) {}

    /**
     * 建立單一示範專案及其預設任務。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function loadDemo(array $input): array
    {
        if (in_array('DEMO', array_column($this->store->all('projects'), 'code'), true)) {
            DomainError::fail('DEMO_EXISTS', '示範專案已存在, 不會重複載入');
        }
        $project = $this->projects->createProject([
            'name' => '產品第一版 · 示範',
            'code' => 'DEMO',
            'description' => '用這個示範專案試試清單、看板與任務修改, 內容不代表正式需求',
        ]);
        $rows = [
            ['整理第一版需求與使用流程', 'closed', 'high', 'task'],
            ['建立專案與任務資料結構', 'closed', 'normal', 'task'],
            ['設計任務清單的搜尋與篩選', 'inprogress', 'high', 'task'],
            ['讓看板拖曳與任務狀態同步', 'inprogress', 'normal', 'task'],
            ['手機上的表單需要更好操作', 'open', 'high', 'bug'],
            ['補上任務留言與異動紀錄', 'open', 'normal', 'task'],
            ['確認 Laravel API 部署方式', 'open', 'normal', 'task'],
            ['討論下一次迭代範圍', 'open', 'low', 'task'],
            ['研究適合團隊的登入流程', 'open', 'normal', 'task'],
        ];
        foreach ($rows as $index => [$title, $state, $priority, $type]) {
            $this->issues->createIssue([
                'projectId' => $project['id'],
                'title' => $title,
                'state' => $state,
                'priority' => $priority,
                'type' => $type,
                'assignee' => in_array($index, [4, 6, 7, 8], true) ? '' : $this->workspaceSession['user']['id'],
            ]);
        }

        return ['projectId' => $project['id']];
    }
}
