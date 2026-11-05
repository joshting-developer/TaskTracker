<?php

namespace App\Domain;

/**
 * 驗證既有欄位格式、類型及日期，提供穩定的業務輸入契約。
 */
class Fields
{
    public const STATES = [
        [
            'id' => 'backlog',
            'label' => '待規劃',
            'color' => '#9a9da9',
            'category' => 'backlog',
        ],
        [
            'id' => 'todo',
            'label' => '待處理',
            'color' => '#7b82e8',
            'category' => 'active',
        ],
        [
            'id' => 'in_progress',
            'label' => '進行中',
            'color' => '#e7a443',
            'category' => 'active',
        ],
        [
            'id' => 'done',
            'label' => '已完成',
            'color' => '#4aab8a',
            'category' => 'done',
        ],
    ];

    public const ISSUE_FIELDS = ['title', 'description', 'state', 'priority', 'type', 'assignee', 'dueDate', 'epicId', 'estimateMinutes', 'parentIssueId', 'relatedIssueIds'];

    /**
     * 驗證文字型別、必要內容及長度。
     *
     * @param  mixed  $value  待驗證或正規化的值
     * @param  string  $label  欄位或資源顯示名稱
     * @param  int  $max  允許的最大長度或數值
     * @param  bool  $required  是否要求非空內容
     * @return string
     */
    public static function text(mixed $value, string $label, int $max, bool $required = false): string
    {
        if (! is_string($value)) {
            DomainError::fail('INVALID_INPUT', $label.'格式不正確');
        }
        $text = trim($value);
        if (mb_strlen($text, 'UTF-8') > $max || $required && $text === '') {
            DomainError::fail('INVALID_INPUT', $label.'必填或超過字數限制');
        }

        return $text;
    }

    /**
     * 驗證Markdown原稿但保留縮排、硬換行及首尾內容。
     *
     * @param  mixed  $value  原稿
     * @param  string  $label  對外欄位名稱
     * @param  int  $max  字數上限
     * @param  bool  $required  是否要求有可見內容
     * @return string 完整原稿
     */
    public static function markdown(mixed $value, string $label, int $max, bool $required = false): string
    {
        if (! is_string($value)) {
            DomainError::fail('INVALID_INPUT', $label.'格式不正確');
        }
        if (mb_strlen($value, 'UTF-8') > $max || $required && trim($value) === '') {
            DomainError::fail('INVALID_INPUT', $label.'必填或超過字數限制');
        }

        return $value;
    }

    /**
     * 驗證整數型別與允許範圍。
     *
     * @param  mixed  $value  待驗證或正規化的值
     * @param  string  $label  欄位或資源顯示名稱
     * @param  int  $min  允許的最小數值
     * @param  int  $max  允許的最大長度或數值
     * @return int
     */
    public static function integer(mixed $value, string $label, int $min, int $max): int
    {
        if (! is_int($value) || $value < $min || $value > $max) {
            DomainError::fail('INVALID_INPUT', $label.'超出範圍或不是整數');
        }

        return $value;
    }

    /**
     * 驗證值是否屬於明確允許的選項。
     *
     * @param  mixed  $value  待驗證或正規化的值
     * @param  array  $choices  明確允許的選項
     * @param  string  $label  欄位或資源顯示名稱
     * @return string
     */
    public static function choice(mixed $value, array $choices, string $label): string
    {
        if (! in_array($value, $choices, true)) {
            DomainError::fail('INVALID_INPUT', $label.'選項不正確');
        }

        return $value;
    }

    /**
     * 驗證真實存在的日曆日期。
     *
     * @param  mixed  $value  待驗證或正規化的值
     * @return string
     */
    public static function date(mixed $value): string
    {
        $date = self::text($value, '日期', 10, true);
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            DomainError::fail('INVALID_INPUT', '日期格式不正確');
        }

        return $date;
    }

    /**
     * 依固定狀態ID查詢任務的狀態分類。
     *
     * @param  array  $issue  目前任務內容
     * @param  array  $project  目前專案內容與設定
     * @return string
     */
    public static function category(array $issue, array $project): string
    {
        foreach ($project['statuses'] ?? self::STATES as $state) {
            if ($state['id'] === $issue['state']) {
                return $state['category'];
            }
        }

        return 'active';
    }

    /**
     * 將允許的任務欄位轉成經驗證的資料。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @param  array  $project  目前專案內容與設定
     * @return array
     */
    public static function issue(array $input, array $project): array
    {
        $statuses = $project['statuses'] ?? self::STATES;
        $dueDate = self::text($input['dueDate'] ?? '', '到期日', 10);
        if ($dueDate !== '') {
            self::date($dueDate);
        }
        if (array_key_exists('blockedBy', $input)) {
            DomainError::fail('INVALID_INPUT', '前置任務已停用, 請重新整理後使用子任務或關聯任務');
        }
        $ids = $input['relatedIssueIds'] ?? [];
        if (! is_array($ids) || ! array_is_list($ids) || count($ids) > 200) {
            DomainError::fail('INVALID_INPUT', '關聯任務最多 200 項');
        }
        $ids = array_map(fn ($id) => self::text($id, '關聯任務', 100, true), $ids);
        if (count(array_unique($ids)) !== count($ids)) {
            DomainError::fail('INVALID_INPUT', '關聯任務不可重複');
        }
        sort($ids);
        $defaultState = array_values(array_filter($statuses, fn ($s) => ! in_array($s['category'], ['done', 'canceled'], true)))[0]['id'];

        return [
            'title' => self::text($input['title'] ?? null, '標題', 180, true),
            'description' => self::markdown($input['description'] ?? '', '描述', 6000),
            'state' => self::choice($input['state'] ?? $defaultState, array_column($statuses, 'id'), '狀態'),
            'priority' => self::choice($input['priority'] ?? 'normal', ['urgent', 'high', 'normal', 'low'], '優先級'),
            'type' => self::choice($input['type'] ?? 'task', ['epic', 'task', 'bug'], '類型'),
            'assignee' => self::text($input['assignee'] ?? '', '負責人', 60),
            'dueDate' => $dueDate,
            'epicId' => self::text($input['epicId'] ?? '', 'Epic', 100),
            'estimateMinutes' => self::integer($input['estimateMinutes'] ?? 0, '預估分鐘', 0, 525600),
            'parentIssueId' => self::text($input['parentIssueId'] ?? '', '父任務', 100),
            'relatedIssueIds' => $ids,
        ];
    }
}
