---
name: soleil-ai-review-engine-cli
description: "Use when the user needs to run soleil-ai-review-engine CLI commands like analyze/index a repo, check status, clean the index, generate a wiki, or list indexed repos. Examples: \"Index this repo\", \"Reanalyze the codebase\", \"Generate a wiki\""
---

# soleil-ai-review-engine CLI Commands

All commands work via `npx` — no global install required.

## Commands

### analyze — Build or refresh the index

```bash
npx soleil-ai-review-engine analyze
```

Run from the project root. This parses all source files, builds the knowledge graph, writes it to `.soleil-ai-review-engine/`, and generates CLAUDE.md / AGENTS.md context files.

| Flag           | Effect                                                           |
| -------------- | ---------------------------------------------------------------- |
| `--force`      | Force full re-index even if up to date                           |
| `--embeddings` | Enable embedding generation for semantic search (off by default) |

**When to run:** First time in a project, after major code changes, or when `soleil-ai-review-engine://repo/{name}/context` reports the index is stale. In Claude Code, a PostToolUse hook runs `analyze` automatically after `git commit` and `git merge`, preserving embeddings if previously generated.

### status — Check index freshness

```bash
npx soleil-ai-review-engine status
```

Shows whether the current repo has a soleil-ai-review-engine index, when it was last updated, and symbol/relationship counts. Use this to check if re-indexing is needed.

### clean — Delete the index

```bash
npx soleil-ai-review-engine clean
```

Deletes the `.soleil-ai-review-engine/` directory and unregisters the repo from the global registry. Use before re-indexing if the index is corrupt or after removing soleil-ai-review-engine from a project.

| Flag      | Effect                                            |
| --------- | ------------------------------------------------- |
| `--force` | Skip confirmation prompt                          |
| `--all`   | Clean all indexed repos, not just the current one |

### wiki — Generate documentation from the graph

```bash
npx soleil-ai-review-engine wiki
```

Generates repository documentation from the knowledge graph using an LLM. Requires an API key (saved to `~/.soleil-ai-review-engine/config.json` on first use).

| Flag                | Effect                                    |
| ------------------- | ----------------------------------------- |
| `--force`           | Force full regeneration                   |
| `--model <model>`   | LLM model (default: minimax/minimax-m2.5) |
| `--base-url <url>`  | LLM API base URL                          |
| `--api-key <key>`   | LLM API key                               |
| `--concurrency <n>` | Parallel LLM calls (default: 3)           |
| `--gist`            | Publish wiki as a public GitHub Gist      |

### list — Show all indexed repos

```bash
npx soleil-ai-review-engine list
```

Lists all repositories registered in `~/.soleil-ai-review-engine/registry.json`. The MCP `list_repos` tool provides the same information.

## After Indexing

1. **Read `soleil-ai-review-engine://repo/{name}/context`** to verify the index loaded
2. Use the other soleil-ai-review-engine skills (`exploring`, `debugging`, `impact-analysis`, `refactoring`) for your task

## Troubleshooting

- **"Not inside a git repository"**: Run from a directory inside a git repo
- **Index is stale after re-analyzing**: Restart Claude Code to reload the MCP server
- **Embeddings slow**: Omit `--embeddings` (it's off by default) or set `OPENAI_API_KEY` for faster API-based embedding
