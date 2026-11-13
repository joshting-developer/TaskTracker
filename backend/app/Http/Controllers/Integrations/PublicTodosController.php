<?php

namespace App\Http\Controllers\Integrations;

use App\Data\TokenContext;
use App\Factories\TodosFactory;
use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Requests\Integrations\PublicTodoWriteRequest;
use App\Http\Requests\PageQueryRequest;
use App\Http\Requests\TodoQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\TodoDetailResource;
use App\Http\Resources\Queries\TodoSummaryResource;
use App\Services\TodoCollaborationService;
use App\Services\TodoReadService;

/**
 * TT-311：公開API的小待辦；讓Agent接任務前讀交辦給自己的待辦, 也能交辦、完成或退回。
 * 讀寫都沿用網頁的TodoReadService／Todos用例, 外層再加Token範圍與requestId重送。
 */
class PublicTodosController extends Controller
{
    /**
     * 注入Token驗證、待辦查詢與寫入用例。
     *
     * @param  BearerTokenSession  $tokens  Token驗證、交易與稽核
     * @param  TodoReadService  $reads  有界待辦查詢
     * @param  TodosFactory  $todos  依當下身分組裝待辦寫入用例
     * @param  TodoCollaborationService  $collaboration  有界留言及交接讀取
     */
    public function __construct(private BearerTokenSession $tokens, private TodoReadService $reads, private TodosFactory $todos, private TodoCollaborationService $collaboration) {}

    /**
     * 待辦列表：scope=mine（交辦給我、預設）或sent（我交辦出去）, state=open／closed, 分頁上限100。
     *
     * @param  TodoQueryRequest  $request  分頁與篩選
     * @return ApiResponse 待辦摘要分頁
     */
    public function index(TodoQueryRequest $request): ApiResponse
    {
        $result = $this->tokens->read($request, fn (TokenContext $token) => ['result' => $this->reads->index($token->workspace, $request->toDto()), 'write' => in_array('write', $token->abilities, true)]);
        $tokenWrite = $result['write'];
        $result = $result['result'];
        $items = array_map(fn ($todo) => (new TodoSummaryResource($todo, $this->tokenCapabilities($result->capabilities[$todo->public_id], $tokenWrite), $result->links[$todo->converted_issue_id] ?? null))->resolve(), $result->page->items());

        return new ApiResponse(PageResource::wrap($result->page, $items));
    }

    /**
     * 單筆待辦, 含完整內容。
     *
     * @param  PageQueryRequest  $request  目前請求
     * @param  string  $todo  待辦ID
     * @return ApiResponse 待辦詳細
     */
    public function show(PageQueryRequest $request, string $todo): ApiResponse
    {
        $data = $this->tokens->read($request, fn (TokenContext $token) => [...$this->reads->show($token->workspace, $todo), 'tokenWrite' => in_array('write', $token->abilities, true)]);

        return new ApiResponse((new TodoDetailResource($data['todo'], $this->tokenCapabilities($data['capabilities'], $data['tokenWrite']), $data['links'][$data['todo']->converted_issue_id] ?? null))->resolve());
    }

    /**
     * 可以交辦的對象（同工作空間、有效成員）。
     *
     * @param  PageQueryRequest  $request  分頁與搜尋
     * @return ApiResponse 成員分頁
     */
    public function assignees(PageQueryRequest $request): ApiResponse
    {
        $page = $this->tokens->read($request, fn (TokenContext $token) => $this->reads->assignees($token->workspace, $request->pagination()));

        return new ApiResponse(PageResource::wrap($page, array_map(fn ($member) => ['id' => $member->id, 'name' => $member->name], $page->items())));
    }

    /**
     * 建立／修改／完成／重開／退回；同requestId重送回原結果。
     *
     * @param  PublicTodoWriteRequest  $request  已驗證的JSON本文
     * @param  string|null  $todo  既有待辦ID（建立時為null）
     * @return ApiResponse {id, revision}
     */
    public function execute(PublicTodoWriteRequest $request, ?string $todo = null): ApiResponse
    {
        $operation = match ($request->route()->getName()) {
            'public.todos.create' => 'createTodo',
            'public.todos.update' => 'updateTodo',
            'public.todos.complete' => 'completeTodo',
            'public.todos.reopen' => 'reopenTodo',
            'public.todos.return' => 'returnTodo',
            'public.todos.comment' => 'commentTodo',
            'public.todos.forward' => 'forwardTodo',
        };
        $input = [...$request->validated(), ...($todo ? ['id' => $todo] : [])];
        $result = $this->tokens->writeOnce($request, 'public.v1.'.$operation, $input, fn (TokenContext $token) => $this->collaboration->authorizeWrite($token->workspace, $operation, $todo),
            fn (TokenContext $token) => $this->todos->forSession($token->workspace->legacyIdentity())->execute($operation, $input), null);

        return new ApiResponse(['id' => $result['id'], 'revision' => $result['revision']]);
    }

    /** 按需讀取留言或經手紀錄, PAT仍受當次經手資格限制
     * @param  PageQueryRequest  $request  有界分頁
     * @param  string  $todo  待辦公開ID
     * @param  string  $kind  路由固定事件種類
     * @return ApiResponse 事件分頁
     */
    public function events(PageQueryRequest $request, string $todo, string $kind): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, fn (TokenContext $token) => $this->collaboration->events($token->workspace, $todo, $kind, $request->pagination())));
    }

    /** 公開操作能力仍取本人權限與Token write範圍交集
     * @param  array<string,bool>  $capabilities  本人當次能力
     * @param  bool  $write  Token是否允許寫入
     * @return array<string,bool> 對這個Token有效的能力
     */
    private function tokenCapabilities(array $capabilities, bool $write): array
    {
        return $write ? $capabilities : array_fill_keys(array_keys($capabilities), false);
    }
}
