<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Opis\JsonSchema\CompliantValidator;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

/** 正式機器規格的雙向路由／安全／必填及範例防漂移，不另存第二份契約。 */
class TokenSpecificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 對照實際公開路由、FormRequest及標準Schema；測試失敗不印文件值或秘密。
     *
     * @param  array  $spec  正式規格或故意破壞的隔離副本
     * @return void
     */
    private function verifyContract(array $spec): void
    {
        $this->assertSame('3.1.0', $spec['openapi']);
        $operations = [];
        $declared = [];
        foreach ($spec['paths'] as $path => $methods) {
            $this->assertTrue(str_starts_with($path, '/api/public/v1/') || in_array($path, ['/api/v1/personal-tokens', '/api/v1/personal-tokens/{token}/revoke', '/api/v1/personal-token-audits'], true));
            foreach ($methods as $method => $operation) {
                $operations[] = $operation['operationId'];
                $declared[] = strtoupper($method).' '.$path;
                $route = $this->app['router']->getRoutes()->match(Request::create(str_replace(['{token}', '{project}', '{issue}'], '1', $path), strtoupper($method)));
                $this->assertSame(ltrim($path, '/'), $route->uri());
                $this->assertNotNull($route->getAction('controller'));
                $this->assertArrayHasKey('200', $operation['responses']);
                $this->assertArrayHasKey('401', $operation['responses']);
                $this->assertSame(isset($operation['x-rate-limit']), isset($operation['responses']['429']));
                if (str_starts_with($path, '/api/public/v1/')) {
                    $this->assertSame([['bearerPat' => []]], $operation['security']);
                    $this->assertSame($method === 'get' ? 'read' : 'write', $operation['x-required-token-ability']);
                } else {
                    $this->assertArrayHasKey('sessionCookie', $operation['security'][0]);
                }
                if (isset($operation['requestBody'])) {
                    [$class,$action] = explode('@', $route->getAction('controller'));
                    $required = [];
                    foreach ((new \ReflectionMethod($class, $action))->getParameters() as $parameter) {
                        $type = $parameter->getType()?->getName();
                        if ($type && is_subclass_of($type, FormRequest::class)) {
                            $request = new $type;
                            $request->setRouteResolver(fn () => $route);
                            foreach ($request->rules() as $name => $rules) {
                                if (! str_contains($name, '.') && in_array('required', is_array($rules) ? $rules : explode('|', $rules), true)) {
                                    $required[] = $name;
                                }
                            }
                        }
                    }
                    $schema = $operation['requestBody']['content']['application/json']['schema'];
                    if (isset($schema['$ref'])) {
                        $schema = $spec['components']['schemas'][basename($schema['$ref'])];
                    }
                    $documented = $schema['required'];
                    sort($documented);
                    sort($required);
                    $this->assertSame($required, $documented, 'FormRequest與OpenAPI必填欄位不一致');
                }
                foreach ($operation['responses'] as $response) {
                    $this->verifyExample($spec, $response['content']['application/json']);
                }
                if (isset($operation['requestBody'])) {
                    $this->verifyExample($spec, $operation['requestBody']['content']['application/json']);
                }
            }
        }
        $this->assertSame(count($operations), count(array_unique($operations)));
        $actual = [];
        foreach ($this->app['router']->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/public/v1/') || str_starts_with($route->getAction('controller') ?? '', 'App\\Http\\Controllers\\Integrations\\PersonalTokensController@')) {
                foreach ($route->methods() as $method) {
                    if ($method !== 'HEAD') {
                        $actual[] = $method.' /'.$route->uri();
                    }
                }
            }
        }
        sort($actual);
        sort($declared);
        $this->assertSame($actual, $declared, '公開實作與正式規格必須雙向完整對應');
    }

    /**
     * 正式範例需符合本operation的標準資料Schema。
     *
     * @param  array  $spec  單一正式規格
     * @param  array  $media  JSON Schema與example
     * @return void
     */
    private function verifyExample(array $spec, array $media): void
    {
        $schema = json_decode(json_encode(['$schema' => 'https://json-schema.org/draft/2020-12/schema', ...$media['schema'], 'components' => $spec['components']]));
        $this->assertTrue((new CompliantValidator)->validate(json_decode(json_encode($media['example'])), $schema)->isValid(), '範例與正式Schema不一致');
    }

    /** @return void */
    public function test_specification_matches_routes_and_has_no_unreleased_business_api(): void
    {
        $spec = $this->getJson('/api/public/openapi.json')->assertOk()->json();
        $this->verifyContract($spec);
        $this->assertStringNotContainsString('plainTextToken', json_encode($spec['components']['schemas']['Token']));
    }

    /** @return void */
    public function test_drift_in_scope_security_required_operation_route_or_examples_is_rejected(): void
    {
        $original = json_decode(file_get_contents(resource_path('openapi.json')), true);
        $mutations = [
            function (&$s) {
                $s['paths']['/api/public/v1/me']['get']['security'] = [];
            },
            function (&$s) {
                $s['paths']['/api/public/v1/me']['get']['x-required-token-ability'] = 'write';
            },
            function (&$s) {
                $s['paths']['/api/public/v1/projects']['get']['operationId'] = 'getTokenIdentity';
            },
            function (&$s) {
                $s['paths']['/api/public/v1/not-implemented'] = $s['paths']['/api/public/v1/me'];
            },
            function (&$s) {
                unset($s['paths']['/api/public/v1/projects']);
            },
            function (&$s) {
                $s['components']['schemas']['PatchIssue']['required'] = ['requestId'];
            },
            function (&$s) {
                unset($s['paths']['/api/public/v1/me']['get']['responses']['200']['content']['application/json']['example']['data']['userId']);
            },
        ];
        foreach ($mutations as $change) {
            $spec = $original;
            $change($spec);
            $rejected = false;
            try {
                $this->verifyContract($spec);
            } catch (AssertionFailedError|HttpExceptionInterface|\ErrorException $e) {
                $rejected = true;
            }
            $this->assertTrue($rejected,'破壞契約後應失敗');
        }
    }
}
