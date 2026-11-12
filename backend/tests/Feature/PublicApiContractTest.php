<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Opis\JsonSchema\CompliantValidator;
use Tests\Concerns\PersonalTokenCases;
use Tests\TestCase;

/** 正式OpenAPI與真實HTTP的契約驗證；不以自行實作的Schema子集取代標準。 */
class PublicApiContractTest extends TestCase
{
    use PersonalTokenCases, RefreshDatabase;

    /**
     * 從正式唯一來源讀規格，測試不另存手寫Schema。
     *
     * @return array 已解碼正式OpenAPI
     */
    private function specification(): array
    {
        return json_decode(file_get_contents(resource_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * 讓標準JSON Schema解析器處理本地ref，保留JSON object／array的型別。
     *
     * @param  mixed  $data  已解碼JSON值
     * @param  array  $schema  正式規格的Schema
     * @return void
     */
    private function matchesSchema(mixed $data, array $schema): void
    {
        $root = json_decode(json_encode(['$schema' => 'https://json-schema.org/draft/2020-12/schema', ...$schema, 'components' => $this->specification()['components']], JSON_THROW_ON_ERROR));
        $result = (new CompliantValidator)->validate($data, $root);
        // 不將實際回應或原文Token寫到assertion錯誤日誌。
        $this->assertTrue($result->isValid(), '正式Schema與JSON資料不符');
    }

    /**
     * 以正式operation的實際status對照Schema，避免只檢查有ok/data。
     *
     * @param  TestResponse  $response  實際HTTP回應，不輸出秘密
     * @param  string  $path  正式path template
     * @param  string  $method  小寫method
     * @return void
     */
    private function matchesResponse(TestResponse $response, string $path, string $method): void
    {
        $operation = $this->specification()['paths'][$path][$method];
        $this->assertArrayHasKey((string) $response->status(), $operation['responses']);
        $this->matchesSchema(json_decode($response->getContent()), $operation['responses'][(string) $response->status()]['content']['application/json']['schema']);
    }

    /** @return void */
    public function test_discovery_is_anonymous_has_no_database_access_or_session_and_examples_match_schemas(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->getJson('/api/public/openapi.json')->assertOk()->assertCookieMissing(config('session.cookie'));
            $this->get('/api/public/contract.md')->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')->assertCookieMissing(config('session.cookie'))->assertSee('TaskTracker HTTP API', false);
            $this->assertCount(0, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
        $spec = $this->specification();
        $this->assertSame('/api/public/contract.md', $spec['x-documentation-url']);
        $this->assertSame([['url' => '/']], $spec['servers']);
        foreach ($spec['paths'] as $methods) {
            foreach ($methods as $operation) {
                $this->assertFalse($operation['deprecated']);
                foreach ($operation['responses'] as $response) {
                    $media = $response['content']['application/json'];
                    $this->matchesSchema(json_decode(json_encode($media['example'])), $media['schema']);
                }
                if (isset($operation['requestBody'])) {
                    $media = $operation['requestBody']['content']['application/json'];
                    $this->matchesSchema(json_decode(json_encode($media['example'])), $media['schema']);
                }
            }
        }
        $this->assertSame(20, $spec['paths']['/api/v1/personal-tokens']['post']['x-rate-limit']['requests']);
        $this->assertSame(120, $spec['paths']['/api/public/v1/me']['get']['x-rate-limit']['requests']);
    }

    /** @return void */
    public function test_current_operations_and_errors_match_the_formal_schemas(): void
    {
        $identity = $this->tokenRegister();
        $request = $this->specification()['paths']['/api/v1/personal-tokens']['post']['requestBody']['content']['application/json']['example'];
        $created = $this->postJson('/api/v1/personal-tokens', $request)->assertOk()->assertHeader('X-RateLimit-Limit', '20');
        $this->matchesResponse($created, '/api/v1/personal-tokens', 'post');
        $plain = $created->json('data.plainTextToken');
        $id = $created->json('data.token.id');
        $this->matchesResponse($this->postJson('/api/v1/personal-tokens', $request)->assertStatus(409), '/api/v1/personal-tokens', 'post');
        $this->matchesResponse($this->getJson('/api/v1/personal-tokens')->assertOk(), '/api/v1/personal-tokens', 'get');
        $this->matchesResponse($this->getJson('/api/v1/personal-tokens?page=0')->assertStatus(400), '/api/v1/personal-tokens', 'get');
        $this->matchesResponse($this->getJson('/api/v1/personal-tokens', ['X-Workspace-ID' => 'other'])->assertStatus(409), '/api/v1/personal-tokens', 'get');
        $this->matchesResponse($this->bearerIdentity($plain)->assertHeader('X-RateLimit-Limit', '120'), '/api/public/v1/me', 'get');
        self::assertStringContainsString('no-store', $this->getJson('/api/public/v1/me', ['Origin' => 'https://untrusted.example'])->assertStatus(403)->headers->get('Cache-Control'));
        $this->matchesResponse($this->getJson('/api/public/v1/me', ['Origin' => 'https://untrusted.example'])->assertStatus(403), '/api/public/v1/me', 'get');
        $this->matchesResponse($this->postJson('/api/v1/personal-tokens/'.$id.'/revoke')->assertOk()->assertHeader('X-RateLimit-Limit', '60'), '/api/v1/personal-tokens/{token}/revoke', 'post');
        $this->matchesResponse($this->bearerIdentity($plain, 401), '/api/public/v1/me', 'get');
        $this->matchesResponse($this->getJson('/api/v1/personal-token-audits')->assertOk(), '/api/v1/personal-token-audits', 'get');
        $this->matchesResponse($this->postJson('/api/v1/personal-tokens/99999999/revoke')->assertStatus(404), '/api/v1/personal-tokens/{token}/revoke', 'post');
        $this->getJson('/api/public/v2/me')->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        $this->postJson('/api/public/v1/me')->assertStatus(405)->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED')->assertHeader('Allow', 'GET, HEAD');
    }

    /** @return void */
    public function test_limits_are_separate_and_429_preserves_retry_after_and_rate_headers(): void
    {
        $this->tokenRegister();
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/personal-tokens', [])->assertStatus(400);
        }
        $createLimit = $this->postJson('/api/v1/personal-tokens', [])->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMIT');
        $this->matchesResponse($createLimit, '/api/v1/personal-tokens', 'post');
        $this->assertGreaterThan(0, (int) $createLimit->headers->get('Retry-After'));
        $this->assertLessThanOrEqual(60, (int) $createLimit->headers->get('Retry-After'));
        $createLimit->assertHeader('X-RateLimit-Limit', '20')->assertHeader('X-RateLimit-Remaining', '0');
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/api/v1/personal-tokens/99999999/revoke')->assertStatus(404);
        }
        $this->postJson('/api/v1/personal-tokens/99999999/revoke')->assertStatus(429)->assertHeader('X-RateLimit-Limit', '60');
        $this->getJson('/api/v1/personal-tokens')->assertOk();
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/public/v1/me')->assertStatus(401);
        }
        $publicLimit = $this->getJson('/api/public/v1/me')->assertStatus(429)->assertHeader('X-RateLimit-Limit', '120');
        $this->assertGreaterThan(0, (int) $publicLimit->headers->get('Retry-After'));
        $this->assertGreaterThanOrEqual(time(), (int) $publicLimit->headers->get('X-RateLimit-Reset'));
        $this->matchesResponse($publicLimit, '/api/public/v1/me', 'get');
    }

    /** @return void */
    public function test_unexpected_public_exception_is_a_safe_error_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/public/v1/contract-test-failure', fn () => throw new \RuntimeException('private-sql-marker'));
        $response = $this->getJson('/api/public/v1/contract-test-failure')->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR')->assertDontSee('private-sql-marker', false)->assertDontSee('trace', false);
        $this->matchesResponse($response, '/api/public/v1/me', 'get');
    }
}
