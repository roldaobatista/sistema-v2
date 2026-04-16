import test from 'node:test';
import assert from 'node:assert/strict';

import {
  buildPhpProcessEnv,
  hasRequiredPhpExtensions,
  isPhpVersionCompatible,
  resolvePhpRuntime,
  selectPhpRuntime,
} from '../../scripts/php-runtime.mjs';

test('isPhpVersionCompatible aceita versoes 8.4 ou superiores', () => {
  assert.equal(isPhpVersionCompatible('8.4.0'), true);
  assert.equal(isPhpVersionCompatible('8.4.19'), true);
  assert.equal(isPhpVersionCompatible('8.5.1'), true);
  assert.equal(isPhpVersionCompatible('8.3.99'), false);
});

test('selectPhpRuntime escolhe o primeiro runtime compativel na ordem de prioridade', () => {
  const runtime = selectPhpRuntime([
    { command: 'php', ok: true, version: '8.2.30', extensions: ['pdo_sqlite', 'sqlite3'] },
    { command: 'C:/php84/php.exe', ok: true, version: '8.4.19', extensions: ['pdo_sqlite', 'sqlite3'] },
    { command: 'C:/php85/php.exe', ok: true, version: '8.5.0', extensions: ['pdo_sqlite', 'sqlite3'] },
  ]);

  assert.deepEqual(runtime, {
    command: 'C:/php84/php.exe',
    ok: true,
    version: '8.4.19',
    extensions: ['pdo_sqlite', 'sqlite3'],
  });
});

test('selectPhpRuntime ignora runtime compativel sem sqlite3 standalone', () => {
  const runtime = selectPhpRuntime([
    { command: 'php', ok: true, version: '8.5.5', extensions: ['pdo_sqlite'] },
    { command: 'C:/php84/php.exe', ok: true, version: '8.4.19', extensions: ['pdo_sqlite', 'sqlite3'] },
  ]);

  assert.deepEqual(runtime, {
    command: 'C:/php84/php.exe',
    ok: true,
    version: '8.4.19',
    extensions: ['pdo_sqlite', 'sqlite3'],
  });
});

test('hasRequiredPhpExtensions exige pdo_sqlite e sqlite3 por padrao', () => {
  assert.equal(hasRequiredPhpExtensions({ extensions: ['pdo_sqlite', 'sqlite3'] }), true);
  assert.equal(hasRequiredPhpExtensions({ extensions: ['pdo_sqlite'] }), false);
});

test('buildPhpProcessEnv isola PHP do WinGet do scan dir do Scoop', () => {
  const env = { PHP_INI_SCAN_DIR: '$HOME/scoop/apps/php/current/cli' };

  assert.equal(
    buildPhpProcessEnv('$HOME/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe', env).PHP_INI_SCAN_DIR,
    '',
  );
  assert.equal(buildPhpProcessEnv('php', env).PHP_INI_SCAN_DIR, env.PHP_INI_SCAN_DIR);
});

test('resolvePhpRuntime falha com mensagem clara quando nenhum binario atende a versao minima', () => {
  assert.throws(() => resolvePhpRuntime({
    env: { KALIBRIUM_PHP_BIN: 'custom-php' },
    probe: (command) => ({
      command,
      ok: true,
      version: '8.2.30',
      extensions: ['pdo_sqlite', 'sqlite3'],
    }),
  }), /Nenhum PHP compatível com >= 8\.4\.0 foi encontrado/);
});

test('resolvePhpRuntime respeita override explicito do ambiente quando ele ja e compativel', () => {
  const runtime = resolvePhpRuntime({
    env: { KALIBRIUM_PHP_BIN: 'custom-php' },
    probe: (command) => ({
      command,
      ok: true,
      version: command === 'custom-php' ? '8.4.19' : '8.2.30',
      extensions: ['pdo_sqlite', 'sqlite3'],
    }),
  });

  assert.deepEqual(runtime, {
    command: 'custom-php',
    ok: true,
    version: '8.4.19',
    extensions: ['pdo_sqlite', 'sqlite3'],
  });
});
