# Soleil → Vertical SaaS for VN Homestays — Validation Pack

> Created 2026-06-19. Internal strategy + meeting-ready interview script.
> Status: **PRE-CODE / VALIDATION**. No product code until the go/no-go gate (§6) passes.

---

## 1. Codebase reality check (decides the whole plan)

- **[CONFIRMED] Soleil is single-tenant.** No `tenant` / `organization` / `owner_id` / `account_id`
  in models or migrations. The model is `Location → Room → Booking` + `User` roles — *one hostel
  brand with multiple physical locations*, not *many independent hostel customers*. True
  multi-tenant SaaS would require scoping every table by tenant, making the overlap **exclusion
  constraint per-tenant**, and re-deriving RBAC + billing per tenant — touching the two highest-risk
  invariants in `CLAUDE.md` (booking overlap + auth). Retrofitting multi-tenancy into a mature
  single-tenant app is one of the most expensive, regression-prone refactors there is. **Do not
  start there.**
- **[CONFIRMED] Payments are Stripe (Laravel Cashier).** Strong infra: `StripeService`, webhook
  idempotency, refund reconciliation, deposit lifecycle, ledger immutability triggers.
- **[INFERRED] Stripe doesn't cleanly serve VN hostels** — they need VNPay / MoMo / ZaloPay /
  bank-QR. The *architecture* (idempotent webhooks, reconciliation, deposit lifecycle) is reusable;
  the *gateway* must be swapped.

### Decision that follows
Do **not** build multi-tenancy before a paying customer. Validate with **one deploy per design
partner** ("manual multi-tenancy"): each hostel gets its own Soleil instance, nearly as-is. Reuse
what's built, prove willingness-to-pay in weeks, and **defer the tenant-isolation refactor until
3–5 paying customers justify it.**

---

## 2. The wedge: "Giữ hoa hồng" — direct-booking page + VN payment

Direct-booking beats channel-sync for the MVP: channel sync needs Booking.com/Agoda/Airbnb API
partnerships (gated, slow, volume-gated). Direct-booking reuses the existing availability + booking
engine, and the value prop is a number the owner feels monthly: *stop paying 15–18% OTA commission
on repeat guests.*

**Reuse map**

| Reuse as-is | Reuse the pattern, swap impl | Build thin | Defer / do NOT build |
|---|---|---|---|
| Availability/overlap engine, Room/Booking model, locking | Payment idempotency, reconciliation, deposit lifecycle (Stripe → VNPay/MoMo) | Public mobile-first booking widget; VN payment adapter; QR/link generator | Multi-tenancy, channel sync, new admin UI, AI features, multi-currency |

---

## 3. Interview script — dùng với 5 chủ hostel (≈20 phút)

Đây là **cuộc trò chuyện, không phải bài chào hàng**. Đừng mô tả sản phẩm trước câu 8.

**Khởi động**
1. Anh/chị đang vận hành bao nhiêu phòng/giường, ở mấy cơ sở?
2. Khách đặt phòng qua những kênh nào? (Booking.com, Agoda, Airbnb, Facebook, Zalo, gọi trực tiếp…) Tỉ lệ mỗi kênh?

**Đào sâu nỗi đau (quan trọng nhất — đừng gợi ý đáp án)**
3. Mỗi tháng trả khoảng bao nhiêu tiền hoa hồng cho OTA? (xin con số/% cụ thể)
4. Đã bao giờ bị **trùng phòng / overbooking** chưa? Lần gần nhất xử lý thế nào, mất bao nhiêu?
5. Hiện quản lý lịch phòng bằng gì? Khâu nào tốn thời gian / hay sai nhất?
6. Khách quen quay lại thì đặt qua đâu? Có cách nào để họ đặt thẳng, khỏi qua OTA không?

**Hành vi & sẵn lòng chi trả (lắng nghe, đừng bán)**
7. Nếu có một trang đặt phòng riêng (link/QR) để khách đặt thẳng + trả qua MoMo/VNPay, **không mất hoa hồng**, mỗi tháng tiết kiệm khoảng bao nhiêu?
8. *(Giờ mới mô tả ngắn gọn)* Em đang làm đúng thứ đó. Anh/chị sẵn lòng trả bao nhiêu/tháng? (im lặng — để họ tự nói con số)
9. Điều gì khiến anh/chị **KHÔNG** dùng? (giá, ngại đổi, sợ rối, không tin thanh toán…)

**Chốt design partner**
10. Em chọn 5 chủ hostel làm cùng trong 60 ngày: dùng miễn phí, đổi lại phản hồi thẳng thắn. Thấy đáng tiền thì mới trả. Anh/chị tham gia chứ?

---

## 4. Thin MVP spec (only after §6 gate passes)

**Scope = one job: a guest books a room directly and pays, with zero overbooking.**

| Component | Decision |
|---|---|
| Tenancy | **One deploy per hostel.** No `tenant_id`. Reuse Soleil nearly as-is. |
| Public booking flow | New mobile-first page: pick dates → available rooms (existing availability query) → guest details → pay → confirmation. Vietnamese UI. |
| Payment | **VN adapter**, start with one (MoMo *or* VNPay). Reuse idempotency + reconciliation patterns; swap gateway. |
| Admin | Reuse existing admin dashboard as-is. No redesign. |
| Distribution to guests | Shareable link + **QR code** for desk/Zalo/Facebook bio. |
| Deferred | Multi-tenancy, channel sync, AI features, multi-currency, new admin UI. |

**Build order:** VN payment adapter (riskiest first) → public booking widget → QR/link generator →
deploy-per-tenant runbook. ≤2 weeks for partner #1.

---

## 5. Pricing hypothesis (test, don't announce)

Pitch is ROI, not features: *"You pay X, you save 3–5× X in commission."*

- **Hypothesis:** 299k–599k VND/month flat, or a small % of direct-booking volume.
- Anchor every conversation to the Q3 commission number. If they pay Booking.com 3M VND/month, 500k
  to recover part of it is an easy yes.

---

## 6. Go / No-Go gate (before writing any code)

Proceed to §4 only if **≥3 of 5 owners**:
1. Quantify OTA commission as real monthly pain (Q3), **and**
2. Say a believable price unprompted at Q8, **and**
3. Agree to be a design partner at Q10.

If not met, the wedge is wrong — **change the wedge, don't build it.**
