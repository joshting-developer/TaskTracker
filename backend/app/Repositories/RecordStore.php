<?php

namespace App\Repositories;

use App\Domain\DomainError;
use Illuminate\Support\Facades\DB;

// 保留原型 payload 契約, 由伺服器限定 Workspace namespace
class RecordStore
{
    public function __construct(private string $prefix = '') {}

    public function forWorkspace(string $id): self
    {
        return new self('w:'.$id.':');
    }

    public function get(string $kind, string $id): ?array
    {
        $value = DB::table('records')->where('kind', $this->prefix.$kind)->where('id', $id)->value('payload');
        return $value === null ? null : json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }

    public function require(string $kind, string $id): array
    {
        return $this->get($kind, $id) ?? DomainError::fail('NOT_FOUND', '找不到這筆資料, 請重新載入');
    }

    public function all(string $kind): array
    {
        return DB::table('records')->where('kind', $this->prefix.$kind)->orderBy('sequence')->pluck('payload')->map(fn ($v) => json_decode($v, true, flags: JSON_THROW_ON_ERROR))->all();
    }

    public function put(string $kind, string $id, array $value): void
    {
        DB::table('records')->updateOrInsert(['kind' => $this->prefix.$kind, 'id' => $id], ['payload' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    }
}
