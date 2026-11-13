<?php

namespace App\Http\Requests\Attachments;

use App\Data\AttachmentUploadData;
use App\Domain\DomainError;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** 只接受multipart圖片與UUID, 不接受遠端匯入 */
class AttachmentUploadRequest extends FormRequest
{
    /** 授權留交易內重驗
     * @return bool 允許傳輸驗證
     */
    public function authorize(): bool
    {
        return true;
    }

    /** HTTP傳輸限制, 實際內容仍須嗅探
     * @return array 驗證規則
     */
    public function rules(): array
    {
        return ['requestId' => ['required', 'uuid'], 'file' => ['required', 'file', 'max:'.(int) ceil(config('attachments.max_bytes') / 1024)]];
    }

    /** 轉成已嗅探資料
     * @return AttachmentUploadData 圖片資料
     */
    public function toDto(): AttachmentUploadData
    {
        return AttachmentUploadData::from($this->file('file'), $this->validated('requestId'));
    }

    /** 與API保持相同錯誤契約
     * @param  Validator  $validator  驗證結果
     * @return never
     */
    protected function failedValidation(Validator $validator): void
    {
        DomainError::fail('INVALID_INPUT', '請上傳限制大小內的圖片與有效requestId');
    }
}
