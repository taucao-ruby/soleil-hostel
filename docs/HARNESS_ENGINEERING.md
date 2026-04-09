# AI Harness Engineering — 7-Layer Architecture

## Overview

The Soleil Hostel AI Harness is a safety-first orchestration pipeline that mediates
all interactions between the application and LLM providers. Every request flows through
seven discrete layers, each with a specific responsibility and explicit contract.

## Layer Architecture

```
┌─────────────────────────────────────────────────────────┐
│  HTTP Request                                           │
├──────┬──────────────────────────────────────────────────┤
│  L1  │  AiRequestNormalizer (Middleware)                │
│      │  → Validates task_type, builds HarnessRequest DTO│
│      │  → Maps TaskType → RiskTier                      │
│      │  → Extracts auth context (user, role, locale)    │
├──────┼──────────────────────────────────────────────────┤
│  L2  │  ContextAssemblyService                         │
│      │  → Source allowlist per TaskType (static)        │
│      │  → Token budget enforcement                      │
│      │  → Freshness checking                            │
│      │  → RBAC-aware filtering                          │
│      │  → Provenance tagging                            │
├──────┼──────────────────────────────────────────────────┤
│  L3  │  ModelExecutionService                          │
│      │  → Provider selection + failover                │
│      │  → Timeout ladder per task type                  │
│      │  → Circuit breaker (cache-based)                 │
│      │  → Retry with exponential backoff + jitter       │
├──────┼──────────────────────────────────────────────────┤
│  L4  │  PolicyEnforcementService                       │
│      │  → PRE-CALL: PII scan, injection heuristics,    │
│      │    risk tier gate, input length check            │
│      │  → POST-CALL: PII output scan, tool proposal    │
│      │    classification, empty response check          │
│      │  → AUTHORITATIVE: decisions are final            │
├──────┼──────────────────────────────────────────────────┤
│  L5  │  ToolOrchestrationService                       │
│      │  → READ_ONLY → auto-execute                     │
│      │  → APPROVAL_REQUIRED → return ToolDraft struct   │
│      │  → BLOCKED → throw BlockedToolException          │
│      │  → Delegates to existing service layer           │
├──────┼──────────────────────────────────────────────────┤
│  L6  │  AiObservabilityService                         │
│      │  → Builds RequestTrace (17 fields)              │
│      │  → Logs to 'ai' channel with masked PII         │
│      │  → Cost estimation per provider                  │
├──────┼──────────────────────────────────────────────────┤
│  L7  │  AiEvalCommand (Evaluation Runner)              │
│      │  → Golden scenario evaluation                    │
│      │  → Slice-level degradation detection             │
│      │  → Regression gate (all-phases)                  │
│      │  → Automated nightly CI job                      │
└──────┴──────────────────────────────────────────────────┘
```

## Orchestration Flow

```
L0: AiOrchestrationService.handle(HarnessRequest)
  │
  ├── L2: ContextAssemblyService.assemble()
  │     → GroundedContext (sources, tokens, provenance)
  │
  ├── L4: PolicyEnforcementService.screenInput() [PRE-CALL]
  │     → PolicyDecision (allow|reject|abstain|escalate)
  │     → Short-circuit on reject/abstain
  │
  ├── L3: ModelExecutionService.execute()
  │     → RawModelResponse (content, tokens, tool proposals)
  │
  ├── L4: PolicyEnforcementService.validateOutput() [POST-CALL]
  │     → PolicyDecision (validates output safety)
  │
  ├── L5: ToolOrchestrationService.execute()
  │     → Tool results or ToolDraft structs
  │
  └── L6: AiObservabilityService.recordTrace()
        → RequestTrace (17-field lifecycle trace)
```

## Tool Classification

| Classification      | Behavior                              | Examples                        |
|---------------------|---------------------------------------|---------------------------------|
| READ_ONLY           | Auto-execute via service layer        | search_rooms, lookup_policy     |
| APPROVAL_REQUIRED   | Return ToolDraft, no DB write         | draft_admin_message             |
| BLOCKED             | Throw exception, log to 'ai' channel | create_booking, cancel_booking  |

Unknown tools default to **BLOCKED** (safety invariant).

## Key Data Types

| DTO             | Purpose                                    |
|-----------------|--------------------------------------------|
| HarnessRequest  | Immutable request context (10 fields)      |
| HarnessResponse | Final response to caller (7 fields)        |
| GroundedContext | Assembled context with provenance          |
| PolicyDecision  | Policy layer verdict                        |
| RequestTrace   | 17-field observability trace                |
| ToolDraft      | APPROVAL_REQUIRED output (Phase 3)         |
| RawModelResponse| Unvalidated provider response              |

## Task Types

| TaskType        | Risk Tier | Token Budget | Timeout | Tools                         |
|-----------------|-----------|-------------|---------|-------------------------------|
| faq_lookup      | LOW       | 2000        | 3s      | lookup_policy, get_faq_content|
| room_discovery  | LOW       | 4000        | 8s      | search_rooms, check_availability, get_location_detail |
| booking_status  | MEDIUM    | 2000        | 5s      | get_booking_status, get_user_bookings |
| admin_draft     | HIGH      | 6000        | 15s     | draft_admin_message, draft_cancellation_summary |

## Safety Boundaries

1. **PII Detection**: Email, phone, passport patterns — pre and post call
2. **Prompt Injection**: 7 heuristic patterns including DAN, jailbreak
3. **Tool Gating**: Static registry with BLOCKED-by-default for unknowns
4. **RBAC Filtering**: Context assembly filters sources by user role
5. **Cross-Customer PII**: Admin draft blocks third-party customer data
6. **Autonomous Action Detection**: Pattern matching for "I have cancelled/refunded" claims

## Files

```
backend/app/AiHarness/
├── DTOs/         (5 data transfer objects)
├── Enums/        (4 enums)
├── Exceptions/   (3 exception types)
├── Middleware/    (3 middleware)
├── Providers/    (interface + Anthropic + RawModelResponse)
├── Services/     (7 services)
├── PromptRegistry.php
└── ToolRegistry.php
```
