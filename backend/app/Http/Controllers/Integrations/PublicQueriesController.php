<?php

namespace App\Http\Controllers\Integrations;

use App\Data\TokenContext;
use App\Database\DatabaseTimes;
use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Requests\Integrations\IssueChangesRequest;
use App\Http\Requests\IssueQueryRequest;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\IssueDetailResource;
use App\Http\Resources\Queries\IssueListResource;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\ProjectResource;
use App\Services\Integrations\IssueChangeService;
use App\Services\IssueReadService;
use App\Services\ProjectReadService;
use Illuminate\Http\Request;

/** 公開PAT只讀適配器；授權／SQL及投影均沿既有用例，不依賴Session或SPA狀態。 */
class PublicQueriesController extends Controller
{
    /**
     * 注入Bearer可信交易邊界；其餘查詢服務由各方法按需解析。
     *
     * @param  BearerTokenSession  $tokens  Bearer Token交易與資格邊界
     */
    public function __construct(private BearerTokenSession $tokens) {}

    /**
     * SQL先過濾本人可見範圍，再回Project摘要及本人的有效權限。
     *
     * @param  PageQueryRequest  $request  分頁與搜尋
     * @param  ProjectReadService  $projects  共用Project查詢
     * @return ApiResponse 有界摘要，不含Issues／歷史
     */
    public function projects(PageQueryRequest $request, ProjectReadService $projects): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $projects) {
            $result = $projects->index($token->workspace, $request->pagination());
            $items = array_map(fn ($p) => [...(new ProjectResource($p))->resolve(), 'permissions' => $result->permissions[$p->public_id]], $result->page->items());

            return PageResource::wrap($result->page, $items);
        }));
    }

    /**
     * 取得指定可見Project的metadata與狀態，不載入Workspace其他內容。
     *
     * @param  Request  $request  純Bearer
     * @param  string  $project  Project公開ID
     * @param  ProjectReadService  $projects  共用Project查詢
     * @return ApiResponse Project詳細及本人的權限來源
     */
    public function project(Request $request, string $project, ProjectReadService $projects): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($project, $projects) {
            $data = $projects->show($token->workspace, $project);

            return [...(new ProjectResource($data['project']))->resolve(), ...$data['capabilities']];
        }));
    }

    /**
     * 只取得指定可讀Project的有效成員id／name選項，沒有email或ACL全集。
     *
     * @param  PageQueryRequest  $request  有界搜尋與分頁
     * @param  string  $project  Project公開ID
     * @param  ProjectReadService  $projects  共用成員候選用例
     * @return ApiResponse 有界成員最小投影
     */
    public function members(PageQueryRequest $request, string $project, ProjectReadService $projects): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project, $projects) {
            $page = $projects->memberOptions($token->workspace, $project, $request->pagination());

            return PageResource::wrap($page, array_map(fn ($m) => ['id' => $m->user_id, 'name' => $m->getRelation('user')->name], $page->items()));
        }));
    }

    /**
     * 使用同一Project設定來源回狀態候選，狀態type不可視為固定enum。
     *
     * @param  Request  $request  純Bearer
     * @param  string  $project  Project公開ID
     * @param  ProjectReadService  $projects  共用Project查詢
     * @return ApiResponse 依Project設定排序的狀態
     */
    public function statuses(Request $request, string $project, ProjectReadService $projects): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($project, $projects) {
            return ['items' => $projects->statuses($token->workspace, $project)->map(fn ($status) => ['id' => $status->code, 'label' => $status->label, 'color' => $status->color])->all()];
        }));
    }

    /**
     * 共用SQL篩選及穩定排序，identifier精準定位仍回摘要分頁。
     *
     * @param  IssueQueryRequest  $request  有界任務查詢
     * @param  string  $project  Project公開ID
     * @param  IssueReadService  $issues  共用任務用例
     * @return ApiResponse 不含描述／活動的任務摘要
     */
    public function issues(IssueQueryRequest $request, string $project, IssueReadService $issues): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project, $issues) {
            $page = $issues->index($token->workspace, $project, $request->toDto());

            return PageResource::wrap($page, IssueListResource::collection($page->items())->resolve());
        }));
    }

    /**
     * 以本輪固定水位回傳目前可讀Project的有界增量摘要。
     *
     * @param  IssueChangesRequest  $request  有界增量／接續條件
     * @param  string  $project  Project公開ID
     * @param  IssueChangeService  $changes  共用資格與最小摘要用例
     * @return ApiResponse 增量摘要、接續cursor及本輪固定水位
     */
    public function changes(IssueChangesRequest $request, string $project, IssueChangeService $changes): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project, $changes) {
            $result = $changes->read($token->workspace, $project, $request->toChangesDto());
            $result['items'] = IssueListResource::collection($result['items'])->resolve();

            return $result;
        }));
    }

    /**
     * 單卡取得可編輯屬性及最新revision，不附留言／異動全集。
     *
     * @param  Request  $request  純Bearer
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID，卡號先用identifier篩選取得
     * @param  IssueReadService  $issues  共用單卡查詢
     * @return ApiResponse 完整單卡屬性與有限關聯參照
     */
    public function issue(Request $request, string $project, string $issue, IssueReadService $issues): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, fn (TokenContext $token) => (new IssueDetailResource($issues->show($token->workspace, $project, $issue)))->resolve()));
    }

    /**
     * 按需取得指定卡片留言，不同時讀取其他活動或卡片。
     *
     * @param  PageQueryRequest  $request  分頁條件
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID
     * @param  IssueReadService  $issues  共用留言查詢
     * @return ApiResponse 單卡留言分頁
     */
    public function comments(PageQueryRequest $request, string $project, string $issue, IssueReadService $issues): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project, $issue, $issues) {
            $page = $issues->comments($token->workspace, $project, $issue, $request->pagination());

            return PageResource::wrap($page, array_map(fn ($row) => ['id' => $row->id, 'content' => $row->content, 'author' => $row->author, 'createdAt' => DatabaseTimes::public($row->created_at)], $page->items()));
        }));
    }
}
