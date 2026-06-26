# Soleil Hostel — Hướng dẫn Deploy Staging (DE-01, Option B) — bản thực thi

> **Companion thực thi** cho [`FIRST_DEPLOY_STAGING.md`](./FIRST_DEPLOY_STAGING.md) (Option B: Oracle
> Always Free + DuckDNS + Caddy). Tài liệu kia là checklist/altitude cao; tài liệu này là kịch bản
> chạy-từng-lệnh, dán-output-về-chat.
>
> **Mô hình làm việc:** bạn chạy lệnh **trên VM Oracle** (Claude không SSH vào được — sandbox chặn ra
> Internet), rồi **dán output về chat**; Claude kiểm tra từng bước và đưa lệnh tiếp theo.
>
> **Nguyên tắc bảo mật (bắt buộc):** mọi **secret thật** (mật khẩu DB/Redis, APP_KEY, key MoMo
> sandbox, key Stripe test) **chỉ gõ trực tiếp trên VM**, không gửi vào chat, không commit. `.env` và
> `backend/.env.production` đều đã `.gitignore` (đã xác minh: `.gitignore:5 .env`, `backend/.gitignore:12 .env.production`).
>
> Mục tiêu: `https://<sub>.duckdns.org/api/health/live` → `{"status":"ok"}` qua HTTPS thật, MoMo IPN
> được chứng minh bằng 1 lần quét QR sandbox thật, 1 giao dịch Stripe test, kết thúc bằng `DEPLOYMENT_LOG.md`.

---

## Tổng quan 6 giai đoạn

| GĐ | Nội dung | Ai làm | Tín hiệu xác nhận |
|----|----------|--------|-------------------|
| 0 | Tạo VM + DuckDNS + mở cổng (Console) | Bạn | SSH vào được; `dig` ra IP VM |
| 1 | Firewall + Docker + clone repo | Bạn chạy / Claude verify | Docker OK, HEAD `cf02173` |
| 2 | Tạo file env (điền secret) | Bạn điền / Claude verify tên biến | Đủ biến, file gitignored |
| 3 | Build + migrate + **seed** + health | Bạn chạy / Claude verify | `/api/health/live` = ok, HTTPS hợp lệ, có phòng |
| 4 | Chứng minh MoMo (quét QR) + Stripe test | Bạn quét / Claude verify DB | booking `confirmed` + row `momo_webhook_events` |
| 5 | Ghi `DEPLOYMENT_LOG.md` | Claude | Commit `docs:` đẩy lên `dev` |

---

## Giai đoạn 0 — Chuẩn bị hạ tầng (Console web, chỉ bạn làm — ~10 phút)

1. **Tạo VM:** Oracle Cloud → Compute → Instances → Create. Chọn **Ampere A1 (ARM, Always Free)**,
   image **Ubuntu 24.04**, **≥ 4GB RAM** (frontend Vite build tốn RAM — shape quá nhỏ dễ OOM lúc build).
   Lưu **public IP** và **SSH key**.
2. **Mở cổng ở Cloud (OCI):** Networking → VCN → Security List → **Add Ingress Rules**: từ `0.0.0.0/0`,
   TCP, tới **22, 80, 443** (3 rule).
3. **DuckDNS:** vào duckdns.org → tạo subdomain (vd `soleil`) → đặt IP = public IP của VM.
   **Giữ token trên VM, đừng gửi vào chat.**
4. **Kiểm tra từ máy bạn:**
   ```bash
   ssh ubuntu@<VM_PUBLIC_IP> 'echo ok'     # SSH vào được
   dig +short <sub>.duckdns.org            # phải trả về <VM_PUBLIC_IP>
   ```

> Repo `taucao-ruby/soleil-hostel` phải **public** để `git clone` qua HTTPS không cần auth. Nếu private,
> chuẩn bị Personal Access Token hoặc deploy key trước.

---

## Phase 1 — Chuẩn bị VM (chạy trên VM, 1 lần dán)

