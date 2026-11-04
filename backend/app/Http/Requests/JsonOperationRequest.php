<?php

namespace App\Http\Requests;

use App\Domain\DomainError;
use Illuminate\Foundation\Http\FormRequest;
use stdClass;

/**
 * 驗證JSON傳輸邊界；業務欄位與授權保留在交易內的服務。
 */
class JsonOperationRequest extends FormRequest
{
    /**
     * 允許進入傳輸驗證；實際permission必須在交易內重新判斷。
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 業務欄位驗證由既有領域入口執行，避免改變錯誤契約。
     *
     * @return array
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * 只接受大小受限的JSON物件，不改寫重試簽章所用原始欄位。
     *
     * @return array
     */
    public function payload(): array
    {
        if (! $this->isJson()) {
            DomainError::fail('INVALID_INPUT', '需要 JSON 資料');
        }
        if (strlen($this->getContent()) > 50000) {
            DomainError::fail('INVALID_INPUT', '資料太大');
        }
        try {
            $decoded = json_decode($this->getContent(), flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            DomainError::fail('INVALID_INPUT', 'JSON 格式不正確');
        }
        if (! $decoded instanceof stdClass) {
            DomainError::fail('INVALID_INPUT', '需要 JSON 物件');
        }

        return json_decode($this->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
