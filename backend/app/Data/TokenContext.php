<?php

namespace App\Data;

/** 單次Bearer用例的可信來源；不能當作跨請求或免重驗的憑證。 */
final readonly class TokenContext
{
    /**
     * 封裝重驗後的工作區資格與Token metadata，不持有秘密／HTTP物件。
     *
     * @param  WorkspaceContext  $workspace  當次已重驗資格
     * @param  string  $tokenId  Token識別快照，不含秘密
     * @param  string  $tokenName  可辨識操作來源的名稱
     * @param  list<string>  $abilities  此次Token能力；不取代Project permission
     */
    public function __construct(public WorkspaceContext $workspace, public string $tokenId, public string $tokenName, public array $abilities) {}
}
