<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Mime\Header\ParameterizedHeader;

/** 保護HTTP授權路徑與郵件標頭, 避免依賴回退帶回已公告行為 */
class SymfonySecurityRegressionTest extends TestCase
{
    /** GET授權匹配需包含路由同樣接受的HEAD
     * @return void
     */
    public function test_head_cannot_escape_a_get_method_matcher(): void
    {
        $matcher = new MethodRequestMatcher(['GET']);
        $this->assertTrue($matcher->matches(Request::create('/api/private', 'HEAD')));
        $this->assertFalse($matcher->matches(Request::create('/api/private', 'POST')));
    }

    /** 腳本名稱不是完整路徑片段時不能被刪掉而繞過前置路徑限制
     * @return void
     */
    public function test_script_name_prefix_does_not_rewrite_a_protected_path(): void
    {
        $request = Request::create('/index.phpadmin', server: ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => '/srv/public/index.php']);
        $this->assertSame('/index.phpadmin', $request->getPathInfo());
    }

    /** 惡意雙斜線前綴不能變成生成URL的外部主機
     * @return void
     */
    public function test_client_path_cannot_set_an_external_base_url(): void
    {
        $request = Request::create('https://app.example//evil.example/public/index.php/login', server: ['SCRIPT_NAME' => '/public/index.php', 'SCRIPT_FILENAME' => '/srv/public/index.php']);
        $this->assertSame('', $request->getBaseUrl());
        $this->assertSame('', $request->getBasePath());
    }

    /** 附件參數名稱中的CRLF必須拒絕, 正常檔名參數仍可序列化
     * @return void
     */
    public function test_mime_parameter_names_cannot_inject_another_header(): void
    {
        $safe = new ParameterizedHeader('Content-Disposition', 'attachment', ['filename' => 'report.pdf']);
        $this->assertStringContainsString('report.pdf', $safe->toString());
        $this->expectException(RfcComplianceException::class);
        $header = new ParameterizedHeader('Content-Disposition', 'attachment', ["filename\r\nX-Injected" => 'report.pdf']);
        $header->toString();
    }
}
