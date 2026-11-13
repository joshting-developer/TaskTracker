<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** 匿名文件只提供固定安全內容，不讀租戶／帳號資料，不建立Session。 */
class DeveloperDocumentationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 驗證匿名HTML、Markdown與範例下載同源，不建立Session或查詢業務資料庫。
     *
     * @return void
     */
    public function test_documentation_html_markdown_and_downloads_are_anonymous_and_database_free(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            foreach (['index', 'authentication', 'examples', 'agents', 'contract'] as $page) {
                $html = $this->get('/api/public/docs/'.$page)->assertOk()->assertCookieMissing(config('session.cookie'))->assertSee('TaskTracker', false)->assertSee('跳到文件內容', false);
                $this->assertStringContainsString("default-src 'none'", $html->headers->get('Content-Security-Policy'));
                // TT-299：文件頁可回到網站首頁（品牌與明確的回到TaskTracker連結）。
                $html->assertSee('<a class="brand-site" href="/"', false)->assertSee('<a href="/">回到 TaskTracker</a>', false);
                $this->assertStringNotContainsString('<script', $html->getContent());
                $this->assertStringNotContainsString('<input', $html->getContent());
                $this->get('/api/public/docs/'.$page.'.md')->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')->assertCookieMissing(config('session.cookie'));
            }
            $this->get('/api/public/docs')->assertOk();
            $this->get('/api/public/docs/assets/styles.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');
            foreach (['py', 'sh'] as $language) {
                $this->get('/api/public/docs/examples.'.$language)->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertSee('CONFIRM_WRITE', false);
            }
            $this->get('/api/public/docs/private')->assertStatus(404)->assertDontSee('resource_path', false);
            $this->get('/api/public/docs/..%2F.env')->assertStatus(404);
            $this->assertCount(0, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