```bash
sudo iptables -I INPUT -p tcp --dport 80  -j ACCEPT
sudo iptables -I INPUT -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
curl -fsSL https://get.docker.com -o /tmp/get-docker.sh && sudo sh /tmp/get-docker.sh
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
cd ~ && git clone https://github.com/taucao-ruby/soleil-hostel.git; cd ~/soleil-hostel && git checkout dev
echo "========= PASTE FROM HERE ========="
echo "== docker ==";   sudo docker --version
echo "== compose =="; sudo docker compose version
echo "== firewall =="; sudo iptables -S INPUT | grep -E -- '--dport (80|443)'
echo "== HEAD ==";     git -C ~/soleil-hostel rev-parse --short HEAD
echo "== branch ==";   git -C ~/soleil-hostel branch --show-current
echo "========= TO HERE ========="
```

→ **Dán phần giữa 2 marker về chat.** Sau bước này **logout/login lại SSH** để dùng `docker` không cần `sudo`.
**Pass khi:** Docker có, compose v2.x, 2 rule ACCEPT 80/443, HEAD `cf02173` nhánh `dev`.

---

## Phase 2 — Cấu hình env (bạn điền secret trên VM)

### 2a. Tạo APP_KEY (chạy trên VM, copy chuỗi kết quả)
```bash
echo "base64:$(openssl rand -base64 32)"
```

### 2b. File `~/soleil-hostel/.env` (root — cho docker compose)
Tạo file với đúng 6 biến (`nano .env`):
```dotenv
DB_USERNAME=soleil
DB_DATABASE=soleil_hostel
DB_PASSWORD=<mat-khau-DB-manh>
REDIS_PASSWORD=<mat-khau-Redis-manh>
DOMAIN=<sub>.duckdns.org
FRONTEND_PORT=8081
```
> `FRONTEND_PORT=8081` bắt buộc: khi bật `--profile proxy`, Caddy chiếm 80/443, frontend phải nhường cổng.
> Mật khẩu mạnh: `openssl rand -base64 32`.

### 2c. File `~/soleil-hostel/backend/.env.production`
```bash
cp backend/.env.production.example backend/.env.production
nano backend/.env.production
```
Sửa/điền:
```dotenv
APP_KEY=base64:<chuoi-tu-buoc-2a>
APP_URL=https://<sub>.duckdns.org
DB_PASSWORD=<GIỐNG HỆT DB_PASSWORD ở root .env>
REDIS_PASSWORD=<GIỐNG HỆT REDIS_PASSWORD ở root .env>
SANCTUM_STATEFUL_DOMAINS=<sub>.duckdns.org
```
Xoá dòng `CACHE_DRIVER=redis` (biến chết — F-78/F-98; đã có `CACHE_STORE=redis`).

**Thêm khối thanh toán (F-98 — bản mẫu thiếu, phải thêm tay):**
```dotenv
# ===== Stripe (TEST mode) =====
CASHIER_CURRENCY=vnd
STRIPE_KEY=<pk_test_...>
STRIPE_SECRET=<sk_test_...>
STRIPE_WEBHOOK_SECRET=<whsec_...>     # trống = từ chối mọi webhook (fail-closed)
# ===== MoMo (SANDBOX) =====
MOMO_ENDPOINT=https://test-payment.momo.vn/v2/gateway/api/create   # endpoint SANDBOX — xác nhận lại trên dashboard MoMo
MOMO_PARTNER_CODE=<sandbox>
MOMO_ACCESS_KEY=<sandbox>
MOMO_SECRET_KEY=<sandbox>             # trống = từ chối mọi IPN (fail-closed)
MOMO_STORE_ID=<sandbox>
MOMO_REQUEST_TYPE=captureWallet
MOMO_IPN_URL=https://<sub>.duckdns.org/api/v1/payments/momo/ipn
MOMO_REDIRECT_URL=https://<sub>.duckdns.org/booking/momo/return
MOMO_CONNECT_TIMEOUT=2
MOMO_READ_TIMEOUT=5
```
> Dùng **endpoint sandbox** (`test-payment.momo.vn`), KHÔNG dùng prod. Giá trị thật lấy từ MoMo Business
> sandbox + Stripe test dashboard.

