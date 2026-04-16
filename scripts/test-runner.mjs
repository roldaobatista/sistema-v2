#!/usr/bin/env node

/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║          🧪 SISTEMA TEST RUNNER — Dashboard Visual          ║
 * ╠══════════════════════════════════════════════════════════════╣
 * ║  Executa testes backend (Pest) e frontend (Vitest)          ║
 * ║  com interface visual rica: cores, barras, spinners         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * Uso:
 *   node test-runner.mjs              → Roda tudo (backend + frontend)
 *   node test-runner.mjs backend      → Só backend (Pest)
 *   node test-runner.mjs frontend     → Só frontend (Vitest)
 *   node test-runner.mjs --suite=Unit → Suite específica do backend
 *   node test-runner.mjs --watch      → Modo watch (frontend)
 *   node test-runner.mjs --coverage   → Com cobertura de código
 *   node test-runner.mjs --parallel   → Backend + Frontend em paralelo
 */

import { spawn } from 'child_process';
import { stdout } from 'process';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { buildPhpProcessEnv, resolvePhpRuntime } from './php-runtime.mjs';
import { buildBackendTestInvocation } from './test-runner-command.mjs';
import { resolveRunnerFlags } from './test-runner-plan.mjs';
import { attachBrokenPipeGuard, safeWrite } from './terminal-streams.mjs';

// ─── ANSI ESCAPE CODES ──────────────────────────────────────────
const ESC = '\x1b[';
const RESET = `${ESC}0m`;
const BOLD = `${ESC}1m`;
const DIM = `${ESC}2m`;
const ITALIC = `${ESC}3m`;
const UNDERLINE = `${ESC}4m`;
const BLINK = `${ESC}5m`;
const INVERSE = `${ESC}7m`;
const HIDDEN = `${ESC}8m`;
const STRIKETHROUGH = `${ESC}9m`;

// Cores foreground
const FG = {
  black: `${ESC}30m`, red: `${ESC}31m`, green: `${ESC}32m`, yellow: `${ESC}33m`,
  blue: `${ESC}34m`, magenta: `${ESC}35m`, cyan: `${ESC}36m`, white: `${ESC}37m`,
  gray: `${ESC}90m`,
  brightRed: `${ESC}91m`, brightGreen: `${ESC}92m`, brightYellow: `${ESC}93m`,
  brightBlue: `${ESC}94m`, brightMagenta: `${ESC}95m`, brightCyan: `${ESC}96m`,
  brightWhite: `${ESC}97m`,
};

// Cores background
const BG = {
  black: `${ESC}40m`, red: `${ESC}41m`, green: `${ESC}42m`, yellow: `${ESC}43m`,
  blue: `${ESC}44m`, magenta: `${ESC}45m`, cyan: `${ESC}46m`, white: `${ESC}47m`,
  brightGreen: `${ESC}102m`, brightRed: `${ESC}101m`, brightYellow: `${ESC}103m`,
  brightBlue: `${ESC}104m`, brightCyan: `${ESC}106m`,
};

// 256 color
const fg256 = (n) => `${ESC}38;5;${n}m`;
const bg256 = (n) => `${ESC}48;5;${n}m`;

// RGB
const fgRGB = (r, g, b) => `${ESC}38;2;${r};${g};${b}m`;
const bgRGB = (r, g, b) => `${ESC}48;2;${r};${g};${b}m`;

// Cursor
const CLEAR_LINE = `${ESC}2K`;
const CURSOR_UP = (n = 1) => `${ESC}${n}A`;
const CURSOR_DOWN = (n = 1) => `${ESC}${n}B`;
const CURSOR_TO = (col) => `${ESC}${col}G`;
const HIDE_CURSOR = `${ESC}?25l`;
const SHOW_CURSOR = `${ESC}?25h`;
const SAVE_CURSOR = `${ESC}s`;
const RESTORE_CURSOR = `${ESC}u`;
const CLEAR_SCREEN = `${ESC}2J${ESC}H`;

// ─── CONFIGURAÇÃO ────────────────────────────────────────────────
const COLS = Math.min(process.stdout.columns || 100, 120);
const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const ROOT = resolve(__dirname, '..');
const BACKEND_DIR = `${ROOT}/backend`;
const FRONTEND_DIR = `${ROOT}/frontend`;

