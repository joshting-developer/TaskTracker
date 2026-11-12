<?php

namespace App\Http\Requests\Writes;

use App\Data\Writes\IssueWriteData;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Http\Requests\JsonOperationRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * Web RPC卡片寫入的傳輸邊界：只檢查JSON物件與欄位型別；長度、格式、狀態與關聯規則仍由Fields／IssueService判斷，
 * 原有錯誤訊息不變，也不拒絕未知欄位。必填與否也交給授權之後的領域服務判斷，保留「先授權（不可見回404）再驗必填」的順序。
 */
class IssueWriteRequest extends JsonOperationRequest
{
    /**
     * 驗證前先確認是JSON物件並在大小上限內。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->replace($this->payload());
    }

    /**
     * 依路徑上的RPC操作取得型別規則。
     *
     * @return array<string,list<string>> 驗證規則
     */
    public function rules(): array
    {
        $revision = ['expectedRevision' => ['sometimes', 'nullable', 'integer']];
        $base = ['requestId' => ['sometimes', 'nullable', 'string']];

        return match ($this->operation()) {
            'addComment' => [...$base, ...$revision, 'id' => ['sometimes', 'nullable', 'string'], 'content' => ['sometimes', 'nullable', 'string']],
            'updateIssue' => [...$base, ...$revision, 'id' => ['sometimes', 'nullable', 'string'], ...$this->fieldRules()],
            'deleteIssue' => [...$base, ...$revision, 'id' => ['sometimes', 'nullable', 'string'], 'projectId' => ['sometimes', 'nullable', 'string']],
            default => [...$base, 'projectId' => ['sometimes', 'nullable', 'string'], 'boardId' => ['sometimes', 'nullable', 'string'], ...$this->fieldRules()],
        };
    }

    /**
     * 轉成型別化輸入；原始輸入完整保留。
     *
     * @return IssueWriteData 卡片寫入輸入
     */
    public function toDto(): IssueWriteData
    {
        $input = $this->payload();
        $integer = fn (string $key) => array_key_exists($key, $input) && $input[$key] !== null ? (int) $input[$key] : null;

        return new IssueWriteData($this->operation(), (string) ($input['requestId'] ?? ''), $input['id'] ?? null, $integer('expectedRevision'),
            $input['projectId'] ?? null, $input['boardId'] ?? null, $input['content'] ?? null,
            array_intersect_key($input, array_flip(Fields::ISSUE_FIELDS)), $input);
    }

    /**
     * 型別錯誤統一回INVALID_INPUT。
     *
     * @param  Validator  $validator  驗證結果
     * @return never
     *
     * @throws DomainError 欄位型別不符
     */
    protected function failedValidation(Validator $validator): never
    {
        DomainError::fail('INVALID_INPUT', '請確認任務欄位型別、expectedRevision與requestId');
    }

    /**
     * 目前路徑的RPC操作名稱。
     *
     * @return string createIssue、updateIssue或addComment
     */
    private function operation(): string
    {
        return basename($this->path());
    }

    /**
     * 卡片欄位的型別規則；允許null，交給Fields判斷是否必填或如何正規化。
     *
     * @return array<string,list<string>> 驗證規則
     */
    private function fieldRules(): array
    {
        $text = ['sometimes', 'nullable', 'string'];

        return ['title' => $text, 'description' => $text, 'state' => $text, 'priority' => $text, 'type' => $text, 'assignee' => $text,
            'dueDate' => $text, 'epicId' => $text, 'parentIssueId' => $text, 'estimateMinutes' => ['sometimes', 'nullable', 'integer'],
            'relatedIssueIds' => ['sometimes', 'nullable', 'array'], 'relatedIssueIds.*' => ['string']];
    }
}
