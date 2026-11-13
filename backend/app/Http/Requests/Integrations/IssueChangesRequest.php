<?php

namespace App\Http\Requests\Integrations;

use App\Data\IssueChangesQueryData;
use App\Http\Requests\IssueQueryRequest;
use Carbon\CarbonImmutable;

/** 公開增量列表只接受時間升冪keyset，不與頁碼／其他排序混用。 */
class IssueChangesRequest extends IssueQueryRequest
{
    /**
     * 有界cursor與有時區的ISO日期。
     *
     * @return array<string,mixed> 有界cursor與有時區的ISO日期。
     */
    public function rules(): array
    {
        return [...parent::rules(), 'state.*' => ['string', 'max:100'], 'page' => ['prohibited'], 'sort' => ['sometimes', 'in:updated'], 'direction' => ['sometimes', 'in:asc,desc'],
            'cursor' => ['sometimes', 'string', 'max:4000'],
            'updatedSince' => ['sometimes', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', 'max:40']];
    }

    /**
     * 標準化UTC及共用既有Issue allowlist。
     *
     * @return IssueChangesQueryData 標準化UTC及共用既有Issue allowlist。
     */
    public function toChangesDto(): IssueChangesQueryData
    {
        $data = $this->validated();

        return new IssueChangesQueryData($this->toDto(), $data['cursor'] ?? '', isset($data['updatedSince']) ? CarbonImmutable::parse($data['updatedSince'])->utc()->toISOString() : null);
    }
}
