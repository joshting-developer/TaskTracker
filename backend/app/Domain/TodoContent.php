<?php

namespace App\Domain;

/** 保存完整Markdown速記，另產生清單／通知使用的短摘要；相容舊標題與說明。 */
class TodoContent
{
    public const MAX_LENGTH = 6000;

    /**
     * 驗證單塊速記或舊格式輸入，原稿不因摘要截短而遺失。
     *
     * @param  array  $input  本次輸入
     * @param  ?array  $current  目前待辦；建立時為null
     * @return array 可保存的內容欄位
     */
    public static function fields(array $input, ?array $current = null): array
    {
        if (array_key_exists('content', $input)) {
            $content = Fields::markdown($input['content'], '待辦內容', self::MAX_LENGTH, true);
            $line = preg_split('/\R/u', trim($content))[0];
            $summary = preg_replace('/^\s*(?:#{1,6}\s+|[-+*]\s+(?:\[[ xX]\]\s*)?|\d+\.\s+|>\s*)/u', '', $line);
            $summary = trim(preg_replace('/[*_`~]/u', '', $summary));

            return [
                'title' => mb_substr($summary !== '' ? $summary : 'Markdown速記', 0, 180, 'UTF-8'),
                'note' => '',
                'content' => $content,
            ];
        }
        if ($current && ! array_key_exists('title', $input) && ! array_key_exists('note', $input)) {
            return array_intersect_key($current, array_flip(['title', 'note', 'content']));
        }

        $title = Fields::text($input['title'] ?? $current['title'] ?? null, '待辦內容', 180, true);
        $note = Fields::text($input['note'] ?? $current['note'] ?? '', '補充說明', 2000);
        $content = $current['content'] ?? null;
        // 舊客戶端只改摘要時，不清掉它尚未認識的完整原稿。
        if ($content !== null && array_key_exists('note', $input)) {
            $content = $title.($note !== '' ? "\n\n".$note : '');
        }
        return ['title' => $title, 'note' => $note, 'content' => $content];
    }
}
