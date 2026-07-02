# Giai đoạn 1 — Deploy app (forward-reference, ngoài scope GĐ0)

> Outline ngắn cho bước sau khi hạ tầng đã sẵn sàng (bootstrap chạy xong, `verify.sh` xanh). Chi tiết đầy đủ sẽ làm ở phiên riêng nếu bạn muốn (chọn "Full deploy path").

## Chọn topology (khớp `RUNTIME_MODE` lúc bootstrap)

**A. Docker + host Nginx (mặc định, khuyến nghị)** — tái dùng `docker-compose.prod.yml` đã test (db + redis + backend + frontend), host Nginx/Certbot làm TLS reverse proxy. **Không** bật `--profile proxy` (Caddy) vì host Nginx thay vai trò đó.

**B. Native** — chạy thẳng php-fpm + Postgres + Redis trên host (bootstrap `RUNTIME_MODE=native` đã cài).

## Tránh xung đột cổng 80 (Topology A)

`docker-compose.prod.yml` publish frontend ra `${FRONTEND_PORT:-80}:8080`. Host Nginx cũng cần 80/443 → **đụng cổng**. Tạo override bind loopback:

```yaml
# docker-compose.override.prod.yml  (dùng kèm: -f docker-compose.prod.yml -f docker-compose.override.prod.yml)
services:
  frontend:
    ports: ["127.0.0.1:8081:8080"]   # Nginx -> 127.0.0.1:8081
  backend:
    ports: ["127.0.0.1:8080:8080"]   # Nginx -> 127.0.0.1:8080
```

Khớp với `deploy/reference/nginx-soleil.conf.template` (Topology A).

## Các bước

1. **Clone** repo (nếu private: dùng **deploy key** read-only, đừng dùng PAT cá nhân):
   ```bash
   sudo install -d -o deploy -g deploy /var/www/soleil
   sudo -u deploy git clone https://github.com/taucao-ruby/soleil-hostel /var/www/soleil
   ```
2. **Secrets** (KHÔNG commit; `chmod 600`, owner `deploy`). Sinh ngẫu nhiên:
   ```bash
   php artisan key:generate            # APP_KEY (hoặc openssl rand -base64 32)
   openssl rand -base64 32             # DB_PASSWORD
   openssl rand -base64 32             # REDIS_PASSWORD (bắt buộc, prod refuse nếu trống)
   ```
   `.env` production tối thiểu — lưu ý cookie/Sanctum cho domain thật:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://soleil.duckdns.org
   SESSION_SECURE_COOKIE=true
   SESSION_DOMAIN=soleil.duckdns.org
   SANCTUM_STATEFUL_DOMAINS=soleil.duckdns.org
   DB_CONNECTION=pgsql ; DB_DATABASE=soleil_hostel ; DB_USERNAME=soleil
   ```
   > Theo `CLAUDE.md`: runtime dùng `config()` không dùng `env()`; chạy `php artisan config:cache`.
3. **Build & up**
   - Docker: `docker compose -f docker-compose.prod.yml -f docker-compose.override.prod.yml up -d --build`
     (build frontend ăn RAM → swap từ bootstrap giúp tránh OOM trên A1).
   - Native: `composer install --no-dev -o` ; `pnpm install --frozen-lockfile && pnpm build` ; `php artisan config:cache route:cache`.
4. **Nginx vhost**: copy template → `sites-available/soleil` → symlink → `sudo nginx -t`.
5. **TLS**: `sudo certbot --nginx -d soleil.duckdns.org` (auto-renew qua systemd timer của certbot).
6. **Migrate** (lưu ý booking dùng exclusion constraint cần `btree_gist` — native mode bootstrap đã tạo extension):
   ```bash
   php artisan migrate --force        # hoặc: docker compose ... exec backend php artisan migrate --force
   ```
7. **Queue + scheduler** (Redis-backed): tạo systemd service cho `php artisan queue:work` + cron `* * * * * php artisan schedule:run`.
8. **Smoke test**:
   ```bash
   curl -fsS https://soleil.duckdns.org/api/health/live
   curl -I  https://soleil.duckdns.org/
   ```

## Hardening thêm (tùy chọn)
- Nginx rate-limit (`limit_req_zone`) cho `/api/auth/*` (brute-force login).
- fail2ban jail cho Nginx 401/403 (`nginx-http-auth`, `nginx-botsearch`).
- Backup: `pg_dump` định kỳ + offload (OCI Object Storage Always Free 20GB).
