<?php

namespace App\Http\Controllers\Integrations;

use App\Data\TagWriteScope;
use App\Data\TokenContext;
use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Requests\PageQueryRequest;
use App\Http\Requests\TagWriteRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\TagResource;
use App\Services\TagService;
use Illuminate\Http\Request;

/** 公開標籤與卡片差量 API, PAT 不取代專案即時權限 */
class PublicTagsController extends Controller
{
    /** 注入 PAT 交易邊界與共用標籤服務
     *
     * @param  BearerTokenSession  $tokens  PAT 資格、交易與稽核
     * @param  TagService  $tags  與 Web 共用標籤用例
     */
    public function __construct(private BearerTokenSession $tokens, private TagService $tags) {}

    /** 分頁讀取已授權專案的有效定義
     *
     * @param  PageQueryRequest  $request  有界分頁
     * @param  string  $project  專案公開 ID
     * @return ApiResponse 標籤定義分頁
     */
    public function index(PageQueryRequest $request, string $project): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project) {
            $page = $this->tags->index($token->workspace, $project, $request->pagination());

            return PageResource::wrap($page, TagResource::collection($page->items())->resolve());
        }));
    }

    /** 讀取同專案單一標籤定義
     *
     * @param  Request  $request  Bearer 資格
     * @param  string  $project  專案公開 ID
     * @param  string  $tag  定義公開 ID
     * @return ApiResponse 單一定義
     */
    public function show(Request $request, string $project, string $tag): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, fn (TokenContext $token) => (new TagResource($this->tags->show($token->workspace, $project, $tag)))->resolve()));
    }

    /** 在重播前重驗 PAT 與目標權限後執行寫入
     *
     * @param  TagWriteRequest  $request  有界欄位與版本
     * @param  string  $project  路徑專案公開 ID
     * @return ApiResponse 有界首次結果或安全重播
     */
    public function write(TagWriteRequest $request, string $project): ApiResponse
    {
        $data = $request->toDto();
        $target = $request->route('tag') ?? $request->route('issue');
        $scope = null;
        $authorize = function (TokenContext $token) use (&$scope, $project, $data, $target): void {
            $scope = $this->tags->authorize($token->workspace, $project, $data->action, $target);
        };
        $execute = function (TokenContext $token) use (&$scope, $data): array {
            if (! $scope instanceof TagWriteScope) {
                throw new \LogicException('Missing same-transaction tag authorization');
            }

            return $this->tags->execute($token->workspace, $scope, $data->action, $data->input);
        };

        return new ApiResponse($this->tokens->writeOnce($request, 'public.v1.tags.'.$data->action, ['projectId' => $project, 'target' => $target, ...$data->input], $authorize, $execute, $project, $data->action === 'apply' ? 'issue.write' : 'project.tags.manage'));
    }
}
