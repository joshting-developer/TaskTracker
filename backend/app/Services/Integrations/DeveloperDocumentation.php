<?php

namespace App\Services\Integrations;

use App\Domain\DomainError;
use Illuminate\Support\Str;

/** 匿名靜態文件的唯一內容入口；不讀身分、PAT或業務資料，不接受任意檔案路徑。 */
class DeveloperDocumentation
{
    public const PAGES = ['index' => '快速開始', 'authentication' => '認證與權限', 'examples' => '完整操作範例', 'agents' => 'Agent／CLI指南', 'contract' => 'HTTP契約'];

    /**
     * 只接受固定slug，內容與原文共用，不維護第二份HTML文件。
     *
     * @param  string  $slug  固定文件代號
     * @return array{title:string,markdown:string,html:string,slug:string,version:string} 安全文件資料
     */
    public function page(string $slug): array
    {
        if (! array_key_exists($slug, self::PAGES)) {
            DomainError::fail('NOT_FOUND', '找不到這份文件');
        }
        $path = $slug === 'contract' ? resource_path('public-api-contract.md') : resource_path('developer-docs/'.$slug.'.md');
        $markdown = file_get_contents($path);
        if ($slug === 'examples') {
            $markdown = str_replace(['{{PYTHON_EXAMPLE}}', '{{CURL_EXAMPLE}}'], [file_get_contents(resource_path('developer-docs/workflow.py')), file_get_contents(resource_path('developer-docs/workflow.sh'))], $markdown);
        }
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return ['title' => self::PAGES[$slug], 'slug' => $slug, 'markdown' => $markdown, 'html' => str_replace('<pre>', '<pre tabindex="0">', $html), 'version' => json_decode(file_get_contents(resource_path('openapi.json')), true)['info']['version']];
    }
}
