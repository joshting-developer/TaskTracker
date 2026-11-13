<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

/** 舊業務測試的註冊fixture明確走完本機驗證, 驗證專項使用rawRegistration保留pending */
trait RegistersVerifiedFixtures
{
    protected bool $rawRegistration = false;

    /** 業務測試fixture明確走完本機驗證, 不略過正式驗證邏輯
     * @param  string  $uri  JSON端點
     * @param  array  $data  測試輸入
     * @param  array  $headers  測試headers
     * @param  int  $options  JSON旗標
     * @return TestResponse 原回應或完成驗證後的身分
     */
    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        $response = parent::postJson($uri, $data, $headers, $options);
        if (! $this->rawRegistration && $uri === '/api/auth/register' && $response->status() === 200 && $response->json('data.verificationRequired')) {
            return parent::postJson('/api/auth/verify-email', ['code' => 'local666'], $headers, $options)->assertOk();
        }

        return $response;
    }
}
