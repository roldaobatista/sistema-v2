import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, relative, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import test from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(fileURLToPath(new URL('..', import.meta.url)));
const DEPLOY_SCRIPT = join(ROOT, 'deploy', 'deploy.sh');
const SCRIPT_PREFIX = deployScriptPrefix();

test('production deploy entrypoints use the versioned deploy script and existing compose files', () => {
  const deploy = readFileSync(DEPLOY_SCRIPT, 'utf8');
  const hetznerGuide = readFileSync(join(ROOT, 'deploy', 'DEPLOY-HETZNER.md'), 'utf8');
  const productionRule = readFileSync(join(ROOT, '.cursor', 'rules', 'deploy-production.mdc'), 'utf8');

  assert.doesNotMatch(deploy, /docker-compose\.prod-http\.yml/);
  assert.doesNotMatch(hetznerGuide, /docker-compose\.prod-http\.yml/);
  assert.doesNotMatch(hetznerGuide, /deploydeploy-prod\.ps1/);
  assert.match(hetznerGuide, /\.\\deploy\\deploy-prod\.ps1/);
  assert.doesNotMatch(productionRule, /cd \/root\/sistema && bash deploy\.sh(?:\s|['"])/);
  assert.match(productionRule, /cd \/root\/sistema && bash deploy\/deploy\.sh/);
});

test('preflight rejects empty or divergent root Redis password before compose starts', () => {
  const result = runPreflight({
    rootEnv: '',
    backendEnv: productionBackendEnv({ redisPassword: 'secure-backend-pass' }),
  });

  assert.notEqual(result.status, 0);
  assert.match(result.stderr + result.stdout, /REDIS_PASSWORD.*\.env raiz/i);
});

test('preflight rejects production origins that are empty placeholders or localhost', () => {
  const result = runPreflight({
    rootEnv: [
      'REDIS_PASSWORD=secure-root-pass',
      'GO2RTC_API_ORIGIN=',
      '',
    ].join('\n'),
    backendEnv: productionBackendEnv({
      redisPassword: 'secure-root-pass',
      corsAllowedOrigins: 'http://localhost:5173',
      frontendUrl: 'http://127.0.0.1:5173',
    }),
  });

  assert.notEqual(result.status, 0);
  assert.match(result.stderr + result.stdout, /(CORS_ALLOWED_ORIGINS|FRONTEND_URL|GO2RTC_API_ORIGIN)/);
});

test('health check fails when frontend responds but API never reaches 200', () => {
  const result = runShell(`
${SCRIPT_PREFIX}
HEALTH_CHECK_RETRIES=2
HEALTH_CHECK_INTERVAL=0
COMPOSE_FILE=docker-compose.prod.yml
docker() { return 0; }
curl() {
  local url="$*"
  if [[ "$url" == *"http://localhost/up"* ]]; then
    printf '000'
    return 0
  fi
  printf '200'
  return 0
}
health_check
`);

  assert.notEqual(result.status, 0);
  assert.match(result.stderr + result.stdout, /API/i);
});

test('rollback uses the previous-release snapshot instead of selecting the newest rollback tag', () => {
  const deploy = readFileSync(DEPLOY_SCRIPT, 'utf8');

  assert.match(deploy, /PREVIOUS_RELEASE_TAG_FILE/);
  assert.match(deploy, /capture_previous_release/);
  assert.doesNotMatch(deploy, /grep '\^rollback-' \| sort -r \| head -1/);
});

test('rollback snapshots and restores all services rebuilt from app images', () => {
  const root = mkdtempSync(join(ROOT, '.tmp-kalibrium-rollback-'));
  const rootForBash = relative(ROOT, root).replaceAll('\\', '/');
  try {
    const result = runShell(`
${SCRIPT_PREFIX}
cd "${rootForBash}"
BACKUP_DIR="$PWD/backups"
PREVIOUS_RELEASE_TAG_FILE="$BACKUP_DIR/.previous-release-tags"
COMPOSE_FILE=docker-compose.prod.yml
DEPLOY_TAG=20260416_000000
mkdir -p "$BACKUP_DIR"
docker() {
  if [ "$1" = "compose" ] && [ "$4" = "images" ] && [ "$#" -eq 4 ]; then
    printf 'CONTAINER             REPOSITORY          TAG                 PLATFORM            IMAGE ID            SIZE                CREATED\\n'
    printf 'kalibrium_backend     kalibrium_backend   latest              linux/amd64         111111111111        235MB               now\\n'
    printf 'kalibrium_frontend    kalibrium_frontend  latest              linux/amd64         222222222222        28MB                now\\n'
    printf 'kalibrium_queue       kalibrium_queue     latest              linux/amd64         333333333333        235MB               now\\n'
    printf 'kalibrium_scheduler   kalibrium_scheduler latest              linux/amd64         444444444444        235MB               now\\n'
    printf 'kalibrium_reverb      kalibrium_reverb    latest              linux/amd64         555555555555        235MB               now\\n'
    return 0
  fi
  if [ "$1" = "compose" ] && [ "$4" = "images" ]; then
    return 0
  fi
  printf 'DOCKER:%s\\n' "$*"
  return 0
}
wait_for_mysql() { return 0; }
post_deploy() { return 0; }
health_check() { return 0; }
capture_previous_release
rollback_full
printf 'SNAPSHOT_FILE\\n'
cat "$PREVIOUS_RELEASE_TAG_FILE"
`);

    assert.equal(result.status, 0, result.stderr + result.stdout);
    for (const service of ['backend', 'frontend', 'queue', 'scheduler', 'reverb']) {
      assert.match(result.stdout, new RegExp(`Snapshot preservado para ${service}: kalibrium_${service}:previous-${service}-20260416_000000`));
      assert.match(result.stdout, new RegExp(`Restaurado ${service} de kalibrium_${service}:previous-${service}-20260416_000000`));
      assert.match(result.stdout, new RegExp(`${service} kalibrium_${service} previous-${service}-20260416_000000`));
      assert.match(result.stdout, new RegExp(`DOCKER:tag kalibrium_${service}:previous-${service}-20260416_000000 kalibrium_${service}:latest`));
    }
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

function runPreflight({ rootEnv, backendEnv }) {
  const root = mkdtempSync(join(ROOT, '.tmp-kalibrium-preflight-'));
  const rootForBash = relative(ROOT, root).replaceAll('\\', '/');
  try {
    writeFileSync(join(root, 'docker-compose.prod.yml'), 'services: {}\n');
    writeFileSync(join(root, '.env'), rootEnv);
    writeFileSync(join(root, 'backend.env'), backendEnv);
    const script = `
${SCRIPT_PREFIX}
cd "${rootForBash}"
mkdir -p backend
cp backend.env backend/.env
COMPOSE_FILE=docker-compose.prod.yml
docker() { return 0; }
compose() { return 0; }
df() {
  printf 'Filesystem 1M-blocks Used Available Use%% Mounted on\\n'
  printf '/dev/root 1000 100 900 10%% /\\n'
}
preflight
`;

    return runShell(script);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
}

function productionBackendEnv({
  redisPassword,
  corsAllowedOrigins = 'https://app.example.com',
  frontendUrl = 'https://app.example.com',
}) {
  return [
    'APP_ENV=production',
    'APP_DEBUG=false',
    'DB_PASSWORD=secure-db-pass',
    `REDIS_PASSWORD=${redisPassword}`,
    `CORS_ALLOWED_ORIGINS=${corsAllowedOrigins}`,
    `FRONTEND_URL=${frontendUrl}`,
    '',
  ].join('\n');
}

function deployScriptPrefix() {
  const script = readFileSync(DEPLOY_SCRIPT, 'utf8');
  const marker = '# MAIN';
  const index = script.indexOf(marker);
  assert.ok(index > 0, 'deploy.sh deve conter marcador MAIN');

  return script.slice(0, index);
}

function runShell(script) {
  return spawnSync('bash', ['-s'], {
    cwd: ROOT,
    encoding: 'utf8',
    input: script.replace(/\r\n/g, '\n'),
    windowsHide: true,
  });
}
