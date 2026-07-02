---
name: soleil-ai-review-engine-guide
description: "Use when the user asks about soleil-ai-review-engine itself — available tools, how to query the knowledge graph, MCP resources, graph schema, or workflow reference. Examples: \"What soleil-ai-review-engine tools are available?\", \"How do I use soleil-ai-review-engine?\""
---

# soleil-ai-review-engine Guide

Quick reference for all soleil-ai-review-engine MCP tools, resources, and the knowledge graph schema.

## Always Start Here

For any task involving code understanding, debugging, impact analysis, or refactoring:

1. **Read `soleil-ai-review-engine://repo/{name}/context`** — codebase overview + check index freshness
2. **Match your task to a skill below** and **read that skill file**
3. **Follow the skill's workflow and checklist**

> If step 1 warns the index is stale, run `npx soleil-ai-review-engine analyze` in the terminal first.

## Skills

| Task                                         | Skill to read       |
| -------------------------------------------- | ------------------- |
| Understand architecture / "How does X work?" | `soleil-ai-review-engine-exploring`         |
| Blast radius / "What breaks if I change X?"  | `soleil-ai-review-engine-impact-analysis`   |
| Trace bugs / "Why is X failing?"             | `soleil-ai-review-engine-debugging`         |
| Rename / extract / split / refactor          | `soleil-ai-review-engine-refactoring`       |
| Tools, resources, schema reference           | `soleil-ai-review-engine-guide` (this file) |
| Index, status, clean, wiki CLI commands      | `soleil-ai-review-engine-cli`               |

## Tools Reference

| Tool             | What it gives you                                                        |
| ---------------- | ------------------------------------------------------------------------ |
| `query`          | Process-grouped code intelligence — execution flows related to a concept |
| `context`        | 360-degree symbol view — categorized refs, processes it participates in  |
| `impact`         | Symbol blast radius — what breaks at depth 1/2/3 with confidence         |
| `detect_changes` | Git-diff impact — what do your current changes affect                    |
| `rename`         | Multi-file coordinated rename with confidence-tagged edits               |
| `cypher`         | Raw graph queries (read `soleil-ai-review-engine://repo/{name}/schema` first)           |
| `list_repos`     | Discover indexed repos                                                   |

## Resources Reference

Lightweight reads (~100-500 tokens) for navigation:

| Resource                                       | Content                                   |
| ---------------------------------------------- | ----------------------------------------- |
| `soleil-ai-review-engine://repo/{name}/context`               | Stats, staleness check                    |
| `soleil-ai-review-engine://repo/{name}/clusters`              | All functional areas with cohesion scores |
| `soleil-ai-review-engine://repo/{name}/cluster/{clusterName}` | Area members                              |
| `soleil-ai-review-engine://repo/{name}/processes`             | All execution flows                       |
| `soleil-ai-review-engine://repo/{name}/process/{processName}` | Step-by-step trace                        |
| `soleil-ai-review-engine://repo/{name}/schema`                | Graph schema for Cypher                   |

## Graph Schema

**Nodes:** File, Function, Class, Interface, Method, Community, Process
**Edges (via CodeRelation.type):** CALLS, IMPORTS, EXTENDS, IMPLEMENTS, DEFINES, MEMBER_OF, STEP_IN_PROCESS

```cypher
MATCH (caller)-[:CodeRelation {type: 'CALLS'}]->(f:Function {name: "myFunc"})
RETURN caller.name, caller.filePath
```
