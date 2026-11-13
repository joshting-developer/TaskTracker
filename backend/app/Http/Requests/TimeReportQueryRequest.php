<?php

namespace App\Http\Requests;

/** GET沿用分析條件驗證, 只正規化可識別的整數頁碼 */
class TimeReportQueryRequest extends TimeReportRequest
{
    /**
     * GET頁碼由字串傳輸, 非純整數仍交驗證拒絕
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        foreach (['detailPage', 'entryPage'] as $key) {
            $value = $this->input($key);
            if (is_string($value) && preg_match('/^[1-9][0-9]{0,6}$/D', $value)) {
                $this->merge([$key => (int) $value]);
            }
        }
    }
}
