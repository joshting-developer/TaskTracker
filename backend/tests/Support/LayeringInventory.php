<?php

namespace Tests\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/** 以既有PHP Parser AST檢查分層；辨識import別名與方法輸入，不靠Model import字串計數。 */
class LayeringInventory
{
    /**
     * 掃描Controller或Service的實際呼叫節點與穩定依賴參數。
     *
     * @param  string  $source  PHP原始碼
     * @param  bool  $controller  是否為HTTP Controller
     * @return list<string> 具行號的違規原因
     */
    public function inspect(string $source, bool $controller): array
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $ast = $traverser->traverse($ast);
        $finder = new NodeFinder;
        $issues = [];
        foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class) as $method) {
            foreach ($method->params as $param) {
                $type = $param->type instanceof Node\Name ? $param->type->toString() : '';
                if ($controller && str_starts_with($type, 'App\\Repositories\\')) {
                    $issues[] = $param->getStartLine().': controller-repository';
                }
                if ($controller && $method->name->toString() !== '__construct' && str_starts_with($type, 'App\\') && ! str_starts_with($type, 'App\\Http\\Requests\\') && ! str_starts_with($type, 'App\\Models\\') && ! str_starts_with($type, 'App\\Data\\')) {
                    $issues[] = $param->getStartLine().': action-service-injection';
                }
            }
        }
        foreach ($finder->findInstanceOf($ast, Node\Expr::class) as $node) {
            $rule = null;
            if ($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name) {
                $class = $node->class->toString();
                $name = $node->name instanceof Node\Identifier ? $node->name->toString() : '';
                if (str_starts_with($class, 'App\\Models\\')) {
                    $rule = 'model-static-io';
                }
                if ($class === 'Illuminate\\Support\\Facades\\DB' && in_array($name, ['table', 'select', 'selectOne', 'statement', 'insert', 'update', 'delete', 'unprepared'], true)) {
                    $rule = 'direct-sql';
                }
            }
            if ($node instanceof Node\Expr\New_ && $node->class instanceof Node\Name) {
                $class = $node->class->toString();
                if (str_starts_with($class, 'App\\Models\\') || str_starts_with($class, 'App\\Repositories\\') || str_starts_with($class, 'App\\Services\\')) {
                    $rule = 'manual-collaborator';
                }
            }
            if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name && in_array($node->name->toString(), ['app', 'resolve'], true)) {
                $rule = 'service-locator';
            }
            if (($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall) && $node->name instanceof Node\Identifier && in_array($node->name->toString(), ['save', 'saveQuietly', 'refresh', 'fresh', 'load', 'loadMissing'], true)) {
                // 明確constructor-injected Repository呼叫可保存；局部模型／未知scope不得自行發I/O。
                $receiver = $node->var;
                $allowed = false;
                if ($receiver instanceof Node\Expr\PropertyFetch && $receiver->var instanceof Node\Expr\Variable && $receiver->var->name === 'this' && $receiver->name instanceof Node\Identifier) {
                    foreach ($finder->findInstanceOf($ast, Node\Param::class) as $param) {
                        if ($param->var->name === $receiver->name->toString() && $param->type instanceof Node\Name && str_starts_with($param->type->toString(), 'App\\Repositories\\')) {
                            $allowed = true;
                        }
                    }
                }
                if (! $allowed) {
                    $rule = 'model-instance-io';
                }
            }
            if ($rule) {
                $issues[] = $node->getStartLine().': '.$rule;
            }
        }

        return array_values(array_unique($issues));
    }
}
