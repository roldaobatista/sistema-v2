<?php

namespace App\PHPStan\Rules;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<MethodCall>
 */
class PaginateInsteadOfGetInControllersRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();

        // Only apply this rule inside Controllers
        if ($classReflection === null) {
            return [];
        }

        if (! str_contains($classReflection->getName(), '\\Http\\Controllers\\')) {
            return [];
        }

        if (! $this->isListEndpoint($scope)) {
            return [];
        }

        // Check if the method being called is get() or all()
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->toString();

        if (! in_array($methodName, ['get', 'all'], true)) {
            return [];
        }

        if (! $this->isQueryBuilderCall($node, $scope)) {
            return [];
        }

        if ($this->hasBoundedResultSet($node->var)) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Listagens index* em Controllers não devem finalizar queries com ->get() ou ->all(). Use ->paginate() ou ->simplePaginate() para evitar memory leaks (LEI 3b).')
                ->identifier('kalibrium.paginateRequired')
                ->build(),
        ];
    }

    private function isListEndpoint(Scope $scope): bool
    {
        $functionName = $scope->getFunctionName();

        if ($functionName === null) {
            return false;
        }

        $methodName = str_contains($functionName, '::')
            ? substr($functionName, (int) strrpos($functionName, '::') + 2)
            : $functionName;

        return str_starts_with($methodName, 'index');
    }

    private function isQueryBuilderCall(MethodCall $node, Scope $scope): bool
    {
        foreach ($scope->getType($node->var)->getObjectClassNames() as $className) {
            if ($className === EloquentBuilder::class || $className === QueryBuilder::class) {
                return true;
            }

            if (str_starts_with($className, 'Illuminate\\Database\\Eloquent\\Relations\\')
                || str_starts_with($className, 'Illuminate\\Database\\Eloquent\\Builder')
                || str_starts_with($className, 'Illuminate\\Database\\Query\\Builder')) {
                return true;
            }
        }

        return false;
    }

    private function hasBoundedResultSet(Node $node): bool
    {
        if (! $node instanceof MethodCall) {
            return false;
        }

        if ($node->name instanceof Identifier && in_array($node->name->toString(), ['limit', 'take', 'forPage'], true)) {
            return true;
        }

        return $this->hasBoundedResultSet($node->var);
    }
}