### 2d. Verify (KHÔNG in giá trị — dán output về chat)
```bash
cd ~/soleil-hostel
for k in APP_KEY APP_URL DB_PASSWORD REDIS_PASSWORD SANCTUM_STATEFUL_DOMAINS MOMO_SECRET_KEY MOMO_IPN_URL MOMO_ENDPOINT STRIPE_SECRET STRIPE_WEBHOOK_SECRET CASHIER_CURRENCY; do printf '%-26s ' "$k"; grep -q "^$k=" backend/.env.production && echo SET || echo MISSING; done
echo "--- root .env ---"
for k in DB_PASSWORD REDIS_PASSWORD DOMAIN FRONTEND_PORT; do printf '%-16s ' "$k"; grep -q "^$k=" .env && echo SET || echo MISSING; done
echo "--- gitignored? (phải thấy ĐỦ 2 dòng) ---"
git check-ignore -v .env backend/.env.production
```
**Pass khi:** mọi biến `SET`, và `git check-ignore -v` in ra **đủ 2 dòng** (cả `.env` và `backend/.env.production`).
> ⚠️ KHÔNG dùng `git check-ignore a b && echo OK` — lệnh đó trả exit 0 ngay cả khi **chỉ 1** file được ignore
> (false-positive). Phải nhìn thấy **cả 2 dòng** in ra.

---

## Phase 3 — Build + migrate + **seed** + health (chạy trên VM)

```bash
cd ~/soleil-hostel
docker compose -f docker-compose.prod.yml --profile proxy up -d --build
```
Build trên ARM free có thể **>2–4 phút**; theo dõi: `docker compose -f docker-compose.prod.yml logs -f`.
Khi các service `healthy`:
```bash
echo "== ps =="
docker compose -f docker-compose.prod.yml ps
echo "== migrate =="
docker compose -f docker-compose.prod.yml exec -T backend php artisan migrate --force
echo "== seed (BẮT BUỘC: tạo locations + 44 rooms + user test đã verified) =="
docker compose -f docker-compose.prod.yml exec -T backend php artisan db:seed --force
echo "== cache =="
docker compose -f docker-compose.prod.yml exec -T backend php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T backend php artisan route:cache
docker compose -f docker-compose.prod.yml exec -T backend php artisan view:cache
echo "== health (HTTPS thật, Caddy tự xin Let's Encrypt) =="
curl -fsS https://<sub>.duckdns.org/api/health/live; echo
echo "== có phòng chưa? =="
docker compose -f docker-compose.prod.yml exec -T db psql -U soleil -d soleil_hostel -c "SELECT count(*) AS rooms FROM rooms;"
```
→ Dán toàn bộ output về chat.

**Pass khi:** mọi service `healthy`; migrate xong (gồm `momo_payments`, `momo_webhook_events`); `db:seed`
chạy xong; `rooms` > 0; health trả `{"status":"ok"}` qua HTTPS không cảnh báo cert.

