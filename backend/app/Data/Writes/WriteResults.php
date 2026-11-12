<?php

namespace App\Data\Writes;

/**
 * Web RPC專案／看板／卡片／工時寫入的有界結果：只回定位欄位與revision，不帶描述、留言或歷史。
 * 結果會原樣存入防重複提交receipt，因此大小固定，不隨卡片歷史成長；需要最新內容時以GET按需讀取。
 */
final class WriteResults
{
    /** 套用有界結果的卡片寫入操作。 */
    public const ISSUE_OPERATIONS = ['createIssue', 'updateIssue', 'addComment'];

    /** 套用有界結果的工時寫入操作。 */
    public const TIME_OPERATIONS = ['logTime', 'updateTime', 'voidTime'];

    /** 有界Project寫入操作。 */
    public const PROJECT_OPERATIONS = ['createProject', 'updateProject', 'updateStatuses'];

    /** 有界看板寫入操作。 */
    public const BOARD_OPERATIONS = ['createBoard', 'updateBoard', 'archiveBoard'];

    /**
     * 只保留資源識別與版本，不把設定歷史／描述／收錄清單放進新receipt。
     *
     * @param  array<string,mixed>  $resource  領域寫入結果
     * @return array{id:string,revision:int} 固定投影
     */
    public static function resource(array $resource): array
    {
        return ['id' => $resource['id'], 'revision' => (int) $resource['revision']];
    }

    /**
     * 看板回傳所屬Project定位，不帶最多千筆的收錄清單。
     *
     * @param  array<string,mixed>  $board  領域結果
     * @return array{id:string,projectId:string,revision:int} 固定投影
     */
    public static function board(array $board): array
    {
        return [...self::resource($board), 'projectId' => $board['projectId']];
    }

    /**
     * 卡片寫入結果；與公開API寫入回應相同的欄位。
     *
     * @param  array<string,mixed>  $issue  領域服務回傳的完整卡片
     * @return array{id:string,projectId:string,identifier:string,revision:int} 有界結果
     */
    public static function issue(array $issue): array
    {
        return ['id' => $issue['id'], 'projectId' => $issue['projectId'], 'identifier' => $issue['identifier'], 'revision' => (int) $issue['revision']];
    }

    /**
     * 工時寫入結果。
     *
     * @param  array<string,mixed>  $entry  領域服務回傳的完整工時紀錄
     * @return array{id:string,projectId:string,issueId:string,workDate:string,minutes:int,revision:int} 有界結果
     */
    public static function timeEntry(array $entry): array
    {
        return ['id' => $entry['id'], 'projectId' => $entry['projectId'], 'issueId' => $entry['issueId'], 'workDate' => $entry['workDate'], 'minutes' => (int) $entry['minutes'], 'revision' => (int) $entry['revision']];
    }
}
