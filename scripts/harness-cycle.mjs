#!/usr/bin/env node

import { execSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, readdirSync, renameSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const MAX_LAYER_CYCLES = 10;
const VALID_LAYERS = new Set([0, 1, 2, 3, 4, 5, 6, 7]);
const VALID_AUDIT_MODES = new Set(['full', 'targeted', 'verification_only']);
const VALID_CLOSE_STATUS = new Set(['approved', 'blocked', 'escalated']);
const VALID_RECORD_STATUS = new Set(['audit', 'fix', 'reaudit', 'verification', 'blocked', 'escalated']);
const VALID_LAYER_STATUS = new Set(['not_started', 'in_progress', 'approved', 'blocked', 'escalated']);
const VALID_PHASES = new Set(['idle', 'audit', 'fix', 'reaudit', 'verification', 'closed']);
const VALID_CYCLE_STATES = new Set(['idle', 'audit', 'fix', 'reaudit', 'verification', 'closed', 'blocked', 'escalated']);
const VALID_RECORD_TRANSITIONS = {
  audit: new Set(['fix', 'verification', 'blocked']),
  fix: new Set(['verification', 'blocked']),
  verification: new Set(['fix', 'reaudit', 'blocked']),
  reaudit: new Set(['fix', 'verification', 'blocked', 'escalated']),
};
const COMMAND_STATUS = new Set([
  'passed',
  'failed',
  'not_executed',
  'blocked_environment',
  'blocked_policy',
  'waived_by_policy',
  'replaced_by_equivalent',
]);
const COMMAND_ACTOR_ROLES = new Set(['orchestrator', 'auditor', 'fixer', 'verifier', 'llm-cli']);
const VALID_DEPLOY_RECORD_STATUS = new Set(['passed', 'failed', 'blocked_policy']);

const DEFAULT_REPORTS_ROOT = 'docs/harness/runs';
const STATE_RELATIVE_PATH = 'docs/harness/harness-state.json';
const LOCK_RELATIVE_PATH = 'docs/harness/.lock';
const SCHEMAS_ROOT_RELATIVE_PATH = 'docs/harness/schemas';
const LAYER_DIRTY_PATTERNS = {
  0: [/^AGENTS\.md$/, /^\.codex\/memory\.md$/, /^\.agent\//, /^docs\/harness\//, /^scripts\/harness-cycle\./],
  1: [
    /^backend\/app\/Http\/Middleware\//,
    /^backend\/app\/Policies\//,
    /^backend\/app\/Models\//,
    /^backend\/database\/migrations\//,
    /^backend\/database\/factories\//,
    /^backend\/tests\/Feature\/Auth\//,
  ],
  2: [
    /^backend\/app\/Http\/Controllers\//,
    /^backend\/app\/Http\/Requests\//,
    /^backend\/app\/Http\/Resources\//,
    /^backend\/routes\//,
    /^backend\/app\/Models\//,
    /^backend\/database\/migrations\//,
    /^backend\/database\/factories\//,
    /^backend\/tests\/Feature\//,
    /^backend\/tests\/Unit\//,
  ],
  3: [/^frontend\/src\//, /^frontend\/tests\//],
  4: [/^e2e\//, /^frontend\/e2e\//, /^playwright\.config\./, /^frontend\/tests\//, /^backend\/tests\/Feature\//],
  5: [/^Dockerfile/, /^docker-compose/, /^\.github\//, /^nginx\//, /^\.env\.example$/],
  6: [/.+/],
  7: [
    /^deploy\.sh$/,
    /^backend\/database\/migrations\//,
    /^backend\/database\/schema\//,
    /^docs\/auditoria\/CAMADA-7-PRODUCAO-DEPLOY\.md$/,
    /^\.cursor\/rules\/deploy-production\.mdc$/,
    /^\.cursor\/rules\/migration-production\.mdc$/,
  ],
};
const REQUIRED_AUDITORS = [
  { id: 'architecture-dependencies', file: 'auditor-architecture-dependencies.json' },
  { id: 'security-tenant', file: 'auditor-security-tenant.json' },
  { id: 'code-quality', file: 'auditor-code-quality.json' },
  { id: 'tests-verification', file: 'auditor-tests-verification.json' },
  { id: 'ops-provenance', file: 'auditor-ops-provenance.json' },
];

function main() {
  try {
    const parsed = parseArgs(process.argv.slice(2));
    if (!parsed.command || parsed.command === '--help' || parsed.command === '-h' || parsed.options.help) {
      printHelp();
      return;
    }

    const root = resolve(String(parsed.options.root ?? process.cwd()));

    switch (parsed.command) {
      case 'init':
        initHarness(root);
        break;
      case 'start':
        startRun(root, parsed.options);
        break;
      case 'record':
        recordRun(root, parsed.options);
        break;
      case 'close':
        closeRun(root, parsed.options);
        break;
      case 'status':
        printStatus(root, parsed.options);
        break;
      case 'validate':
        validateState(root);
        break;
      case 'authorize-deploy':
        authorizeDeploy(root, parsed.options);
        break;
      case 'record-deploy':
        recordDeploy(root, parsed.options);
        break;
      case 'record-command':
        recordCommand(root, parsed.options);
        break;
      case 'generate-impact':
        generateImpact(root, parsed.options);
        break;
      case 'deploy':
        deploy(root, parsed.options);
        break;
      case 'unlock':
        unlock(root, parsed.options);
        break;
      default:
        fail(`Comando desconhecido: ${parsed.command}`);
    }
  } catch (error) {
    console.error(error instanceof Error ? error.message : String(error));
    process.exit(1);
  }
}

function parseArgs(args) {
  const result = { command: null, options: {} };
  const queue = [...args];
  result.command = queue.shift() ?? null;

  while (queue.length > 0) {
    const token = queue.shift();
    if (!token?.startsWith('--')) {
      fail(`Argumento inesperado: ${token}`);
    }

    const key = token.slice(2);
    const next = queue[0];
    const value = !next || next.startsWith('--') ? true : queue.shift();

    if (Object.hasOwn(result.options, key)) {
      const existing = result.options[key];
      result.options[key] = Array.isArray(existing) ? [...existing, value] : [existing, value];
    } else {
      result.options[key] = value;
    }
  }

  return result;
}

function initHarness(root) {
  const statePath = join(root, STATE_RELATIVE_PATH);
  if (existsSync(statePath)) {
    fail(`blocked_policy: manifesto ja existe em ${STATE_RELATIVE_PATH}.`);
  }
  writeState(root, initialState());
  console.log(`initialized=${STATE_RELATIVE_PATH}`);
}

function startRun(root, options) {
  const layer = parseLayer(options.layer);
  const auditMode = parseAuditMode(options.mode ?? 'full');
  const state = readState(root);
  assertNoActiveRun(root, state);

  const dirtyPaths = getDirtyPaths(root);
  const relevantDirty = dirtyPaths.filter((pathValue) => isRelevantDirtyPath(pathValue, layer));
  if (relevantDirty.length > 0) {
    fail(`blocked_environment: workspace sujo em arquivos sensiveis: ${relevantDirty.join(', ')}`);
  }

  const layerState = state.layers[String(layer)];
  assertLayerStartAllowed(layerState, layer, options);
  const layerDependenciesResolved = dependenciesResolved(state, layerState.depends_on);
  if (!layerDependenciesResolved) {
    fail(`blocked_policy: camada ${layer} exige dependencias aprovadas antes de iniciar.`);
  }
  const cycle = Number(layerState.last_cycle ?? 0) + 1;
  if (cycle > MAX_LAYER_CYCLES) {
    fail(`escalated: camada ${layer} excedeu ${MAX_LAYER_CYCLES} ciclos; decisao humana requerida.`);
  }
  const runId = buildRunId(layer, cycle);
  const reportsRoot = state.reports_root || DEFAULT_REPORTS_ROOT;
  const runDir = join(root, reportsRoot, runId);
  const lockPath = join(root, LOCK_RELATIVE_PATH);
  const now = nowIso();
  const headCommit = getHeadCommit(root);
  const lockPayload = { run_id: runId, created_at: now, owner: 'harness-cycle', pid: process.pid };
  let lockCreated = false;

  if (existsSync(runDir)) {
    fail(`blocked_environment: diretorio de run ja existe: ${toRepoPath(join(reportsRoot, runId))}`);
  }

  mkdirSync(dirname(lockPath), { recursive: true });
  try {
    writeFileSync(lockPath, `${JSON.stringify(lockPayload, null, 2)}\n`, { encoding: 'utf8', flag: 'wx' });
    lockCreated = true;

    mkdirSync(runDir, { recursive: true });
    writeFileSync(join(runDir, 'commands.log.jsonl'), '', 'utf8');
    writeJsonAtomic(join(runDir, 'consolidated-findings.json'), initialConsolidated(runId, layer, cycle, auditMode, headCommit));
    writeFileSync(join(runDir, 'report.md'), initialReport(runId, layer, cycle, auditMode, headCommit, dirtyPaths), 'utf8');

    state.updated_at = now;
    state.last_updated_by = 'harness-cycle';
    state.active_layer = layer;
    state.active_cycle = cycle;
    state.active_run_id = runId;
    state.active_audit_mode = auditMode;
    state.current_phase = 'audit';
    state.cycle_state = 'audit';
    state.audit_quorum = emptyAuditQuorum(runId);
    state.escalation_required = false;
    state.blocking_reason = null;
    state.target_scope = parseTargetScope(options);
    state.deployment_authorization = emptyDeploymentAuthorization();
    state.lock = {
      active: true,
      path: LOCK_RELATIVE_PATH,
      ...lockPayload,
    };
    state.git.base_commit = headCommit;
    state.git.head_commit = headCommit;
    state.git.approved_commit = null;
    state.git.working_tree_clean_at_start = dirtyPaths.length === 0;
    state.git.working_tree_clean_at_close = null;
    state.git.dirty_paths_at_start = dirtyPaths;

    layerState.status = 'in_progress';
    layerState.last_cycle = cycle;
    layerState.last_report = toRepoPath(join(reportsRoot, runId, 'report.md'));
    layerState.layer_dependencies_resolved = layerDependenciesResolved;

    writeState(root, state);
  } catch (error) {
    if (lockCreated) {
      removeLockIfMatches(root, runId);
    }
    rmSync(runDir, { force: true, recursive: true });
    throw error;
  }
  console.log(`run_id=${runId}`);
  console.log(`run_dir=${toRepoPath(join(reportsRoot, runId))}`);
}

function recordRun(root, options) {
  const state = readState(root);
  const status = requireString(options.status, 'status');
  if (!VALID_RECORD_STATUS.has(status)) {
    fail(`status invalido para record: ${status}`);
  }
  assertRunMatches(state, options['run-id']);
  assertActiveRun(state);
  assertLockHeld(root, state, state.active_run_id);
  assertRecordTransition(state.cycle_state, status);
  if (state.cycle_state === 'fix' && status === 'verification') {
    assertChangedFilesRecorded(root, state);
  }
  const reason = status === 'blocked' || status === 'escalated' ? requireString(options.reason, 'reason/motivo') : null;

  state.updated_at = nowIso();
  state.last_updated_by = 'harness-cycle';
  if (status === 'blocked' || status === 'escalated') {
    state.cycle_state = status;
    state.blocking_reason = reason;
    state.escalation_required = status === 'escalated';
  } else {
    state.current_phase = status;
    state.cycle_state = status;
    state.blocking_reason = null;
    state.escalation_required = false;
  }
  state.git.head_commit = getHeadCommit(root);
  writeState(root, state);
  console.log(`recorded ${status} for ${state.active_run_id}`);
}

function closeRun(root, options) {
  const state = readState(root);
  const status = requireString(options.status, 'status');
  if (!VALID_CLOSE_STATUS.has(status)) {
    fail(`status invalido para close: ${status}`);
  }
  assertRunMatches(state, options['run-id']);

  const layer = state.active_layer;
  const cycle = state.active_cycle;
  const runId = state.active_run_id;
  if (layer === null || cycle === null || !runId) {
    fail('Nenhuma run ativa para fechar.');
  }
  assertLockHeld(root, state, runId);

  const now = nowIso();
  const headCommit = getHeadCommit(root);
  const dirtyPaths = getDirtyPaths(root);
  const layerState = state.layers[String(layer)];
  const report = toRepoPath(join(state.reports_root || DEFAULT_REPORTS_ROOT, runId, 'report.md'));
  const reason = status === 'blocked' || status === 'escalated' ? requireString(options.reason, 'reason/motivo') : null;

  layerState.status = status;
  layerState.last_cycle = cycle;
  layerState.last_report = report;
  if (status === 'approved') {
    if (state.current_phase !== 'verification' || state.cycle_state !== 'verification') {
      fail('blocked_policy: close --status approved exige fase verification.');
    }
    if (['blocked', 'escalated'].includes(state.cycle_state)) {
      fail(`blocked_policy: ciclo ${state.cycle_state} nao pode ser fechado como approved.`);
    }
    const nonRuntimeDirty = nonRuntimeDirtyPaths(dirtyPaths, runId);
    if (nonRuntimeDirty.length > 0) {
      fail(`blocked_environment: workspace sujo fora dos artefatos runtime do harness: ${nonRuntimeDirty.join(', ')}`);
    }
    if (!headCommit) {
      fail('blocked_policy: aprovacao exige HEAD git conhecido.');
    }
    if (!layerState.layer_dependencies_resolved) {
      fail(`blocked_policy: camada ${layer} nao pode ser aprovada com dependencias pendentes.`);
    }
    assertApprovalArtifacts(root, state, runId, layer, cycle);
    layerState.approved_report_provenance = {
      cycle,
      report,
      base_commit: state.git.base_commit,
      head_commit: headCommit,
      approved_commit: headCommit,
      approved_at: now,
    };
    state.git.approved_commit = headCommit;
  }

  state.history_summary.push({ run_id: runId, layer, cycle, decision: status, report, reason });
  state.updated_at = now;
  state.last_updated_by = 'harness-cycle';
  state.current_phase = 'idle';
  state.cycle_state = 'idle';
  state.active_layer = null;
  state.active_cycle = null;
  state.active_run_id = null;
  state.active_audit_mode = null;
  state.blocking_reason = null;
  state.escalation_required = false;
  state.deployment_authorization = emptyDeploymentAuthorization();
  state.git.head_commit = headCommit;
  state.git.working_tree_clean_at_close = dirtyPaths.length === 0;
  state.lock = emptyLock();

  removeLock(root);
  writeState(root, state);
  console.log(`closed ${runId} as ${status}`);
}

function printStatus(root, options) {
  const state = readState(root);
  if (options.json) {
    console.log(JSON.stringify(state, null, 2));
    return;
  }
  console.log(`phase=${state.current_phase}`);
  console.log(`cycle_state=${state.cycle_state}`);
  console.log(`active_run_id=${state.active_run_id ?? 'none'}`);
  console.log(`active_layer=${state.active_layer ?? 'none'}`);
  console.log(`lock=${state.lock.active ? state.lock.run_id : 'none'}`);
}

function validateState(root) {
  const state = readState(root);
  assertSchemaValid(root, 'harness-state.schema.json', state, STATE_RELATIVE_PATH);
  const errors = stateInvariantErrors(state, root);
  if (errors.length > 0) {
    for (const error of errors) {
      console.error(error);
    }
    process.exit(1);
  }
  console.log('valid=true');
}

function authorizeDeploy(root, options) {
  const state = readState(root);
  assertLayer7Run(state);
  const runId = requireString(options['run-id'] ?? state.active_run_id, 'run-id');
  assertLockHeld(root, state, runId);
  if (state.active_run_id && state.active_run_id !== runId) {
    fail(`run-id divergente: ativo=${state.active_run_id}, recebido=${runId}`);
  }
  if (!state.layers['7']?.layer_dependencies_resolved) {
    fail('blocked_policy: deploy exige dependencias 0-6 aprovadas.');
  }

  const targetEnvironment = requireString(options.target, 'target');
  const targetCommit = requireString(options.commit, 'commit');
  const headCommit = requireDeployHeadCommit(root, targetEnvironment);
  assertTargetCommit(root, targetCommit);
  const deployCommand = String(options['deploy-command'] ?? defaultDeployCommand(targetEnvironment));
  const rollbackCommand = String(options['rollback-command'] ?? defaultRollbackCommand(targetEnvironment));
  const healthChecks = asArray(options['health-check'] ?? defaultHealthChecks(targetEnvironment));
  const allowMigrations = parseBooleanOption(options['allow-migrations'], false, 'allow-migrations');
  const requiresBackup = parseBooleanOption(options['requires-backup'], true, 'requires-backup');
  const migrationDiffChecked = parseBooleanOption(options['migration-diff-checked'], false, 'migration-diff-checked');
  if (targetEnvironment === 'production') {
    if (!requiresBackup || healthChecks.length === 0) {
      fail('blocked_policy: producao exige backup e health checks.');
    }
    if (!migrationDiffChecked) {
      fail('blocked_policy: producao exige diff de migrations verificado.');
    }
  }
  assertMigrationPolicy({ allow_migrations: allowMigrations, deploy_command: deployCommand });

  state.deployment_authorization = {
    authorized: true,
    run_id: runId,
    layer: state.active_layer,
    cycle: state.active_cycle,
    authorized_by: String(options['authorized-by'] ?? 'human'),
    authorized_at: nowIso(),
    authorized_head_commit: headCommit,
    target_environment: targetEnvironment,
    target_commit: targetCommit,
    deploy_command: deployCommand,
    allow_migrations: allowMigrations,
    migration_diff_checked: migrationDiffChecked,
    requires_backup: requiresBackup,
    rollback_command: rollbackCommand,
    required_health_checks: healthChecks,
  };
  state.updated_at = nowIso();
  state.last_updated_by = 'harness-cycle';
  writeState(root, state);
  console.log(`deployment_authorization=${runId}`);
}

function deploy(root, options) {
  const state = readState(root);
  const runId = requireString(options['run-id'] ?? state.active_run_id, 'run-id');
  assertDeployAuthorized(root, state, runId);

  const command = state.deployment_authorization.deploy_command;
  const dryRun = Boolean(options['dry-run']);
  if (dryRun) {
    console.log(`dry-run deploy command: ${command}`);
    return;
  }

  const runDir = runDirFor(state, runId, root);
  const startedAt = nowIso();
  let stdout = '';
  let stderr = '';
  let exitCode = 0;

  try {
    stdout = execSync(command, { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  } catch (error) {
    exitCode = typeof error.status === 'number' ? error.status : 1;
    stdout = String(error.stdout ?? '');
    stderr = String(error.stderr ?? error.message ?? '');
  }

  const entry = {
    schema_version: 1,
    run_id: runId,
    audited_layer: state.active_layer ?? 7,
    cycle: state.active_cycle ?? 1,
    command,
    cwd: root,
    started_at: startedAt,
    finished_at: nowIso(),
    exit_code: exitCode,
    status: exitCode === 0 ? 'passed' : 'failed',
    stdout_excerpt: excerpt(stdout),
    stderr_excerpt: excerpt(stderr),
    replacement_for: null,
    original_command: command,
    effective_command: command,
    waiver_basis: null,
    canonical_basis: 'deployment_authorization',
    approved_by: state.deployment_authorization.authorized_by,
    justification: null,
    essential_for_approval: true,
    actor_role: 'llm-cli',
    agent_id: 'harness-cycle-deploy',
    review_round: state.active_cycle ?? 1,
    context_fingerprint: `deployment_authorization:${runId}`,
    source_bundle_hash: `deployment_authorization:${state.deployment_authorization.authorized_head_commit}`,
    environment: commandEnvironment(),
  };
  entry.output_hash = hashJson({
    command: entry.command,
    cwd: entry.cwd,
    status: entry.status,
    exit_code: entry.exit_code,
    stdout_excerpt: entry.stdout_excerpt,
    stderr_excerpt: entry.stderr_excerpt,
  });
  appendCommandLog(runDir, entry);

  if (exitCode !== 0) {
    fail(`deploy falhou com exit_code=${exitCode}`);
  }
  state.updated_at = nowIso();
  state.last_updated_by = 'harness-cycle';
  writeState(root, state);
  console.log('deploy recorded as passed');
}

function recordDeploy(root, options) {
  const state = readState(root);
  const runId = requireString(options['run-id'] ?? state.active_run_id, 'run-id');
  assertDeployAuthorized(root, state, runId);
  const status = requireString(options.status, 'status');
  if (!VALID_DEPLOY_RECORD_STATUS.has(status)) {
    fail(`status de deploy invalido: ${status}`);
  }
  const authorizedCommand = state.deployment_authorization.deploy_command;
  const command = String(options.command ?? authorizedCommand);
  if (command !== authorizedCommand) {
    fail('blocked_policy: record-deploy command diverge de deployment_authorization.deploy_command.');
  }
  assertMigrationPolicy({ ...state.deployment_authorization, deploy_command: command });
  const runDir = runDirFor(state, runId, root);
  const entry = {
    schema_version: 1,
    run_id: runId,
    audited_layer: state.active_layer ?? 7,
    cycle: state.active_cycle ?? 1,
    command,
    cwd: root,
    started_at: String(options['started-at'] ?? nowIso()),
    finished_at: String(options['finished-at'] ?? nowIso()),
    exit_code: options['exit-code'] === undefined ? null : Number(options['exit-code']),
    status,
    stdout_excerpt: options.stdout ? String(options.stdout) : null,
    stderr_excerpt: options.stderr ? String(options.stderr) : null,
    replacement_for: null,
    original_command: command,
    effective_command: command,
    waiver_basis: null,
    canonical_basis: 'deployment_authorization',
    approved_by: state.deployment_authorization.authorized_by,
    justification: null,
    essential_for_approval: true,
    actor_role: 'llm-cli',
    agent_id: String(options['agent-id'] ?? 'harness-cycle-record-deploy'),
    review_round: state.active_cycle ?? 1,
    context_fingerprint: String(options['context-fingerprint'] ?? `deployment_authorization:${runId}`),
    source_bundle_hash: String(options['source-bundle-hash'] ?? `deployment_authorization:${state.deployment_authorization.authorized_head_commit}`),
    environment: commandEnvironment(),
  };
  entry.output_hash = hashJson({
    command: entry.command,
    cwd: entry.cwd,
    status: entry.status,
    exit_code: entry.exit_code,
    stdout_excerpt: entry.stdout_excerpt,
    stderr_excerpt: entry.stderr_excerpt,
  });
  appendCommandLog(runDir, entry);
  state.updated_at = nowIso();
  state.last_updated_by = 'harness-cycle';
  writeState(root, state);
  console.log(`recorded deploy ${status} for ${runId}`);
}

function recordCommand(root, options) {
  const state = readState(root);
  const runId = requireString(options['run-id'] ?? state.active_run_id, 'run-id');
  assertRunMatches(state, runId);
  assertActiveRun(state);
  assertLockHeld(root, state, runId);
  const runDir = runDirFor(state, runId, root);
  const entry = commandEntryFromOptions(state, runId, options, {
    command: requireString(options.command, 'command'),
    cwd: root,
    canonical_basis: options['canonical-basis'] ? String(options['canonical-basis']) : 'manual-verification',
    essential_for_approval: parseBooleanOption(options['essential-for-approval'], true, 'essential-for-approval'),
  });
  appendCommandLog(runDir, entry);
  state.updated_at = nowIso();
  state.last_updated_by = 'harness-cycle';
  state.git.head_commit = getHeadCommit(root);
  writeState(root, state);
  console.log(`recorded command ${entry.status} for ${runId}`);
}

function generateImpact(root, options) {
  const state = readState(root);
  const runId = requireString(options['run-id'] ?? state.active_run_id, 'run-id');
  assertRunMatches(state, runId);
  assertActiveRun(state);
  assertLockHeld(root, state, runId);
  const runDir = runDirFor(state, runId, root);
  const manifest = buildImpactManifest(root, state, runId);
  assertSchemaValid(root, 'impact-manifest.schema.json', manifest, 'impact-manifest.json');
  writeJsonAtomic(join(runDir, 'impact-manifest.json'), manifest);
  state.audit_quorum = {
    ...(state.audit_quorum ?? emptyAuditQuorum(runId)),
    run_id: runId,
    impact_manifest_hash: hashJson(manifest),
  };
  state.git.head_commit = manifest.head_commit;
  state.updated_at = nowIso();
  state.last_updated_by = 'harness-cycle';
  writeState(root, state);
  console.log(`impact_manifest=${toRepoPath(join(state.reports_root || DEFAULT_REPORTS_ROOT, runId, 'impact-manifest.json'))}`);
}

function unlock(root, options) {
  const state = readState(root);
  const runId = requireString(options['run-id'] ?? state.active_run_id, 'run-id');
  const reason = requireString(options.reason, 'reason/motivo');
  if (state.lock.active && state.lock.run_id !== runId) {
    fail(`lock pertence a outra run: ${state.lock.run_id}`);
  }
  const lockPath = join(root, LOCK_RELATIVE_PATH);
  if (existsSync(lockPath)) {
    const lockFile = readJsonFile(lockPath, LOCK_RELATIVE_PATH);
    if (lockFile.run_id !== runId) {
      fail(`arquivo de lock pertence a outra run: ${lockFile.run_id}`);
    }
  } else if (state.lock.active) {
    fail(`blocked_environment: arquivo de lock ausente em ${LOCK_RELATIVE_PATH}.`);
  }
  if (state.active_run_id === runId) {
    const now = nowIso();
    const layer = state.active_layer;
    const cycle = state.active_cycle;
    if (layer === null || cycle === null) {
      fail('blocked_policy: unlock encontrou run ativa sem camada/ciclo.');
    }
    const report = toRepoPath(join(state.reports_root || DEFAULT_REPORTS_ROOT, runId, 'report.md'));
    const layerState = state.layers[String(layer)];
    layerState.status = 'blocked';
    layerState.last_cycle = cycle;
    layerState.last_report = report;
    state.history_summary.push({ run_id: runId, layer, cycle, decision: 'blocked', report, reason: `lock_removed: ${reason}` });
    appendRunReport(state, runId, root, `\n## Unlock Manual\n\n- motivo: ${reason}\n- horario: ${now}\n`);
    state.current_phase = 'idle';
    state.cycle_state = 'idle';
    state.active_layer = null;
    state.active_cycle = null;
    state.active_run_id = null;
    state.active_audit_mode = null;
    state.blocking_reason = null;
    state.escalation_required = false;
    state.deployment_authorization = emptyDeploymentAuthorization();
    state.git.head_commit = getHeadCommit(root);
    state.git.working_tree_clean_at_close = getDirtyPaths(root).length === 0;
  }
  state.lock = emptyLock();
  state.updated_at = nowIso();
  state.last_updated_by = 'harness-cycle';
  removeLockIfMatches(root, runId);
  writeState(root, state);
  console.log(`unlocked ${runId}`);
}

function readState(root) {
  const statePath = join(root, STATE_RELATIVE_PATH);
  if (!existsSync(statePath)) {
    fail(`blocked_missing_context: manifesto ausente em ${STATE_RELATIVE_PATH}. Execute init apenas no bootstrap inicial.`);
  }
  return readJsonFile(statePath, STATE_RELATIVE_PATH);
}

function writeState(root, state) {
  writeJsonAtomic(join(root, STATE_RELATIVE_PATH), state);
}

function writeJsonAtomic(filePath, value) {
  mkdirSync(dirname(filePath), { recursive: true });
  const tempPath = `${filePath}.tmp-${process.pid}`;
  writeFileSync(tempPath, `${JSON.stringify(value, null, 2)}\n`, 'utf8');
  renameSync(tempPath, filePath);
}

function readJsonFile(filePath, label) {
  try {
    return JSON.parse(readFileSync(filePath, 'utf8'));
  } catch (error) {
    fail(`harness_output_invalid: ${label} JSON invalido: ${error.message}`);
  }
}

function appendCommandLog(runDir, entry) {
  validateCommandEntry(entry);
  mkdirSync(runDir, { recursive: true });
  writeFileSync(join(runDir, 'commands.log.jsonl'), `${JSON.stringify(entry)}\n`, { encoding: 'utf8', flag: 'a' });
}

function commandEntryFromOptions(state, runId, options, defaults = {}) {
  const status = requireString(options.status, 'status');
  if (!COMMAND_STATUS.has(status)) {
    fail(`status de comando invalido: ${status}`);
  }
  const entry = {
    schema_version: 1,
    run_id: runId,
    audited_layer: state.active_layer,
    cycle: state.active_cycle,
    command: String(defaults.command ?? options.command),
    cwd: String(options.cwd ?? defaults.cwd ?? process.cwd()),
    started_at: String(options['started-at'] ?? nowIso()),
    finished_at: String(options['finished-at'] ?? nowIso()),
    exit_code: options['exit-code'] === undefined ? null : Number(options['exit-code']),
    status,
    stdout_excerpt: options.stdout ? String(options.stdout) : null,
    stderr_excerpt: options.stderr ? String(options.stderr) : null,
    replacement_for: options['replacement-for'] ? String(options['replacement-for']) : null,
    original_command: options['original-command'] ? String(options['original-command']) : String(defaults.command ?? options.command),
    effective_command: options['effective-command'] ? String(options['effective-command']) : String(defaults.command ?? options.command),
    waiver_basis: options['waiver-basis'] ? String(options['waiver-basis']) : null,
    canonical_basis: options['canonical-basis'] ? String(options['canonical-basis']) : (defaults.canonical_basis ?? null),
    approved_by: options['approved-by'] ? String(options['approved-by']) : null,
    justification: options.justification ? String(options.justification) : null,
    essential_for_approval: defaults.essential_for_approval ?? parseBooleanOption(options['essential-for-approval'], false, 'essential-for-approval'),
    actor_role: requireString(options['actor-role'] ?? defaults.actor_role, 'actor-role'),
    agent_id: requireString(options['agent-id'] ?? defaults.agent_id, 'agent-id'),
    review_round: Number(options['review-round'] ?? defaults.review_round ?? state.active_cycle),
    context_fingerprint: requireString(options['context-fingerprint'] ?? defaults.context_fingerprint, 'context-fingerprint'),
    source_bundle_hash: requireString(options['source-bundle-hash'] ?? defaults.source_bundle_hash, 'source-bundle-hash'),
    environment: commandEnvironment(),
  };
  entry.output_hash = hashJson({
    command: entry.command,
    cwd: entry.cwd,
    status: entry.status,
    exit_code: entry.exit_code,
    stdout_excerpt: entry.stdout_excerpt,
    stderr_excerpt: entry.stderr_excerpt,
  });
  return entry;
}

function validateCommandEntry(entry) {
  if (!COMMAND_STATUS.has(entry.status)) {
    fail(`status de comando invalido: ${entry.status}`);
  }
  if (!COMMAND_ACTOR_ROLES.has(entry.actor_role)) {
    fail(`actor_role de comando invalido: ${entry.actor_role}`);
  }
  if (!entry.agent_id || !entry.context_fingerprint || !entry.source_bundle_hash) {
    fail('blocked_policy: comando exige agent_id, context_fingerprint e source_bundle_hash.');
  }
  if (!Number.isInteger(entry.review_round) || entry.review_round < 1 || entry.review_round > MAX_LAYER_CYCLES) {
    fail('blocked_policy: comando exige review_round entre 1 e 10.');
  }
  if (entry.essential_for_approval && entry.actor_role === 'orchestrator') {
    fail('blocked_policy: comando essencial de aprovacao nao pode ser atribuido ao orquestrador.');
  }
  if (entry.status === 'passed' && entry.exit_code !== 0) {
    fail('blocked_policy: comando passed exige exit_code=0.');
  }
  if (entry.status === 'replaced_by_equivalent') {
    for (const key of ['original_command', 'effective_command', 'canonical_basis', 'approved_by', 'justification']) {
      if (!entry[key]) {
        fail(`blocked_policy: comando equivalente exige ${key}.`);
      }
    }
  }
  if (entry.status === 'waived_by_policy') {
    for (const key of ['waiver_basis', 'canonical_basis', 'approved_by']) {
      if (!entry[key]) {
        fail(`blocked_policy: waiver exige ${key}.`);
      }
    }
    if (entry.essential_for_approval) {
      fail('blocked_policy: comando essencial nao pode ser waived_by_policy.');
    }
  }
  if (!entry.environment?.node_version || !entry.environment?.platform || !entry.environment?.arch) {
    fail('blocked_policy: comando exige fingerprint de ambiente.');
  }
  if (!/^sha256:[0-9a-f]{64}$/.test(String(entry.output_hash ?? ''))) {
    fail('blocked_policy: comando exige output_hash sha256.');
  }
}

function commandEnvironment() {
  return {
    node_version: process.version,
    platform: process.platform,
    arch: process.arch,
    pid: process.pid,
    shell: process.env.ComSpec || process.env.SHELL || null,
  };
}

function buildImpactManifest(root, state, runId) {
  const layer = state.active_layer;
  const cycle = state.active_cycle;
  if (layer === null || cycle === null) {
    fail('blocked_policy: impact manifest exige run ativa com camada/ciclo.');
  }
  const headCommit = getHeadCommit(root);
  const changedFiles = expectedChangedFiles(root, state);
  const dirtyPaths = nonRuntimeDirtyPaths(getDirtyPaths(root), runId);
  return {
    schema_version: 1,
    run_id: runId,
    layer,
    cycle,
    audit_mode: state.active_audit_mode,
    generated_at: nowIso(),
    base_commit: state.git.base_commit,
    head_commit: headCommit,
    target_scope: state.target_scope,
    changed_files: changedFiles,
    dirty_paths: dirtyPaths,
    surfaces: classifyImpactSurfaces(changedFiles),
    risk_surfaces: classifyRiskSurfaces(layer, changedFiles),
    assumptions: [
      'Manifesto gerado por heuristica local de paths; nao substitui leitura dos auditores nem execucao de testes.',
    ],
  };
}

function classifyImpactSurfaces(paths) {
  const surfaces = {
    routes: [],
    controllers: [],
    form_requests: [],
    resources: [],
    policies_or_middleware: [],
    models: [],
    migrations_or_schema: [],
    frontend: [],
    tests: [],
    e2e: [],
    infra: [],
    harness: [],
    docs: [],
    other: [],
  };
  for (const pathValue of paths) {
    const path = normalizeRepoPath(pathValue);
    const bucket = impactSurfaceForPath(path);
    surfaces[bucket].push(path);
  }
  for (const key of Object.keys(surfaces)) {
    surfaces[key].sort();
  }
  return surfaces;
}

function impactSurfaceForPath(path) {
  if (/^backend\/routes\//.test(path)) return 'routes';
  if (/^backend\/app\/Http\/Controllers\//.test(path)) return 'controllers';
  if (/^backend\/app\/Http\/Requests\//.test(path)) return 'form_requests';
  if (/^backend\/app\/Http\/Resources\//.test(path)) return 'resources';
  if (/^backend\/app\/(Http\/Middleware|Policies)\//.test(path)) return 'policies_or_middleware';
  if (/^backend\/app\/Models\//.test(path)) return 'models';
  if (/^backend\/database\/(migrations|schema)\//.test(path)) return 'migrations_or_schema';
  if (/^frontend\/src\//.test(path)) return 'frontend';
  if (/^(backend\/tests\/|frontend\/src\/.*\.test\.|frontend\/tests\/)/.test(path)) return 'tests';
  if (/^(e2e\/|frontend\/e2e\/|playwright\.config\.)/.test(path)) return 'e2e';
  if (/^(Dockerfile|docker-compose|\.github\/|nginx\/|\.env\.example$)/.test(path)) return 'infra';
  if (/^(docs\/harness\/|\.agent\/|AGENTS\.md$|scripts\/harness-cycle\.)/.test(path)) return 'harness';
  if (/^docs\//.test(path)) return 'docs';
  return 'other';
}

function classifyRiskSurfaces(layer, paths) {
  const risks = new Set();
  if ([1, 7].includes(layer)) {
    risks.add(layer === 1 ? 'auth-tenant' : 'production-ops');
  }
  for (const pathValue of paths) {
    const path = normalizeRepoPath(pathValue);
    const surface = impactSurfaceForPath(path);
    if (['routes', 'controllers', 'form_requests', 'resources'].includes(surface)) {
      risks.add('api-contract');
    }
    if (['policies_or_middleware'].includes(surface) || /Auth|Tenant|Permission|Policy|Middleware/i.test(path)) {
      risks.add('auth-tenant');
    }
    if (['models', 'migrations_or_schema'].includes(surface)) {
      risks.add('data-schema');
    }
    if (surface === 'frontend') {
      risks.add('frontend-contract');
    }
    if (surface === 'e2e') {
      risks.add('e2e-runtime');
    }
    if (['infra', 'harness'].includes(surface) || /^deploy\.sh$/.test(path)) {
      risks.add('ops-provenance');
    }
  }
  return [...risks].sort();
}

function hashJson(value) {
  return `sha256:${createHash('sha256').update(JSON.stringify(value)).digest('hex')}`;
}

function hashFile(filePath) {
  return `sha256:${createHash('sha256').update(readFileSync(filePath)).digest('hex')}`;
}

function initialConsolidated(runId, layer, cycle, auditMode, headCommit) {
  return {
    schema_version: 1,
    run_id: runId,
    layer,
    cycle,
    audit_mode: auditMode,
    status: 'blocked',
    decision: 'blocked',
    target_scope: { type: 'layer', paths: [], finding_ids: [], context: 'pending audit' },
    base_commit: headCommit,
    head_commit: headCommit,
    review_round: cycle,
    max_review_rounds: MAX_LAYER_CYCLES,
    auditor_quorum_size: REQUIRED_AUDITORS.length,
    audit_coverage: {
      executed_auditors: [],
      distinct_agent_ids: [],
      quorum_met: false,
      coverage_gaps: ['pending auditor outputs'],
      unresolved_required_verifications: [],
      commands_log_present: false,
      impact_manifest_present: false,
    },
    fixer_agent_id: null,
    findings: [],
    harness_errors: [{ type: 'harness_incomplete_run', message: 'pending auditor outputs' }],
    next_action: 'reaudit',
  };
}

function initialReport(runId, layer, cycle, auditMode, headCommit, dirtyPaths) {
  return `# Harness Run: layer ${layer} cycle ${cycle}

## Escopo

- \`run_id\`: \`${runId}\`
- camada: ${layer}
- ciclo: ${cycle}
- \`audit_mode\`: \`${auditMode}\`
- \`target_scope\`: layer

## Proveniencia Git

- \`base_commit\`: \`${headCommit ?? 'unknown'}\`
- \`head_commit\`: \`${headCommit ?? 'unknown'}\`
- \`approved_commit\`:
- \`working_tree_clean_at_start\`: ${dirtyPaths.length === 0}
- \`dirty_paths_at_start\`: ${dirtyPaths.length === 0 ? '[]' : dirtyPaths.join(', ')}

## Modo de Auditoria

- modo: \`${auditMode}\`
- aprovacao exige: cinco auditores independentes, contexto limpo e audit_mode=full

## Manifesto de Impacto

Pendente. Gerar com \`node scripts/harness-cycle.mjs generate-impact --run-id ${runId}\` antes dos auditores.

## Proveniencia dos Agentes

Pendente. O orquestrador nao pode auditar nem corrigir codigo.

## Auditores Executados

Pendente.

## Findings Consolidados

Pendente.

## Divergencias e Resolucao

Pendente.

## Arquivos Alterados

Pendente.

## Comandos e Evidencias

Fonte canonica: \`commands.log.jsonl\`.

## Deploy

Pendente.

## Decisao Final

Pendente.

## Riscos Remanescentes

Pendente.

## Proximo Passo

Executar auditoria conforme \`docs/harness/autonomous-orchestrator.md\`.
`;
}

function initialState() {
  const layers = {};
  const dependencies = {
    0: [],
    1: [0],
    2: [0, 1],
    3: [0, 2],
    4: [0, 1, 2, 3],
    5: [0, 2, 3],
    6: [0, 1, 2, 3, 4, 5],
    7: [0, 1, 2, 3, 4, 5, 6],
  };
  for (const layer of Object.keys(dependencies)) {
    layers[layer] = {
      status: 'not_started',
      last_cycle: null,
      last_report: null,
      approved_report_provenance: {
        cycle: null,
        report: null,
        base_commit: null,
        head_commit: null,
        approved_commit: null,
        approved_at: null,
      },
      depends_on: dependencies[layer],
      layer_dependencies_resolved: layer === '0',
      invalidated_by: null,
    };
  }

  return {
    schema_version: 1,
    protocol_version: '0.1.0',
    dependency_matrix_version: '0.1.0',
    updated_at: nowIso(),
    last_updated_by: 'harness-cycle',
    active_layer: null,
    active_cycle: null,
    active_run_id: null,
    active_audit_mode: null,
    target_scope: { type: 'layer', paths: [], finding_ids: [], context: null },
    current_phase: 'idle',
    cycle_state: 'idle',
    escalation_required: false,
    blocking_reason: null,
    reports_root: DEFAULT_REPORTS_ROOT,
    lock: emptyLock(),
    deployment_authorization: emptyDeploymentAuthorization(),
    audit_quorum: emptyAuditQuorum(null),
    git: {
      base_commit: null,
      head_commit: null,
      approved_commit: null,
      working_tree_clean_at_start: null,
      working_tree_clean_at_close: null,
      dirty_paths_at_start: [],
    },
    history_summary: [],
    layers,
  };
}

function emptyLock() {
  return { active: false, path: null, run_id: null, created_at: null, owner: null, pid: null };
}

function emptyDeploymentAuthorization() {
  return {
    authorized: false,
    run_id: null,
    layer: null,
    cycle: null,
    authorized_by: null,
    authorized_at: null,
    authorized_head_commit: null,
    target_environment: null,
    target_commit: null,
    deploy_command: null,
    allow_migrations: false,
    migration_diff_checked: false,
    requires_backup: true,
    rollback_command: null,
    required_health_checks: [],
  };
}

function emptyAuditQuorum(runId) {
  return {
    run_id: runId,
    executed_auditors: [],
    distinct_agent_ids: [],
    quorum_met: false,
    commands_log_present: false,
    commands_log_hash: null,
    impact_manifest_hash: null,
  };
}

function parseLayer(value) {
  const layer = Number(value);
  if (!Number.isInteger(layer) || !VALID_LAYERS.has(layer)) {
    fail(`layer invalida: ${value}`);
  }
  return layer;
}

function parseAuditMode(value) {
  const mode = String(value);
  if (!VALID_AUDIT_MODES.has(mode)) {
    fail(`audit_mode invalido: ${mode}`);
  }
  return mode;
}

function parseTargetScope(options) {
  const type = String(options['scope-type'] ?? 'layer');
  if (!['layer', 'module', 'finding_set'].includes(type)) {
    fail(`target_scope.type invalido: ${type}`);
  }
  const paths = asArray(options.path ?? []);
  const findingIds = asArray(options['finding-id'] ?? []);
  if ((type === 'module' && paths.length === 0) || (type === 'finding_set' && findingIds.length === 0)) {
    fail(`target_scope ${type} exige paths ou finding_ids`);
  }
  return {
    type,
    paths,
    finding_ids: findingIds,
    context: options.context ? String(options.context) : null,
  };
}

function assertNoActiveRun(root, state) {
  if (
    state.current_phase !== 'idle' ||
    state.active_run_id !== null ||
    state.active_layer !== null ||
    state.active_cycle !== null ||
    state.active_audit_mode !== null ||
    state.lock.active ||
    existsSync(join(root, LOCK_RELATIVE_PATH))
  ) {
    fail('blocked_environment: ja existe run ativa ou lock pendente.');
  }
}

function assertRunMatches(state, runIdOption) {
  const runId = runIdOption ? String(runIdOption) : state.active_run_id;
  if (!runId) {
    fail('run-id ausente e nao ha run ativa.');
  }
  if (state.active_run_id && state.active_run_id !== runId) {
    fail(`run-id divergente: ativo=${state.active_run_id}, recebido=${runId}`);
  }
}

function assertRecordTransition(currentState, nextState) {
  const allowed = VALID_RECORD_TRANSITIONS[currentState];
  if (!allowed?.has(nextState)) {
    fail(`blocked_policy: transicao de ciclo invalida: ${currentState} -> ${nextState}.`);
  }
}

function assertLayerStartAllowed(layerState, layer, options) {
  if (layerState.status === 'approved') {
    const invalidationReason = requireString(options['invalidation-reason'], 'invalidation-reason/invalidacao');
    layerState.invalidated_by = `approved_invalidated: ${invalidationReason}`;
    return;
  }
  if (layerState.status === 'escalated') {
    const humanDecision = requireString(options['human-decision'], 'human-decision/decisao humana');
    layerState.invalidated_by = `human_decision: ${humanDecision}`;
    return;
  }
  if (layerState.status === 'in_progress') {
    fail(`blocked_policy: camada ${layer} ja esta in_progress sem run ativa consistente.`);
  }
}

function assertActiveRun(state) {
  if (!state.active_run_id || state.active_layer === null || state.active_cycle === null) {
    fail('blocked_policy: comando exige run ativa.');
  }
}

function assertLockHeld(root, state, runId) {
  if (!state.lock?.active) {
    fail('blocked_environment: lock da run ausente no manifesto.');
  }
  if (state.lock.run_id !== runId) {
    fail(`blocked_environment: lock aponta para ${state.lock.run_id}, mas a run solicitada e ${runId}.`);
  }
  const lockPath = join(root, LOCK_RELATIVE_PATH);
  if (!existsSync(lockPath)) {
    fail(`blocked_environment: arquivo de lock ausente em ${LOCK_RELATIVE_PATH}.`);
  }
  const lockFile = readJsonFile(lockPath, LOCK_RELATIVE_PATH);
  if (lockFile.run_id !== runId) {
    fail(`blocked_environment: arquivo de lock aponta para ${lockFile.run_id}, mas a run solicitada e ${runId}.`);
  }
}

function assertDeployAuthorized(root, state, runId) {
  assertLayer7Run(state);
  assertLockHeld(root, state, runId);
  if (state.active_run_id && state.active_run_id !== runId) {
    fail(`run-id divergente: ativo=${state.active_run_id}, recebido=${runId}`);
  }
  const auth = state.deployment_authorization;
  if (!auth.authorized) {
    fail('blocked_policy: deployment_authorization ausente.');
  }
  if (auth.run_id !== runId || auth.layer !== state.active_layer || auth.cycle !== state.active_cycle) {
    fail('blocked_policy: deployment_authorization pertence a outra run/camada/ciclo.');
  }
  for (const key of ['target_environment', 'target_commit', 'deploy_command', 'rollback_command', 'authorized_by', 'authorized_at']) {
    if (!auth[key]) {
      fail(`blocked_policy: deployment_authorization.${key} ausente.`);
    }
  }
  if (!auth.authorized_head_commit) {
    fail('blocked_policy: deployment_authorization.authorized_head_commit ausente.');
  }
  const headCommit = requireDeployHeadCommit(root, auth.target_environment);
  if (auth.authorized_head_commit !== headCommit) {
    fail(`blocked_policy: deployment_authorization.authorized_head_commit ${auth.authorized_head_commit} diverge do HEAD ${headCommit}.`);
  }
  if (auth.target_environment === 'production' && (!auth.requires_backup || auth.required_health_checks.length === 0)) {
    fail('blocked_policy: producao exige backup e health checks.');
  }
  if (!state.layers['7']?.layer_dependencies_resolved) {
    fail('blocked_policy: deploy exige dependencias 0-6 aprovadas.');
  }
  assertMigrationPolicy(auth);
  if (auth.target_environment === 'production' && !auth.migration_diff_checked) {
    fail('blocked_policy: producao exige diff de migrations verificado.');
  }
  assertTargetCommit(root, auth.target_commit);
}

function assertMigrationPolicy(auth) {
  const violation = migrationPolicyViolation(auth);
  if (violation) {
    fail(`blocked_policy: ${violation}`);
  }
}

function migrationPolicyViolation(auth) {
  if (commandUsesShellExpansion(auth.deploy_command)) {
    return 'deploy_command usa expansao shell nao permitida porque pode ocultar migration em deploy autorizado.';
  }
  if (commandRequestsDestructiveMigration(auth.deploy_command)) {
    return 'deploy_command contem migration destrutiva proibida em producao.';
  }
  if (auth.allow_migrations !== false) {
    return null;
  }
  if (commandRequestsMigration(auth.deploy_command)) {
    return 'deploy_command contem migrate/migration, mas allow_migrations=false.';
  }
  return null;
}

function commandUsesShellExpansion(command) {
  return /[`$]/.test(String(command ?? ''));
}

function commandRequestsDestructiveMigration(command) {
  const normalized = normalizeDeployCommand(command);
  return /(^|[\s;&|])(migrate:(fresh|reset|refresh|rollback)|db:wipe)(?=$|[\s;&|=:])/i.test(normalized);
}

function commandRequestsMigration(command) {
  const normalized = normalizeDeployCommand(command);
  return /(^|[\s;&|])(--migrate(?:[=:]\S*)?|migrate(?::[a-z0-9_-]+)?(?:[=:]\S*)?)(?=$|[\s;&|])/i.test(normalized);
}

function normalizeDeployCommand(command) {
  return String(command ?? '')
    .replace(/\\U([0-9a-fA-F]{8})/g, (_, code) => String.fromCodePoint(Number.parseInt(code, 16)))
    .replace(/\\u([0-9a-fA-F]{4})/g, (_, code) => String.fromCodePoint(Number.parseInt(code, 16)))
    .replace(/\\x([0-9a-fA-F]{2})/g, (_, code) => String.fromCodePoint(Number.parseInt(code, 16)))
    .replace(/\\([0-7]{1,3})/g, (_, code) => String.fromCodePoint(Number.parseInt(code, 8)))
    .replace(/\\(["'=])/g, '$1')
    .replace(/['"]/g, '')
    .replace(/\\/g, '')
    .replace(/\$/g, '');
}

function requireDeployHeadCommit(root, targetEnvironment) {
  const headCommit = getHeadCommit(root);
  if (!headCommit) {
    fail(`blocked_policy: deploy para ${targetEnvironment} exige repositorio Git com HEAD conhecido.`);
  }
  return headCommit;
}

function assertLayer7Run(state) {
  if (!state.active_run_id || state.active_layer !== 7) {
    fail('blocked_policy: deploy exige run ativa da camada 7.');
  }
}

function assertTargetCommit(root, targetCommit) {
  if (!/^[0-9a-f]{7,40}$/i.test(targetCommit)) {
    fail(`blocked_policy: target_commit invalido: ${targetCommit}`);
  }
  const headCommit = getHeadCommit(root);
  if (headCommit && targetCommit !== headCommit && !headCommit.startsWith(targetCommit)) {
    fail(`blocked_policy: target_commit ${targetCommit} diverge do HEAD ${headCommit}.`);
  }
  if (isGitRepo(root) && !remoteContainsCommit(root, targetCommit)) {
    fail(`blocked_policy: target_commit ${targetCommit} nao encontrado em branch remota.`);
  }
}

function assertApprovalArtifacts(root, state, runId, layer, cycle) {
  const runDir = runDirFor(state, runId, root);
  const headCommit = getHeadCommit(root);
  if (state.active_audit_mode !== 'full') {
    fail('blocked_policy: aprovacao de camada exige audit_mode=full com cinco auditores independentes.');
  }
  const consolidated = readRunJson(runDir, 'consolidated-findings.json');
  assertSchemaValid(root, 'consolidated-findings.schema.json', consolidated, 'consolidated-findings.json');
  if (consolidated.run_id !== runId || consolidated.layer !== layer || consolidated.cycle !== cycle) {
    fail('blocked_policy: consolidated-findings.json nao corresponde a run ativa.');
  }
  if (consolidated.audit_mode !== state.active_audit_mode || consolidated.audit_mode !== 'full') {
    fail('blocked_policy: consolidated-findings.json precisa corresponder ao audit_mode full da run ativa.');
  }
  if (
    consolidated.review_round !== cycle ||
    consolidated.max_review_rounds !== MAX_LAYER_CYCLES ||
    consolidated.auditor_quorum_size !== REQUIRED_AUDITORS.length
  ) {
    fail('blocked_policy: consolidated-findings.json precisa registrar rodada e quorum de auditoria da run ativa.');
  }
  if (consolidated.base_commit !== state.git.base_commit || consolidated.head_commit !== headCommit) {
    fail('harness_provenance_mismatch: consolidated-findings.json diverge da proveniencia Git da run ativa.');
  }
  if (state.git.base_commit !== headCommit && (!consolidated.fixer_agent_id || consolidated.fixer_agent_id === 'orchestrator')) {
    fail('blocked_policy: aprovacao apos correcao exige fixer_agent_id separado do orquestrador.');
  }
  if (consolidated.status !== 'approved' || consolidated.decision !== 'approve') {
    fail('blocked_policy: consolidated-findings.json precisa estar approved/approve.');
  }
  if (Array.isArray(consolidated.harness_errors) && consolidated.harness_errors.length > 0) {
    fail('blocked_policy: harness_errors abertos impedem aprovacao.');
  }
  if ((consolidated.findings ?? []).some((finding) => finding.blocking)) {
    fail('blocked_policy: findings bloqueantes ainda impedem aprovacao.');
  }
  assertImpactManifest(root, state, runDir, runId, layer, cycle, headCommit);

  assertExpectedAuditorFilesOnly(runDir);
  const requiredAuditors = REQUIRED_AUDITORS;
  const seenAgentIds = new Set();
  const executedAuditors = [];
  for (const auditor of requiredAuditors) {
    const output = readRunJson(runDir, auditor.file);
    assertSchemaValid(root, 'auditor-output.schema.json', output, auditor.file);
    if (output.auditor !== auditor.id || output.run_id !== runId || output.layer !== layer || output.cycle !== cycle) {
      fail(`blocked_policy: ${auditor.file} nao corresponde ao auditor/run/camada/ciclo esperado.`);
    }
    assertAuditorProvenance(output, auditor.file, state.git.base_commit, headCommit);
    assertAuditorAgentProvenance(output, auditor.file, seenAgentIds);
    if (output.status !== 'approved') {
      fail(`blocked_policy: auditor ${auditor.id} nao aprovou.`);
    }
    if ((output.harness_errors ?? []).length > 0) {
      fail(`blocked_policy: auditor ${auditor.id} possui harness_errors abertos.`);
    }
    if ((output.findings ?? []).some((finding) => finding.blocking)) {
      fail(`blocked_policy: auditor ${auditor.id} possui finding bloqueante.`);
    }
    assertAuditorLimitations(output, auditor.file);
    executedAuditors.push({
      auditor: output.auditor,
      agent_id: output.agent_provenance.agent_id,
      artifact: auditor.file,
    });
  }

  const commandEntries = readCommandLog(runDir);
  if (commandEntries.length === 0) {
    fail('blocked_policy: aprovacao exige commands.log.jsonl com evidencias.');
  }
  for (const [index, entry] of commandEntries.entries()) {
    assertSchemaValid(root, 'commands-log.schema.json', entry, `commands.log.jsonl:${index + 1}`);
    validateCommandEntry(entry);
    assertCommandEntryProvenance(entry, runId, layer, cycle);
  }
  const essentialEntries = commandEntries.filter((entry) => entry.essential_for_approval);
  if (!essentialEntries.some((entry) => entry.status === 'passed' && entry.exit_code === 0)) {
    fail('blocked_policy: aprovacao exige pelo menos um comando essencial com status passed e exit_code=0.');
  }
  const blockedEssential = essentialEntries.find((entry) =>
    ['failed', 'not_executed', 'blocked_environment', 'blocked_policy', 'waived_by_policy'].includes(entry.status),
  );
  if (blockedEssential) {
    fail(`blocked_policy: comando essencial nao aprovado: ${blockedEssential.command}`);
  }
  assertConsolidatedCoverage(consolidated, executedAuditors, commandEntries);
  state.audit_quorum = {
    run_id: runId,
    executed_auditors: executedAuditors,
    distinct_agent_ids: [...seenAgentIds].sort(),
    quorum_met: true,
    commands_log_present: commandEntries.length > 0,
    commands_log_hash: hashFile(join(runDir, 'commands.log.jsonl')),
    impact_manifest_hash: hashFile(join(runDir, 'impact-manifest.json')),
  };
}

function assertExpectedAuditorFilesOnly(runDir) {
  const expected = new Set(REQUIRED_AUDITORS.map((auditor) => auditor.file));
  const found = readdirSync(runDir).filter((fileName) => /^auditor-.+\.json$/.test(fileName));
  for (const fileName of found) {
    if (!expected.has(fileName)) {
      fail(`blocked_policy: auditor inesperado na run: ${fileName}.`);
    }
  }
  for (const fileName of expected) {
    if (!found.includes(fileName)) {
      fail(`blocked_policy: aprovacao exige os cinco auditores obrigatorios; ausente: ${fileName}.`);
    }
  }
}

function assertImpactManifest(root, state, runDir, runId, layer, cycle, headCommit) {
  const manifest = readRunJson(runDir, 'impact-manifest.json');
  assertSchemaValid(root, 'impact-manifest.schema.json', manifest, 'impact-manifest.json');
  if (manifest.run_id !== runId || manifest.layer !== layer || manifest.cycle !== cycle) {
    fail('blocked_policy: impact-manifest.json nao corresponde a run ativa.');
  }
  if (manifest.audit_mode !== state.active_audit_mode) {
    fail('blocked_policy: impact-manifest.json diverge do audit_mode ativo.');
  }
  if (manifest.base_commit !== state.git.base_commit || manifest.head_commit !== headCommit) {
    fail('harness_provenance_mismatch: impact-manifest.json esta desatualizado em relacao ao HEAD da run.');
  }
}

function assertAuditorProvenance(output, fileName, baseCommit, headCommit) {
  const provenance = output.provenance ?? {};
  if (provenance.base_commit !== baseCommit || provenance.head_commit !== headCommit) {
    fail(`harness_provenance_mismatch: ${fileName} diverge da proveniencia Git da run ativa.`);
  }
  if (provenance.working_tree_state !== 'clean') {
    fail(`harness_provenance_mismatch: ${fileName} exige working_tree_state=clean para aprovacao.`);
  }
}

function assertAuditorAgentProvenance(output, fileName, seenAgentIds) {
  const agent = output.agent_provenance ?? {};
  if (agent.agent_role !== 'auditor-readonly') {
    fail(`blocked_policy: ${fileName} deve ser gerado por auditor-readonly.`);
  }
  if (agent.context_mode !== 'clean' || agent.conversation_isolated !== true) {
    fail(`blocked_policy: ${fileName} exige contexto limpo e isolado.`);
  }
  if (agent.orchestrator_generated !== false) {
    fail(`blocked_policy: ${fileName} nao pode ser gerado pelo orquestrador.`);
  }
  if (!agent.agent_id || seenAgentIds.has(agent.agent_id)) {
    fail(`blocked_policy: ${fileName} exige agent_id unico entre os cinco auditores.`);
  }
  if (output.scope?.readonly !== true) {
    fail(`blocked_policy: ${fileName} exige scope.readonly=true.`);
  }
  seenAgentIds.add(agent.agent_id);
}

function assertAuditorLimitations(output, fileName) {
  const limitations = output.audit_limitations;
  if (!limitations) {
    fail(`blocked_policy: ${fileName} exige audit_limitations com cegueiras, premissas e verificacoes pendentes.`);
  }
  for (const key of ['not_inspected', 'assumptions', 'required_verifications_not_executed']) {
    if (!Array.isArray(limitations[key])) {
      fail(`blocked_policy: ${fileName} audit_limitations.${key} deve ser array.`);
    }
  }
  if (limitations.required_verifications_not_executed.length > 0) {
    fail(`blocked_policy: ${fileName} possui verificacoes obrigatorias nao executadas: ${limitations.required_verifications_not_executed.join('; ')}`);
  }
}

function assertConsolidatedCoverage(consolidated, executedAuditors, commandEntries) {
  const coverage = consolidated.audit_coverage;
  if (!coverage) {
    fail('blocked_policy: consolidated-findings.json exige audit_coverage com quorum e lacunas.');
  }
  if (coverage.quorum_met !== true) {
    fail('blocked_policy: consolidated-findings.json precisa declarar quorum_met=true.');
  }
  const expectedAuditors = REQUIRED_AUDITORS.map((auditor) => auditor.id).sort();
  const actualAuditors = (coverage.executed_auditors ?? []).map((entry) => entry.auditor).sort();
  if (JSON.stringify(actualAuditors) !== JSON.stringify(expectedAuditors)) {
    fail('blocked_policy: audit_coverage.executed_auditors precisa listar os cinco auditores obrigatorios.');
  }
  const executedByAuditor = new Map(executedAuditors.map((entry) => [entry.auditor, entry]));
  for (const coverageEntry of coverage.executed_auditors ?? []) {
    const executedEntry = executedByAuditor.get(coverageEntry.auditor);
    if (!executedEntry || coverageEntry.agent_id !== executedEntry.agent_id || coverageEntry.artifact !== executedEntry.artifact) {
      fail('blocked_policy: audit_coverage.executed_auditors diverge dos auditores executados.');
    }
  }
  const coverageAgentIds = new Set((coverage.executed_auditors ?? []).map((entry) => entry.agent_id));
  const executedAgentIds = new Set(executedAuditors.map((entry) => entry.agent_id));
  if (coverageAgentIds.size !== REQUIRED_AUDITORS.length || coverageAgentIds.size !== executedAgentIds.size) {
    fail('blocked_policy: audit_coverage precisa comprovar cinco agent_id distintos.');
  }
  for (const agentId of executedAgentIds) {
    if (!coverageAgentIds.has(agentId)) {
      fail('blocked_policy: audit_coverage diverge dos agent_id dos auditores.');
    }
  }
  const declaredDistinctAgentIds = [...new Set(coverage.distinct_agent_ids ?? [])].sort();
  const actualDistinctAgentIds = [...executedAgentIds].sort();
  if (declaredDistinctAgentIds.length !== REQUIRED_AUDITORS.length || JSON.stringify(declaredDistinctAgentIds) !== JSON.stringify(actualDistinctAgentIds)) {
    fail('blocked_policy: audit_coverage.distinct_agent_ids precisa corresponder exatamente aos cinco auditores executados.');
  }
  if ((coverage.unresolved_required_verifications ?? []).length > 0) {
    fail(`blocked_policy: audit_coverage contem verificacoes obrigatorias nao executadas: ${coverage.unresolved_required_verifications.join('; ')}`);
  }
  if (coverage.commands_log_present !== true) {
    fail('blocked_policy: audit_coverage precisa declarar commands_log_present=true.');
  }
  if (coverage.impact_manifest_present !== true) {
    fail('blocked_policy: audit_coverage precisa declarar impact_manifest_present=true.');
  }
}

function assertCommandEntryProvenance(entry, runId, layer, cycle) {
  if (entry.run_id !== runId || entry.audited_layer !== layer || entry.cycle !== cycle) {
    fail('harness_command_log_mismatch: commands.log.jsonl contem evidencia de outra run/camada/ciclo.');
  }
  if (entry.review_round !== cycle) {
    fail('harness_command_log_mismatch: commands.log.jsonl contem evidencia de outra rodada de review.');
  }
}

function assertChangedFilesRecorded(root, state) {
  const runId = state.active_run_id;
  if (!runId) {
    fail('blocked_policy: fix -> verification exige run ativa.');
  }
  const changedFilesPath = join(runDirFor(state, runId, root), 'changed-files.txt');
  if (!existsSync(changedFilesPath)) {
    fail('harness_provenance_mismatch: fix -> verification exige changed-files.txt antes da verificacao.');
  }
  const recorded = readChangedFiles(changedFilesPath);
  if (recorded.length === 0) {
    fail('harness_provenance_mismatch: changed-files.txt nao pode estar vazio em fix -> verification.');
  }
  const expected = expectedChangedFiles(root, state);
  for (const filePath of expected) {
    if (!recorded.includes(filePath)) {
      fail(`harness_provenance_mismatch: changed-files.txt nao registra arquivo alterado: ${filePath}`);
    }
  }
}

function readChangedFiles(filePath) {
  return readFileSync(filePath, 'utf8')
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line && !line.startsWith('#'))
    .map((line) => normalizeRepoPath(line.replace(/^[A-Z?]{1,2}\s+/, '')))
    .filter(Boolean);
}

function expectedChangedFiles(root, state) {
  const paths = new Set();
  const headCommit = getHeadCommit(root);
  for (const pathValue of gitDiffPaths(root, state.git.head_commit, headCommit)) {
    paths.add(pathValue);
  }
  for (const pathValue of getDirtyPaths(root)) {
    const normalized = normalizeRepoPath(pathValue);
    if (!isHarnessRuntimeArtifact(normalized, state.active_run_id)) {
      paths.add(normalized);
    }
  }
  return [...paths].sort();
}

function gitDiffPaths(root, baseCommit, headCommit) {
  if (!isCommitish(baseCommit) || !isCommitish(headCommit) || baseCommit === headCommit) {
    return [];
  }
  try {
    return execSync(`git diff --name-only ${baseCommit}..${headCommit}`, {
      cwd: root,
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
    })
      .split(/\r?\n/)
      .map((line) => normalizeRepoPath(line.trim()))
      .filter(Boolean);
  } catch {
    return [];
  }
}

function isCommitish(value) {
  return typeof value === 'string' && /^[0-9a-f]{7,40}$/i.test(value);
}

function isHarnessRuntimeArtifact(pathValue, runId) {
  return (
    pathValue === LOCK_RELATIVE_PATH ||
    pathValue === STATE_RELATIVE_PATH ||
    pathValue === 'docs/harness/.lock' ||
    pathValue === 'docs/harness/runs/' ||
    (runId ? pathValue.startsWith(`docs/harness/runs/${runId}/`) : pathValue.startsWith('docs/harness/runs/'))
  );
}

function nonRuntimeDirtyPaths(paths, runId) {
  return paths
    .map((pathValue) => normalizeRepoPath(pathValue))
    .filter((pathValue) => !isHarnessRuntimeArtifact(pathValue, runId));
}

function stateInvariantErrors(state, root = null) {
  const errors = [];
  if (state.schema_version !== 1) {
    errors.push('schema_version deve ser 1.');
  }
  if (!VALID_PHASES.has(state.current_phase)) {
    errors.push(`current_phase invalido: ${state.current_phase}`);
  }
  if (!VALID_CYCLE_STATES.has(state.cycle_state)) {
    errors.push(`cycle_state invalido: ${state.cycle_state}`);
  }
  if (state.current_phase !== 'idle' && (state.active_layer === null || state.active_cycle === null || !state.active_run_id)) {
    errors.push('current_phase nao idle exige active_layer, active_cycle e active_run_id.');
  }
  if (
    state.current_phase === 'idle' &&
    (state.active_layer !== null || state.active_cycle !== null || state.active_run_id !== null || state.active_audit_mode !== null)
  ) {
    errors.push('current_phase idle exige active_layer, active_cycle, active_run_id e active_audit_mode nulos.');
  }
  if (state.current_phase !== 'idle' && !state.lock?.active) {
    errors.push('run ativa exige lock ativo no manifesto.');
  }
  if (state.cycle_state === 'idle' && state.current_phase !== 'idle') {
    errors.push('cycle_state idle exige current_phase idle.');
  }
  if (state.lock?.active && state.lock.run_id !== state.active_run_id) {
    errors.push('lock ativo deve apontar para active_run_id.');
  }
  if (state.current_phase !== 'idle' && state.audit_quorum?.run_id !== state.active_run_id) {
    errors.push('run ativa exige audit_quorum.run_id igual ao active_run_id.');
  }
  if (state.audit_quorum?.quorum_met) {
    if ((state.audit_quorum.executed_auditors ?? []).length !== REQUIRED_AUDITORS.length) {
      errors.push('audit_quorum.quorum_met exige cinco auditores executados.');
    }
    if ((state.audit_quorum.distinct_agent_ids ?? []).length !== REQUIRED_AUDITORS.length) {
      errors.push('audit_quorum.quorum_met exige cinco agent_ids distintos.');
    }
    if (!state.audit_quorum.commands_log_present || !state.audit_quorum.commands_log_hash || !state.audit_quorum.impact_manifest_hash) {
      errors.push('audit_quorum.quorum_met exige commands_log e impact_manifest com hashes.');
    }
  }
  if (root && state.lock?.active) {
    const lockPath = join(root, LOCK_RELATIVE_PATH);
    if (!existsSync(lockPath)) {
      errors.push(`lock ativo exige arquivo ${LOCK_RELATIVE_PATH}.`);
    } else {
      const lockFile = readJsonFile(lockPath, LOCK_RELATIVE_PATH);
      if (lockFile.run_id !== state.lock.run_id) {
        errors.push(`arquivo ${LOCK_RELATIVE_PATH} deve apontar para lock.run_id.`);
      }
    }
  }
  if (root && state.current_phase === 'idle' && existsSync(join(root, LOCK_RELATIVE_PATH))) {
    errors.push(`${LOCK_RELATIVE_PATH} nao pode existir quando current_phase e idle.`);
  }
  if (state.blocking_reason !== null && !['blocked', 'escalated'].includes(state.cycle_state) && state.current_phase !== 'closed') {
    errors.push('blocking_reason exige ciclo blocked/escalated ou fase closed.');
  }
  if (state.target_scope?.type === 'module' && (!Array.isArray(state.target_scope.paths) || state.target_scope.paths.length === 0)) {
    errors.push('target_scope module exige paths nao vazio.');
  }
  if (state.target_scope?.type === 'finding_set' && (!Array.isArray(state.target_scope.finding_ids) || state.target_scope.finding_ids.length === 0)) {
    errors.push('target_scope finding_set exige finding_ids nao vazio.');
  }
  for (const item of state.history_summary ?? []) {
    if (['blocked', 'escalated'].includes(item.decision) && !item.reason) {
      errors.push(`history_summary ${item.run_id} ${item.decision} exige reason.`);
    }
  }

  for (const layer of VALID_LAYERS) {
    const layerState = state.layers?.[String(layer)];
    if (!layerState) {
      errors.push(`camada ${layer} ausente.`);
      continue;
    }
    if (!VALID_LAYER_STATUS.has(layerState.status)) {
      errors.push(`camada ${layer} com status invalido: ${layerState.status}`);
    }
    if (!Array.isArray(layerState.depends_on)) {
      errors.push(`camada ${layer} deve ter depends_on array.`);
    }
    if (layerState.status === 'approved') {
      const provenance = layerState.approved_report_provenance ?? {};
      for (const key of ['report', 'approved_commit', 'approved_at']) {
        if (!provenance[key]) {
          errors.push(`camada ${layer} approved exige approved_report_provenance.${key}.`);
        }
      }
      for (const dependency of layerState.depends_on ?? []) {
        if (state.layers?.[String(dependency)]?.status !== 'approved') {
          errors.push(`camada ${layer} approved exige dependencia ${dependency} approved.`);
        }
      }
    }
  }

  const auth = state.deployment_authorization;
  if (auth?.authorized) {
    for (const key of ['run_id', 'layer', 'cycle', 'authorized_head_commit', 'target_environment', 'target_commit', 'deploy_command', 'rollback_command', 'authorized_by', 'authorized_at']) {
      if (!auth[key]) {
        errors.push(`deployment_authorization.${key} ausente.`);
      }
    }
    if (auth.target_environment === 'production' && !auth.migration_diff_checked) {
      errors.push('deployment_authorization.migration_diff_checked deve ser true para producao.');
    }
    const migrationViolation = migrationPolicyViolation(auth);
    if (migrationViolation) {
      errors.push(`deployment_authorization.${migrationViolation}`);
    }
    if (
      auth.target_environment === 'production' &&
      (!auth.requires_backup || !Array.isArray(auth.required_health_checks) || auth.required_health_checks.length === 0)
    ) {
      errors.push('producao exige requires_backup true e required_health_checks nao vazio.');
    }
  }
  return errors;
}

function readRunJson(runDir, fileName) {
  const filePath = join(runDir, fileName);
  if (!existsSync(filePath)) {
    fail(`blocked_policy: artefato obrigatorio ausente: ${fileName}`);
  }
  return readJsonFile(filePath, fileName);
}

function assertSchemaValid(root, schemaFile, value, label) {
  const schemaPath = join(root, SCHEMAS_ROOT_RELATIVE_PATH, schemaFile);
  if (!existsSync(schemaPath)) {
    fail(`blocked_missing_context: schema ausente: ${toRepoPath(join(SCHEMAS_ROOT_RELATIVE_PATH, schemaFile))}`);
  }
  const schema = readJsonFile(schemaPath, schemaFile);
  const errors = validateJsonSchema(value, schema, label, schema);
  if (errors.length > 0) {
    fail(`harness_output_invalid: ${label} viola ${schemaFile}: ${errors[0]}`);
  }
}

function validateJsonSchema(value, schema, path, rootSchema) {
  const resolvedSchema = schema.$ref ? resolveSchemaRef(rootSchema, schema.$ref) : schema;
  const errors = [];

  if (resolvedSchema.allOf) {
    for (const [index, subschema] of resolvedSchema.allOf.entries()) {
      errors.push(...validateJsonSchema(value, subschema, `${path}.allOf[${index}]`, rootSchema));
    }
  }
  if (resolvedSchema.if && resolvedSchema.then) {
    const conditionErrors = validateJsonSchema(value, resolvedSchema.if, path, rootSchema);
    if (conditionErrors.length === 0) {
      errors.push(...validateJsonSchema(value, resolvedSchema.then, path, rootSchema));
    }
  }

  if (resolvedSchema.const !== undefined && !jsonEquals(value, resolvedSchema.const)) {
    errors.push(`${path} deve ser ${JSON.stringify(resolvedSchema.const)}.`);
  }
  if (resolvedSchema.enum && !resolvedSchema.enum.some((candidate) => jsonEquals(value, candidate))) {
    errors.push(`${path} deve estar em ${JSON.stringify(resolvedSchema.enum)}.`);
  }
  if (resolvedSchema.type && !schemaTypeMatches(value, resolvedSchema.type)) {
    errors.push(`${path} deve ser ${Array.isArray(resolvedSchema.type) ? resolvedSchema.type.join('|') : resolvedSchema.type}, recebido ${jsonType(value)}.`);
    return errors;
  }
  if (typeof value === 'number') {
    if (resolvedSchema.minimum !== undefined && value < resolvedSchema.minimum) {
      errors.push(`${path} deve ser >= ${resolvedSchema.minimum}.`);
    }
    if (resolvedSchema.maximum !== undefined && value > resolvedSchema.maximum) {
      errors.push(`${path} deve ser <= ${resolvedSchema.maximum}.`);
    }
  }
  if (typeof value === 'string') {
    if (resolvedSchema.minLength !== undefined && value.length < resolvedSchema.minLength) {
      errors.push(`${path} deve ter pelo menos ${resolvedSchema.minLength} caracteres.`);
    }
    if (resolvedSchema.pattern && !(new RegExp(resolvedSchema.pattern).test(value))) {
      errors.push(`${path} nao corresponde ao pattern ${resolvedSchema.pattern}.`);
    }
    if (resolvedSchema.format === 'date-time' && !isDateTimeString(value)) {
      errors.push(`${path} deve estar em formato date-time RFC3339.`);
    }
  }
  if (Array.isArray(value) && resolvedSchema.items) {
    if (resolvedSchema.minItems !== undefined && value.length < resolvedSchema.minItems) {
      errors.push(`${path} deve ter pelo menos ${resolvedSchema.minItems} itens.`);
    }
    for (const [index, item] of value.entries()) {
      errors.push(...validateJsonSchema(item, resolvedSchema.items, `${path}[${index}]`, rootSchema));
    }
  }
  if (isPlainObject(value)) {
    if (resolvedSchema.required) {
      for (const key of resolvedSchema.required) {
        if (!Object.hasOwn(value, key)) {
          errors.push(`${path}.${key} obrigatorio.`);
        }
      }
    }
    const properties = resolvedSchema.properties ?? {};
    if (resolvedSchema.additionalProperties === false) {
      for (const key of Object.keys(value)) {
        if (!Object.hasOwn(properties, key)) {
          errors.push(`${path}.${key} nao permitido pelo schema.`);
        }
      }
    }
    for (const [key, propertySchema] of Object.entries(properties)) {
      if (Object.hasOwn(value, key)) {
        errors.push(...validateJsonSchema(value[key], propertySchema, `${path}.${key}`, rootSchema));
      }
    }
  }

  return errors;
}

function resolveSchemaRef(rootSchema, ref) {
  if (!ref.startsWith('#/')) {
    fail(`blocked_policy: schema ref externo nao suportado: ${ref}`);
  }
  return ref
    .slice(2)
    .split('/')
    .reduce((node, segment) => {
      const key = segment.replaceAll('~1', '/').replaceAll('~0', '~');
      if (!node || !Object.hasOwn(node, key)) {
        fail(`blocked_policy: schema ref invalido: ${ref}`);
      }
      return node[key];
    }, rootSchema);
}

function schemaTypeMatches(value, type) {
  const types = Array.isArray(type) ? type : [type];
  return types.some((expected) => jsonType(value) === expected);
}

function jsonType(value) {
  if (value === null) {
    return 'null';
  }
  if (Array.isArray(value)) {
    return 'array';
  }
  if (Number.isInteger(value)) {
    return 'integer';
  }
  if (typeof value === 'number') {
    return 'number';
  }
  return typeof value === 'object' ? 'object' : typeof value;
}

function isDateTimeString(value) {
  return (
    /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/.test(value) &&
    !Number.isNaN(Date.parse(value))
  );
}

function isPlainObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function jsonEquals(left, right) {
  return JSON.stringify(left) === JSON.stringify(right);
}

function auditorFilesPresent(runDir) {
  return REQUIRED_AUDITORS.filter((auditor) => existsSync(join(runDir, auditor.file)));
}

function readCommandLog(runDir) {
  const filePath = join(runDir, 'commands.log.jsonl');
  if (!existsSync(filePath)) {
    return [];
  }
  return readFileSync(filePath, 'utf8')
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line, index) => {
      try {
        return JSON.parse(line);
      } catch (error) {
        fail(`harness_output_invalid: commands.log.jsonl linha ${index + 1} JSON invalido: ${error.message}`);
      }
    });
}

function isGitRepo(root) {
  try {
    return execSync('git rev-parse --is-inside-work-tree', { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim() === 'true';
  } catch {
    return false;
  }
}

function remoteContainsCommit(root, targetCommit) {
  try {
    const output = execSync(`git branch -r --contains ${targetCommit}`, {
      cwd: root,
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
    });
    return output.trim().length > 0;
  } catch {
    return false;
  }
}

function dependenciesResolved(state, dependsOn) {
  return dependsOn.every((dependency) => state.layers[String(dependency)]?.status === 'approved');
}

function getHeadCommit(root) {
  try {
    return execSync('git rev-parse HEAD', { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  } catch {
    return null;
  }
}

function getDirtyPaths(root) {
  try {
    const output = execSync('git status --porcelain=v1 -z', { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
    const entries = output.split('\0').filter(Boolean);
    const paths = [];
    for (let index = 0; index < entries.length; index += 1) {
      const entry = entries[index];
      const status = entry.slice(0, 2);
      const pathValue = entry.slice(3);
      if (pathValue) {
        paths.push(...expandDirtyPath(root, pathValue));
      }
      if ((status.includes('R') || status.includes('C')) && entries[index + 1]) {
        paths.push(...expandDirtyPath(root, entries[index + 1]));
        index += 1;
      }
    }
    return paths;
  } catch {
    return [];
  }
}

function expandDirtyPath(root, pathValue) {
  const normalized = normalizeRepoPath(pathValue);
  if (!normalized.endsWith('/')) {
    return [normalized];
  }
  const absolute = join(root, ...normalized.split('/').filter(Boolean));
  if (!existsSync(absolute)) {
    return [normalized];
  }
  try {
    if (!statSync(absolute).isDirectory()) {
      return [normalized];
    }
    return listFilesRecursive(absolute).map((filePath) => `${normalized}${filePath}`);
  } catch {
    return [normalized];
  }
}

function listFilesRecursive(directory, prefix = '') {
  const files = [];
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const relative = prefix ? `${prefix}/${entry.name}` : entry.name;
    const absolute = join(directory, entry.name);
    if (entry.isDirectory()) {
      files.push(...listFilesRecursive(absolute, relative));
    } else {
      files.push(relative);
    }
  }
  return files;
}

function isRelevantDirtyPath(pathValue, layer = null) {
  const normalizedPath = normalizeRepoPath(pathValue);
  return (
    isHarnessGovernanceDirtyPath(normalizedPath) ||
    (Number.isInteger(layer) && (LAYER_DIRTY_PATTERNS[layer] ?? []).some((pattern) => pattern.test(normalizedPath)))
  );
}

function isHarnessGovernanceDirtyPath(pathValue) {
  return (
    pathValue === 'AGENTS.md' ||
    pathValue.startsWith('.agent/rules/') ||
    pathValue.startsWith('docs/harness/') ||
    pathValue.startsWith('scripts/harness-cycle.')
  );
}

function normalizeRepoPath(pathValue) {
  return String(pathValue).replaceAll('\\', '/');
}

function buildRunId(layer, cycle) {
  const compact = new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d{3}Z$/, 'Z');
  return `${compact}-layer-${layer}-cycle-${cycle}`;
}

function runDirFor(state, runId, root) {
  return join(root, state.reports_root || DEFAULT_REPORTS_ROOT, runId);
}

function appendRunReport(state, runId, root, text) {
  const reportPath = join(runDirFor(state, runId, root), 'report.md');
  if (existsSync(reportPath)) {
    writeFileSync(reportPath, text, { encoding: 'utf8', flag: 'a' });
  }
}

function removeLock(root) {
  const lockPath = join(root, LOCK_RELATIVE_PATH);
  if (existsSync(lockPath)) {
    rmSync(lockPath, { force: true });
  }
}

function removeLockIfMatches(root, runId) {
  const lockPath = join(root, LOCK_RELATIVE_PATH);
  if (!existsSync(lockPath)) {
    return;
  }
  const lockFile = readJsonFile(lockPath, LOCK_RELATIVE_PATH);
  if (lockFile.run_id === runId) {
    rmSync(lockPath, { force: true });
  }
}

function defaultDeployCommand(target) {
  if (target !== 'production') {
    fail(`deploy_command obrigatorio para target ${target}`);
  }
  const home = process.env.USERPROFILE || process.env.HOME;
  if (!home) {
    fail('deploy_command obrigatorio: USERPROFILE/HOME indisponivel.');
  }
  const key = join(home, '.ssh', 'id_ed25519');
  return `ssh -i "${key}" -o ServerAliveInterval=15 -o ServerAliveCountMax=20 -o StrictHostKeyChecking=no deploy@203.0.113.10 "cd /srv/kalibrium && bash deploy/deploy.sh"`;
}

function defaultRollbackCommand(target) {
  if (target !== 'production') {
    fail(`rollback_command obrigatorio para target ${target}`);
  }
  const home = process.env.USERPROFILE || process.env.HOME;
  if (!home) {
    fail('rollback_command obrigatorio: USERPROFILE/HOME indisponivel.');
  }
  const key = join(home, '.ssh', 'id_ed25519');
  return `ssh -i "${key}" -o ServerAliveInterval=15 -o ServerAliveCountMax=20 -o StrictHostKeyChecking=no deploy@203.0.113.10 "cd /srv/kalibrium && bash deploy/deploy.sh --rollback"`;
}

function defaultHealthChecks(target) {
  if (target !== 'production') {
    return [];
  }
  return ['https://app.example.test', 'https://app.example.test/up', 'http://203.0.113.10'];
}

function asArray(value) {
  if (value === undefined || value === null) {
    return [];
  }
  return Array.isArray(value) ? value.map(String) : [String(value)];
}

function parseBooleanOption(value, defaultValue, name) {
  if (value === undefined || value === null) {
    return defaultValue;
  }
  if (value === true || value === false) {
    return value;
  }
  const normalized = String(value).trim().toLowerCase();
  if (['true', '1', 'yes', 'y'].includes(normalized)) {
    return true;
  }
  if (['false', '0', 'no', 'n'].includes(normalized)) {
    return false;
  }
  fail(`${name} deve ser booleano.`);
}

function requireString(value, name) {
  if (value === undefined || value === null || value === true || String(value).trim() === '') {
    fail(`${name} obrigatorio.`);
  }
  return String(value);
}

function excerpt(value) {
  if (!value) {
    return null;
  }
  return String(value).slice(0, 4000);
}

function toRepoPath(pathValue) {
  return pathValue.replaceAll('\\', '/');
}

function nowIso() {
  return new Date().toISOString();
}

function fail(message) {
  throw new Error(message);
}

function printHelp() {
  console.log(`Uso:
  node scripts/harness-cycle.mjs init [--root PATH]
  node scripts/harness-cycle.mjs status [--json] [--root PATH]
  node scripts/harness-cycle.mjs validate [--root PATH]
  node scripts/harness-cycle.mjs start --layer N [--mode full|targeted|verification_only] [--invalidation-reason TEXT] [--human-decision TEXT] [--root PATH]
  node scripts/harness-cycle.mjs record --status audit|fix|reaudit|verification|blocked|escalated [--run-id ID] [--reason TEXT]
  node scripts/harness-cycle.mjs generate-impact --run-id ID
  node scripts/harness-cycle.mjs record-command --run-id ID --command CMD --status passed|failed|not_executed|blocked_environment|blocked_policy|waived_by_policy|replaced_by_equivalent --actor-role verifier|auditor|fixer|llm-cli --agent-id ID --context-fingerprint HASH --source-bundle-hash HASH [--review-round N] [--justification TEXT]
  node scripts/harness-cycle.mjs close --status approved|blocked|escalated [--run-id ID] [--reason TEXT]
  node scripts/harness-cycle.mjs authorize-deploy --run-id ID --target production --commit SHA [--deploy-command CMD] [--rollback-command CMD] [--allow-migrations true|false] [--migration-diff-checked true|false] [--requires-backup true|false] [--health-check URL] [--authorized-by NAME]
  node scripts/harness-cycle.mjs deploy --run-id ID [--dry-run]
  node scripts/harness-cycle.mjs record-deploy --run-id ID --status passed|failed|blocked_policy --exit-code CODE [--command CMD]
  node scripts/harness-cycle.mjs unlock --run-id ID --reason TEXT
`);
}

const invokedPath = process.argv[1] ? resolve(process.argv[1]) : '';
const thisPath = fileURLToPath(import.meta.url);
if (invokedPath === thisPath) {
  main();
}