// ─── SPINNERS ────────────────────────────────────────────────────
const SPINNERS = {
  dots: { frames: ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'], interval: 80 },
  arc: { frames: ['◜', '◠', '◝', '◞', '◡', '◟'], interval: 100 },
  bouncingBar: { frames: ['[    ]', '[=   ]', '[==  ]', '[=== ]', '[ ===]', '[  ==]', '[   =]', '[    ]', '[   =]', '[  ==]', '[ ===]', '[====]', '[=== ]', '[==  ]', '[=   ]'], interval: 80 },
  clock: { frames: ['🕛', '🕐', '🕑', '🕒', '🕓', '🕔', '🕕', '🕖', '🕗', '🕘', '🕙', '🕚'], interval: 100 },
  line: { frames: ['-', '\\', '|', '/'], interval: 130 },
};

// ─── ÍCONES ──────────────────────────────────────────────────────
const ICONS = {
  pass: `${FG.brightGreen}✓${RESET}`,
  fail: `${FG.brightRed}✗${RESET}`,
  skip: `${FG.yellow}○${RESET}`,
  pending: `${FG.cyan}◌${RESET}`,
  running: `${FG.brightCyan}▶${RESET}`,
  warning: `${FG.yellow}⚠${RESET}`,
  info: `${FG.brightBlue}ℹ${RESET}`,
  rocket: '🚀',
  fire: '🔥',
  check: '✅',
  cross: '❌',
  clock: '⏱',
  gear: '⚙',
  chart: '📊',
  flask: '🧪',
  trophy: '🏆',
  bug: '🐛',
  sparkle: '✨',
  lightning: '⚡',
  shield: '🛡',
  folder: '📁',
  php: '🐘',
  react: '⚛',
};

// ─── UTILIDADES ──────────────────────────────────────────────────
const stdoutState = attachBrokenPipeGuard(stdout);
const write = (text) => safeWrite(stdout, stdoutState, text);
const writeln = (text = '') => safeWrite(stdout, stdoutState, text + '\n');

function repeat(char, n) {
  return char.repeat(Math.max(0, n));
}

function pad(str, len, char = ' ', align = 'left') {
  const stripped = stripAnsi(str);
  const diff = len - stripped.length;
  if (diff <= 0) return str;
  if (align === 'right') return repeat(char, diff) + str;
  if (align === 'center') return repeat(char, Math.floor(diff / 2)) + str + repeat(char, Math.ceil(diff / 2));
  return str + repeat(char, diff);
}

function stripAnsi(str) {
  return str.replace(/\x1b\[[0-9;]*[a-zA-Z?]/g, '');
}

function truncate(str, maxLen) {
  const stripped = stripAnsi(str);
  if (stripped.length <= maxLen) return str;
  return stripped.slice(0, maxLen - 1) + '…';
}

function formatDuration(ms) {
  if (ms < 1000) return `${ms}ms`;
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
  const min = Math.floor(ms / 60000);
  const sec = ((ms % 60000) / 1000).toFixed(0);
  return `${min}m${sec}s`;
}

function now() {
  return Date.now();
}

function quoteShellArg(arg) {
  if (!/[\s"]/u.test(arg)) {
    return arg;
  }

  return `"${arg.replace(/"/g, '\\"')}"`;
}

// ─── COMPONENTES VISUAIS ─────────────────────────────────────────

function drawBox(lines, { title = '', borderColor = FG.cyan, width = COLS, padding = 1 } = {}) {
  const bdr = borderColor;
  const inner = width - 2;
  const topBorder = title
    ? `${bdr}╔══${RESET} ${BOLD}${title}${RESET} ${bdr}${repeat('═', Math.max(0, inner - stripAnsi(title).length - 4))}╗${RESET}`
    : `${bdr}╔${repeat('═', inner)}╗${RESET}`;

  writeln(topBorder);

  for (let p = 0; p < padding; p++) {
    writeln(`${bdr}║${RESET}${repeat(' ', inner)}${bdr}║${RESET}`);
  }

  for (const line of lines) {
    const stripped = stripAnsi(line);
    const padLen = inner - 2 - stripped.length;
    writeln(`${bdr}║${RESET} ${line}${repeat(' ', Math.max(0, padLen + 1))}${bdr}║${RESET}`);
  }

  for (let p = 0; p < padding; p++) {
    writeln(`${bdr}║${RESET}${repeat(' ', inner)}${bdr}║${RESET}`);
  }

  writeln(`${bdr}╚${repeat('═', inner)}╝${RESET}`);
}

function progressBar(current, total, { width = 40, filled = '█', empty = '░', color = FG.brightGreen, showPercent = true, showCount = true } = {}) {
  const pct = total > 0 ? current / total : 0;
  const filledLen = Math.round(pct * width);
  const emptyLen = width - filledLen;

  let bar = `${color}${repeat(filled, filledLen)}${FG.gray}${repeat(empty, emptyLen)}${RESET}`;

  const parts = [bar];
  if (showPercent) parts.push(`${BOLD}${(pct * 100).toFixed(0)}%${RESET}`);
  if (showCount) parts.push(`${DIM}(${current}/${total})${RESET}`);

  return parts.join(' ');
}

function gradientProgressBar(current, total, width = 40) {
  const pct = total > 0 ? current / total : 0;
  const filledLen = Math.round(pct * width);
  let bar = '';

  for (let i = 0; i < width; i++) {
    if (i < filledLen) {
      // Gradiente verde -> amarelo -> vermelho baseado na posição
      const ratio = i / width;
      if (ratio < 0.5) {
        const g = Math.floor(255 * (1 - ratio * 2));
        bar += `${fgRGB(0, 255, g)}█${RESET}`;
      } else {
        const r = Math.floor(255 * ((ratio - 0.5) * 2));
        bar += `${fgRGB(r, 255, 0)}█${RESET}`;
      }
    } else {
      bar += `${FG.gray}░${RESET}`;
    }
  }

  return `${bar} ${BOLD}${(pct * 100).toFixed(0)}%${RESET}`;
}

function statusBadge(status) {
  const badges = {
    PASS: `${BG.green}${FG.black}${BOLD} PASS ${RESET}`,
    FAIL: `${BG.red}${FG.white}${BOLD} FAIL ${RESET}`,
    SKIP: `${BG.yellow}${FG.black}${BOLD} SKIP ${RESET}`,
    RUN: `${BG.blue}${FG.white}${BOLD} RUN  ${RESET}`,
    WAIT: `${BG.black}${FG.white} WAIT ${RESET}`,
    DONE: `${BG.green}${FG.black}${BOLD} DONE ${RESET}`,
    ERROR: `${BG.red}${FG.white}${BOLD} ERR  ${RESET}`,
    WARN: `${BG.yellow}${FG.black}${BOLD} WARN ${RESET}`,
  };
  return badges[status] || `[${status}]`;
}

function miniChart(values, { width = 20, height = 5, color = FG.green } = {}) {
  // Sparkline simples
  const chars = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];
  const max = Math.max(...values, 1);
  return values.map(v => {
    const idx = Math.min(Math.floor((v / max) * (chars.length - 1)), chars.length - 1);
    return `${color}${chars[idx]}${RESET}`;
  }).join('');
}

// ─── SPINNER ANIMADO ─────────────────────────────────────────────
class Spinner {
  constructor(text, spinnerType = 'dots') {
    this.text = text;
    this.spinner = SPINNERS[spinnerType];
    this.frameIdx = 0;
    this.timer = null;
    this.startTime = now();
  }

  start() {
    write(HIDE_CURSOR);
    this.timer = setInterval(() => {
      const frame = this.spinner.frames[this.frameIdx % this.spinner.frames.length];
      const elapsed = formatDuration(now() - this.startTime);
      write(`\r${CLEAR_LINE}  ${FG.cyan}${frame}${RESET} ${this.text} ${DIM}${elapsed}${RESET}`);
      this.frameIdx++;
    }, this.spinner.interval);
    return this;
  }

  update(text) {
    this.text = text;
  }

  succeed(text) {
    this.stop();
    const elapsed = formatDuration(now() - this.startTime);
    writeln(`\r${CLEAR_LINE}  ${ICONS.pass} ${text || this.text} ${DIM}${elapsed}${RESET}`);
  }

  fail(text) {
    this.stop();
    const elapsed = formatDuration(now() - this.startTime);
    writeln(`\r${CLEAR_LINE}  ${ICONS.fail} ${FG.red}${text || this.text}${RESET} ${DIM}${elapsed}${RESET}`);
  }

  warn(text) {
    this.stop();
    const elapsed = formatDuration(now() - this.startTime);
    writeln(`\r${CLEAR_LINE}  ${ICONS.warning} ${FG.yellow}${text || this.text}${RESET} ${DIM}${elapsed}${RESET}`);
  }

  stop() {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
    write(SHOW_CURSOR);
  }
}

// ─── PARSER DE RESULTADO ─────────────────────────────────────────

class TestResult {
  constructor(name) {
    this.name = name;
    this.passed = 0;
    this.failed = 0;
    this.skipped = 0;
    this.errors = 0;
    this.total = 0;
    this.duration = 0;
    this.failedTests = [];
    this.suites = {};
    this.output = '';
    this.exitCode = null;
  }

  get status() {
    if (this.failed > 0 || this.errors > 0) return 'FAIL';
    if (this.skipped > 0 && this.passed === 0) return 'SKIP';
    return 'PASS';
  }

  get passRate() {
    return this.total > 0 ? ((this.passed / this.total) * 100).toFixed(1) : '0.0';
  }
}

function parsePestOutput(output) {
  const result = new TestResult('Backend (Pest/PHPUnit)');
  result.output = output;

  // Strip ANSI para parsing confiável
  const clean = stripAnsi(output);

  // Pest summary: "Tests:    4910 passed (14134 assertions)"
  // Or: "Tests:    3 failed, 4907 passed (14126 assertions)"
  const summaryMatch = clean.match(/Tests:\s+(.*)/);
  if (summaryMatch) {
    const summary = summaryMatch[1];
    const passedMatch = summary.match(/(\d+)\s*passed/);
    const failedMatch = summary.match(/(\d+)\s*failed/);
    const skippedMatch = summary.match(/(\d+)\s*(?:skipped|pending|todo)/);

    if (passedMatch) result.passed = parseInt(passedMatch[1]);
    if (failedMatch) result.failed = parseInt(failedMatch[1]);
    if (skippedMatch) result.skipped = parseInt(skippedMatch[1]);
    result.total = result.passed + result.failed + result.skipped;
  }

  // Duration: "Duration:   45.23s"
  const durationMatch = clean.match(/Duration:\s+([\d.]+)s/);
  if (durationMatch) {
    result.duration = Math.round(parseFloat(durationMatch[1]) * 1000);
  }

  // Capturar nomes de testes que falharam
  const failLineRegex = /(?:✕|FAIL|×)\s+(.+)/g;
  let match;
  while ((match = failLineRegex.exec(clean)) !== null) {
    result.failedTests.push(match[1].trim());
  }

  // Fallback: contagem direta
  if (result.total === 0) {
    const passMatches = clean.match(/✓|PASS/g);
    const failMatches = clean.match(/✕|FAIL(?!ED)|×/g);
    result.passed = passMatches ? passMatches.length : 0;
    result.failed = failMatches ? failMatches.length : 0;
    result.total = result.passed + result.failed;
  }

  return result;
}

function parseVitestOutput(output) {
  const result = new TestResult('Frontend (Vitest)');
  result.output = output;

  // Strip ANSI para parsing confiável
  const clean = stripAnsi(output);

  // Vitest summary format (ANSI stripped):
  //  Test Files  181 passed (181)
  //       Tests  1903 passed (1903)
  //    Duration  106.98s (...)
  const testFilesMatch = clean.match(/Test Files\s+(.*)/);
  if (testFilesMatch) {
    const info = testFilesMatch[1];
    result.suites.passed = parseInt((info.match(/(\d+)\s*passed/) || [])[1] || '0');
    result.suites.failed = parseInt((info.match(/(\d+)\s*failed/) || [])[1] || '0');
    result.suites.total = parseInt((info.match(/\((\d+)\)/) || [])[1] || '0');
  }

  const testsMatch = clean.match(/Tests\s+(.*)/);
  if (testsMatch) {
    const summary = testsMatch[1];
    const passedMatch = summary.match(/(\d+)\s*passed/);
    const failedMatch = summary.match(/(\d+)\s*failed/);
    const skippedMatch = summary.match(/(\d+)\s*(?:skipped|todo)/);

    if (passedMatch) result.passed = parseInt(passedMatch[1]);
    if (failedMatch) result.failed = parseInt(failedMatch[1]);
    if (skippedMatch) result.skipped = parseInt(skippedMatch[1]);
    result.total = result.passed + result.failed + result.skipped;
  }

  // Duration: "Duration  106.98s (...)"
  const durationMatch = clean.match(/Duration\s+([\d.]+)\s*s/);
  if (durationMatch) {
    result.duration = Math.round(parseFloat(durationMatch[1]) * 1000);
  }

  // Capturar testes/suites falhados
  const failRegex = /FAIL\s+(.+)/g;
  let match;
  while ((match = failRegex.exec(clean)) !== null) {
    result.failedTests.push(match[1].trim());
  }

  // Fallback: contagem direta de checkmarks
  if (result.total === 0) {
    const passCount = (clean.match(/✓/g) || []).length;
    const failCount = (clean.match(/✗|×|✕/g) || []).length;
    result.passed = passCount;
    result.failed = failCount;
    result.total = passCount + failCount;
  }

  return result;
}

// ─── EXECUÇÃO DE TESTES ──────────────────────────────────────────

function runProcess(command, args, cwd, { onLine, env = {} } = {}) {
  return new Promise((resolve, reject) => {
    let output = '';
    // Junta tudo num único comando shell para evitar DEP0190
    const fullCmd = [command, ...args].map(quoteShellArg).join(' ');
    const proc = spawn(fullCmd, [], {
      cwd,
      env: { ...process.env, FORCE_COLOR: '1', TERM: 'xterm-256color', ...env },
      shell: true,
      stdio: ['pipe', 'pipe', 'pipe'],
    });

    const handleData = (data) => {
      const text = data.toString();
      output += text;
      if (onLine) {
        text.split('\n').filter(l => l.trim()).forEach(l => onLine(l));
      }
    };

    proc.stdout.on('data', handleData);
    proc.stderr.on('data', handleData);

    proc.on('close', (code) => {
      resolve({ output, exitCode: code });
    });

    proc.on('error', (err) => {
      reject(err);
    });
  });
}

async function runBackendTests({ phpCommand, suite = null, coverage = false } = {}) {
  const invocation = buildBackendTestInvocation({ suite, coverage });
  const phpEnv = buildPhpProcessEnv(phpCommand, {});

  const startTime = now();
  let lastLine = '';
  let testCount = 0;

  const spinner = new Spinner(`Rodando testes backend${suite ? ` (suite: ${suite})` : ''}...`, 'dots');
  spinner.start();

  const { output, exitCode } = await runProcess(phpCommand, invocation.args, BACKEND_DIR, {
    env: phpEnv,
    onLine: (line) => {
      testCount++;
      const clean = stripAnsi(line).trim();
      if (clean.startsWith('✓') || clean.startsWith('✗') || clean.startsWith('PASS') || clean.startsWith('FAIL')) {
        lastLine = clean;
        spinner.update(`Backend: ${testCount} testes processados — ${truncate(clean, 50)}`);
      }
    },
  });

  spinner.stop();
  write(`\r${CLEAR_LINE}`);

  const result = parsePestOutput(output);
  result.exitCode = exitCode;
  if (result.duration === 0) {
    result.duration = now() - startTime;
  }

  return result;
}

async function runFrontendTests({ coverage = false, watch = false } = {}) {
  const args = ['vitest'];

  if (watch) {
    args.push('--watch');
  } else {
    args.push('run');
  }

  if (coverage) {
    args.push('--coverage');
  }

  const startTime = now();
  let testCount = 0;

  const spinner = new Spinner('Rodando testes frontend...', 'dots');
  spinner.start();

  const { output, exitCode } = await runProcess('npx', args, FRONTEND_DIR, {
    onLine: (line) => {
      testCount++;
      const clean = stripAnsi(line).trim();
      if (clean.includes('✓') || clean.includes('✗') || clean.includes('PASS') || clean.includes('FAIL') || clean.includes('passed') || clean.includes('Tests')) {
        spinner.update(`Frontend: ${testCount} linhas processadas — ${truncate(clean, 50)}`);
      }
    },
  });

  spinner.stop();
  write(`\r${CLEAR_LINE}`);

  const result = parseVitestOutput(output);
  result.exitCode = exitCode;
  if (result.duration === 0) {
    result.duration = now() - startTime;
  }

  return result;
}

async function runPhpStan({ phpCommand }) {
  const spinner = new Spinner('Rodando análise estática (PHPStan)...', 'arc');
  spinner.start();
  const startTime = now();
  const phpEnv = buildPhpProcessEnv(phpCommand, {});

  const { output, exitCode } = await runProcess(
    phpCommand,
    ['vendor/bin/phpstan', 'analyse', '--memory-limit=512M', '--no-progress', '--error-format=raw'],
    BACKEND_DIR,
    { env: phpEnv },
  );

  const duration = now() - startTime;

  const errorMatch = output.match(/Found (\d+) error/);
  const errors = errorMatch ? parseInt(errorMatch[1]) : 0;

  if (exitCode === 0) {
    spinner.succeed(`PHPStan: 0 erros ${DIM}(${formatDuration(duration)})${RESET}`);
  } else if (errors > 0) {
    spinner.fail(`PHPStan: ${errors} erros ${DIM}(${formatDuration(duration)})${RESET}`);
  } else {
    spinner.succeed(`PHPStan concluído ${DIM}(${formatDuration(duration)})${RESET}`);
  }

  return { errors, exitCode, duration, output };
}

async function runTypeScript() {
  const spinner = new Spinner('Rodando checagem de tipos (TypeScript)...', 'arc');
  spinner.start();
  const startTime = now();

  const { output, exitCode } = await runProcess('npx', ['tsc', '--noEmit'], FRONTEND_DIR);

  const duration = now() - startTime;
  const errorLines = output.split('\n').filter(l => l.includes('error TS'));

  if (exitCode === 0) {
    spinner.succeed(`TypeScript: 0 erros ${DIM}(${formatDuration(duration)})${RESET}`);
  } else {
    spinner.fail(`TypeScript: ${errorLines.length} erros ${DIM}(${formatDuration(duration)})${RESET}`);
  }

  return { errors: errorLines.length, exitCode, duration, output };
}

// ─── APRESENTAÇÃO ────────────────────────────────────────────────

function printBanner() {
  writeln();
  const banner = [
    `${fgRGB(80, 200, 255)}  ╔══════════════════════════════════════════════════════════════════╗${RESET}`,
    `${fgRGB(80, 200, 255)}  ║${RESET}${fgRGB(255, 255, 255)}${BOLD}    ${ICONS.flask} SISTEMA — Test Runner Dashboard ${ICONS.lightning}                      ${RESET}${fgRGB(80, 200, 255)}║${RESET}`,
    `${fgRGB(80, 200, 255)}  ║${RESET}${DIM}    Backend (PHP/Pest) + Frontend (React/Vitest) + Análise     ${RESET}${fgRGB(80, 200, 255)}║${RESET}`,
    `${fgRGB(80, 200, 255)}  ╚══════════════════════════════════════════════════════════════════╝${RESET}`,
  ];
  banner.forEach(l => writeln(l));
  writeln();
}

function printSectionHeader(title, icon = '▸') {
  writeln();
  writeln(`  ${fgRGB(100, 180, 255)}${icon} ${BOLD}${title}${RESET}`);
  writeln(`  ${FG.gray}${repeat('─', Math.min(COLS - 4, 66))}${RESET}`);
}

function printTestResult(result) {
  const statusColor = result.status === 'PASS' ? FG.brightGreen : FG.brightRed;
  const badge = statusBadge(result.status);

  printSectionHeader(`${result.name}`, result.name.includes('Backend') ? ICONS.php : ICONS.react);

  // Barra de progresso
  writeln();
  write(`    `);
  if (result.total > 0) {
    // Barra tricolor: verde (pass) | vermelho (fail) | amarelo (skip)
    const barWidth = 50;
    const passLen = Math.round((result.passed / result.total) * barWidth);
    const failLen = Math.round((result.failed / result.total) * barWidth);
    const skipLen = barWidth - passLen - failLen;

    write(`${FG.brightGreen}${repeat('█', passLen)}${RESET}`);
    write(`${FG.brightRed}${repeat('█', failLen)}${RESET}`);
    write(`${FG.yellow}${repeat('█', skipLen)}${RESET}`);
    writeln(`  ${badge}`);
  } else {
    writeln(`  ${FG.gray}Sem dados de testes${RESET}`);
  }
  writeln();

  // Info de suites (se disponível)
  if (result.suites && result.suites.total > 0) {
    writeln(`    ${ICONS.folder} ${FG.brightCyan}${BOLD}${result.suites.passed}${RESET}${DIM}/${result.suites.total} suites${RESET}`);
  }

  // Métricas em grid
  const metrics = [
    `    ${ICONS.pass} ${FG.brightGreen}${BOLD}${result.passed.toLocaleString()}${RESET} ${DIM}passaram${RESET}`,
    `    ${ICONS.fail} ${FG.brightRed}${BOLD}${result.failed}${RESET} ${DIM}falharam${RESET}`,
    `    ${ICONS.skip} ${FG.yellow}${BOLD}${result.skipped}${RESET} ${DIM}pulados${RESET}`,
    `    ${ICONS.clock} ${FG.cyan}${BOLD}${formatDuration(result.duration)}${RESET} ${DIM}duração${RESET}`,
  ];

  // Duas métricas por linha
  for (let i = 0; i < metrics.length; i += 2) {
    const left = pad(metrics[i], 35);
    const right = metrics[i + 1] || '';
    writeln(`${left}${right}`);
  }

  // Taxa de aprovação com barra visual
  writeln();
  const rate = parseFloat(result.passRate);
  const rateColor = rate >= 99 ? fgRGB(0, 255, 100) : rate >= 90 ? FG.yellow : FG.brightRed;
  writeln(`    ${ICONS.chart} Taxa: ${rateColor}${BOLD}${result.passRate}%${RESET} ${DIM}aprovação${RESET}`);

  // Sparkline visual da taxa
  const sparkBlocks = Math.round(rate / 5);
  const sparkStr = repeat('▓', sparkBlocks) + repeat('░', 20 - sparkBlocks);
  writeln(`    ${rateColor}    ${sparkStr}${RESET}`);

  // Testes falhados em destaque
  if (result.failedTests.length > 0) {
    writeln();
    writeln(`    ${FG.brightRed}${BOLD}Testes falhados:${RESET}`);
    result.failedTests.slice(0, 15).forEach(t => {
      writeln(`    ${FG.red}  ✗ ${truncate(t, COLS - 10)}${RESET}`);
    });
    if (result.failedTests.length > 15) {
      writeln(`    ${DIM}  ... e mais ${result.failedTests.length - 15}${RESET}`);
    }
  }
}

function printSummaryDashboard(results, extras = {}) {
  const totalPassed = results.reduce((s, r) => s + r.passed, 0);
  const totalFailed = results.reduce((s, r) => s + r.failed, 0);
  const totalSkipped = results.reduce((s, r) => s + r.skipped, 0);
  const totalTests = results.reduce((s, r) => s + r.total, 0);
  const totalDuration = results.reduce((s, r) => s + r.duration, 0);
  const allPassed = results.every(r => r.status === 'PASS');

  writeln();
  writeln();

  // Header do resumo
  const summaryBorder = allPassed ? fgRGB(0, 200, 100) : fgRGB(255, 80, 80);
  const summaryIcon = allPassed ? ICONS.trophy : ICONS.bug;
  const summaryTitle = allPassed ? ' TODOS OS TESTES PASSARAM ' : ' EXISTEM FALHAS ';
  const summaryBg = allPassed ? bgRGB(0, 120, 60) : bgRGB(180, 30, 30);

  writeln(`  ${summaryBorder}╔══════════════════════════════════════════════════════════════════╗${RESET}`);
  writeln(`  ${summaryBorder}║${RESET}  ${summaryIcon} ${summaryBg}${FG.white}${BOLD}${summaryTitle}${RESET}${repeat(' ', Math.max(0, 42 - summaryTitle.length))}${summaryBorder}║${RESET}`);
  writeln(`  ${summaryBorder}╠══════════════════════════════════════════════════════════════════╣${RESET}`);

  // Métricas consolidadas
  const lines = [
    `  ${FG.brightGreen}${BOLD}${totalPassed.toLocaleString()}${RESET} ${DIM}passaram${RESET}  ·  ${FG.brightRed}${BOLD}${totalFailed}${RESET} ${DIM}falharam${RESET}  ·  ${FG.yellow}${BOLD}${totalSkipped}${RESET} ${DIM}pulados${RESET}  ·  ${BOLD}${totalTests.toLocaleString()}${RESET} ${DIM}total${RESET}`,
    `  ${ICONS.clock} ${FG.cyan}${BOLD}${formatDuration(totalDuration)}${RESET} ${DIM}tempo total${RESET}`,
  ];

  for (const line of lines) {
    const stripped = stripAnsi(line);
    const padR = Math.max(0, 64 - stripped.length);
    writeln(`  ${summaryBorder}║${RESET}${line}${repeat(' ', padR)}${summaryBorder}║${RESET}`);
  }

  // Barra de progresso total
  if (totalTests > 0) {
    const barWidth = 50;
    const passLen = Math.round((totalPassed / totalTests) * barWidth);
    const failLen = Math.round((totalFailed / totalTests) * barWidth);
    const skipLen = barWidth - passLen - failLen;
    const bar = `  ${FG.brightGreen}${repeat('█', passLen)}${FG.brightRed}${repeat('█', failLen)}${FG.yellow}${repeat('█', Math.max(0, skipLen))}${RESET}`;
    const rate = ((totalPassed / totalTests) * 100).toFixed(1);
    const barLine = `${bar} ${BOLD}${rate}%${RESET}`;
    const stripped2 = stripAnsi(barLine);
    const padR2 = Math.max(0, 64 - stripped2.length);
    writeln(`  ${summaryBorder}║${RESET}${barLine}${repeat(' ', padR2)}${summaryBorder}║${RESET}`);
  }

  // Extras (PHPStan, TypeScript)
  if (extras.phpstan !== undefined || extras.typescript !== undefined) {
    writeln(`  ${summaryBorder}╠══════════════════════════════════════════════════════════════════╣${RESET}`);

    if (extras.phpstan !== undefined) {
      const phpstanIcon = extras.phpstan.errors === 0 ? ICONS.pass : ICONS.fail;
      const phpstanText = `  ${phpstanIcon} PHPStan: ${extras.phpstan.errors} erros ${DIM}(${formatDuration(extras.phpstan.duration)})${RESET}`;
      const stripped3 = stripAnsi(phpstanText);
      writeln(`  ${summaryBorder}║${RESET}${phpstanText}${repeat(' ', Math.max(0, 64 - stripped3.length))}${summaryBorder}║${RESET}`);
    }

    if (extras.typescript !== undefined) {
      const tsIcon = extras.typescript.errors === 0 ? ICONS.pass : ICONS.fail;
      const tsText = `  ${tsIcon} TypeScript: ${extras.typescript.errors} erros ${DIM}(${formatDuration(extras.typescript.duration)})${RESET}`;
      const stripped4 = stripAnsi(tsText);
      writeln(`  ${summaryBorder}║${RESET}${tsText}${repeat(' ', Math.max(0, 64 - stripped4.length))}${summaryBorder}║${RESET}`);
    }
  }

  writeln(`  ${summaryBorder}╚══════════════════════════════════════════════════════════════════╝${RESET}`);

  // Timestamp
  writeln();
  const timestamp = new Date().toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' });
  writeln(`  ${DIM}${ICONS.gear} Executado em ${timestamp}${RESET}`);
  writeln();
}

function printLiveOutput(result) {
  // Mostra o output real do teste com cores originais
  if (result.output && result.failedTests.length > 0) {
    printSectionHeader('Output detalhado das falhas', '📋');

    // Extrair apenas seções de falha do output
    const lines = result.output.split('\n');
    let inFailBlock = false;
    let failOutput = [];

    for (const line of lines) {
      const clean = stripAnsi(line);
      if (clean.includes('FAIL') || clean.includes('✗') || clean.includes('×') || clean.includes('Error:') || clean.includes('Failed asserting')) {
        inFailBlock = true;
      }
      if (inFailBlock) {
        failOutput.push(`    ${line}`);
        if (clean.trim() === '' && failOutput.length > 3) {
          inFailBlock = false;
        }
      }
    }

    if (failOutput.length > 0) {
      failOutput.slice(0, 50).forEach(l => writeln(l));
      if (failOutput.length > 50) {
        writeln(`    ${DIM}... truncado (${failOutput.length} linhas totais)${RESET}`);
      }
    }
  }
}

// ─── MODO WATCH VISUAL ──────────────────────────────────────────

async function watchMode() {
  writeln(`${FG.cyan}${BOLD}  Modo Watch ativo — Vitest está monitorando alterações...${RESET}`);
  writeln(`${DIM}  Pressione Ctrl+C para sair${RESET}`);
  writeln();

  const proc = spawn('npx', ['vitest', '--watch'], {
    cwd: FRONTEND_DIR,
    env: { ...process.env, FORCE_COLOR: '1', TERM: 'xterm-256color' },
    shell: true,
    stdio: 'inherit',
  });

  return new Promise((resolve) => {
    proc.on('close', resolve);
  });
}

// ─── MAIN ────────────────────────────────────────────────────────

async function main() {
  const args = process.argv.slice(2);

  if (args.includes('--help') || args.includes('-h')) {
    printBanner();
    writeln(`  ${BOLD}Uso:${RESET}`);
    writeln(`    ${FG.cyan}node test-runner.mjs${RESET}                 ${DIM}Roda tudo (backend + frontend + análise)${RESET}`);
    writeln(`    ${FG.cyan}node test-runner.mjs backend${RESET}         ${DIM}Só testes backend (Pest/PHPUnit)${RESET}`);
    writeln(`    ${FG.cyan}node test-runner.mjs frontend${RESET}        ${DIM}Só testes frontend (Vitest)${RESET}`);
    writeln(`    ${FG.cyan}node test-runner.mjs analysis${RESET}        ${DIM}Só análise estática (PHPStan + TypeScript)${RESET}`);
    writeln();
    writeln(`  ${BOLD}Flags:${RESET}`);
    writeln(`    ${FG.yellow}--suite=Unit${RESET}        ${DIM}Suite específica do backend (Unit, Feature, Smoke, Critical, Arch, E2E)${RESET}`);
    writeln(`    ${FG.yellow}--parallel${RESET}          ${DIM}Roda backend e frontend em paralelo${RESET}`);
    writeln(`    ${FG.yellow}--coverage${RESET}          ${DIM}Gera relatório de cobertura${RESET}`);
    writeln(`    ${FG.yellow}--watch${RESET}             ${DIM}Modo watch (frontend — monitora mudanças)${RESET}`);
    writeln(`    ${FG.yellow}--verbose, -v${RESET}       ${DIM}Mostra output detalhado das falhas${RESET}`);
    writeln();
    writeln(`  ${BOLD}Exemplos:${RESET}`);
    writeln(`    ${DIM}node test-runner.mjs backend --suite=Unit     ${RESET}${FG.gray}# Só testes unitários do backend${RESET}`);
    writeln(`    ${DIM}node test-runner.mjs --parallel               ${RESET}${FG.gray}# Backend + Frontend ao mesmo tempo${RESET}`);
    writeln(`    ${DIM}node test-runner.mjs frontend --watch          ${RESET}${FG.gray}# Vitest em modo watch${RESET}`);
    writeln(`    ${DIM}node test-runner.mjs frontend --coverage       ${RESET}${FG.gray}# Frontend com cobertura de código${RESET}`);
    writeln();
    return;
  }

  const flags = resolveRunnerFlags(args);
  const runBackend = flags.runBackendTests;
  const runFrontend = flags.runFrontendTests;
  const runAnalysis = flags.runAnalysis;
  const parallel = args.includes('--parallel');
  const coverage = args.includes('--coverage');
  const watch = args.includes('--watch');
  const suiteArg = args.find(a => a.startsWith('--suite='));
  const suite = suiteArg ? suiteArg.split('=')[1] : null;
  const onlyAnalysis = flags.onlyAnalysis;
  const verbose = args.includes('--verbose') || args.includes('-v');
  const phpRuntime = flags.shouldResolvePhpRuntime
    ? resolvePhpRuntime()
    : null;

  // Modo watch especial
  if (watch) {
    printBanner();
    await watchMode();
    return;
  }

  const totalStart = now();
  printBanner();

  // Info do ambiente
  const phpDisplay = phpRuntime ? `${phpRuntime.version} (${phpRuntime.command})` : 'N/A';
  writeln(`  ${ICONS.gear} ${DIM}PHP ${phpDisplay} · Node 24 · Pest · Vitest · PHPStan · TypeScript${RESET}`);
  writeln(`  ${ICONS.folder} ${DIM}${ROOT}${RESET}`);
  writeln();

  const results = [];
  const extras = {};

  // Check for SQLite driver if running backend tests
  let canRunBackend = runBackend;
  if (runBackend && phpRuntime) {
    const { output: phpModules } = await runProcess(phpRuntime.command, ['-m'], ROOT, {
      env: buildPhpProcessEnv(phpRuntime.command, {}),
    });
    if (!phpModules.includes('pdo_sqlite') && !phpModules.includes('sqlite3')) {
      writeln(`  ${ICONS.warning} ${FG.yellow}Aviso: Driver SQLite não encontrado no PHP. Pulando testes de backend.${RESET}`);
      canRunBackend = false;
      const skippedResult = new TestResult('Backend (Pest/PHPUnit)');
      skippedResult.skipped = 1;
      skippedResult.total = 1;
      results.push(skippedResult);
    }
  }

  if (parallel && canRunBackend && runFrontend) {
    // Execução paralela
    writeln(`  ${ICONS.lightning} ${FG.brightCyan}${BOLD}Execução em paralelo${RESET}`);
    writeln();

    const [backendResult, frontendResult] = await Promise.all([
      runBackendTests({ phpCommand: phpRuntime.command, suite, coverage }),
      runFrontendTests({ coverage }),
    ]);

    results.push(backendResult, frontendResult);
  } else {
    // Execução sequencial
    if (runBackend && !onlyAnalysis) {
      const backendResult = await runBackendTests({ phpCommand: phpRuntime.command, suite, coverage });
      results.push(backendResult);
      printTestResult(backendResult);
      if (verbose) printLiveOutput(backendResult);
    }

    if (runFrontend && !onlyAnalysis) {
      const frontendResult = await runFrontendTests({ coverage });
      results.push(frontendResult);
      printTestResult(frontendResult);
      if (verbose) printLiveOutput(frontendResult);
    }
  }

  // Se rodou em paralelo, mostra resultados depois
  if (parallel) {
    results.forEach(r => {
      printTestResult(r);
      if (verbose) printLiveOutput(r);
    });
  }

  // Análise estática
  if (runAnalysis) {
    printSectionHeader('Análise Estática', ICONS.shield);
    writeln();

    const [phpstanResult, tsResult] = await Promise.all([
      flags.runBackendAnalysis ? runPhpStan({ phpCommand: phpRuntime.command }) : Promise.resolve(null),
      flags.runFrontendAnalysis ? runTypeScript() : Promise.resolve(null),
    ]);

    if (phpstanResult) extras.phpstan = phpstanResult;
    if (tsResult) extras.typescript = tsResult;
  }

  // Dashboard final
  if (results.length > 0) {
    printSummaryDashboard(results, extras);
  } else if (onlyAnalysis) {
    writeln();
    writeln(`  ${ICONS.check} Análise estática concluída.`);
    writeln();
  }

  // Exit code
  const hasFailures = results.some(r => r.status === 'FAIL')
    || (extras.phpstan && extras.phpstan.errors > 0)
    || (extras.typescript && extras.typescript.errors > 0);

  process.exit(hasFailures ? 1 : 0);
}

// Handler de SIGINT para limpar cursor
process.on('SIGINT', () => {
  write(SHOW_CURSOR);
  writeln();
  writeln(`\n  ${DIM}Interrompido pelo usuário.${RESET}\n`);
  process.exit(130);
});

main().catch(err => {
  write(SHOW_CURSOR);
  console.error(`${FG.red}Erro fatal: ${err.message}${RESET}`);
  process.exit(1);
});
