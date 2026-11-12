<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** 正式規格僅列已交付路由，來源可匿名讀取且不啟動Session。 */
class TokenSpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_specification_matches_routes_and_has_no_unreleased_business_api(): void
    {
        $spec = $this->getJson('/api/public/openapi.json')->assertOk()->json();
        $this->assertSame('3.1.0', $spec['openapi']);
        $operations = [];
        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $operations[] = $operation['operationId'];
                $route = $this->app['router']->getRoutes()->match(Request::create(str_replace(['{token}', '{project}', '{issue}'], '1', $path), strtoupper($method)));
                $this->assertNotNull($route->getAction('controller'));
                $this->assertArrayHasKey('200', $operation['responses']);
                $this->assertArrayHasKey('401', $operation['responses']);
                $this->assertSame(isset($operation['x-rate-limit']), isset($operation['responses']['429']));
            }
        }
        $this->assertSame(count($operations), count(array_unique($operations)));
        $this->assertSame(15, count($operations));
        $this->assertSame([['bearerPat' => []]], $spec['paths']['/api/public/v1/me']['get']['security']);
        $this->assertCount(11, $spec['paths']);
        $this->assertStringNotContainsString('plainTextToken', json_encode($spec['components']['schemas']['Token']));
    }
}
