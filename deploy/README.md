# `deploy/` — Hạ tầng & triển khai Soleil Hostel

Bộ artifact để dựng Soleil trên **Oracle Cloud Ampere A1 (ARM64, Always Free) + Ubuntu 24.04 + DuckDNS + Nginx/Certbot**.

## Thứ tự thực hiện

| Bước | File | Ai làm |
|------|------|--------|
| **GĐ0** — tạo VM, mở cổng OCI, DuckDNS, verify | [`GIAI_DOAN_0_RUNBOOK.md`](./GIAI_DOAN_0_RUNBOOK.md) | **Bạn** (console web) |
| **Bootstrap** — hardening + runtime layer | [`bootstrap.sh`](./bootstrap.sh) | SSH vào VM, `sudo bash` |
| **Verify** — kiểm tra sau bootstrap | [`verify.sh`](./verify.sh) | `bash deploy/verify.sh <domain>` |
| **GĐ1** — deploy app (ngoài scope hiện tại) | [`reference/GIAI_DOAN_1_NEXT.md`](./reference/GIAI_DOAN_1_NEXT.md) | phiên sau |

```bash
# Trên VM (sau khi GĐ0 xong và SSH vào được bằng user 'ubuntu')
scp deploy/bootstrap.sh ubuntu@<IP>:~
ssh ubuntu@<IP>
sudo RUNTIME_MODE=docker bash bootstrap.sh        # hoặc RUNTIME_MODE=native
# ⚠️ mở terminal thứ 2, test  ssh deploy@<IP>  TRƯỚC khi đóng session
bash deploy/verify.sh soleil.duckdns.org
```

## Mô hình bảo mật (defense in depth)

```
Internet ─▶ OCI NSG (cloud L3/4) ─▶ iptables/UFW (host) ─▶ fail2ban ─▶ SSH key-only ─▶ app
                22/80/443+ICMP        +80/443 augment       ban brute    no root, ed25519
```

`bootstrap.sh` áp: deploy user (non-root, key-only), SSH lockdown (validate `sshd -t` trước reload — **không tự khóa**), firewall thích ứng (vá đúng **cạm bẫy iptables của OCI Ubuntu**), fail2ban, swap (chống OOM khi build Vite/ARM), sysctl hardening, auto security-updates, time sync.

## Quyết định topology (bạn đã chọn Nginx + Certbot)

Repo đã có sẵn `Caddyfile` + `docker-compose.prod.yml` (db + redis + backend + frontend + Caddy proxy tùy chọn). Bạn chọn **Nginx + Certbot**, nên **host Nginx đảm nhận TLS reverse proxy**, thay cho profile Caddy.

| | **A. Docker + host Nginx** (mặc định) | **B. Native** |
|--|--|--|
| Runtime | `RUNTIME_MODE=docker`: Docker Engine + compose + Nginx + Certbot | `RUNTIME_MODE=native`: php8.3-fpm, Composer, Node 22 + pnpm, PostgreSQL 16 (+`btree_gist`), Redis, Nginx, Certbot |
| App chạy bằng | `docker-compose.prod.yml` (đã test) sau Nginx | service host trực tiếp |
| Ưu | Khớp định nghĩa prod hiện có, ít drift | Không cần Docker, nhẹ |
| Lưu ý | Bind container ra `127.0.0.1` để Nginx proxy (tránh đụng cổng 80) | Tự quản version PHP/PG/Redis |

> Bootstrap mặc định `docker` vì tái dùng stack prod đã có. Đổi sang `native` bằng 1 biến môi trường nếu muốn chạy thẳng trên host.

## Khớp với stack thật của repo

PHP **8.3** (Laravel 12) · frontend **pnpm 9.15.9** + Vite · **PostgreSQL 16** (`soleil_hostel`/`soleil`) · **Redis 7** (REDIS_PASSWORD bắt buộc ở prod) · security headers/CSP trong `reference/nginx-soleil.conf.template` sao y `Caddyfile` (gồm allowlist Stripe). Booking exclusion constraint cần extension `btree_gist` (đã tạo ở native mode).

## Bảo mật vận hành

- DuckDNS token & `.env` production: chỉ trên VM, `chmod 600`, **không commit / không paste chat**.
- Runtime dùng `config()` không `env()` (theo `CLAUDE.md`); chạy `php artisan config:cache`.
- Repo private → dùng **deploy key** read-only thay vì PAT cá nhân.

## Files

```
deploy/
├── README.md                         # file này
├── GIAI_DOAN_0_RUNBOOK.md            # runbook console thủ công (GĐ0)
├── bootstrap.sh                      # hardening + runtime (idempotent)
├── verify.sh                         # kiểm tra sau bootstrap
└── reference/
    ├── nginx-soleil.conf.template    # vhost Nginx (GĐ1) — headers/CSP sao từ Caddyfile
    └── GIAI_DOAN_1_NEXT.md           # outline deploy app (GĐ1)
```

> Thư mục `deploy/` là artifact vận hành, không nằm trong document-map/decision-order của `CLAUDE.md`. Không chạm `backend/`, `frontend/`, `.github/`, `docker-compose*`.
