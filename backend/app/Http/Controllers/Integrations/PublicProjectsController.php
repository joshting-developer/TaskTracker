<?php

namespace App\Http\Controllers\Integrations;

use App\Data\TokenContext;
use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\ProjectResource;
use App\Services\ProjectReadService;
use Illuminate\Http\Request;

/** 公開PAT依單一領域分責查詢，不解析無關用例服務。 */
class PublicProjectsController extends Controller
{
    /**
     * 注入公開Bearer交易及資格邊界。
     *
     * @param  BearerTokenSession  $tokens  每次重驗資格的邊界
     * @param  ProjectReadService  $projects  當次HTTP用例的穩定協作者
     */
    public function __construct(private BearerTokenSession $tokens, private ProjectReadService $projects) {}

    /**
     * SQL先過濾本人可見範圍，再回Project摘要及本人的有效權限。
     *
     * @param  PageQueryRequest  $request  分頁與搜尋
     * @return ApiResponse 有界摘要，不含Issues／歷史
     */
    public function projects(PageQueryRequest $request): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request) {
            $result = $this->projects->index($token->workspace, $request->pagination());
            $items = array_map(fn ($p) => [...(new ProjectResource($p))->resolve(), 'permissions' => $result->permissions[$p->public_id]], $result->page->items());

            return PageResource::wrap($result->page, $items);
        }));
    }

    /**
     * 取得指定可見Project的metadata與狀態，不載入Workspace其他內容。
     *
     * @param  Request  $request  純Bearer
     * @param  string  $project  Project公開ID
     * @return ApiResponse Project詳細及本人的權限來源
     */
    public function project(Request $request, string $project): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($project) {
            $data = $this->projects->show($token->workspace, $project);

            return [...(new ProjectResource($data['project']))->resolve(), ...$data['capabilities']];
        }));
    }

    /**
     * 只取得指定可讀Project的有效成員id／name選項，沒有email或ACL全集。
     *
     * @param  PageQueryRequest  $request  有界搜尋與分頁
     * @param  string  $project  Project公開ID
     * @return ApiResponse 有界成員最小投影
     */
    public function members(PageQueryRequest $request, string $project): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project) {
            $page = $this->projects->memberOptions($token->workspace, $project, $request->pagination());

            return PageResource::wrap($page, array_map(fn ($m) => ['id' => $m->user_id, 'name' => $m->getRelation('user')->name], $page->items()));
        }));
    }

    /**
     * 使用同一Project設定來源回狀態候選，狀態type不可視為固定enum。
     *
     * @param  Request  $request  純Bearer
     * @param  string  $project  Project公開ID
     * @return ApiResponse 依Project設定排序的狀態
     */
    public function statuses(Request $request, string $project): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($project) {
            return ['items' => $this->projects->statuses($token->workspace, $project)->map(fn ($status) => ['id' => $status->code, 'label' => $status->label, 'color' => $status->color])->all()];
        }));
    }
}
