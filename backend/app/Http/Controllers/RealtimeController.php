<?php

namespace App\Http\Controllers;

use App\Authorization\ProjectAccess;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Repositories\RecordStore;
use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

/** 保留Session／CSRF與來源邊界的Reverb設定及私有頻道簽章入口。 */
class RealtimeController extends Controller
{
    /** 回傳公鑰及同origin代理路徑；不回傳secret、內網位址或資料內容。
     * @param  JsonOperationRequest  $request  JSON請求
     * @param  AccountService  $accounts  可信Session服務
     * @return ApiResponse
     */
    public function config(JsonOperationRequest $request, AccountService $accounts): ApiResponse
    {
        $request->payload();
        $accounts->context($request);
        $enabled = config('broadcasting.default') === 'reverb' && (bool) config('broadcasting.connections.reverb.key');

        return new ApiResponse([
            'enabled' => $enabled,
            'key' => $enabled ? config('broadcasting.connections.reverb.key') : null,
            'path' => '/reverb',
        ]);
    }

    /** 同交易重查空間及Project讀取資格，再由Laravel簽章固定格式的私有頻道。
     * @param  JsonOperationRequest  $request  JSON請求
     * @param  AccountService  $accounts  可信Session服務
     * @return ApiResponse
     */
    public function authorizeChannel(JsonOperationRequest $request, AccountService $accounts): ApiResponse
    {
        $input = $request->payload();
        $result = DB::transaction(function () use ($request, $accounts, $input) {
            $context = $accounts->context($request);
            $channel = Fields::text($input['channel_name'] ?? null, '頻道', 200, true);
            $socket = Fields::text($input['socket_id'] ?? null, '連線', 64, true);
            if (! preg_match('/^private-workspaces\.([A-Za-z0-9_-]{1,100})\.projects\.([A-Za-z0-9_-]{1,100})$/D', $channel, $parts) || ! preg_match('/^\d+\.\d+$/D', $socket)) {
                DomainError::fail('INVALID_INPUT', '無效的即時頻道或連線');
            }
            if ($parts[1] !== $context['workspace']['id']) {
                DomainError::fail('FORBIDDEN', '沒有訂閱此專案的權限');
            }
            $access = new ProjectAccess((new RecordStore)->forWorkspace($parts[1]), $context);
            $access->require($parts[2], 'project.read');
            $access->require($parts[2], 'issue.read');
            if (config('broadcasting.default') !== 'reverb') {
                DomainError::fail('INVALID_INPUT', '即時協作尚未啟用');
            }

            return Broadcast::connection('reverb')->validAuthenticationResponse(new Request([
                'channel_name' => $channel,
                'socket_id' => $socket,
            ]), true);
        });

        return new ApiResponse($result);
    }
}
