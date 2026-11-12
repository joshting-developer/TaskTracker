<?php

namespace Tests\Support;

/** Token層靜態盤點：忽略字串／註解，列出建構、定位器及建構子型別邊；不是PHP型別分析器。 */
final class DependencyInventory
{
    /**
     * 盤點單檔所有位置，保留無依賴檔案作為覆蓋率分母。
     *
     * @param  string  $path  相對backend路徑
     * @param  string  $source  自有PHP內容，不含vendor／秘密設定檔
     * @return array 檔案、類別、建構子參數及逐位置依賴清單
     */
    public function file(string $path, string $source): array
    {
        $tokens = array_values(array_filter(token_get_all($source), fn ($t) => ! is_array($t) || ! in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        $namespace = '';
        $imports = [];
        $class = '';
        $sites = [];
        $parameters = [];
        $limitations = [];
        $nameTokens = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];
        $text = static fn ($t) => is_array($t) ? $t[1] : $t;
        foreach ($tokens as $i => $token) {
            if (! is_array($token)) {
                continue;
            }
            [$kind, $value, $line] = $token;
            if ($kind === T_NAMESPACE) {
                $namespace = $text($tokens[$i + 1]);
            }
            if ($kind === T_USE && $class === '' && ($tokens[$i + 1] ?? '') !== '(') {
                $parts = [];
                for ($n = $i + 1; isset($tokens[$n]) && $tokens[$n] !== ';'; $n++) {
                    $parts[] = $text($tokens[$n]);
                }
                $use = implode(' ', $parts);
                if (str_contains($use, '{')) {
                    $limitations[] = 'grouped import needs manual type resolution at line '.$line;
                }
                if (! str_contains($use, '{') && ! str_starts_with($use, 'function ') && ! str_starts_with($use, 'const ')) {
                    foreach (explode(',', $use) as $item) {
                        $pair = preg_split('/\s+as\s+/i', trim($item));
                        $name = trim($pair[0], ' \\');
                        $imports[$pair[1] ?? substr($name, (int) strrpos('\\'.$name, '\\'))] = $name;
                    }
                }
            }
            if (in_array($kind, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && $class === '' && is_array($tokens[$i + 1] ?? null) && $tokens[$i + 1][0] === T_STRING && $text($tokens[$i - 1] ?? '') !== '::') {
                $class = ($namespace === '' ? '' : $namespace.'\\').$tokens[$i + 1][1];
            }
            if ($kind === T_NEW) {
                $next = $tokens[$i + 1] ?? '';
                $raw = $text($next);
                $target = is_array($next) && (in_array($next[0], $nameTokens, true) || $next[0] === T_STATIC)
                    ? $this->name($raw, $namespace, $imports, $class) : '(dynamic/anonymous)';
                $sites[] = ['line' => $line, 'kind' => 'new', 'target' => $target, 'decision' => $this->decision($path, $target)];
            }
            $previous = $text($tokens[$i - 1] ?? '');
            $global = in_array($kind, $nameTokens, true) && in_array(ltrim($value, '\\'), ['app', 'resolve'], true) && ($tokens[$i + 1] ?? '') === '(' && ! in_array($previous, ['->', '?->', '::', 'function', 'new'], true);
            $containerMethod = $kind === T_STRING && in_array($value, ['make', 'resolve', 'bind', 'singleton', 'scoped', 'instance'], true) && $previous === '->' && in_array($text($tokens[$i - 2] ?? ''), ['$app', 'app'], true);
            if ($global || $containerMethod) {
                $arg = $tokens[$i + 2] ?? '';
                $target = is_array($arg) && in_array($arg[0], $nameTokens, true) ? $this->name($arg[1], $namespace, $imports, $class) : '(binding/expression)';
                $sites[] = ['line' => $line, 'kind' => $containerMethod ? 'container.'.$value : ltrim($value, '\\'), 'target' => $target, 'decision' => $this->decision($path, $target, true)];
            }
            if ($kind === T_FUNCTION && strtolower($text($tokens[$i + 1] ?? '')) === '__construct') {
                $depth = 0;
                $prefix = [];
                $seenVariable = false;
                for ($n = $i + 2; isset($tokens[$n]); $n++) {
                    $part = $tokens[$n];
                    $word = $text($part);
                    if ($word === '(') {
                        $depth++;
                    } elseif ($word === ')') {
                        if (--$depth === 0) {
                            break;
                        }
                    } elseif ($word === ',' && $depth === 1) {
                        $prefix = [];
                        $seenVariable = false;
                    } elseif (is_array($part) && $part[0] === T_VARIABLE && ! $seenVariable) {
                        $parameters[] = ['name' => $word, 'types' => $prefix];
                        $seenVariable = true;
                    } elseif (! $seenVariable && is_array($part) && in_array($part[0], [...$nameTokens, T_ARRAY, T_CALLABLE], true)) {
                        $prefix[] = $this->name($word, $namespace, $imports, $class);
                    }
                }
            }
        }

        return ['path' => $path, 'class' => $class, 'constructorParameters' => $parameters, 'sites' => $sites, 'limitations' => $limitations];
    }

    /**
     * 建構子顯式型別的自有類別依賴圖；不假裝推導動態factory／method injection。
     *
     * @param  array  $files  逐檔token結果
     * @return array{edges:array,cycles:array} 靜態建構子圖與回邊循環
     */
    public function graph(array $files): array
    {
        $classes = array_fill_keys(array_filter(array_column($files, 'class')), true);
        $edges = [];
        foreach ($files as $file) {
            if ($file['class'] === '') {
                continue;
            }
            $edges[$file['class']] ??= [];
            foreach ($file['constructorParameters'] as $parameter) {
                foreach ($parameter['types'] as $type) {
                    if (isset($classes[$type])) {
                        $edges[$file['class']][] = $type;
                    }
                }
            }
            $edges[$file['class']] = array_values(array_unique($edges[$file['class']]));
            sort($edges[$file['class']]);
        }
        ksort($edges);
        $visited = [];
        $active = [];
        $cycles = [];
        $visit = function ($node, $path) use (&$visit, &$visited, &$active, &$cycles, $edges) {
            if (isset($active[$node])) {
                $cycles[] = [...array_slice($path, array_search($node, $path, true)), $node];

                return;
            }
            if (isset($visited[$node])) {
                return;
            }
            $active[$node] = true;
            foreach ($edges[$node] ?? [] as $target) {
                $visit($target, [...$path, $node]);
            }
            unset($active[$node]);
            $visited[$node] = true;
        };
        foreach (array_keys($edges) as $node) {
            $visit($node, []);
        }

        return ['edges' => $edges, 'cycles' => $cycles];
    }

    /**
     * 解析目前專案常用的namespace／import alias，native型別維持原值。
     *
     * @param  string  $name  token中的名稱
     * @param  string  $namespace  當前namespace
     * @param  array<string,string>  $imports  類別import映射
     * @param  string  $class  當前第一個具名類別
     * @return string 正規化名稱；動態呼叫仍另列人工檢視
     */
    private function name(string $name, string $namespace, array $imports, string $class): string
    {
        if (in_array($name, ['string', 'int', 'bool', 'float', 'array', 'callable', 'mixed', 'object', 'iterable', 'null'], true)) {
            return $name;
        }
        if (in_array($name, ['self', 'static'], true)) {
            return $class ?: '(self)';
        }
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }
        if (str_starts_with($name, 'namespace\\')) {
            return ($namespace === '' ? '' : $namespace.'\\').substr($name, 10);
        }
        $head = explode('\\', $name)[0];
        if (isset($imports[$head])) {
            return $imports[$head].substr($name, strlen($head));
        }

        return $namespace === '' ? $name : $namespace.'\\'.$name;
    }

    /**
     * 自動分類只決定審查責任，exception-candidate仍須人工確認，不等於全部DI合規。
     *
     * @param  string  $path  目前檔案
     * @param  string  $target  解析的相依
     * @param  bool  $locator  是否容器解析／註冊
     * @return string 待遷移或合理例外候選
     */
    private function decision(string $path, string $target, bool $locator = false): string
    {
        if (str_starts_with($path, 'tests/') || str_starts_with($path, 'tools/')) {
            return 'test-tool-exception-candidate';
        }
        if (str_starts_with($path, 'app/Factories/') || str_starts_with($path, 'app/Providers/') || str_starts_with($path, 'bootstrap/') || str_starts_with($path, 'docker/')) {
            return 'composition-root-exception-candidate';
        }
        if ($target === 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport') {
            return 'needs-di-migration-review';
        }
        if (! $locator && (str_starts_with($target, 'App\\Events\\') || str_starts_with($target, 'App\\Data\\') || str_starts_with($target, 'App\\Models\\') || str_starts_with($target, 'App\\Http\\Resources\\') || $target === 'App\\Domain\\DomainError' || ! str_starts_with($target, 'App\\'))) {
            return 'value-framework-dynamic-review';
        }

        return 'needs-di-migration-review';
    }
}
