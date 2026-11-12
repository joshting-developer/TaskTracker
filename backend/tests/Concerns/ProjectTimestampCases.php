<?php

namespace Tests\Concerns;

use App\Domain\Fields;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** TT-274：專案建立／更新時間戳與「最近更新」列表順序，SQLite與獨立MySQL共用案例。 */
trait ProjectTimestampCases
{
    /**
     * 經正式Session RPC送出，未指定requestId時補新UUID。
     *
     * @param  string  $operation  已註冊的RPC操作
     * @param  array<string,mixed>  $input  測試輸入
     * @return TestResponse 真實HTTP測試回應
     */
    private function timestampRpc(string $operation, array $input = []): TestResponse
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /**
     * 讀取專案列表目前順序的代碼。
     *
     * @return array<int,string> 依API順序的專案代碼
     */
    private function listedCodes(): array
    {
        return array_column($this->getJson('/api/v1/projects?perPage=50')->assertOk()->json('data.items'), 'code');
    }

    /**
     * 新建／介紹更新／狀態設定都留下可信時間戳，列表依最近更新而非UUID排序；重播不重複推進。
     *
     * @return void
     */
    public function test_project_timestamps_drive_recently_updated_order(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Stamp', 'email' => 'stamp-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Stamp'])->assertOk();
        $this->travelTo(now()->utc()->setTime(1, 0));
        $first = $this->timestampRpc('createProject', ['name' => 'First', 'code' => 'FIRST'])->assertOk()->json('data');
        $this->travel(1)->minutes();
        $second = $this->timestampRpc('createProject', ['name' => 'Second', 'code' => 'SECOND'])->assertOk()->json('data');
        foreach ([$first, $second] as $project) {
            $row = DB::table('projects')->where('public_id', $project['id'])->first(['created_at', 'updated_at']);
            $this->assertNotNull($row->created_at);
            $this->assertSame((string) $row->created_at, (string) $row->updated_at);
        }
        $this->assertSame(['SECOND', 'FIRST'], array_values(array_intersect($this->listedCodes(), ['FIRST', 'SECOND'])));

        $this->travel(1)->minutes();
        $input = ['requestId' => (string) Str::uuid(), 'id' => $first['id'], 'expectedRevision' => 1, 'description' => '更新介紹'];
        $updated = $this->timestampRpc('updateProject', $input)->assertOk()->json('data');
        $this->assertSame(2, $updated['revision']);
        $stamp = DB::table('projects')->where('public_id', $first['id'])->value('updated_at');
        $this->travel(1)->minutes();
        $this->assertSame($updated, $this->timestampRpc('updateProject', $input)->assertOk()->json('data'));
        $this->assertSame((string) $stamp, (string) DB::table('projects')->where('public_id', $first['id'])->value('updated_at'));
        $this->assertSame(['FIRST', 'SECOND'], array_values(array_intersect($this->listedCodes(), ['FIRST', 'SECOND'])));

        $this->travel(1)->minutes();
        $detail = $this->getJson('/api/v1/projects/'.$second['id'])->assertOk()->json('data');
        $statuses = [...Fields::STATES, ['id' => 'review', 'label' => 'Review', 'color' => '#71a2d5']];
        $this->timestampRpc('updateStatuses', ['projectId' => $second['id'], 'expectedRevision' => $detail['revision'], 'statuses' => $statuses])->assertOk();
        $created = DB::table('projects')->where('public_id', $second['id'])->value('created_at');
        $this->assertNotSame((string) $created, (string) DB::table('projects')->where('public_id', $second['id'])->value('updated_at'));
        $this->assertSame(['SECOND', 'FIRST'], array_values(array_intersect($this->listedCodes(), ['FIRST', 'SECOND'])));
    }

    /**
     * 補值migration只採created_at與已保存設定／介紹事件時間，無紀錄者維持NULL並以created_at／public_id穩定排序。
     *
     * @return void
     */
    public function test_backfill_uses_recorded_times_only(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Fill', 'email' => 'fill-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Fill'])->assertOk();
        $this->travelTo(now()->utc()->setTime(2, 0));
        $plain = $this->timestampRpc('createProject', ['name' => 'Plain', 'code' => 'PLAIN'])->assertOk()->json('data');
        $this->travel(1)->minutes();
        $edited = $this->timestampRpc('createProject', ['name' => 'Edited', 'code' => 'EDITED'])->assertOk()->json('data');
        $this->travel(5)->minutes();
        $this->timestampRpc('updateProject', ['id' => $edited['id'], 'expectedRevision' => 1, 'description' => 'x'])->assertOk();
        $this->travel(1)->minutes();
        $blank = $this->timestampRpc('createProject', ['name' => 'Blank', 'code' => 'BLANK'])->assertOk()->json('data');
        $editedAt = DB::table('projects')->where('public_id', $edited['id'])->value('updated_at');
        $plainCreated = DB::table('projects')->where('public_id', $plain['id'])->value('created_at');
        DB::table('projects')->whereIn('public_id', [$plain['id'], $edited['id'], $blank['id']])->update(['updated_at' => null]);
        DB::table('projects')->where('public_id', $blank['id'])->update(['created_at' => null]);
        $revisions = DB::table('projects')->pluck('revision', 'public_id')->all();

        (require database_path('migrations/2026_11_13_100000_backfill_project_updated_at.php'))->up();

        $this->assertSame((string) $plainCreated, (string) DB::table('projects')->where('public_id', $plain['id'])->value('updated_at'));
        $restored = DB::table('projects')->where('public_id', $edited['id'])->value('updated_at');
        $this->assertTrue(CarbonImmutable::parse($editedAt, 'UTC')->equalTo(CarbonImmutable::parse($restored, 'UTC')));
        $this->assertNull(DB::table('projects')->where('public_id', $blank['id'])->value('updated_at'));
        $this->assertSame($revisions, DB::table('projects')->pluck('revision', 'public_id')->all());
        $this->assertSame(['EDITED', 'PLAIN', 'BLANK'], array_values(array_intersect($this->listedCodes(), ['PLAIN', 'EDITED', 'BLANK'])));
    }
}
