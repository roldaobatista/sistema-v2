import { spawnSync } from 'child_process';

const DEFAULT_MINIMUM_VERSION = '8.4.0';
const DEFAULT_REQUIRED_EXTENSIONS = ['pdo_sqlite', 'sqlite3'];

export function parsePhpVersion(version) {
  const match = version?.trim().match(/^(\d+)\.(\d+)\.(\d+)/);

  if (!match) {
    return null;
  }

  return match.slice(1).map((segment) => Number.parseInt(segment, 10));
}

export function isPhpVersionCompatible(version, minimumVersion = DEFAULT_MINIMUM_VERSION) {
  const parsedVersion = parsePhpVersion(version);
  const parsedMinimum = parsePhpVersion(minimumVersion);

  if (!parsedVersion || !parsedMinimum) {
    return false;
  }

  for (let index = 0; index < parsedMinimum.length; index += 1) {
    if (parsedVersion[index] > parsedMinimum[index]) {
      return true;
    }

    if (parsedVersion[index] < parsedMinimum[index]) {
      return false;
    }
  }

  return true;
}

export function buildPhpCandidates(env = process.env) {
  const wingetBase = env.LOCALAPPDATA
    ? `${env.LOCALAPPDATA}/Microsoft/WinGet/Packages`
    : null;

  return [
    env.KALIBRIUM_PHP_BIN,
    'php',
    wingetBase ? `${wingetBase}/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe` : null,
    wingetBase ? `${wingetBase}/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe` : null,
  ].filter(Boolean);
}

export function buildPhpProcessEnv(command, env = process.env) {
  const normalizedCommand = command.replace(/\\/g, '/').toLowerCase();

  if (!normalizedCommand.includes('/microsoft/winget/packages/php.php.')) {
    return env;
  }

  return {
    ...env,
    PHP_INI_SCAN_DIR: '',
  };
}

export function hasRequiredPhpExtensions(candidate, requiredExtensions = DEFAULT_REQUIRED_EXTENSIONS) {
  const loadedExtensions = new Set(candidate.extensions ?? []);

  return requiredExtensions.every((extension) => loadedExtensions.has(extension));
}

export function selectPhpRuntime(
  probedCandidates,
  minimumVersion = DEFAULT_MINIMUM_VERSION,
  requiredExtensions = DEFAULT_REQUIRED_EXTENSIONS,
) {
  return probedCandidates.find((candidate) => (
    candidate.ok && isPhpVersionCompatible(candidate.version, minimumVersion)
    && hasRequiredPhpExtensions(candidate, requiredExtensions)
  )) ?? null;
}

function buildExtensionProbeCode(requiredExtensions) {
  const extensions = requiredExtensions
    .map((extension) => `'${extension.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`)
    .join(',');

  return `echo PHP_VERSION, PHP_EOL; foreach ([${extensions}] as $extension) { echo $extension, '=', extension_loaded($extension) ? '1' : '0', PHP_EOL; }`;
}

function parsePhpProbeOutput(output) {
  const lines = output.trim().split(/\r?\n/u);
  const version = lines.shift()?.trim() ?? '';
  const extensions = lines
    .map((line) => line.match(/^([^=]+)=(0|1)$/u))
    .filter((match) => match?.[2] === '1')
    .map((match) => match[1]);

  return { version, extensions };
}

export function probePhpCommand(command, requiredExtensions = DEFAULT_REQUIRED_EXTENSIONS) {
  try {
    const result = spawnSync(command, ['-r', buildExtensionProbeCode(requiredExtensions)], {
      encoding: 'utf8',
      env: buildPhpProcessEnv(command),
      shell: false,
      windowsHide: true,
    });

    if (result.status !== 0) {
      return {
        command,
        ok: false,
        error: (result.stderr || result.stdout || '').trim() || `exit ${result.status}`,
      };
    }

    const { version, extensions } = parsePhpProbeOutput(result.stdout);

    return {
      command,
      ok: true,
      version,
      extensions,
    };
  } catch (error) {
    return {
      command,
      ok: false,
      error: error instanceof Error ? error.message : String(error),
    };
  }
}

function formatCandidateAttempt(candidate, requiredExtensions) {
  if (!candidate.ok) {
    return `${candidate.command}: ${candidate.error}`;
  }

  const extensions = candidate.extensions ?? [];
  const missingExtensions = requiredExtensions
    .filter((extension) => !extensions.includes(extension));
  const missingText = missingExtensions.length > 0
    ? `, faltando: ${missingExtensions.join(',')}`
    : '';

  return `${candidate.command}: PHP ${candidate.version}, extensions: ${extensions.join(',') || 'none'}${missingText}`;
}

export function resolvePhpRuntime({
  minimumVersion = DEFAULT_MINIMUM_VERSION,
  requiredExtensions = DEFAULT_REQUIRED_EXTENSIONS,
  env = process.env,
  probe = probePhpCommand,
} = {}) {
  const candidates = buildPhpCandidates(env);
  const probedCandidates = [];
  const seen = new Set();

  for (const candidate of candidates) {
    if (seen.has(candidate)) {
      continue;
    }

    seen.add(candidate);
    probedCandidates.push(probe(candidate, requiredExtensions));
  }

  const runtime = selectPhpRuntime(probedCandidates, minimumVersion, requiredExtensions);

  if (!runtime) {
    const attempts = probedCandidates
      .map((candidate) => formatCandidateAttempt(candidate, requiredExtensions))
      .join(' | ');

    throw new Error(
      `Nenhum PHP compatível com >= ${minimumVersion} foi encontrado. Tentativas: ${attempts}`,
    );
  }

  return runtime;
}
