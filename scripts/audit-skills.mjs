#!/usr/bin/env node
// scripts/audit-skills.mjs — Soleil Hostel
// Validates .claude/skills/ skill files for safe defaults:
//   - Generated skills under .claude/skills/generated/* MUST have disable-model-invocation: true
//   - No skill should declare overly broad allowed-tools (e.g. unrestricted "*")
//   - Each SKILL.md needs name + description in frontmatter
// Exit codes: 0 ok / warnings only; 1 hard violation.

import fs from 'node:fs';
import path from 'node:path';

const root = path.join(process.cwd(), '.claude', 'skills');
let hardFail = false;
const note = m => process.stdout.write(`[audit-skills] ${m}\n`);
const warn = m => { process.stderr.write(`[audit-skills] WARN ${m}\n`); hardFail = true; };

if (!fs.existsSync(root)) {
  note('no .claude/skills/ directory; skipping (fail-soft).');
  process.exit(0);
}

function* skillFiles(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) yield* skillFiles(p);
    else if (e.isFile() && e.name === 'SKILL.md') yield p;
  }
}

function parseFrontmatter(txt) {
  const m = txt.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  if (!m) return null;
  const fm = {};
  for (const line of m[1].split(/\r?\n/)) {
    const kv = line.match(/^([\w-]+):\s*(.*?)\s*$/);
    if (kv) fm[kv[1]] = kv[2].replace(/^["']|["']$/g, '');
  }
  return fm;
}

let count = 0;
for (const file of skillFiles(root)) {
  count++;
  const txt = fs.readFileSync(file, 'utf8');
  const fm = parseFrontmatter(txt);
  if (!fm) { warn(`${file}: missing or malformed frontmatter`); continue; }
  if (!fm.name) warn(`${file}: missing 'name'`);
  if (!fm.description) warn(`${file}: missing 'description'`);

  const isGenerated = file.includes(`${path.sep}generated${path.sep}`);
  if (isGenerated && fm['disable-model-invocation'] !== 'true') {
    warn(`${file}: generated skill must declare 'disable-model-invocation: true'`);
  }

  if (fm['allowed-tools']) {
    const tools = fm['allowed-tools'];
    if (/(^|\s)\*(\s|$)/.test(tools) || /\bAll\b/i.test(tools)) {
      warn(`${file}: 'allowed-tools' contains overly broad value (${tools})`);
    }
  }
}

note(`scanned ${count} skill files`);
process.exit(hardFail ? 1 : 0);
