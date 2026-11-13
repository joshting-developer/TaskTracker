<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** 信件outbox；傳輸內容與收件地址加密, 不自動序列化內容 */
#[Guarded([])]
#[Hidden(['payload', 'recipient', 'event_key'])]
class MailDelivery extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** 保留加密內容與時間／次數型別
     *
     * @return array<string,string> casts
     */
    protected function casts(): array
    {
        return ['recipient' => 'encrypted', 'payload' => 'encrypted:array', 'attempts' => 'integer', 'next_attempt_at' => 'datetime', 'started_at' => 'datetime', 'sent_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
