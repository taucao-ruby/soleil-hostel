---
name: cluster-1
description: "Skill for the Cluster_1 area of soleil-hostel. 18 symbols across 1 files."
---

# Cluster_1

18 symbols | 1 files | Cohesion: 94%

## When to Use

- Understanding how run, preflight, runGateChecks work
- Modifying cluster_1-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `deploy.php` | run, preflight, runGateChecks, runDeployOperations, runVerification (+13) |

## Entry Points

Start here when exploring this area:

- **`run`** (Method) — `deploy.php:47`
- **`preflight`** (Method) — `deploy.php:70`
- **`runGateChecks`** (Method) — `deploy.php:106`
- **`runDeployOperations`** (Method) — `deploy.php:158`
- **`runVerification`** (Method) — `deploy.php:204`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `run` | Method | `deploy.php` | 47 |
| `preflight` | Method | `deploy.php` | 70 |
| `runGateChecks` | Method | `deploy.php` | 106 |
| `runDeployOperations` | Method | `deploy.php` | 158 |
| `runVerification` | Method | `deploy.php` | 204 |
| `runCommand` | Method | `deploy.php` | 250 |
| `resolveHealthBaseUrl` | Method | `deploy.php` | 298 |
| `httpGet` | Method | `deploy.php` | 321 |
| `parseDotEnv` | Method | `deploy.php` | 357 |
| `findExecutable` | Method | `deploy.php` | 390 |
| `finish` | Method | `deploy.php` | 431 |
| `banner` | Method | `deploy.php` | 450 |
| `phase` | Method | `deploy.php` | 459 |
| `printList` | Method | `deploy.php` | 468 |
| `printBlock` | Method | `deploy.php` | 481 |
| `recordSuccess` | Method | `deploy.php` | 489 |
| `recordError` | Method | `deploy.php` | 501 |
| `line` | Method | `deploy.php` | 507 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Run → Success` | cross_community | 4 |
| `Run → Line` | intra_community | 4 |
| `Run → PrintBlock` | intra_community | 4 |
| `Run → Phase` | intra_community | 3 |
| `Run → RecordError` | intra_community | 3 |
| `Run → FindExecutable` | intra_community | 3 |
| `RunVerification → PrintBlock` | intra_community | 3 |
| `RunVerification → Success` | cross_community | 3 |
| `RunGateChecks → Success` | cross_community | 3 |
| `RunDeployOperations → PrintBlock` | intra_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Bookings | 3 calls |
| Cache | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "run"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "cluster_1"})` — find related execution flows
3. Read key files listed above for implementation details
