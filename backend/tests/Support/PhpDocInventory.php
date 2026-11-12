<?php

namespace Tests\Support;

/**
 * 以PHP tokenizer盤點自有後端的Class／Method PHPDoc，對照實際簽章找出缺漏或不一致；不執行原始碼、不bootstrap Laravel。
 */
class PhpDocInventory
{
    /**
     * 盤點單一檔案。
     *
     * @param  string  $path  相對路徑
     * @param  string  $source  原始碼
     * @return array{path:string,classes:int,methods:int,issues:list<array{line:int,symbol:string,kind:string}>} 檔案結果
     */
    public function file(string $path, string $source): array
    {
        $tokens = token_get_all($source);
        $count = count($tokens);
        $issues = [];
        $classes = 0;
        $methods = 0;
        $depth = 0;
        $classDepth = [];
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;

                continue;
            }
            if ($token === '}') {
                $depth--;
                if ($classDepth && end($classDepth) === $depth) {
                    array_pop($classDepth);
                }

                continue;
            }
            if (! is_array($token)) {
                continue;
            }
            if (in_array($token[0], [T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM], true)) {
                $name = $this->nextName($tokens, $i);
                if ($name === null || $this->previousSignificant($tokens, $i) === T_DOUBLE_COLON) {
                    continue;
                }
                $anonymous = $name === '';
                $classes++;
                if (! $anonymous && $this->docBefore($tokens, $i) === null) {
                    $issues[] = ['line' => $token[2], 'symbol' => $name, 'kind' => 'class-missing'];
                }
                $classDepth[] = $depth;

                continue;
            }
            if ($token[0] === T_FUNCTION && $classDepth && end($classDepth) === $depth - 1) {
                $name = $this->nextName($tokens, $i);
                if ($name === null || $name === '') {
                    continue;
                }
                $methods++;
                $doc = $this->docBefore($tokens, $i);
                $params = $this->parameters($tokens, $i);
                $line = $token[2];
                if ($doc === null) {
                    $issues[] = ['line' => $line, 'symbol' => $name, 'kind' => 'method-missing'];

                    continue;
                }
                preg_match_all('/@param\s+[^\s$][^$\n]*?\s(&?\.\.\.)?\$(\w+)/', $doc, $documented);
                $names = $documented[2];
                if (preg_match('/@param\s+\$\w+/', $doc)) {
                    $issues[] = ['line' => $line, 'symbol' => $name, 'kind' => 'param-missing-type'];
                }
                $missing = array_diff($params, $names);
                $extra = array_diff($names, $params);
                if ($missing) {
                    $issues[] = ['line' => $line, 'symbol' => $name.'('.implode(',', $missing).')', 'kind' => 'param-undocumented'];
                }
                if ($extra) {
                    $issues[] = ['line' => $line, 'symbol' => $name.'('.implode(',', $extra).')', 'kind' => 'param-stale'];
                }
                if ($name !== '__construct' && ! str_contains($doc, '@return')) {
                    $issues[] = ['line' => $line, 'symbol' => $name, 'kind' => 'return-missing'];
                }
                if (! preg_match('/\/\*\*\s*\n?\s*\*?\s*[^@\s*]/u', $doc)) {
                    $issues[] = ['line' => $line, 'symbol' => $name, 'kind' => 'summary-missing'];
                }
            }
        }

        return ['path' => $path, 'classes' => $classes, 'methods' => $methods, 'issues' => $issues];
    }

    /**
     * 取得class／function關鍵字後的名稱；匿名class回空字串，無法判斷時回null。
     *
     * @param  array<int,mixed>  $tokens  token串
     * @param  int  $index  關鍵字位置
     * @return string|null 名稱
     */
    private function nextName(array $tokens, int $index): ?string
    {
        for ($j = $index + 1, $n = count($tokens); $j < $n; $j++) {
            $token = $tokens[$j];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($token === '&') {
                continue;
            }
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }

            return in_array($token, ['(', '{'], true) || (is_array($token) && in_array($token[0], [T_EXTENDS, T_IMPLEMENTS], true)) ? '' : null;
        }

        return null;
    }

    /**
     * 前一個有意義token的種類。
     *
     * @param  array<int,mixed>  $tokens  token串
     * @param  int  $index  目前位置
     * @return int|string|null token種類或字元
     */
    private function previousSignificant(array $tokens, int $index): int|string|null
    {
        for ($j = $index - 1; $j >= 0; $j--) {
            $token = $tokens[$j];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT], true)) {
                continue;
            }

            return is_array($token) ? $token[0] : $token;
        }

        return null;
    }

    /**
     * 往前跳過修飾字、attribute與空白，取得緊鄰的docblock。
     *
     * @param  array<int,mixed>  $tokens  token串
     * @param  int  $index  class／function關鍵字位置
     * @return string|null docblock原文
     */
    private function docBefore(array $tokens, int $index): ?string
    {
        $skip = [T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL, T_READONLY, T_COMMENT];
        $attribute = 0;
        for ($j = $index - 1; $j >= 0; $j--) {
            $token = $tokens[$j];
            if ($token === ']') {
                $attribute++;

                continue;
            }
            if ($attribute > 0) {
                if ($token === '[' || (is_array($token) && $token[0] === T_ATTRIBUTE)) {
                    $attribute--;
                }

                continue;
            }
            if (is_array($token) && in_array($token[0], $skip, true)) {
                continue;
            }

            return is_array($token) && $token[0] === T_DOC_COMMENT ? $token[1] : null;
        }

        return null;
    }

    /**
     * 讀出function簽章第一層括號內的參數名稱。
     *
     * @param  array<int,mixed>  $tokens  token串
     * @param  int  $index  function關鍵字位置
     * @return list<string> 參數名稱（不含$）
     */
    private function parameters(array $tokens, int $index): array
    {
        $names = [];
        $depth = 0;
        for ($j = $index + 1, $n = count($tokens); $j < $n; $j++) {
            $token = $tokens[$j];
            if ($token === '(') {
                $depth++;

                continue;
            }
            if ($token === ')') {
                if (--$depth === 0) {
                    break;
                }

                continue;
            }
            if ($depth === 1 && is_array($token) && $token[0] === T_VARIABLE) {
                $names[] = substr($token[1], 1);
            }
        }

        return $names;
    }
}
