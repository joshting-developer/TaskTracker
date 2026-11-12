<?php

namespace App\Http\Controllers\Integrations;

use App\Domain\DomainError;
use App\Http\Controllers\Controller;
use App\Services\Integrations\DeveloperDocumentation;
use Illuminate\Http\Response;

/** 同部署邊界的匿名開發者文件，不收集Token、不建立Session或另開服務。 */
class DeveloperDocumentationController extends Controller
{
    /** 注入固定內容／安全Markdown轉換入口。 */
    public function __construct(private DeveloperDocumentation $documents) {}

    /**
     * 輸出HTML或同份Markdown原文，所有slug均由服務allowlist核對。
     *
     * @param  string  $page  文件slug，可含.md後綴
     * @return Response 無身分或業務內容的匿名文件
     */
    public function show(string $page = 'index'): Response
    {
        $raw = str_ends_with($page, '.md');
        $data = $this->documents->page($raw ? substr($page, 0, -3) : $page);
        if ($raw) {
            return response($data['markdown'])->header('Content-Type', 'text/markdown; charset=UTF-8');
        }

        return response()->view('developer-docs', [...$data, 'pages' => DeveloperDocumentation::PAGES])
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
    }

    /**
     * 固定樣式資源，不映射用戶輸入的檔案。
     *
     * @return Response 文件CSS
     */
    public function styles(): Response
    {
        return response(file_get_contents(resource_path('developer-docs/styles.css')))->header('Content-Type', 'text/css; charset=UTF-8');
    }

    /**
     * 下載明確列出的純HTTP範例，原始碼不含憑證。
     *
     * @param  string  $language  py或sh
     * @return Response text/plain可讀與下載的範例
     */
    public function example(string $language): Response
    {
        if (! in_array($language, ['py', 'sh'], true)) {
            DomainError::fail('NOT_FOUND', '找不到範例');
        }

        return response(file_get_contents(resource_path('developer-docs/workflow.'.$language)))->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
