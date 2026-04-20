<?php

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * qa-05 (Re-auditoria Camada 1 r3): smoke mecânico que garante que TODO
 * model com `use HasFactory` possui uma factory invocável.
 *
 * Previne regressões silenciosas onde um model ganha `HasFactory` mas nunca
 * gera factory (testes que chamam `Model::factory()->create()` quebram em
 * tempo de execução, não de compile). Arch test detecta no ato.
 *
 * Excluídos: models abstratos ou pivots legítimos sem factory próprio,
 * e a whitelist de models listados em `docs/TECHNICAL-DECISIONS.md §14.26`
 * (40 models em backlog de criação de factory, aceito como limitação
 * documentada — novos models fora dessa lista DEVEM ter factory).
 */

/**
 * Whitelist de models com HasFactory mas sem Factory class — backlog de
 * criação aceito em TECHNICAL-DECISIONS.md §14.26. Adições/reduções nesta
 * lista exigem atualização de §14.26 no mesmo commit.
 */
const QA05_FACTORY_BACKLOG_WHITELIST = [
    'AccountPayablePayment',
    'AccountPlan',
    'AccountPlanAction',
    'Admission',
    'AgendaItemComment',
    'AgendaItemHistory',
    'CalibrationDecisionLog',
    'ClientPortalUser',
    'Commitment',
    'ContactPolicy',
    'ContinuousFeedback',
    'CrmDealStageHistory',
    'CrmFunnelAutomation',
    'CustomerRfmScore',
    'EmailActivity',
    'EmailNote',
    'EmailSignature',
    'EmailTag',
    'EmailTemplate',
    'EmployeeDependent',
    'ESocialRubric',
    'EspelhoConfirmation',
    'FuelingLog',
    'GamificationBadge',
    'GamificationScore',
    'ImportantDate',
    'MarketingIntegration',
    'NpsResponse',
    'PaymentGatewayConfig',
    'PortalGuestLink',
    'QuickNote',
    'QuotePhoto',
    'ReconciliationRule',
    'SealApplication',
    'SkillRequirement',
    'TechnicianFeedback',
    'VisitReport',
    'WorkOrderRecurrence',
    'WorkOrderSignature',
    'WorkOrderTimeLog',
];
test('todos os models com HasFactory possuem factory declarada e invocável', function (): void {
    $appPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Models';

    expect(is_dir($appPath))->toBeTrue("app/Models não existe em {$appPath}");

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appPath, FilesystemIterator::SKIP_DOTS)
    );

    $violations = [];
    $checked = 0;

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace(
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR,
            '',
            $file->getPathname()
        );

        // Resolver nome de classe a partir do namespace.
        $source = file_get_contents($file->getPathname());
        if ($source === false
            || ! preg_match('/namespace\s+([^;]+);/', $source, $nsMatch)
            || ! preg_match('/class\s+([A-Za-z_][A-Za-z0-9_]*)/', $source, $clsMatch)
        ) {
            continue;
        }

        $fqcn = $nsMatch[1].'\\'.$clsMatch[1];

        if (! class_exists($fqcn)) {
            continue;
        }

        $reflection = new ReflectionClass($fqcn);

        // Só analisa models concretos, não abstratos, que usam HasFactory.
        if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
            continue;
        }

        $traits = class_uses_recursive($fqcn);
        if (! in_array(HasFactory::class, $traits, true)) {
            continue;
        }

        // Whitelist documentada §14.26: backlog aceito como limitação.
        if (in_array($clsMatch[1], QA05_FACTORY_BACKLOG_WHITELIST, true)) {
            continue;
        }

        $checked++;

        // Escopo do qa-05: garantir que a classe Factory existe e é resolvível.
        // NÃO testa ->make()/->create() aqui porque isso disparaia a cadeia
        // completa de Faker + factories relacionadas (fora do escopo deste
        // smoke). Bugs em factories individuais são pegos pelos testes que
        // efetivamente as utilizam.
        try {
            $factory = $fqcn::factory();

            if (! $factory instanceof Factory) {
                $violations[] = "{$relative}: ::factory() retornou instância não-Factory";
            }
        } catch (Throwable $e) {
            $violations[] = "{$relative}: ::factory() não resolveu — {$e->getMessage()}";
        }
    }

    expect($violations)->toBe(
        [],
        "Factories inválidas ou ausentes em models com HasFactory:\n - ".implode("\n - ", $violations)
    );

    // Smoke sanity: o teste tem que ter encontrado ao menos 1 model.
    expect($checked)->toBeGreaterThan(0, 'nenhum model com HasFactory encontrado — teste provavelmente não escaneou o diretório correto');
});
