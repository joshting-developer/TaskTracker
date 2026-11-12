<?php

namespace App\Http\Middleware;

use App\Services\HttpOperationLogs;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * TT-260：在回應送出後記錄具名路由的寫入操作；紀錄失敗只寫log，不改變已送出的API結果。
 */
class RecordHttpOperation
{
    private const STARTED_AT = 'tasktracker.operationStartedAt';

    private const DURATION = 'tasktracker.operationDurationMs';

    /**
     * 注入操作紀錄服務；本中介層不保存請求狀態，耗時放在Request attributes。
     *
     * @param  HttpOperationLogs  $logs  記錄判斷與寫入
     */
    public function __construct(private HttpOperationLogs $logs) {}

    /**
     * 量測路由處理耗時（不含回應送出）。
     *
     * @param  Request  $request  目前HTTP請求
     * @param  Closure  $next  後續HTTP處理程序
     * @return Response 原樣回傳的回應
     */
    public function handle(Request $request, Closure $next): Response
    {
        Context::forgetHidden(HttpOperationLogs::TOKEN_ACTOR);
        $request->attributes->set(self::STARTED_AT, hrtime(true));
        $response = $next($request);
        $request->attributes->set(self::DURATION, intdiv(hrtime(true) - $request->attributes->get(self::STARTED_AT), 1_000_000));

        return $response;
    }

    /**
     * 回應後整理可信識別碼並寫入；只取Session與Bearer邊界放入Context的身分，不讀密碼、Token原文、Cookie或body內容。
     *
     * @param  Request  $request  已處理完成的HTTP請求
     * @param  Response  $response  已送出的回應
     * @return void
     */
    public function terminate(Request $request, Response $response): void
    {
        $route = $request->route();
        $name = $route?->getName();
        if (! $this->logs->shouldRecord($name, $request->getMethod())) {
            return;
        }
        try {
            $token = Context::getHidden(HttpOperationLogs::TOKEN_ACTOR);
            $session = ! $token && $request->hasSession();
            $project = $route->parameter('project') ?? $request->input('projectId');
            $requestId = $request->input('requestId');
            $this->logs->record([
                'workspaceId' => $token['workspaceId'] ?? ($session ? $this->identifier($request->session()->get('workspace_id'), 36) : null),
                'userId' => $token['userId'] ?? ($session ? $this->identifier($request->user()?->getAuthIdentifier(), 36) : null),
                'tokenId' => $token['tokenId'] ?? null,
                'routeName' => $name,
                'method' => $request->getMethod(),
                'status' => $response->getStatusCode(),
                'projectId' => $this->identifier($project, 64),
                'requestId' => $this->identifier($requestId, 100),
                'durationMs' => (int) $request->attributes->get(self::DURATION, 0),
            ]);
        } catch (\Throwable $error) {
            Log::warning('HTTP操作紀錄寫入失敗', ['route' => $name, 'error' => $error::class]);
        } finally {
            Context::forgetHidden(HttpOperationLogs::TOKEN_ACTOR);
        }
    }

    /**
     * 只保留安全格式的識別碼字串；其他輸入（陣列、過長或含特殊字元）一律捨棄。
     *
     * @param  mixed  $value  路由參數、輸入或Session值
     * @param  int  $max  最大長度
     * @return string|null 合格識別碼
     */
    private function identifier(mixed $value, int $max): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9._:-]{1,'.$max.'}$/D', $value) ? $value : null;
    }
}
