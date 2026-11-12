<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** TT-274：補齊舊專案空的updated_at；只採已保存的建立／設定／介紹歷史時間，不捏造時間。 */
return new class extends Migration
{
    /**
     * 每個updated_at為NULL的專案取created_at與其settingsHistory／detailsHistory事件at的最新者；
     * 兩者皆無紀錄則維持NULL，列表改由created_at與public_id作穩定次序。
     *
     * @return void
     */
    public function up(): void
    {
        DB::table('projects')->whereNull('updated_at')->orderBy('id')->select(['id', 'created_at'])
            ->chunkById(200, function ($projects): void {
                $events = DB::table('audit_events')->where('entity_type', 'projects')
                    ->whereIn('stream', ['settingsHistory', 'detailsHistory'])
                    ->whereIn('entity_id', $projects->pluck('id'))->get(['entity_id', 'event_data'])->groupBy('entity_id');
                foreach ($projects as $project) {
                    $times = array_filter([$project->created_at, ...($events->get($project->id)?->map(
                        fn ($event) => json_decode($event->event_data, true)['at'] ?? null)->all() ?? [])]);
                    if ($times === []) {
                        continue;
                    }
                    $latest = max(array_map(fn ($time) => CarbonImmutable::parse($time, 'UTC')->utc(), $times));
                    $value = DB::getDriverName() === 'mysql' ? $latest->format('Y-m-d H:i:s.u') : $latest->toISOString();
                    DB::table('projects')->where('id', $project->id)->whereNull('updated_at')->update(['updated_at' => $value]);
                }
            });
    }

    /**
     * 補值無法區分原本為NULL的列，回退不清除時間以免抹掉之後的真實更新。
     *
     * @return void
     */
    public function down(): void
    {
    }
};
