---
name: security
description: "Skill for the Security area of soleil-hostel. 57 symbols across 7 files."
---

# Security

57 symbols | 7 files | Cohesion: 95%

## When to Use

- Working with code in `backend/`
- Understanding how csp_nonce, getInstance, purify work
- Modifying security-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | blocks_basic_script_tag, blocks_script_with_src, blocks_script_with_event_handlers, blocks_onclick_handler, blocks_onmouseover_handler (+43) |
| `backend/app/Services/HtmlPurifierService.php` | getInstance, purify, plaintext, doPurify |
| `backend/app/Macros/FormRequestPurifyMacro.php` | register |
| `backend/app/Http/Requests/UpdateReviewRequest.php` | validated |
| `backend/app/Http/Controllers/ReviewController.php` | update |
| `backend/app/Helpers/SecurityHelpers.php` | csp_nonce |
| `backend/tests/Feature/Security/SecurityHeadersTest.php` | test_nonce_directive_available |

## Entry Points

Start here when exploring this area:

- **`csp_nonce`** (Function) — `backend/app/Helpers/SecurityHelpers.php:15`
- **`getInstance`** (Method) — `backend/app/Services/HtmlPurifierService.php:32`
- **`purify`** (Method) — `backend/app/Services/HtmlPurifierService.php:48`
- **`plaintext`** (Method) — `backend/app/Services/HtmlPurifierService.php:59`
- **`doPurify`** (Method) — `backend/app/Services/HtmlPurifierService.php:75`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `csp_nonce` | Function | `backend/app/Helpers/SecurityHelpers.php` | 15 |
| `getInstance` | Method | `backend/app/Services/HtmlPurifierService.php` | 32 |
| `purify` | Method | `backend/app/Services/HtmlPurifierService.php` | 48 |
| `plaintext` | Method | `backend/app/Services/HtmlPurifierService.php` | 59 |
| `doPurify` | Method | `backend/app/Services/HtmlPurifierService.php` | 75 |
| `register` | Method | `backend/app/Macros/FormRequestPurifyMacro.php` | 33 |
| `blocks_basic_script_tag` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 28 |
| `blocks_script_with_src` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 38 |
| `blocks_script_with_event_handlers` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 48 |
| `blocks_onclick_handler` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 63 |
| `blocks_onmouseover_handler` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 72 |
| `blocks_onload_handler` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 81 |
| `blocks_onerror_handler` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 90 |
| `blocks_onchange_handler` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 99 |
| `blocks_onsubmit_handler` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 108 |
| `blocks_oninput_handler` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 117 |
| `blocks_javascript_protocol_in_href` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 132 |
| `blocks_javascript_protocol_uppercase` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 141 |
| `blocks_javascript_with_newlines` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 154 |
| `blocks_javascript_with_tabs` | Method | `backend/tests/Feature/Security/HtmlPurifierXssTest.php` | 168 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Store → GetInstance` | cross_community | 4 |
| `Store → DoPurify` | cross_community | 4 |
| `BootPurifiable → GetInstance` | cross_community | 4 |
| `BootPurifiable → DoPurify` | cross_community | 4 |
| `Store → GetInstance` | cross_community | 4 |
| `Store → DoPurify` | cross_community | 4 |
| `Update → GetInstance` | cross_community | 4 |
| `Update → DoPurify` | cross_community | 4 |
| `Store → GetInstance` | cross_community | 4 |
| `Store → DoPurify` | cross_community | 4 |

## How to Explore

1. `gitnexus_context({name: "csp_nonce"})` — see callers and callees
2. `gitnexus_query({query: "security"})` — find related execution flows
3. Read key files listed above for implementation details
