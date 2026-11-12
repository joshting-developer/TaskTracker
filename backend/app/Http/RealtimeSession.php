<?php

namespace App\Http;

use App\Authorization\ProjectChannelAccess;
use App\Domain\DomainError;
use App\Domain\Fields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

/** Reverb的HTTP協調：同交易驗證身分／頻道後簽章，不在Controller查資料。 */
class RealtimeSession
{
    /** 注入HTTP身分與獨立頻道授權，兩者均不由Controller查資料。 */
    public function __construct(private WorkspaceSession $session, private ProjectChannelAccess $access) {}

    /**
     * @param  Request  $request  目前HTTP與Session
     * @return array 可公開的Reverb設定
     */
    public function configuration(Request $request): array
    {
        $this->session->context($request);
        $enabled = config('broadcasting.default') === 'reverb' && (bool) config('broadcasting.connections.reverb.key');

        return ['enabled' => $enabled, 'key' => $enabled ? config('broadcasting.connections.reverb.key') : null, 'path' => '/reverb'];
    }

    /**
     * @param  Request  $request  目前HTTP與Session
     * @param  array  $input  JSON傳輸輸入
     * @return array Pusher協定的認證簽章
     */
    public function authorize(Request $request, array $input): array
    {
        return DB::transaction(function () use ($request, $input) {
            $context = $this->session->verified($request);
            $channel = Fields::text($input['channel_name'] ?? null, '頻道', 200, true);
            $socket = Fields::text($input['socket_id'] ?? null, '連線', 64, true);
            $this->access->authorize($context, $channel, $socket);
            if (config('broadcasting.default') !== 'reverb') {
                DomainError::fail('INVALID_INPUT', '即時協作尚未啟用');
            }

            return Broadcast::connection('reverb')->validAuthenticationResponse(new Request([
                'channel_name' => $channel, 'socket_id' => $socket,
            ]), true);
        });
    }
}
