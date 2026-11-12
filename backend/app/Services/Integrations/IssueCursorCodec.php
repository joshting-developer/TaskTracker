<?php

namespace App\Services\Integrations;

use App\Domain\DomainError;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/** 經認證加密的cursor只保存固定水位與接續位置，不含卡片內容或PAT。 */
class IssueCursorCodec
{
    /**
     * @param array $position 伺服器產生的有界接續資訊
     * @return string 不透明且可驗證的識別
     */
    public function encode(array $position): string
    {
        return Crypt::encryptString(json_encode($position, JSON_THROW_ON_ERROR));
    }

    /**
     * @param string $cursor 接續識別
     * @param string $binding 本人／租戶／Project／完整篩選的雜湊
     * @return array 已驗證的水位與位置
     */
    public function decode(string $cursor, string $binding): array
    {
        try {
            $data = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $error) {
            DomainError::fail('INVALID_INPUT', 'Cursor無效或已不適用，請重新查詢');
        }
        if (!is_array($data) || ($data['version'] ?? null) !== 1 || ($data['binding'] ?? null) !== $binding || !is_string($data['until'] ?? null) || !is_int($data['id'] ?? null) || !array_key_exists('time', $data) || (!is_null($data['time']) && !is_string($data['time']))) {
            DomainError::fail('INVALID_INPUT', 'Cursor與查詢範圍不符，請重新查詢');
        }

        return $data;
    }
}
