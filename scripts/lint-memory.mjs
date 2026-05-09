#!/usr/bin/env node
// scripts/lint-memory.mjs — Soleil Hostel
// Validates the user-home auto-memory directory for size, drift, and secret-like content.
// Fail-soft: if the memory dir is absent (e.g., CI), exit 0 with a note.
// Exit codes: 0 ok / warnings only; 1 hard violation.

import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';

const MAX_LINES = 200;
const MAX_BYTES = 32 * 1024;
const STALE_DAYS = 60;

const SECRET_PATTERNS = [
  /AKIA[0-9A-Z]{16}/,                                    // AWS access key id
  /(sk-|rk_)live_[A-Za-z0-9]{20,}/,                      // Stripe live key
  /xox[baprs]-[A-Za-z0-9-]{10,}/,                        // Slack token
  /-----BEGIN (?:RSA|OPENSSH|EC|DSA|PGP|ENCRYPTED) PRIVATE KEY/, // PEM
  /ghp_[A-Za-z0-9]{36}/,                                 // GitHub PAT
  /password\s*[:=]\s*['"][^'"]{6,}/i,                    // password literal
];

const repoRoot = process.cwd();
const homeMemDir = path.join(
  os.homedir(),
  '.claude',
  'projects',
  repoRoot.replace(/[\\/:]/g, '-'),
  'memory'
);

function note(msg) { process.stdout.write(`[lint-memory] ${msg}\n`); }
function warn(msg) { process.stderr.write(`[lint-memory] WARN ${msg}\n`); }

if (!fs.existsSync(homeMemDir)) {
  note(`memory dir not present (${homeMemDir}); skipping (fail-soft).`);
  process.exit(0);
}

const memFile = path.join(homeMemDir, 'MEMORY.md');
let hardFail = false;

if (!fs.existsSync(memFile)) {
  note('MEMORY.md not present; skipping.');
  process.exit(0);
}

const txt = fs.readFileSync(memFile, 'utf8');
const lineCount = txt.split(/\r?\n/).length;
const byteCount = Buffer.byteLength(txt, 'utf8');

note(`MEMORY.md: ${lineCount} lines, ${byteCount} bytes`);

if (lineCount > MAX_LINES) {
  warn(`MEMORY.md exceeds ${MAX_LINES} lines (${lineCount}); promote stable facts to canonical docs and archive stale entries to MEMORY_LOG.md`);
  hardFail = true;
}
if (byteCount > MAX_BYTES) {
  warn(`MEMORY.md exceeds ${MAX_BYTES} bytes (${byteCount})`);
  hardFail = true;
}

for (const re of SECRET_PATTERNS) {
  if (re.test(txt)) {
    warn(`MEMORY.md matches secret-like pattern ${re}`);
    hardFail = true;
  }
}

const dateRe = /\b(20\d{2})-(\d{2})-(\d{2})\b/g;
const now = Date.now();
const dates = [...txt.matchAll(dateRe)].map(m => Date.parse(m[0])).filter(t => !Number.isNaN(t));
if (dates.length > 0) {
  const newest = Math.max(...dates);
  const ageDays = Math.floor((now - newest) / 86400_000);
  if (ageDays > STALE_DAYS) {
    warn(`MEMORY.md newest dated entry is ${ageDays}d old (>${STALE_DAYS}d); reviewer should refresh or archive`);
  } else {
    note(`MEMORY.md newest dated entry: ${ageDays}d old`);
  }
}

process.exit(hardFail ? 1 : 0);
