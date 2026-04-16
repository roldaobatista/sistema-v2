import { execFileSync, spawnSync } from 'node:child_process';
import { cpSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import test from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(fileURLToPath(new URL('..', import.meta.url)));
const CLI = join(ROOT, 'scripts', 'harness-cycle.mjs');
const COMMAND_PROVENANCE_ARGS = [
  '--actor-role',
  'verifier',
  '--agent-id',
  'verifier-smoke',
  '--review-round',
  '1',
  '--context-fingerprint',
  'test-context',
  '--source-bundle-hash',
  'test-bundle',
];

test('aceita help como comando inicial', () => {
  const root = createHarnessRoot();
  try {
    const help = runCli(root, ['--help']);

    assert.equal(help.status, 0);
    assert.match(help.stdout, /Uso:/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('documentacao de deploy nao usa $env:USERPROFILE em comandos copiaveis', () => {
  for (const file of ['AGENTS.md', 'deploy/DEPLOY-HETZNER.md', 'docs/harness/autonomous-orchestrator.md']) {
    const content = readFileSync(join(ROOT, file), 'utf8');

    assert.doesNotMatch(content, /\$env:USERPROFILE/i, file);
  }
});

test('bloqueia aprovacao com artefatos fora do schema formal', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithInvalidSchema(root, runId);
    recordPassedCommand(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /harness_output_invalid|schema/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('aprova ciclo com artefatos dentro do schema formal', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    recordPassedCommand(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved']);

    assert.equal(close.status, 0);
    assert.match(close.stdout, /approved/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('generate-impact registra manifesto de impacto com superficies afetadas', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    mkdirSync(join(root, 'backend', 'routes'), { recursive: true });
    writeFileSync(join(root, 'backend', 'routes', 'api.php'), '<?php\n');

    const generated = runCli(root, ['generate-impact', '--run-id', runId]);
    const manifest = JSON.parse(readFileSync(join(root, 'docs', 'harness', 'runs', runId, 'impact-manifest.json'), 'utf8'));

    assert.equal(generated.status, 0);
    assert.equal(manifest.run_id, runId);
    assert.ok(manifest.changed_files.includes('backend/routes/api.php'));
    assert.ok(manifest.surfaces.routes.includes('backend/routes/api.php'));
    assert.ok(manifest.risk_surfaces.includes('api-contract'));
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao sem manifesto de impacto atualizado', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    recordPassedCommand(root, runId);
    rmSync(join(root, 'docs', 'harness', 'runs', runId, 'impact-manifest.json'), { force: true });
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /impact-manifest|impacto/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao quando auditor nao declara cegueiras e premissas', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    const auditorPath = join(root, 'docs', 'harness', 'runs', runId, 'auditor-code-quality.json');
    const output = JSON.parse(readFileSync(auditorPath, 'utf8'));
    delete output.audit_limitations;
    writeFileSync(auditorPath, `${JSON.stringify(output, null, 2)}\n`);
    recordPassedCommand(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /audit_limitations|cegueiras|premissas/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao quando auditor declara verificacao obrigatoria nao executada', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    const auditorPath = join(root, 'docs', 'harness', 'runs', runId, 'auditor-tests-verification.json');
    const output = JSON.parse(readFileSync(auditorPath, 'utf8'));
    output.audit_limitations = output.audit_limitations ?? {
      not_inspected: [],
      assumptions: [],
      required_verifications_not_executed: [],
    };
    output.audit_limitations.required_verifications_not_executed = ['rerun Playwright afetado para flakiness'];
    writeFileSync(auditorPath, `${JSON.stringify(output, null, 2)}\n`);
    recordPassedCommand(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /verificacoes obrigatorias nao executadas|required_verifications_not_executed/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao quando consolidado nao declara manifesto de impacto usado', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    const consolidatedPath = join(root, 'docs', 'harness', 'runs', runId, 'consolidated-findings.json');
    const consolidated = JSON.parse(readFileSync(consolidatedPath, 'utf8'));
    consolidated.audit_coverage.impact_manifest_present = false;
    writeFileSync(consolidatedPath, `${JSON.stringify(consolidated, null, 2)}\n`);
    recordPassedCommand(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /impact_manifest_present|manifesto de impacto/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao quando audit_coverage nao corresponde aos agent_ids dos auditores', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    const consolidatedPath = join(root, 'docs', 'harness', 'runs', runId, 'consolidated-findings.json');
    const consolidated = JSON.parse(readFileSync(consolidatedPath, 'utf8'));
    consolidated.audit_coverage.distinct_agent_ids = ['fake-1', 'fake-2', 'fake-3', 'fake-4', 'fake-5'];
    writeFileSync(consolidatedPath, `${JSON.stringify(consolidated, null, 2)}\n`);
    recordPassedCommand(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /distinct_agent_ids|agent_id/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('record-command registra fingerprint de ambiente e hash de saida', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);

    recordPassedCommand(root, runId, 'node --version');
    const [entry] = readFileSync(join(root, 'docs', 'harness', 'runs', runId, 'commands.log.jsonl'), 'utf8')
      .trim()
      .split(/\r?\n/)
      .map((line) => JSON.parse(line));

    assert.equal(entry.environment.node_version, process.version);
    assert.equal(entry.environment.platform, process.platform);
    assert.match(entry.output_hash, /^sha256:[0-9a-f]{64}$/);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao de camada fora do modo full', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'targeted']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    recordPassedCommand(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /audit_mode=full|cinco auditores/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao quando falta um dos cinco auditores obrigatorios', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    rmSync(join(root, 'docs', 'harness', 'runs', runId, 'auditor-security-tenant.json'));
    recordPassedCommand(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /cinco auditores|ausente|auditor-security-tenant/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao quando auditor nao tem agente unico e contexto limpo', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    const auditorPath = join(root, 'docs', 'harness', 'runs', runId, 'auditor-security-tenant.json');
    const output = JSON.parse(readFileSync(auditorPath, 'utf8'));
    output.agent_provenance.agent_id = 'auditor-1';
    writeFileSync(auditorPath, `${JSON.stringify(output, null, 2)}\n`);
    recordPassedCommand(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /agent_id unico|auditores/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao quando consolidado nao corresponde ao modo e rodada ativos', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    const consolidatedPath = join(root, 'docs', 'harness', 'runs', runId, 'consolidated-findings.json');
    const consolidated = JSON.parse(readFileSync(consolidatedPath, 'utf8'));
    consolidated.audit_mode = 'targeted';
    consolidated.review_round = 2;
    writeFileSync(consolidatedPath, `${JSON.stringify(consolidated, null, 2)}\n`);
    recordPassedCommand(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /audit_mode|rodada|quorum/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao apos correcao sem fixer_agent_id separado', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    const baseCommit = execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim();
    writeFileSync(join(root, 'fix.txt'), 'fixed\n');
    execFileSync('git', ['add', 'fix.txt'], { cwd: root });
    execFileSync('git', ['commit', '-m', 'apply fixer change', '--quiet'], { cwd: root });
    const headCommit = execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim();
    writeApprovedArtifactsWithValidSchema(root, runId, { baseCommit, headCommit });
    recordPassedCommand(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /fixer_agent_id|correcao|orquestrador/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao quando artefatos aprovados apontam para HEAD antigo', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    recordPassedCommand(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);
    writeFileSync(join(root, 'change.txt'), 'changed\n');
    execFileSync('git', ['add', 'change.txt'], { cwd: root });
    execFileSync('git', ['commit', '-m', 'change head after audit', '--quiet'], { cwd: root });

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /provenance|proveniencia|HEAD|Git/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao quando commands.log pertence a outra run camada ou ciclo', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);
    writeCommandLogEntry(root, runId, {
      schema_version: 1,
      run_id: 'WRONG-RUN-ID',
      audited_layer: 7,
      cycle: 99,
      command: 'node --version',
      cwd: root,
      started_at: new Date().toISOString(),
      finished_at: new Date().toISOString(),
      exit_code: 0,
      status: 'passed',
      stdout_excerpt: null,
      stderr_excerpt: null,
      replacement_for: null,
      original_command: 'node --version',
      effective_command: 'node --version',
      waiver_basis: null,
      canonical_basis: 'smoke',
      approved_by: null,
      justification: null,
      essential_for_approval: true,
    });

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /commands\.log|run|camada|ciclo|mismatch/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia comando essencial de aprovacao atribuido ao orquestrador', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    writeCommandLogEntry(root, runId, {
      schema_version: 1,
      run_id: runId,
      audited_layer: 0,
      cycle: 1,
      command: 'node --version',
      cwd: root,
      started_at: new Date().toISOString(),
      finished_at: new Date().toISOString(),
      exit_code: 0,
      status: 'passed',
      stdout_excerpt: null,
      stderr_excerpt: null,
      replacement_for: null,
      original_command: 'node --version',
      effective_command: 'node --version',
      waiver_basis: null,
      canonical_basis: 'smoke',
      approved_by: null,
      justification: null,
      essential_for_approval: true,
      actor_role: 'orchestrator',
      agent_id: 'orchestrator',
    });
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /orquestrador|comando essencial/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao fora da fase verification', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    recordPassedCommand(root, runId);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /verification|verificacao|fase/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia aprovacao com worktree sujo fora dos artefatos runtime do harness', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    recordPassedCommand(root, runId);
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);
    writeFileSync(join(root, 'outside-runtime.txt'), 'dirty\n');

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /workspace sujo|outside-runtime\.txt|runtime/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia deploy com migrate quando migrations nao estao autorizadas', () => {
  const context = createDeployHarnessRoot();
  const { root, head } = context;
  try {
    const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    const authorize = runCli(root, [
      'authorize-deploy',
      '--run-id',
      runId,
      '--target',
      'production',
      '--commit',
      head,
      '--allow-migrations',
      'false',
      '--migration-diff-checked',
      'true',
      '--deploy-command',
      'bash deploy.sh --migrate',
    ], { allowFailure: true });
    const validate = runCli(root, ['validate']);

    assert.notEqual(authorize.status, 0);
    assert.match(authorize.stderr, /migration|migrate/i);
    assert.equal(validate.status, 0);
  } finally {
    removeDeployHarnessRoot(context);
  }
});

test('bloqueia deploy com migrate=true quando migrations nao estao autorizadas', () => {
  const context = createDeployHarnessRoot();
  const { root, head } = context;
  try {
    const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    const authorize = runCli(root, [
      'authorize-deploy',
      '--run-id',
      runId,
      '--target',
      'production',
      '--commit',
      head,
      '--allow-migrations',
      'false',
      '--migration-diff-checked',
      'true',
      '--deploy-command',
      'bash deploy.sh --migrate=true',
    ], { allowFailure: true });
    const validate = runCli(root, ['validate']);

    assert.notEqual(authorize.status, 0);
    assert.match(authorize.stderr, /migration|migrate/i);
    assert.equal(validate.status, 0);
  } finally {
    removeDeployHarnessRoot(context);
  }
});

test('bloqueia deploy com migrate shell-quoted quando migrations nao estao autorizadas', () => {
  for (const command of [
    'bash deploy.sh "--migrate"',
    "bash deploy.sh '--migrate'",
    'php artisan "migrate" --force',
    'bash deploy.sh --migrate\\=true',
    'php artisan mi"gr"ate --force',
    'bash deploy.sh --migr"ate"=true',
    "php artisan migr'ate':fresh",
    'php artisan mi\\grate --force',
    'bash deploy.sh --migr\\ate=true',
    'php artisan migr\\ate:fresh',
    'bash -lc "php artisan mi\\grate --force"',
    "bash -lc \"php artisan $'migrate' --force\"",
    "php artisan m$''igrate --force",
    "php artisan $'migr''ate' --force",
    "bash -lc \"php artisan $'\\x6d\\x69\\x67\\x72\\x61\\x74\\x65' --force\"",
    "bash -lc \"php artisan $'\\155\\151\\147\\162\\141\\164\\145' --force\"",
    "bash -lc \"php artisan $'mi\\147rate' --force\"",
    'php${IFS}artisan${IFS}migrate --force',
    'php artisan ${EMPTY}migrate --force',
    'bash -lc "php artisan $(printf migrate) --force"',
    'bash -lc "`php artisan migrate --force`"',
    "ssh root@example.test 'cd /app && php${IFS}artisan${IFS}migrate --force'",
    'cmd=migrate; php artisan $cmd --force',
    'cmd=migrate:fresh; php artisan $cmd --force',
  ]) {
    const context = createDeployHarnessRoot();
    const { root, head } = context;
    try {
      const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
      const runId = parseRunId(start.stdout);
      const authorize = runCli(root, [
        'authorize-deploy',
        '--run-id',
        runId,
        '--target',
        'production',
        '--commit',
        head,
        '--allow-migrations',
        'false',
        '--migration-diff-checked',
        'true',
        '--deploy-command',
        command,
      ], { allowFailure: true });
      const validate = runCli(root, ['validate']);

      assert.notEqual(authorize.status, 0, command);
      assert.match(authorize.stderr, /migration|migrate/i, command);
      assert.equal(validate.status, 0, command);
    } finally {
      removeDeployHarnessRoot(context);
    }
  }
});

test('bloqueia migration destrutiva mesmo quando migrations estao autorizadas', () => {
  for (const command of [
    'php artisan migrate:fresh --force',
    'php artisan migrate:reset --force',
    'php artisan migrate:refresh --force',
    'php artisan migrate:rollback --force',
    'php artisan db:wipe --force',
  ]) {
    const context = createDeployHarnessRoot();
    const { root, head } = context;
    try {
      const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
      const runId = parseRunId(start.stdout);
      const authorize = runCli(root, [
        'authorize-deploy',
        '--run-id',
        runId,
        '--target',
        'production',
        '--commit',
        head,
        '--allow-migrations',
        'true',
        '--migration-diff-checked',
        'true',
        '--deploy-command',
        command,
      ], { allowFailure: true });

      assert.notEqual(authorize.status, 0, command);
      assert.match(authorize.stderr, /migration|destrutiva|db:wipe/i, command);
    } finally {
      removeDeployHarnessRoot(context);
    }
  }
});

test('record-command rejeita passed sem exit_code zero', () => {
  for (const args of [
    ['record-command', '--command', 'false', '--status', 'passed'],
    ['record-command', '--command', 'false', '--status', 'passed', '--exit-code', '1'],
  ]) {
    const root = createGitHarnessRoot();
    try {
      const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
      const runId = parseRunId(start.stdout);

      const record = runCli(root, [args[0], '--run-id', runId, ...args.slice(1), ...COMMAND_PROVENANCE_ARGS], { allowFailure: true });

      assert.notEqual(record.status, 0);
      assert.match(record.stderr, /exit_code|passed/i);
    } finally {
      rmSync(root, { force: true, recursive: true });
    }
  }
});

test('close rejeita commands.log corrompido com passed sem exit_code zero', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeApprovedArtifactsWithValidSchema(root, runId);
    writeCommandLogEntry(root, runId, {
      schema_version: 1,
      run_id: runId,
      audited_layer: 0,
      cycle: 1,
      command: 'false',
      cwd: root,
      started_at: new Date().toISOString(),
      finished_at: new Date().toISOString(),
      exit_code: null,
      status: 'passed',
      stdout_excerpt: null,
      stderr_excerpt: null,
      replacement_for: null,
      original_command: 'false',
      effective_command: 'false',
      waiver_basis: null,
      canonical_basis: 'smoke',
      approved_by: null,
      justification: null,
      essential_for_approval: true,
    });
    runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'approved'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /exit_code|passed|commands-log/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('start bloqueia workspace sujo em docs/harness com status unstaged', () => {
  const root = createGitHarnessRoot();
  try {
    const statePath = join(root, 'docs', 'harness', 'harness-state.json');
    const state = JSON.parse(readFileSync(statePath, 'utf8'));
    state.last_updated_by = 'dirty-smoke';
    writeFileSync(statePath, `${JSON.stringify(state, null, 2)}\n`);

    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full'], { allowFailure: true });

    assert.notEqual(start.status, 0);
    assert.match(start.stderr, /workspace sujo|docs\/harness|docs\\harness/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('start bloqueia workspace sujo em .agent/rules com status staged', () => {
  const root = createGitHarnessRoot();
  try {
    const rulePath = join(root, '.agent', 'rules', 'smoke.md');
    mkdirSync(join(root, '.agent', 'rules'), { recursive: true });
    writeFileSync(rulePath, 'initial\n');
    execFileSync('git', ['add', '.agent/rules/smoke.md'], { cwd: root });
    execFileSync('git', ['commit', '-m', 'add rule smoke', '--quiet'], { cwd: root });
    writeFileSync(rulePath, 'changed\n');
    execFileSync('git', ['add', '.agent/rules/smoke.md'], { cwd: root });

    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full'], { allowFailure: true });

    assert.notEqual(start.status, 0);
    assert.match(start.stderr, /workspace sujo|\.agent\/rules|\.agent\\rules/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('start bloqueia workspace sujo em docs/harness com arquivo untracked', () => {
  const root = createGitHarnessRoot();
  try {
    writeFileSync(join(root, 'docs', 'harness', 'untracked.txt'), 'new\n');

    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full'], { allowFailure: true });

    assert.notEqual(start.status, 0);
    assert.match(start.stderr, /workspace sujo|docs\/harness|docs\\harness/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('start bloqueia workspace sujo em arquivo da camada ativa', () => {
  const root = createGitHarnessRoot();
  try {
    markDependenciesApproved(root);
    commitHarnessState(root, 'approve dependencies');
    const controllerPath = join(root, 'backend', 'app', 'Http', 'Controllers', 'SmokeController.php');
    mkdirSync(join(root, 'backend', 'app', 'Http', 'Controllers'), { recursive: true });
    writeFileSync(controllerPath, '<?php\n// initial\n');
    execFileSync('git', ['add', 'backend/app/Http/Controllers/SmokeController.php'], { cwd: root });
    execFileSync('git', ['commit', '-m', 'add controller smoke', '--quiet'], { cwd: root });
    writeFileSync(controllerPath, '<?php\n// changed\n');

    const start = runCli(root, ['start', '--layer', '2', '--mode', 'full'], { allowFailure: true });

    assert.notEqual(start.status, 0);
    assert.match(start.stderr, /workspace sujo|backend\/app\/Http\/Controllers|backend\\app\\Http\\Controllers/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('start bloqueia camada 7 com migration dirty', () => {
  const context = createDeployHarnessRoot();
  const { root } = context;
  try {
    const migrationPath = join(root, 'backend', 'database', 'migrations', '2026_04_14_000000_smoke.php');
    mkdirSync(join(root, 'backend', 'database', 'migrations'), { recursive: true });
    writeFileSync(migrationPath, '<?php\n// initial\n');
    execFileSync('git', ['add', 'backend/database/migrations/2026_04_14_000000_smoke.php'], { cwd: root });
    execFileSync('git', ['commit', '-m', 'add migration smoke', '--quiet'], { cwd: root });
    writeFileSync(migrationPath, '<?php\n// changed\n');

    const start = runCli(root, ['start', '--layer', '7', '--mode', 'full'], { allowFailure: true });

    assert.notEqual(start.status, 0);
    assert.match(start.stderr, /workspace sujo|backend\/database\/migrations|backend\\database\\migrations/i);
  } finally {
    removeDeployHarnessRoot(context);
  }
});

test('start bloqueia camada com dependencias pendentes', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '1', '--mode', 'full'], { allowFailure: true });

    assert.notEqual(start.status, 0);
    assert.match(start.stderr, /dependencias|aprovadas/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('start bloqueia reabertura de camada approved sem invalidacao formal', () => {
  const root = createGitHarnessRoot();
  try {
    setLayerStatus(root, 0, 'approved');

    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full'], { allowFailure: true });

    assert.notEqual(start.status, 0);
    assert.match(start.stderr, /approved|invalidacao|invalidation/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('start bloqueia reabertura de camada escalated sem decisao humana', () => {
  const root = createGitHarnessRoot();
  try {
    setLayerStatus(root, 0, 'escalated');

    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full'], { allowFailure: true });

    assert.notEqual(start.status, 0);
    assert.match(start.stderr, /escalated|decisao humana|human-decision/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('validate rejeita target_scope persistido sem escopo obrigatorio', () => {
  for (const targetScope of [
    { type: 'module', paths: [], finding_ids: [], context: null },
    { type: 'finding_set', paths: [], finding_ids: [], context: null },
  ]) {
    const root = createHarnessRoot();
    try {
      const statePath = join(root, 'docs', 'harness', 'harness-state.json');
      const state = JSON.parse(readFileSync(statePath, 'utf8'));
      state.target_scope = targetScope;
      writeFileSync(statePath, `${JSON.stringify(state, null, 2)}\n`);

      const validate = runCli(root, ['validate'], { allowFailure: true });

      assert.notEqual(validate.status, 0);
      assert.match(validate.stderr, /target_scope|paths|finding_ids|schema/i);
    } finally {
      rmSync(root, { force: true, recursive: true });
    }
  }
});

test('validate rejeita campos date-time fora de RFC3339', () => {
  const root = createHarnessRoot();
  try {
    const statePath = join(root, 'docs', 'harness', 'harness-state.json');
    const state = JSON.parse(readFileSync(statePath, 'utf8'));
    state.updated_at = '14/04/2026 10:00';
    writeFileSync(statePath, `${JSON.stringify(state, null, 2)}\n`);

    const validate = runCli(root, ['validate'], { allowFailure: true });

    assert.notEqual(validate.status, 0);
    assert.match(validate.stderr, /date-time|RFC3339|schema/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('record rejeita transicao invalida e bloqueio sem motivo', () => {
  const root = createHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);

    const invalidTransition = runCli(root, ['record', '--run-id', runId, '--status', 'reaudit'], { allowFailure: true });
    const blockedWithoutReason = runCli(root, ['record', '--run-id', runId, '--status', 'blocked'], { allowFailure: true });
    const blockedWithReason = runCli(root, ['record', '--run-id', runId, '--status', 'blocked', '--reason', 'smoke']);

    assert.notEqual(invalidTransition.status, 0);
    assert.match(invalidTransition.stderr, /transicao|invalida/i);
    assert.notEqual(blockedWithoutReason.status, 0);
    assert.match(blockedWithoutReason.stderr, /reason|motivo/i);
    assert.equal(blockedWithReason.status, 0);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('record exige changed-files.txt ao sair de fix para verification', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    runCli(root, ['record', '--run-id', runId, '--status', 'fix']);
    writeFileSync(join(root, 'change.txt'), 'changed\n');
    execFileSync('git', ['add', 'change.txt'], { cwd: root });
    execFileSync('git', ['commit', '-m', 'change during fix', '--quiet'], { cwd: root });

    const record = runCli(root, ['record', '--run-id', runId, '--status', 'verification'], { allowFailure: true });

    assert.notEqual(record.status, 0);
    assert.match(record.stderr, /changed-files\.txt|provenance|proveniencia/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('record aceita fix para verification quando changed-files.txt registra alteracoes', () => {
  const root = createGitHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    runCli(root, ['record', '--run-id', runId, '--status', 'fix']);
    writeFileSync(join(root, 'change.txt'), 'changed\n');
    execFileSync('git', ['add', 'change.txt'], { cwd: root });
    execFileSync('git', ['commit', '-m', 'change during fix', '--quiet'], { cwd: root });
    writeFileSync(join(root, 'docs', 'harness', 'runs', runId, 'changed-files.txt'), 'change.txt\n');

    const record = runCli(root, ['record', '--run-id', runId, '--status', 'verification']);

    assert.equal(record.status, 0);
    assert.match(record.stdout, /verification/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('validate rejeita deployment_authorization com migration proibida', () => {
  const context = createDeployHarnessRoot();
  const { root, head } = context;
  try {
    const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    const statePath = join(root, 'docs', 'harness', 'harness-state.json');
    const state = JSON.parse(readFileSync(statePath, 'utf8'));
    state.deployment_authorization = {
      authorized: true,
      run_id: runId,
      layer: 7,
      cycle: 1,
      authorized_by: 'human',
      authorized_at: new Date().toISOString(),
      authorized_head_commit: head,
      target_environment: 'production',
      target_commit: head,
      deploy_command: 'bash deploy.sh --migrate',
      allow_migrations: false,
      migration_diff_checked: true,
      requires_backup: true,
      rollback_command: 'echo rollback',
      required_health_checks: ['http://127.0.0.1/up'],
    };
    writeFileSync(statePath, `${JSON.stringify(state, null, 2)}\n`);

    const validate = runCli(root, ['validate'], { allowFailure: true });

    assert.notEqual(validate.status, 0);
    assert.match(validate.stderr, /migration|migrate/i);
  } finally {
    removeDeployHarnessRoot(context);
  }
});

test('bloqueia autorizacao de deploy de producao fora de repositorio Git', () => {
  const root = createHarnessRoot();
  try {
    markDependenciesApproved(root);
    const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const runId = parseRunId(start.stdout);

    const authorize = runCli(root, [
      'authorize-deploy',
      '--run-id',
      runId,
      '--target',
      'production',
      '--commit',
      'abcdef1',
      '--deploy-command',
      'echo deploy',
      '--migration-diff-checked',
      'true',
    ], { allowFailure: true });

    assert.notEqual(authorize.status, 0);
    assert.match(authorize.stderr, /Git|HEAD|repositorio/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('bloqueia deploy com autorizacao reaproveitada de outro ciclo', () => {
  const context = createDeployHarnessRoot();
  const { root, head } = context;
  try {
    const firstStart = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const firstRunId = parseRunId(firstStart.stdout);
    runCli(root, ['authorize-deploy', '--run-id', firstRunId, '--target', 'production', '--commit', head, '--deploy-command', 'echo deploy-old', '--migration-diff-checked', 'true']);
    runCli(root, ['close', '--run-id', firstRunId, '--status', 'blocked', '--reason', 'smoke']);
    execFileSync('git', ['add', 'docs/harness'], { cwd: root });
    execFileSync('git', ['commit', '-m', 'close first run', '--quiet'], { cwd: root });

    const secondStart = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const secondRunId = parseRunId(secondStart.stdout);
    const deploy = runCli(root, ['deploy', '--run-id', secondRunId, '--dry-run'], { allowFailure: true });

    assert.notEqual(deploy.status, 0);
    assert.match(deploy.stderr, /authorization|autorizacao|run-id|deployment_authorization/i);
  } finally {
    removeDeployHarnessRoot(context);
  }
});

test('bloqueia deploy quando HEAD mudou apos autorizacao', () => {
  const root = createGitHarnessRoot();
  const remote = mkdtempSync(join(tmpdir(), 'harness-remote-'));
  try {
    execFileSync('git', ['init', '--bare', '--quiet', remote]);
    execFileSync('git', ['remote', 'add', 'origin', remote], { cwd: root });
    execFileSync('git', ['push', '--quiet', '-u', 'origin', 'HEAD'], { cwd: root });
    markDependenciesApproved(root);
    commitHarnessState(root, 'approve deploy dependencies');
    execFileSync('git', ['push', '--quiet'], { cwd: root });
    const head = execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim();
    const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    runCli(root, ['authorize-deploy', '--run-id', runId, '--target', 'production', '--commit', head, '--deploy-command', 'echo deploy', '--migration-diff-checked', 'true']);

    writeFileSync(join(root, 'change.txt'), 'changed\n');
    execFileSync('git', ['add', 'change.txt'], { cwd: root });
    execFileSync('git', ['commit', '-m', 'change head', '--quiet'], { cwd: root });

    const deploy = runCli(root, ['deploy', '--run-id', runId, '--dry-run'], { allowFailure: true });

    assert.notEqual(deploy.status, 0);
    assert.match(deploy.stderr, /authorized_head_commit|HEAD/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
    rmSync(remote, { force: true, recursive: true });
  }
});

test('bloqueia autorizacao de deploy de producao sem diff de migrations verificado', () => {
  const context = createDeployHarnessRoot();
  const { root, head } = context;
  try {
    const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    const authorize = runCli(root, ['authorize-deploy', '--run-id', runId, '--target', 'production', '--commit', head, '--deploy-command', 'echo deploy'], { allowFailure: true });
    const validate = runCli(root, ['validate']);

    assert.notEqual(authorize.status, 0);
    assert.match(authorize.stderr, /migration|diff/i);
    assert.equal(validate.status, 0);
    assert.match(validate.stdout, /valid=true/);
  } finally {
    removeDeployHarnessRoot(context);
  }
});

test('permite dry-run de deploy de producao com diff de migrations verificado', () => {
  const context = createDeployHarnessRoot();
  const { root, head } = context;
  try {
    const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    runCli(root, [
      'authorize-deploy',
      '--run-id',
      runId,
      '--target',
      'production',
      '--commit',
      head,
      '--deploy-command',
      'echo deploy',
      '--migration-diff-checked',
      'true',
    ]);

    const deploy = runCli(root, ['deploy', '--run-id', runId, '--dry-run']);

    assert.equal(deploy.status, 0);
    assert.match(deploy.stdout, /dry-run deploy command: echo deploy/);
  } finally {
    removeDeployHarnessRoot(context);
  }
});

test('autorizacao de deploy padrao usa deploy script versionado para deploy e rollback', () => {
  const context = createDeployHarnessRoot();
  const { root, head } = context;
  try {
    const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const runId = parseRunId(start.stdout);

    runCli(root, [
      'authorize-deploy',
      '--run-id',
      runId,
      '--target',
      'production',
      '--commit',
      head,
      '--migration-diff-checked',
      'true',
    ]);

    const state = JSON.parse(readFileSync(join(root, 'docs', 'harness', 'harness-state.json'), 'utf8'));
    const authorization = state.deployment_authorization;

    assert.match(authorization.deploy_command, /cd \/root\/sistema && bash deploy\/deploy\.sh"/);
    assert.match(authorization.rollback_command, /cd \/root\/sistema && bash deploy\/deploy\.sh --rollback"/);
    assert.doesNotMatch(authorization.deploy_command, /cd \/root\/sistema && bash deploy\.sh(?:\s|")/);
    assert.doesNotMatch(authorization.rollback_command, /cd \/root\/sistema && bash deploy\.sh --rollback(?:\s|")/);
    assert.doesNotMatch(authorization.deploy_command, /\$/);
    assert.doesNotMatch(authorization.rollback_command, /\$/);
  } finally {
    removeDeployHarnessRoot(context);
  }
});

test('bloqueia deploy quando lock ativo foi removido', () => {
  const context = createDeployHarnessRoot();
  const { root, head } = context;
  try {
    const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    runCli(root, ['authorize-deploy', '--run-id', runId, '--target', 'production', '--commit', head, '--deploy-command', 'echo deploy', '--migration-diff-checked', 'true']);
    runCli(root, ['unlock', '--run-id', runId, '--reason', 'smoke']);

    const deploy = runCli(root, ['deploy', '--run-id', runId, '--dry-run'], { allowFailure: true });

    assert.notEqual(deploy.status, 0);
    assert.match(deploy.stderr, /lock/i);
  } finally {
    removeDeployHarnessRoot(context);
  }
});

test('record-deploy registra evidencia valida no commands log', () => {
  const context = createDeployHarnessRoot();
  const { root, head } = context;
  try {
    const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    runCli(root, [
      'authorize-deploy',
      '--run-id',
      runId,
      '--target',
      'production',
      '--commit',
      head,
      '--deploy-command',
      'echo deploy',
      '--migration-diff-checked',
      'true',
    ]);

    const record = runCli(root, [
      'record-deploy',
      '--run-id',
      runId,
      '--status',
      'passed',
      '--exit-code',
      '0',
      '--command',
      'echo deploy',
    ]);
    const logPath = join(root, 'docs', 'harness', 'runs', runId, 'commands.log.jsonl');
    const entry = JSON.parse(readFileSync(logPath, 'utf8').trim());

    assert.match(record.stdout, /recorded deploy passed/);
    assert.equal(entry.command, 'echo deploy');
    assert.equal(entry.status, 'passed');
    assert.equal(entry.environment.platform, process.platform);
    assert.match(entry.output_hash, /^sha256:[0-9a-f]{64}$/);
  } finally {
    removeDeployHarnessRoot(context);
  }
});

test('record-deploy rejeita comando divergente da autorizacao', () => {
  const context = createDeployHarnessRoot();
  const { root, head } = context;
  try {
    const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    runCli(root, [
      'authorize-deploy',
      '--run-id',
      runId,
      '--target',
      'production',
      '--commit',
      head,
      '--deploy-command',
      'echo deploy',
      '--migration-diff-checked',
      'true',
    ]);

    const record = runCli(root, [
      'record-deploy',
      '--run-id',
      runId,
      '--status',
      'passed',
      '--command',
      'php artisan migrate:fresh --force',
    ], { allowFailure: true });

    assert.notEqual(record.status, 0);
    assert.match(record.stderr, /command|deployment_authorization|diverge/i);
  } finally {
    removeDeployHarnessRoot(context);
  }
});

test('record-deploy rejeita status fora do contrato e help documenta exit-code', () => {
  const context = createDeployHarnessRoot();
  const { root, head } = context;
  try {
    const help = runCli(root, ['--help']);
    const start = runCli(root, ['start', '--layer', '7', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    runCli(root, [
      'authorize-deploy',
      '--run-id',
      runId,
      '--target',
      'production',
      '--commit',
      head,
      '--deploy-command',
      'echo deploy',
      '--migration-diff-checked',
      'true',
    ]);

    const record = runCli(root, [
      'record-deploy',
      '--run-id',
      runId,
      '--status',
      'waived_by_policy',
      '--exit-code',
      '0',
    ], { allowFailure: true });

    assert.match(help.stdout, /record-deploy .*--exit-code CODE/i);
    assert.notEqual(record.status, 0);
    assert.match(record.stderr, /status de deploy invalido|waived_by_policy/i);
  } finally {
    removeDeployHarnessRoot(context);
  }
});

test('unlock manual fecha a run ativa como blocked', () => {
  const root = createHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    runCli(root, ['unlock', '--run-id', runId, '--reason', 'lock orphan smoke']);

    const status = runCli(root, ['status', '--json']);
    const state = JSON.parse(status.stdout);

    assert.equal(state.active_run_id, null);
    assert.equal(state.layers['0'].status, 'blocked');
    assert.equal(state.history_summary.at(-1).decision, 'blocked');
    assert.match(state.history_summary.at(-1).reason, /lock orphan smoke/);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('unlock falha quando lock fisico pertence a outra run', () => {
  const root = createHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    writeFileSync(join(root, 'docs', 'harness', '.lock'), `${JSON.stringify({ run_id: 'other-run', created_at: new Date().toISOString(), owner: 'test', pid: 1 }, null, 2)}\n`);

    const unlock = runCli(root, ['unlock', '--run-id', runId, '--reason', 'mismatch smoke'], { allowFailure: true });
    const lock = JSON.parse(readFileSync(join(root, 'docs', 'harness', '.lock'), 'utf8'));

    assert.notEqual(unlock.status, 0);
    assert.equal(lock.run_id, 'other-run');
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('validate rejeita run ativa sem lock', () => {
  const root = createHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);
    removeHarnessLockOnly(root);
    const validate = runCli(root, ['validate'], { allowFailure: true });

    assert.notEqual(validate.status, 0);
    assert.match(validate.stderr, /lock/i);
    assert.ok(runId);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('validate e start rejeitam campos ativos quando fase esta idle', () => {
  const root = createHarnessRoot();
  try {
    const statePath = join(root, 'docs', 'harness', 'harness-state.json');
    const state = JSON.parse(readFileSync(statePath, 'utf8'));
    state.current_phase = 'idle';
    state.cycle_state = 'idle';
    state.active_layer = 0;
    state.active_cycle = 1;
    state.active_run_id = '20260414T000000Z-layer-0-cycle-1';
    state.active_audit_mode = 'full';
    writeFileSync(statePath, `${JSON.stringify(state, null, 2)}\n`);

    const validate = runCli(root, ['validate'], { allowFailure: true });
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full'], { allowFailure: true });

    assert.notEqual(validate.status, 0);
    assert.match(validate.stderr, /active|ativo|idle/i);
    assert.notEqual(start.status, 0);
    assert.match(start.stderr, /ativa|active|lock|pendente/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('start concorrente nao apaga lock de outra run', () => {
  const root = createHarnessRoot();
  try {
    mkdirSync(join(root, 'docs', 'harness'), { recursive: true });
    writeFileSync(join(root, 'docs', 'harness', '.lock'), `${JSON.stringify({ run_id: 'other-run', created_at: new Date().toISOString(), owner: 'test', pid: 1 }, null, 2)}\n`);
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full'], { allowFailure: true });

    assert.notEqual(start.status, 0);
    const lock = JSON.parse(readFileSync(join(root, 'docs', 'harness', '.lock'), 'utf8'));
    assert.equal(lock.run_id, 'other-run');
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('exige motivo ao fechar ciclo como blocked ou escalated', () => {
  const root = createHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);

    const close = runCli(root, ['close', '--run-id', runId, '--status', 'blocked'], { allowFailure: true });

    assert.notEqual(close.status, 0);
    assert.match(close.stderr, /reason|motivo/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('schema exige reason para historico blocked ou escalated', () => {
  const root = createHarnessRoot();
  try {
    const statePath = join(root, 'docs', 'harness', 'harness-state.json');
    const state = JSON.parse(readFileSync(statePath, 'utf8'));
    state.history_summary.push({
      run_id: '20260414T000000Z-layer-0-cycle-1',
      layer: 0,
      cycle: 1,
      decision: 'blocked',
      report: 'docs/harness/runs/smoke/report.md',
    });
    writeFileSync(statePath, `${JSON.stringify(state, null, 2)}\n`);

    const validate = runCli(root, ['validate'], { allowFailure: true });

    assert.notEqual(validate.status, 0);
    assert.match(validate.stderr, /reason|motivo|schema/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

test('exige justificativa para comando equivalente', () => {
  const root = createHarnessRoot();
  try {
    const start = runCli(root, ['start', '--layer', '0', '--mode', 'full']);
    const runId = parseRunId(start.stdout);

    const record = runCli(root, [
      'record-command',
      '--run-id',
      runId,
      '--command',
      'effective',
      '--status',
      'replaced_by_equivalent',
      '--original-command',
      'original',
      '--effective-command',
      'effective',
      '--canonical-basis',
      'smoke',
      '--approved-by',
      'smoke',
      ...COMMAND_PROVENANCE_ARGS,
    ], { allowFailure: true });

    assert.notEqual(record.status, 0);
    assert.match(record.stderr, /justification|justificativa/i);
  } finally {
    rmSync(root, { force: true, recursive: true });
  }
});

function createHarnessRoot() {
  const root = mkdtempSync(join(tmpdir(), 'harness-test-'));
  runCli(root, ['init']);
  cpSync(join(ROOT, 'docs', 'harness', 'schemas'), join(root, 'docs', 'harness', 'schemas'), { recursive: true });
  return root;
}

function createGitHarnessRoot() {
  const root = createHarnessRoot();
  execFileSync('git', ['init', '--quiet'], { cwd: root });
  execFileSync('git', ['config', 'user.email', 'harness@example.test'], { cwd: root });
  execFileSync('git', ['config', 'user.name', 'Harness Test'], { cwd: root });
  execFileSync('git', ['config', 'core.autocrlf', 'false'], { cwd: root });
  execFileSync('git', ['add', 'docs/harness'], { cwd: root });
  execFileSync('git', ['commit', '-m', 'init harness', '--quiet'], { cwd: root });
  return root;
}

function createDeployHarnessRoot() {
  const root = createGitHarnessRoot();
  const remote = mkdtempSync(join(tmpdir(), 'harness-remote-'));
  execFileSync('git', ['init', '--bare', '--quiet', remote]);
  execFileSync('git', ['remote', 'add', 'origin', remote], { cwd: root });
  execFileSync('git', ['push', '--quiet', '-u', 'origin', 'HEAD'], { cwd: root });
  markDependenciesApproved(root);
  commitHarnessState(root, 'approve deploy dependencies');
  execFileSync('git', ['push', '--quiet'], { cwd: root });
  const head = execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim();
  return { root, remote, head };
}

function removeDeployHarnessRoot(context) {
  rmSync(context.root, { force: true, recursive: true });
  rmSync(context.remote, { force: true, recursive: true });
}

function runCli(root, args, options = {}) {
  const result = spawnSync(process.execPath, [CLI, ...args, '--root', root], {
    encoding: 'utf8',
    windowsHide: true,
  });
  if (!options.allowFailure && result.status !== 0) {
    throw new Error(`CLI falhou: node ${CLI} ${args.join(' ')}\nstdout=${result.stdout}\nstderr=${result.stderr}`);
  }
  return result;
}

function parseRunId(stdout) {
  const match = stdout.match(/^run_id=(.+)$/m);
  assert.ok(match, `run_id ausente em stdout: ${stdout}`);
  return match[1].trim();
}

function recordPassedCommand(root, runId, command = 'node --version') {
  return runCli(root, [
    'record-command',
    '--run-id',
    runId,
    '--command',
    command,
    '--status',
    'passed',
    '--exit-code',
    '0',
    ...COMMAND_PROVENANCE_ARGS,
  ]);
}

function markDependenciesApproved(root) {
  const statePath = join(root, 'docs', 'harness', 'harness-state.json');
  const state = JSON.parse(readFileSync(statePath, 'utf8'));
  for (const layer of ['0', '1', '2', '3', '4', '5', '6']) {
    state.layers[layer].status = 'approved';
    state.layers[layer].approved_report_provenance = {
      cycle: 1,
      report: 'smoke',
      base_commit: null,
      head_commit: null,
      approved_commit: 'abcdef1',
      approved_at: new Date().toISOString(),
    };
    state.layers[layer].layer_dependencies_resolved = true;
  }
  writeFileSync(statePath, `${JSON.stringify(state, null, 2)}\n`);
}

function commitHarnessState(root, message) {
  execFileSync('git', ['add', 'docs/harness/harness-state.json'], { cwd: root });
  execFileSync('git', ['commit', '-m', message, '--quiet'], { cwd: root });
}

function setLayerStatus(root, layer, status) {
  const statePath = join(root, 'docs', 'harness', 'harness-state.json');
  const state = JSON.parse(readFileSync(statePath, 'utf8'));
  const layerState = state.layers[String(layer)];
  layerState.status = status;
  layerState.last_cycle = 1;
  layerState.last_report = 'docs/harness/runs/smoke/report.md';
  layerState.layer_dependencies_resolved = true;
  if (status === 'approved') {
    layerState.approved_report_provenance = {
      cycle: 1,
      report: 'docs/harness/runs/smoke/report.md',
      base_commit: null,
      head_commit: null,
      approved_commit: 'abcdef1',
      approved_at: new Date().toISOString(),
    };
  }
  writeFileSync(statePath, `${JSON.stringify(state, null, 2)}\n`);
  commitHarnessState(root, `mark layer ${layer} ${status}`);
}

function writeCommandLogEntry(root, runId, entry) {
  writeFileSync(join(root, 'docs', 'harness', 'runs', runId, 'commands.log.jsonl'), `${JSON.stringify({
    actor_role: 'verifier',
    agent_id: 'verifier-smoke',
    review_round: 1,
    context_fingerprint: 'test-context',
    source_bundle_hash: 'test-bundle',
    environment: {
      node_version: process.version,
      platform: process.platform,
      arch: process.arch,
      pid: process.pid,
      shell: null,
    },
    output_hash: `sha256:${'0'.repeat(64)}`,
    ...entry,
  })}\n`);
}

function removeHarnessLockOnly(root) {
  rmSync(join(root, 'docs', 'harness', '.lock'), { force: true });
}

function writeApprovedArtifactsWithInvalidSchema(root, runId) {
  const runDir = join(root, 'docs', 'harness', 'runs', runId);
  const consolidated = {
    run_id: runId,
    layer: 0,
    cycle: 1,
    status: 'approved',
    decision: 'approve',
    findings: [],
    harness_errors: [],
  };
  writeFileSync(join(runDir, 'consolidated-findings.json'), `${JSON.stringify(consolidated, null, 2)}\n`);
  for (const [auditor, file] of [
    ['architecture-dependencies', 'auditor-architecture-dependencies.json'],
    ['security-tenant', 'auditor-security-tenant.json'],
    ['code-quality', 'auditor-code-quality.json'],
    ['tests-verification', 'auditor-tests-verification.json'],
    ['ops-provenance', 'auditor-ops-provenance.json'],
  ]) {
    writeFileSync(join(runDir, file), `${JSON.stringify({ run_id: runId, layer: 0, cycle: 1, auditor, status: 'approved', findings: [], harness_errors: [] }, null, 2)}\n`);
  }
}

function writeApprovedArtifactsWithValidSchema(root, runId, options = {}) {
  const runDir = join(root, 'docs', 'harness', 'runs', runId);
  runCli(root, ['generate-impact', '--run-id', runId]);
  const headCommit = options.headCommit ?? execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim();
  const baseCommit = options.baseCommit ?? headCommit;
  const generatedAt = new Date().toISOString();
  const executedAuditors = [
    ['architecture-dependencies', 'auditor-architecture-dependencies.json', 'auditor-1'],
    ['security-tenant', 'auditor-security-tenant.json', 'auditor-2'],
    ['code-quality', 'auditor-code-quality.json', 'auditor-3'],
    ['tests-verification', 'auditor-tests-verification.json', 'auditor-4'],
    ['ops-provenance', 'auditor-ops-provenance.json', 'auditor-5'],
  ].map(([auditor, artifact, agentId]) => ({ auditor, artifact, agent_id: agentId }));
  const consolidated = {
    schema_version: 1,
    run_id: runId,
    layer: 0,
    cycle: 1,
    audit_mode: 'full',
    status: 'approved',
    decision: 'approve',
    target_scope: { type: 'layer', paths: [], finding_ids: [], context: null },
    base_commit: baseCommit,
    head_commit: headCommit,
    review_round: 1,
    max_review_rounds: 10,
    auditor_quorum_size: 5,
    audit_coverage: {
      executed_auditors: executedAuditors,
      distinct_agent_ids: executedAuditors.map((auditor) => auditor.agent_id),
      quorum_met: true,
      coverage_gaps: [],
      unresolved_required_verifications: [],
      commands_log_present: true,
      impact_manifest_present: true,
    },
    fixer_agent_id: options.fixerAgentId ?? null,
    findings: [],
    harness_errors: [],
    next_action: 'none',
  };
  writeFileSync(join(runDir, 'consolidated-findings.json'), `${JSON.stringify(consolidated, null, 2)}\n`);
  for (const [index, { auditor, artifact: file, agent_id: agentId }] of executedAuditors.entries()) {
    writeFileSync(join(runDir, file), `${JSON.stringify({
      schema_version: 1,
      run_id: runId,
      layer: 0,
      cycle: 1,
      audit_mode: 'full',
      auditor,
      status: 'approved',
      generated_at: generatedAt,
      summary: 'approved by smoke test',
      agent_provenance: {
        agent_id: agentId,
        agent_role: 'auditor-readonly',
        context_mode: 'clean',
        conversation_isolated: true,
        orchestrator_generated: false,
        prompt_summary: 'isolated smoke auditor context',
        context_fingerprint: null,
        input_bundle_hash: null,
      },
      scope: {
        allowed_paths: [],
        readonly: true,
        canonical_sources: ['scripts/harness-cycle.test.mjs'],
      },
      provenance: {
        base_commit: baseCommit,
        head_commit: headCommit,
        working_tree_state: 'clean',
      },
      audit_limitations: {
        not_inspected: [`smoke fixture ${index + 1} nao inspeciona o produto real`],
        assumptions: ['fixture sintetico usado apenas para contratos do harness'],
        required_verifications_not_executed: [],
      },
      findings: [],
      harness_errors: [],
    }, null, 2)}\n`);
  }
}