> **Vì sao bắt buộc seed:** `migrate --force` KHÔNG seed. Không seed → 0 phòng/địa điểm/user → Phase 4
> không tạo nổi booking. `DatabaseSeeder` tạo `LocationSeeder` + `RoomSeeder` + 1 user test **đã verified**
> (`test@example.com` / `password`).
>
> **Nếu Caddy chưa ra cert:** kiểm tra DuckDNS đã trỏ đúng IP + cổng 80 mở (Let's Encrypt dùng HTTP-01).
> Log: `docker compose -f docker-compose.prod.yml logs proxy`.

---

## Phase 4 — Chứng minh thanh toán

### 4a. MoMo — IPN thật (bước duy nhất chỉ con người làm được)

> **Đăng nhập tài khoản test đã seed:** `test@example.com` / `password` (đã verified sẵn → bỏ qua hẳn
> đường OTP/email, vốn không chạy được vì container không có queue worker + SMTP trống).

1. Trên SPA `https://<sub>.duckdns.org`: đăng nhập `test@example.com`/`password` → tạo 1 booking
   **prepaid** trạng thái `pending` → chọn MoMo → hiện QR.
2. **Quét QR bằng app MoMo sandbox** → MoMo POST một IPN thật về `MOMO_IPN_URL`.
3. Kiểm tra phía server (dán output):
   ```bash
   cd ~/soleil-hostel
   docker compose -f docker-compose.prod.yml exec -T db psql -U soleil -d soleil_hostel -c \
    "SELECT id, status, payment_status, updated_at FROM bookings ORDER BY id DESC LIMIT 5;"
   docker compose -f docker-compose.prod.yml exec -T db psql -U soleil -d soleil_hostel -c \
    "SELECT order_id, trans_id, status, result_code FROM momo_webhook_events ORDER BY id DESC LIMIT 5;"
   ```
**Pass khi:** booking → `confirmed`, có 1 row trong `momo_webhook_events` ứng với (`order_id`,`trans_id`).
Gửi lại notification y hệt → trả **204, không confirm 2 lần** (idempotent).
> Nếu chữ ký MoMo bị từ chối: **dừng lại**, copy log từ chối gửi Claude — đây là rủi ro thứ tự field,
> **không tự sửa crypto** (sẽ ghi vào FINDINGS_BACKLOG).

### 4b. Stripe — test mode
1. Stripe Dashboard (test) → Developers → Webhooks → endpoint trỏ về **`https://<sub>.duckdns.org/api/webhooks/stripe`**
   (route thật, unversioned). Copy signing secret vào `STRIPE_WEBHOOK_SECRET` (rồi `config:cache` lại).
2. Thực hiện 1 giao dịch **test-mode** → `payment_intent.succeeded` → booking `confirmed`.
3. Refund thử → xuất hiện row idempotency trong `stripe_refund_events`.

---

## Phase 5 — Ghi nhận (Claude làm)

Claude viết `DEPLOYMENT_LOG.md` ở **repo local** (commit sha, target, service healthy, số migration,
MoMo PASS/FAIL, Stripe PASS/FAIL, health/Sentry), kiểm tra **không có secret** trong staged diff
(`git diff --cached`), commit `docs:` và đẩy lên `dev`.
> Clone trên VM sẽ lệch 1 commit so với origin/dev → `git pull` trên VM nếu muốn đồng bộ.
> **Không** promote `main` và **không** tạo tag `v*` trừ khi bạn yêu cầu rõ sau khi staging xanh.

---

## Giới hạn đã biết của lần deploy này (không phải lỗi — để biết)

- `[CONFIRMED]` **Container chỉ chạy web** (`php-fpm + nginx`), KHÔNG có queue worker / scheduler. MoMo IPN
  vẫn confirm đồng bộ nên proof OK; nhưng **email xác nhận không gửi**, `ExpireStaleBookings` + webhook-reaper
  **không chạy**. Staging thật cần thêm worker — việc riêng, **đừng sửa compose/Dockerfile inline lúc này**.
- `[CONFIRMED]` SMTP để trống → không gửi mail; vì vậy dùng user **đã seed-verified**, không dùng luồng đăng ký OTP.

## Nhắc lại bảo mật
- Secret chỉ trên VM, trong `.env` + `backend/.env.production` (đã gitignore). Không gửi chat, không commit.
- `STRIPE_WEBHOOK_SECRET` / `MOMO_SECRET_KEY` để trống = từ chối toàn bộ (fail-closed) — đúng thiết kế.
- `test@example.com` / `password` là cred **yếu, công khai** → sau khi proof xong **đổi mật khẩu hoặc xoá user**.
- Trước mỗi commit: `git status` + `git diff --cached`, chỉ stage đúng file (đừng `git add -A` — repo có nhiễu CRLF do mount Windows).
