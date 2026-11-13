<?php

use App\Database\RelationalDataMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TT-314：任務記下建卡的人（狀態調整要通知他）, 通知加上類型（讓「已關閉」「待驗收」能特別標示）。
 * 舊卡只在異動紀錄第一筆的名字能在同工作空間對到唯一成員時才補建卡者；對不到就留空, 不猜。
 */
return new class extends Migration
{
    /**
     * 新增欄位並保守補值。
     *
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasColumn('issues', 'created_by_id')) {
            // SQLite（只用在測試）加複合外鍵要重建issues表, 表內有資料時會連帶刪掉留言等子表；這時只加欄位, 外鍵由MySQL與空表測試把關。
            $foreignKey = Schema::getConnection()->getDriverName() !== 'sqlite' || ! DB::table('issues')->exists();
            Schema::table('issues', function (Blueprint $table) use ($foreignKey): void {
                $table->unsignedBigInteger('created_by_id')->nullable()->after('assignee_id');
                if (! $foreignKey) {
                    return;
                }
                $table->foreign(['workspace_id', 'created_by_id'], 'issues_created_by_id_fk')->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
            });
        }
        Schema::table('issue_notifications', function (Blueprint $table): void {
            $table->string('kind', 30)->default('general')->after('status');
        });

        $this->restoreFromArchive();

        // 每個工作空間：名字 => 唯一成員ID；同名的人不補。
        $names = [];
        foreach (DB::table('workspace_members as m')->join('users as u', 'u.id', '=', 'm.user_id')->get(['m.workspace_id', 'm.id', 'u.name']) as $row) {
            $names[$row->workspace_id][$row->name][] = $row->id;
        }
        DB::table('audit_events')->where('entity_type', 'issues')->where('stream', 'history')->where('position', 0)
            ->orderBy('id')->chunkById(200, function ($events) use ($names): void {
                foreach ($events as $event) {
                    $data = json_decode($event->event_data, true);
                    if (($data['kind'] ?? '') !== 'created' || ! isset($names[$event->workspace_id][$data['actor'] ?? ''])) {
                        continue;
                    }
                    $members = $names[$event->workspace_id][$data['actor']];
                    if (count($members) === 1) {
                        DB::table('issues')->where('id', $event->entity_id)->where('workspace_id', $event->workspace_id)->whereNull('created_by_id')->update(['created_by_id' => $members[0]]);
                    }
                }
            });
    }

    /**
     * 先退回再重建關聯表時, 退回前的最新資料匯出在records；這兩個欄位比基礎匯入晚建立, 要在這裡從備份補回。
     * 正式第一次升級時records裡沒有這兩個值, 不會改到任何資料。
     *
     * @return void
     */
    private function restoreFromArchive(): void
    {
        if (! Schema::hasTable('records')) {
            return;
        }
        $workspaces = DB::table('workspaces')->pluck('id', 'public_id')->all();
        DB::table('records')->where('kind', 'like', 'w:%:issues')->orWhere('kind', 'like', 'w:%:notifications')
            ->orderBy('sequence')->chunkById(200, function ($records) use ($workspaces): void {
                foreach ($records as $record) {
                    [, $workspacePublicId, $kind] = explode(':', $record->kind, 3);
                    $workspaceId = $workspaces[$workspacePublicId] ?? null;
                    $payload = json_decode($record->payload, true);
                    if ($workspaceId === null || ! is_array($payload)) {
                        continue;
                    }
                    if ($kind === 'notifications' && is_string($payload['kind'] ?? null) && $payload['kind'] !== '') {
                        DB::table('issue_notifications')->where('workspace_id', $workspaceId)->where('public_id', $record->id)->update(['kind' => mb_substr($payload['kind'], 0, 30)]);
                    } elseif ($kind === 'issues' && is_string($payload['createdBy'] ?? null) && $payload['createdBy'] !== '') {
                        // 建卡者要還是同工作空間的成員才補, 不然留空。
                        $member = DB::table('workspace_members')->where('workspace_id', $workspaceId)->where('user_id', $payload['createdBy'])->value('id');
                        if ($member !== null) {
                            DB::table('issues')->where('workspace_id', $workspaceId)->where('public_id', $record->id)->update(['created_by_id' => $member]);
                        }
                    }
                }
            }, 'sequence');
    }

    /**
     * 移除欄位。
     *
     * @return void
     */
    public function down(): void
    {
        // 撤欄位前先把最新資料（含這兩個欄位）匯出到records, 之後基礎migration再撤也不會蓋掉。
        RelationalDataMigration::export();
        Schema::table('issue_notifications', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
        // SQLite（只用在測試）拿掉外鍵要重建issues表, 會連帶把留言、關聯等子表資料刪掉；所以SQLite只撤kind, 建卡者欄位留著, up再遇到就跳過。
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('issues', function (Blueprint $table): void {
                $table->dropForeign('issues_created_by_id_fk');
                $table->dropColumn('created_by_id');
            });
        }
    }
};
