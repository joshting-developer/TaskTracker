<?php

namespace App\Http\Controllers\Integrations;

use App\Data\TokenContext;
use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Requests\Integrations\PublicIssueBatchRequest;
use App\Http\Requests\Integrations\PublicIssueUpsertRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\IssueDetailResource;
use App\Services\Integrations\IssueExternalReferences;
use Illuminate\Http\Request;

/** TT-171：外部識別查詢與upsert的薄HTTP入口；交易、授權與冪等由Bearer邊界及共用Service負責。 */
class PublicIssueExternalController extends Controller
{
    /**
     * 注入Bearer交易邊界與外部識別用例。
     *
     * @param  BearerTokenSession  $tokens  Bearer Token交易與資格邊界
     * @param  IssueExternalReferences  $references  外部識別查詢／upsert用例
     */
    public function __construct(private BearerTokenSession $tokens, private IssueExternalReferences $references) {}

    /**
     * 依外部識別取得完整單卡。
     *
     * @param  Request  $request  純Bearer
     * @param  string  $project  Project公開ID
     * @param  string  $source  外部來源代號
     * @param  string  $externalId  外部識別
     * @return ApiResponse 完整單卡屬性
     */
    public function show(Request $request, string $project, string $source, string $externalId): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, fn (TokenContext $token) => (new IssueDetailResource($this->references->show($token->workspace, $project, $source, $externalId)))->resolve()));
    }

    /**
     * 未綁定時建立、已綁定時以expectedRevision更新；同requestId重播原結果。
     *
     * @param  PublicIssueUpsertRequest  $request  已驗證輸入與路徑外部識別
     * @param  string  $project  Project公開ID
     * @param  string  $source  外部來源代號
     * @param  string  $externalId  外部識別
     * @return ApiResponse 有界結果與created旗標
     */
    public function upsert(PublicIssueUpsertRequest $request, string $project, string $source, string $externalId): ApiResponse
    {
        $input = [...$request->validated(), 'projectId' => $project, 'externalSource' => $source, 'externalId' => $externalId];

        return new ApiResponse($this->tokens->writeOnce($request, 'public.v1.upsertIssue', $input, fn () => null,
            fn (TokenContext $token) => $this->references->upsert($token->workspace, $project, $source, $externalId, $request->fields()), $project));
    }

    /**
     * TT-172：批次upsert；逐項回報，整批同一receipt，逾時以同requestId重送取回原結果。
     *
     * @param  PublicIssueBatchRequest  $request  已驗證批次
     * @param  string  $project  Project公開ID
     * @return ApiResponse 逐項有界結果與統計
     */
    public function batch(PublicIssueBatchRequest $request, string $project): ApiResponse
    {
        $input = [...$request->validated(), 'projectId' => $project];

        return new ApiResponse($this->tokens->writeOnce($request, 'public.v1.batchUpsertIssues', $input, fn () => null,
            fn (TokenContext $token) => $this->references->batch($token->workspace, $project, $request->items(), $request->createOnly()), $project));
    }
}
