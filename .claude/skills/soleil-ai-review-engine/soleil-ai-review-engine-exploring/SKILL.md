---
name: soleil-ai-review-engine-exploring
description: "Use when the user asks how code works, wants to understand architecture, trace execution flows, or explore unfamiliar parts of the codebase. Examples: \"How does X work?\", \"What calls this function?\", \"Show me the auth flow\""
---

# Exploring Codebases with soleil-ai-review-engine

## When to Use

- "How does authentication work?"
- "What's the project structure?"
- "Show me the main components"
- "Where is the database logic?"
- Understanding code you haven't seen before

## Workflow

```
1. READ soleil-ai-review-engine://repos                          → Discover indexed repos
2. READ soleil-ai-review-engine://repo/{name}/context             → Codebase overview, check staleness
3. soleil-ai-review-engine_query({query: "<what you want to understand>"})  → Find related execution flows
4. soleil-ai-review-engine_context({name: "<symbol>"})            → Deep dive on specific symbol
5. READ soleil-ai-review-engine://repo/{name}/process/{name}      → Trace full execution flow
```

> If step 2 says "Index is stale" → run `npx soleil-ai-review-engine analyze` in terminal.

## Checklist

```
- [ ] READ soleil-ai-review-engine://repo/{name}/context
- [ ] soleil-ai-review-engine_query for the concept you want to understand
- [ ] Review returned processes (execution flows)
- [ ] soleil-ai-review-engine_context on key symbols for callers/callees
- [ ] READ process resource for full execution traces
- [ ] Read source files for implementation details
```

## Resources

| Resource                                | What you get                                            |
| --------------------------------------- | ------------------------------------------------------- |
| `soleil-ai-review-engine://repo/{name}/context`        | Stats, staleness warning (~150 tokens)                  |
| `soleil-ai-review-engine://repo/{name}/clusters`       | All functional areas with cohesion scores (~300 tokens) |
| `soleil-ai-review-engine://repo/{name}/cluster/{name}` | Area members with file paths (~500 tokens)              |
| `soleil-ai-review-engine://repo/{name}/process/{name}` | Step-by-step execution trace (~200 tokens)              |

## Tools

**soleil-ai-review-engine_query** — find execution flows related to a concept:

```
soleil-ai-review-engine_query({query: "payment processing"})
→ Processes: CheckoutFlow, RefundFlow, WebhookHandler
→ Symbols grouped by flow with file locations
```

**soleil-ai-review-engine_context** — 360-degree view of a symbol:

```
soleil-ai-review-engine_context({name: "validateUser"})
→ Incoming calls: loginHandler, apiMiddleware
→ Outgoing calls: checkToken, getUserById
→ Processes: LoginFlow (step 2/5), TokenRefresh (step 1/3)
```

## Example: "How does payment processing work?"

```
1. READ soleil-ai-review-engine://repo/my-app/context       → 918 symbols, 45 processes
2. soleil-ai-review-engine_query({query: "payment processing"})
   → CheckoutFlow: processPayment → validateCard → chargeStripe
   → RefundFlow: initiateRefund → calculateRefund → processRefund
3. soleil-ai-review-engine_context({name: "processPayment"})
   → Incoming: checkoutHandler, webhookHandler
   → Outgoing: validateCard, chargeStripe, saveTransaction
4. Read src/payments/processor.ts for implementation details
```
