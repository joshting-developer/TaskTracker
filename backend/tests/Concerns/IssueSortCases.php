<?php

namespace Tests\Concerns;

use App\Database\DatabaseTimes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** TT-306：任務列表四種排序與升降序、換頁穩定, 以及異動清單的方向與cursor；SQLite與獨立MySQL共用。 */
trait IssueSortCases
{
    /**
     * 建4張卡並固定建立／更新時間、優先級、到期日, 方便比對順序。
     *
     * @return array{0: string, 1: array<string,string>} 專案ID與 標題→卡片ID
     */
    private function sortFixture(): array
    {
        $this->postJson('/api/auth/register', ['name' => 'Sorter', 'email' => 'sorter-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Sort'])->assertOk();
        $rpc = fn (string $op, array $input) => $this->postJson('/api/rpc/'.$op, ['requestId' => (string) Str::uuid(), ...$input])->assertOk()->json('data');
        $project = $rpc('createProject', ['name' => 'Sort', 'code' => 'SRT']);
        // 標題 => [建立時間, 更新時間, 優先級, 到期日]
        $plan = ['A' => ['2026-11-01T01:00:00Z', '2026-11-05T01:00:00Z', 'low', '2026-12-01'], 'B' => ['2026-11-02T01:00:00Z', '2026-11-03T01:00:00Z', 'urgent', ''],
            'C' => ['2026-11-03T01:00:00Z', '2026-11-04T01:00:00Z', 'high', '2026-11-20'], 'D' => ['2026-11-04T01:00:00Z', '2026-11-06T01:00:00Z', 'normal', '2026-11-10']];
        $ids = [];
        foreach ($plan as $title => [$created, $updated, $priority, $due]) {
            $ids[$title] = $rpc('createIssue', ['projectId' => $project['id'], 'title' => $title, 'priority' => $priority, 'dueDate' => $due])['id'];
            DB::table('issues')->where('public_id', $ids[$title])->update(['created_at' => DatabaseTimes::store($created), 'updated_at' => DatabaseTimes::store($updated)]);
        }

        return [$project['id'], $ids];
    }

    /**
     * 依查詢條件取得標題順序（翻完所有頁）。
     *
     * @param  string  $project  專案ID
     * @param  array<string,mixed>  $query  排序條件
     * @param  int  $perPage  每頁筆數
     * @return list<string> 標題順序
     */
    private function sortedTitles(string $project, array $query, int $perPage = 100): array
    {
        $titles = [];
        $page = 1;
        do {
            $data = $this->getJson('/api/v1/projects/'.$project.'/issues?'.http_build_query([...$query, 'perPage' => $perPage, 'page' => $page]))->assertOk()->json('data');
            $titles = [...$titles, ...array_column($data['items'], 'title')];
        } while ($page++ < $data['lastPage']);

        return $titles;
    }

    /**
     * 四種排序的預設方向與反向；到期日反向時沒填的排前面；換頁不重複不漏；方向不合法回400。
     *
     * @return void
     */
    public function test_issue_list_sorts_with_direction_and_stable_pages(): void
    {
        [$project] = $this->sortFixture();
        $this->assertSame(['D', 'A', 'C', 'B'], $this->sortedTitles($project, []), '預設更新時間新到舊');
        $this->assertSame(['B', 'C', 'A', 'D'], $this->sortedTitles($project, ['sort' => 'updated', 'direction' => 'asc']));
        $this->assertSame(['D', 'C', 'B', 'A'], $this->sortedTitles($project, ['sort' => 'created']), '建立日期預設新到舊');
        $this->assertSame(['A', 'B', 'C', 'D'], $this->sortedTitles($project, ['sort' => 'created', 'direction' => 'asc']));
        $this->assertSame(['B', 'C', 'D', 'A'], $this->sortedTitles($project, ['sort' => 'priority']), '優先級預設緊急到低');
        $this->assertSame(['A', 'D', 'C', 'B'], $this->sortedTitles($project, ['sort' => 'priority', 'direction' => 'desc']));
        $this->assertSame(['D', 'C', 'A', 'B'], $this->sortedTitles($project, ['sort' => 'due']), '到期日預設近到遠, 沒填排最後');
        $this->assertSame(['B', 'A', 'C', 'D'], $this->sortedTitles($project, ['sort' => 'due', 'direction' => 'desc']), '反向時沒填的照方向排最前');
        $this->assertSame($this->sortedTitles($project, ['sort' => 'created', 'direction' => 'asc']), $this->sortedTitles($project, ['sort' => 'created', 'direction' => 'asc'], 1), '每頁1筆翻完一樣');
        $this->getJson('/api/v1/projects/'.$project.'/issues?direction=sideways')->assertStatus(400);
        $this->getJson('/api/v1/projects/'.$project.'/issues?sort=title')->assertStatus(400);
    }

    /**
     * 異動清單預設舊到新；desc從最新往回讀並用cursor接續；換方向拿舊cursor續讀被擋下。
     *
     * @return void
     */
    public function test_issue_changes_direction_and_cursor(): void
    {
        [$project] = $this->sortFixture();
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'sync', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $read = function (array $query) use ($project, $token) {
            $titles = [];
            $cursor = null;
            do {
                $data = $this->getJson('/api/public/v1/projects/'.$project.'/issue-changes?'.http_build_query([...$query, 'perPage' => 1, ...($cursor ? ['cursor' => $cursor] : [])]), ['Authorization' => 'Bearer '.$token])->assertOk()->json('data');
                $titles = [...$titles, ...array_column($data['items'], 'title')];
            } while ($cursor = $data['nextCursor']);

            return $titles;
        };
        $this->assertSame(['B', 'C', 'A', 'D'], $read([]), '預設舊到新');
        $this->assertSame(['D', 'A', 'C', 'B'], $read(['direction' => 'desc']), 'desc從最新往回讀');
        $first = $this->getJson('/api/public/v1/projects/'.$project.'/issue-changes?perPage=1', ['Authorization' => 'Bearer '.$token])->assertOk()->json('data.nextCursor');
        $this->getJson('/api/public/v1/projects/'.$project.'/issue-changes?'.http_build_query(['perPage' => 1, 'direction' => 'desc', 'cursor' => $first]), ['Authorization' => 'Bearer '.$token])->assertStatus(400);
        $this->getJson('/api/public/v1/projects/'.$project.'/issue-changes?direction=up', ['Authorization' => 'Bearer '.$token])->assertStatus(400);
    }
}
