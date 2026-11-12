<?php

namespace App\Http\Controllers\Queries;

use App\Http\ApiExecution;
use App\Http\Controllers\Controller;
use App\Http\Requests\PageQueryRequest;
use App\Http\Requests\TodoQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\TodoDetailResource;
use App\Http\Resources\Queries\TodoNotificationResource;
use App\Http\Resources\Queries\TodoSummaryResource;
use App\Services\TodoReadService;
use Illuminate\Http\Request;

/** 個人待辦、內容、歷史與通知各自有界的HTTP查詢入口。 */
class TodosController extends Controller
{
    /**
     * 取得個人待辦摘要分頁，不附其他頁面資料。
     *
     * @param  TodoQueryRequest  $request  已驗證的個人列表條件
     * @param  ApiExecution  $execution  可信資格與交易邊界
     * @param  TodoReadService  $todos  個人查詢用例
     * @return ApiResponse 待辦摘要分頁
     */
    public function index(TodoQueryRequest $request, ApiExecution $execution, TodoReadService $todos): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['result' => $todos->index($actor, $request->toDto())]);
        $result = $data['result'];
        $items = array_map(fn ($todo) => (new TodoSummaryResource($todo, $result->capabilities[$todo->public_id], $result->links[$todo->converted_issue_id] ?? null))->resolve(), $result->page->items());

        return new ApiResponse(PageResource::wrap($result->page, $items));
    }

    /**
     * 只讀指定待辦原稿，保留不可見資源404。
     *
     * @param  Request  $request  HTTP工作區標頭
     * @param  string  $todo  Todo公開ID
     * @param  ApiExecution  $execution  可信資格與交易邊界
     * @param  TodoReadService  $todos  個人查詢用例
     * @return ApiResponse 單待辦內容
     */
    public function show(Request $request, string $todo, ApiExecution $execution, TodoReadService $todos): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => $todos->show($actor, $todo));

        return new ApiResponse((new TodoDetailResource($data['todo'], $data['capabilities'], $data['links'][$data['todo']->converted_issue_id] ?? null))->resolve());
    }

    /**
     * 只讀指定待辦的歷史分頁，不附原稿。
     *
     * @param  PageQueryRequest  $request  有界分頁
     * @param  string  $todo  Todo公開ID
     * @param  ApiExecution  $execution  可信資格與交易邊界
     * @param  TodoReadService  $todos  個人查詢用例
     * @return ApiResponse 歷史分頁
     */
    public function history(PageQueryRequest $request, string $todo, ApiExecution $execution, TodoReadService $todos): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['page' => $todos->history($actor, $todo, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], array_map(fn ($event) => json_decode($event->event_data, true, flags: JSON_THROW_ON_ERROR), $data['page']->items())));
    }

    /**
     * 只讀個人待辦數量，不附待辦列表或通知。
     *
     * @param  Request  $request  HTTP工作區標頭
     * @param  ApiExecution  $execution  可信資格與交易邊界
     * @param  TodoReadService  $todos  個人查詢用例
     * @return ApiResponse 範圍與生命週期數量
     */
    public function counts(Request $request, ApiExecution $execution, TodoReadService $todos): ApiResponse
    {
        return new ApiResponse($execution->query($request, fn ($actor) => $todos->counts($actor)));
    }

    /**
     * 只讀本人有效未讀通知數。
     *
     * @param  Request  $request  HTTP工作區標頭
     * @param  ApiExecution  $execution  可信資格與交易邊界
     * @param  TodoReadService  $todos  個人查詢用例
     * @return ApiResponse 未讀摘要
     */
    public function unread(Request $request, ApiExecution $execution, TodoReadService $todos): ApiResponse
    {
        return new ApiResponse($execution->query($request, fn ($actor) => $todos->unread($actor)));
    }

    /**
     * 按需取得收件人通知分頁，不讀待辦內容。
     *
     * @param  PageQueryRequest  $request  有界分頁
     * @param  ApiExecution  $execution  可信資格與交易邊界
     * @param  TodoReadService  $todos  個人查詢用例
     * @return ApiResponse 通知分頁
     */
    public function notifications(PageQueryRequest $request, ApiExecution $execution, TodoReadService $todos): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['page' => $todos->notifications($actor, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], TodoNotificationResource::collection($data['page']->items())->resolve()));
    }

    /**
     * 變更偵測不下載待辦、通知原文或成員集合。
     *
     * @param  Request  $request  HTTP工作區標頭
     * @param  ApiExecution  $execution  可信資格與交易邊界
     * @param  TodoReadService  $todos  個人查詢用例
     * @return ApiResponse 當次個人範圍版本
     */
    public function versions(Request $request, ApiExecution $execution, TodoReadService $todos): ApiResponse
    {
        return new ApiResponse($execution->query($request, fn ($actor) => $todos->versions($actor)));
    }

    /**
     * 搜尋可接待辦的有效成員，不附工作區權限歷史。
     *
     * @param  PageQueryRequest  $request  有界分頁／搜尋
     * @param  ApiExecution  $execution  可信資格與交易邊界
     * @param  TodoReadService  $todos  個人查詢用例
     * @return ApiResponse 承辦者最小選項
     */
    public function assignees(PageQueryRequest $request, ApiExecution $execution, TodoReadService $todos): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['page' => $todos->assignees($actor, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], array_map(fn ($member) => ['id' => $member->id, 'name' => $member->name, 'email' => $member->email], $data['page']->items())));
    }
}
