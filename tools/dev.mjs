/**
 * tools/dev.mjs
 *
 * UNC-safe dev launcher for Soleil Hostel monorepo.
 *
 * Why this exists:
 *   `concurrently` (and npm scripts in general) delegate to cmd.exe on Windows.
 *   cmd.exe cannot operate in a UNC working directory (\\?\...) and silently
 *   falls back to C:\Windows, making every subsequent `npm run` fail with ENOENT.
 *   Node.js resolves __dirname via import.meta.url at the V8 level — it never
 *   touches cmd.exe's cwd, so the UNC path is irrelevant.
 *
 * Usage: node tools/dev.mjs
 */

import { spawn }    from 'child_process';
import { fileURLToPath } from 'url';
import { dirname, join, resolve } from 'path';

// ── Absolute root, derived from this file's location ────────────────────────
const __filename = fileURLToPath(import.meta.url);   // …/tools/dev.mjs
const ROOT       = resolve(dirname(__filename), '..'); // …/soleil-hostel

// ── ANSI colour helpers ──────────────────────────────────────────────────────
const C = {
  backend:  '\x1b[36m',   // cyan
  frontend: '\x1b[35m',   // magenta
  info:     '\x1b[33m',   // yellow
  reset:    '\x1b[0m',
};

function tag(name, text) {
  return `${C[name] ?? ''}[${name}]${C.reset} ${text}`;
}

// ── Process spawner ──────────────────────────────────────────────────────────
function run(name, cmd, args, cwd) {
  console.log(tag('info', `Starting ${name}  →  ${cmd} ${args.join(' ')}  (cwd: ${cwd})`));

  const proc = spawn(cmd, args, {
    cwd,                    // absolute path derived from import.meta.url — never UNC
    stdio: ['ignore', 'pipe', 'pipe'],
    shell: true,            // required on Windows: pnpm/artisan ship as .cmd wrappers,
                            // not native .exe — shell: false cannot resolve them.
                            // Safe here because cwd is an explicit absolute path,
                            // not inherited from the UNC parent session.
    windowsHide: false,
  });

  proc.stdout.on('data', d =>
    process.stdout.write(tag(name, String(d).trimEnd()) + '\n'));

  proc.stderr.on('data', d =>
    process.stderr.write(tag(name, String(d).trimEnd()) + '\n'));

  proc.on('error', err =>
    console.error(tag(name, `ERROR: ${err.message}`)));

  proc.on('exit', (code, signal) =>
    console.log(tag(name, `exited  code=${code ?? 'null'}  signal=${signal ?? 'none'}`)));

  return proc;
}

// ── Launch both servers ──────────────────────────────────────────────────────
console.log(`\n${C.info}[dev]${C.reset} Soleil Hostel dev servers starting from ${ROOT}\n`);

const procs = [
  run('backend',  'php',  ['artisan', 'serve', '--host=127.0.0.1', '--port=8000'],
      join(ROOT, 'backend')),

  run('frontend', 'pnpm', ['run', 'dev', '--', '--host'],
      join(ROOT, 'frontend')),
];

// ── Graceful shutdown on Ctrl-C ──────────────────────────────────────────────
function shutdown() {
  console.log(`\n${C.info}[dev]${C.reset} Shutting down…`);
  procs.forEach(p => { try { p.kill('SIGTERM'); } catch (_) {} });
  setTimeout(() => process.exit(0), 1000);
}

process.on('SIGINT',  shutdown);
process.on('SIGTERM', shutdown);
