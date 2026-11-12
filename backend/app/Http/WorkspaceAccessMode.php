<?php

namespace App\Http;

/**
 * Workspace交易的存取模式，由呼叫端明確宣告；決定是否取得寫入鎖與是否使用唯讀交易。
 */
enum WorkspaceAccessMode
{
    /** 先鎖User再鎖Workspace，於一般交易內重驗資格後寫入。 */
    case Write;

    /** 不取寫入鎖；SQLite使用query_only唯讀交易，供v1查詢與報表。 */
    case Read;

    /** 不取寫入鎖的一般交易；保留給舊RPC讀取（可能有相容性的延遲初始化寫入），不使用query_only。 */
    case Unlocked;
}
