<?php

namespace App\PHPStan\Rules;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Ensures Model queries in Controllers include tenant_id filtering.
 * Detects direct Model::where() chains that do not include tenant_id.
 *
 * @implements Rule<StaticCall>
 */
class TenantIdInQueriesRule implements Rule
{
    /** Models that are exempt from tenant_id requirement (global models) */
    private const EXEMPT_MODELS = [
        'User',
        'Tenant',
        'Permission',
        'Role',
        'SaasPlan',
        'PersonalAccessToken',
        'PasswordResetToken',
    ];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();

        // Only apply inside Controllers
        if ($classReflection === null) {
            return [];
        }

        if (! str_contains($classReflection->getName(), '\\Http\\Controllers\\')) {
            return [];
        }

        // Check if it's a static call to a Model (e.g., Model::where(), Model::query())
        if (! $node->class instanceof Name) {
            return [];
        }

        $className = $scope->resolveName($node->class);

        // Skip exempt models
        $shortName = class_basename($className);
        if (in_array($shortName, self::EXEMPT_MODELS, true)) {
            return [];
        }

        if (! $this->reflectionProvider->hasClass($className)) {
            return [];
        }

        $modelReflection = $this->reflectionProvider->getClass($className);

        if (! $modelReflection->isSubclassOf(Model::class)) {
            return [];
        }

        if ($this->usesBelongsToTenant($modelReflection)) {
            return [];
        }

        if (! $node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->toString();

        // Only flag query-starting methods
        $queryMethods = ['where', 'query', 'all', 'get', 'first', 'find', 'findOrFail'];
        if (! in_array($methodName, $queryMethods, true)) {
            return [];
        }

        // Check if any argument in the call contains 'tenant_id'
        foreach ($node->getArgs() as $arg) {
            if ($this->containsTenantId($arg->value)) {
                return [];
            }
        }

        // For Model::query() or Model::where() without tenant_id, emit a tip
        return [
            RuleErrorBuilder::message(
                "Query direta em {$shortName}::{$methodName}() sem filtro de tenant_id. "
                .'Certifique-se de que o Model usa BelongsToTenant trait ou adicione tenant_id explicitamente (LEI 3b).'
            )
                ->identifier('kalibrium.tenantIdRequired')
                ->tip('Models com BelongsToTenant já são ignorados por esta regra; para models globais, adicione EXEMPT_MODELS.')
                ->build(),
        ];
    }

    private function usesBelongsToTenant(ClassReflection $classReflection): bool
    {
        foreach ($classReflection->getTraits() as $trait) {
            if ($trait->getName() === BelongsToTenant::class || $this->usesBelongsToTenant($trait)) {
                return true;
            }
        }

        return false;
    }

    private function containsTenantId(Node $node): bool
    {
        if ($node instanceof String_ && $node->value === 'tenant_id') {
            return true;
        }

        foreach ($node->getSubNodeNames() as $name) {
            $subNode = $node->{$name};
            if ($subNode instanceof Node && $this->containsTenantId($subNode)) {
                return true;
            }
            if (is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof Node && $this->containsTenantId($item)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
