<?php

namespace Tests\Concerns;

use App\Data\IssueQueryData;
use App\Data\PageQueryData;
use App\Models\Issue;
use App\Models\Project;
use App\Repositories\IssueReadRepository;
use Illuminate\Support\Facades\DB;

/** 驗證當頁Epic批次估時, 不讀無關卡片內容或活動 */
trait EpicEstimateProjectionCases
{
    /**
     * 一般卡片沒有Epic估時子查詢, Epic只加總同專案直接所屬卡片
     *
     * @return void
     */
    public function test_epic_estimates_are_batched_only_for_current_page(): void
    {
        $workspace = DB::table('workspaces')->insertGetId(['public_id' => 'projection-ws', 'name' => 'Synthetic']);
        $makeProject = function (string $code) use ($workspace): Project {
            $id = DB::table('projects')->insertGetId(['workspace_id' => $workspace, 'public_id' => $code, 'name' => $code, 'code' => $code]);
            DB::table('project_statuses')->insert(['workspace_id' => $workspace, 'project_id' => $id, 'code' => 'open', 'label' => 'Open', 'category' => 'open', 'color' => '#888888', 'position' => 0]);

            return Project::findOrFail($id);
        };
        $project = $makeProject('PROJ');
        $other = $makeProject('OTHER');
        $create = function (Project $p, string $id, string $type, int $estimate = 0, ?int $epic = null): Issue {
            return Issue::create(['workspace_id' => $p->workspace_id, 'project_id' => $p->id, 'public_id' => $id, 'identifier' => $id, 'title' => $id,
                'type' => $type, 'estimate_minutes' => $estimate, 'epic_id' => $epic, 'state_id' => DB::table('project_statuses')->where('project_id', $p->id)->value('id')]);
        };
        $epic = $create($project, 'A', 'epic', 999);
        $empty = $create($project, 'B', 'epic', 999);
        $create($project, 'C', 'task', 45, $epic->id);
        $create($project, 'D', 'bug', 75, $epic->id);
        $create($project, 'E', 'task', 900);
        $foreign = $create($other, 'OTHER-A', 'epic');
        $create($other, 'OTHER-B', 'task', 500, $foreign->id);
        $repo = app(IssueReadRepository::class);
        DB::enableQueryLog();
        try {
            DB::flushQueryLog();
            $tasks = $repo->page($project, '', new IssueQueryData(new PageQueryData(1, 20), type: 'task'));
            $this->assertSame(2, $tasks->total());
            $this->assertSame([45, 900], array_map(fn ($i) => (int) $i->estimate_minutes, $tasks->items()));
            $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains(strtolower($q['query']), 'sum(estimate_minutes)')));
            DB::flushQueryLog();
            $first = $repo->page($project, '', new IssueQueryData(new PageQueryData(1, 1), type: 'epic'));
            $this->assertSame('A', $first->items()[0]->public_id);
            $this->assertSame(120, $first->items()[0]->epic_estimate_minutes);
            $aggregate = array_values(array_filter(DB::getQueryLog(), fn ($q) => str_contains(strtolower($q['query']), 'sum(estimate_minutes)')));
            $this->assertCount(1, $aggregate);
            $this->assertSame([$project->workspace_id, $project->id, $epic->id], $aggregate[0]['bindings']);
            $second = $repo->page($project, '', new IssueQueryData(new PageQueryData(2, 1), type: 'epic'));
            $this->assertSame(0, $second->items()[0]->epic_estimate_minutes);
            $this->assertSame(120, $repo->find($project, 'A')->epic_estimate_minutes);
            $this->assertSame(0, $repo->find($project, 'B')->epic_estimate_minutes);
            $this->assertNull($repo->find($project, 'OTHER-A'));
            DB::flushQueryLog();
            $repo->page($project, '', new IssueQueryData(new PageQueryData(1, 20)), listOnly: true);
            $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains(strtolower($q['query']), 'sum(estimate_minutes)')));
        } finally {
            DB::disableQueryLog();
        }
    }
}
