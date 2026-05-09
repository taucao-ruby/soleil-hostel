#!/usr/bin/env node
// scripts/lint-doc-pointers.mjs — Soleil Hostel
// Verifies that every relative file path cited inside authoritative instruction
// docs resolves on disk. Catches dangling pointers and typos.
//
// Audited targets (with optional region trimming):
//   - CLAUDE.md                              constitutional region only
//   - AGENTS.md                              full file
//   - docs/README.md                         full file
//   - docs/agents/CONTROL_PLANE_OWNERSHIP.md full file
//
// A target that does not exist is skipped (fail-soft).
// Exit codes: 0 ok; 1 dangling pointer detected.

import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
let hardFail = false;
const note = (m) => process.stdout.write(`[lint-doc-pointers] ${m}\n`);
const warn = (m) => {
  process.stderr.write(`[lint-doc-pointers] WARN ${m}\n`);
  hardFail = true;
};

// targets: { rel, trim?(txt) -> region }
const targets = [
  {
    rel: "CLAUDE.md",
    // CLAUDE.md has an auto-injected soleil-ai-review-engine block; only audit the
    // constitutional region above it.
    trim: (txt) => {
      const cutoff = txt.search(/<!--\s*soleil-ai-review-engine:start/);
      return cutoff > 0 ? txt.slice(0, cutoff) : txt;
    },
  },
  { rel: "AGENTS.md" },
  { rel: "docs/README.md" },
  { rel: "docs/agents/CONTROL_PLANE_OWNERSHIP.md" },
];

// Collect repo paths from two syntaxes:
//   1. Backtick-quoted: `docs/foo.md`. Bare filenames in prose are excluded
//      (must contain a path separator).
//   2. Markdown links: [text](docs/foo.md) or [text](./foo.md) or [text](../foo.md).
//      Bare filenames are accepted here because the markdown link itself signals
//      a pointer claim.
const backtickRe = /`([A-Za-z0-9_./\-]+(?:\.[A-Za-z0-9]+|\/))`/g;
const mdLinkRe = /\]\(([A-Za-z0-9_./\-]+(?:\.[A-Za-z0-9]+|\/))(?:#[^)]*)?\)/g;

function isExcluded(p) {
  if (p.startsWith("http") || p.startsWith("/api/") || p.includes("://"))
    return true;
  if (p.includes("*")) return true;
  if (p.startsWith("frontend/src/") || p.startsWith("backend/")) return true;
  return false;
}

// Paths that are intentionally absent from the repository and must not trigger
// lint failures. These are valid documentation references to operational
// artifacts that are either generated at runtime by tooling or are per-developer
// local config. All entries are confirmed in .gitignore.
//
//   .claude/worktrees/              runtime — Claude Code generated worktree dir        (.gitignore:51)
//   .soleil-ai-review-engine/meta.json  runtime — produced by `npx soleil-engine-cli analyze` (.gitignore:84, via .soleil-ai-review-engine/)
//   .claude/settings.local.json     local   — per-developer Claude settings override   (.gitignore:50)
//
// To add an entry, confirm the path is in .gitignore or is truly generated/local,
// then append it here with a brief reason. Do NOT use this as a broad bypass.
const KNOWN_ABSENT = new Set([
  ".claude/worktrees/",
  ".soleil-ai-review-engine/meta.json",
  ".claude/settings.local.json",
]);

function collectPaths(region) {
  const seen = new Set();
  let m;
  while ((m = backtickRe.exec(region)) !== null) {
    const p = m[1];
    if (!p.includes("/")) continue;
    if (isExcluded(p)) continue;
    seen.add({ raw: p, kind: "backtick" });
  }
  while ((m = mdLinkRe.exec(region)) !== null) {
    const p = m[1];
    if (isExcluded(p)) continue;
    seen.add({ raw: p, kind: "mdlink" });
  }
  return [...seen];
}

let totalChecked = 0;
let totalMissing = 0;
let auditedFiles = 0;

for (const { rel, trim } of targets) {
  const abs = path.join(root, rel);
  if (!fs.existsSync(abs)) {
    note(`${rel} not present; skipping.`);
    continue;
  }
  auditedFiles++;
  const txt = fs.readFileSync(abs, "utf8");
  const region = trim ? trim(txt) : txt;
  const paths = collectPaths(region);
  const fileDir = path.dirname(abs);
  let missing = 0;
  // dedupe by resolved absolute path so we only complain once per actual target
  const resolved = new Map();
  for (const { raw, kind } of paths) {
    const base =
      kind === "mdlink" && (raw.startsWith("./") || raw.startsWith("../"))
        ? fileDir
        : root;
    const absTarget = path.resolve(base, raw);
    if (!resolved.has(absTarget)) resolved.set(absTarget, raw);
  }
  for (const [absTarget, raw] of [...resolved].sort()) {
    totalChecked++;
    if (KNOWN_ABSENT.has(raw)) continue; // intentionally absent runtime/local path; see KNOWN_ABSENT
    if (!fs.existsSync(absTarget)) {
      warn(`${rel} cites missing path: ${raw}`);
      missing++;
      totalMissing++;
    }
  }
  note(`${rel}: ${resolved.size} cited paths checked, ${missing} missing`);
}

note(
  `audited ${auditedFiles} files; ${totalChecked} total cited paths; ${totalMissing} missing`,
);
process.exit(hardFail ? 1 : 0);
