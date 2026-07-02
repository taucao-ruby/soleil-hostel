# Giai đoạn 0 — Chuẩn bị hạ tầng (Hardened Runbook)

> Soleil Hostel · Oracle Cloud **Ampere A1 (ARM64, Always Free)** + **Ubuntu 24.04 LTS** + **DuckDNS** + **Nginx/Certbot**.
> Đây là các bước **console web — chỉ bạn thao tác được** (cần credential OCI + token DuckDNS của bạn). Sau khi SSH vào được, chạy `deploy/bootstrap.sh` để hardening + cài runtime tự động.

**Mô hình bảo mật (defense in depth):** OCI NSG (lớp cloud) → iptables/UFW (lớp host) → fail2ban → SSH key-only → app. Mỗi lớp độc lập; thủng một lớp vẫn còn lớp sau.

**Thứ tự tổng thể:** GĐ0 (tài liệu này) → `bootstrap.sh` → `verify.sh` → GĐ1 (`deploy/reference/GIAI_DOAN_1_NEXT.md`).

---

## 1. Tạo VM (Compute Instance)

**Console:** OCI → Menu → **Compute → Instances → Create instance**.

| Mục | Giá trị | Lý do (expert) |
|-----|---------|----------------|
| **Image** | Canonical **Ubuntu 24.04** (aarch64) | 24.04 ship sẵn PHP 8.3 + PostgreSQL 16 (khớp `composer.json` & `docker-compose.prod.yml`). |
| **Shape** | `VM.Standard.A1.Flex` — **2 OCPU / 12 GB** (có thể tới 4/24, đều **free**) | Vite/pnpm build ăn RAM; shape quá nhỏ OOM lúc build frontend. Always Free A1 cho tổng **4 OCPU + 24 GB** — cứ lấy rộng tay. |
| **Boot volume** | **50–100 GB** (free tới 200 GB block) | Docker images + Postgres data + build cache. Bật **in-transit encryption**. |
| **SSH keys** | **Tự sinh ed25519** rồi paste **public** key | Đừng để Oracle sinh hộ (tải private key xuống kém an toàn). `ssh-keygen -t ed25519 -C soleil`. |
| **Public IPv4** | **Reserve** (Reserved public IP), đừng để Ephemeral | Ephemeral **đổi IP khi stop/start** → DuckDNS sai. Reserved giữ nguyên. |

> ⚠️ **"Out of host capacity"** là lỗi A1 kinh điển. Cách xử lý: đổi **Availability Domain**, thử lại nhiều lần (capacity quay vòng), hoặc đổi **home region** lúc tạo tenancy. Có thể script retry tạo instance qua OCI CLI nếu cần.

Lưu lại: **VM_PUBLIC_IP** và đường dẫn **private key** (`~/.ssh/id_ed25519`).

---

## 2. Mở cổng ở Cloud (OCI ingress)

**Khuyến nghị dùng Network Security Group (NSG)** thay vì sửa Security List — NSG gắn theo VNIC, granular hơn, stateful.

**Console:** Networking → **Virtual Cloud Networks** → VCN của bạn → **Network Security Groups → Create NSG** → thêm ingress rules, rồi gắn NSG vào VNIC của instance (Instance → Attached VNICs → Edit → chọn NSG).

| # | Direction | Source | Proto | Port | Ghi chú |
|---|-----------|--------|-------|------|---------|
| 1 | Ingress | **IP nhà bạn `/32`** (lý tưởng) hoặc `0.0.0.0/0` | TCP | **22** | Nếu IP tĩnh → khóa về `/32`. Nếu IP động → để `0.0.0.0/0` nhưng **bắt buộc** key-only + fail2ban (bootstrap lo). |
| 2 | Ingress | `0.0.0.0/0` | TCP | **80** | HTTP + ACME HTTP-01 (Let's Encrypt). |
| 3 | Ingress | `0.0.0.0/0` | TCP | **443** | HTTPS. |
| 4 | Ingress | `0.0.0.0/0` | ICMP | type **3**, code **4** | Path MTU discovery — tránh treo kết nối với gói lớn. |
| 5 | Egress | `0.0.0.0/0` | All | — | Mặc định cho phép — giữ nguyên. |

Rule **stateful** → không cần mở cổng reply thủ công.

> 🪤 **Cạm bẫy lớn nhất của OCI Ubuntu:** mở cổng ở NSG **chưa đủ**. Image Ubuntu trên OCI ship sẵn **iptables host firewall** chỉ cho 22 và REJECT phần còn lại (lưu ở `/etc/iptables/rules.v4`). 80/443 vẫn bị chặn **ở trong máy** dù NSG đã mở. → `bootstrap.sh` tự phát hiện và **augment** ruleset này (thêm 80/443, giữ nguyên rule metadata/DHCP/ICMP của OCI). Không cần làm tay.

---

## 3. DuckDNS

1. Vào **duckdns.org** → đăng nhập → tạo subdomain (vd `soleil`) → set **current ip = VM_PUBLIC_IP**.
2. **Token để trên VM, KHÔNG dán vào chat / không commit.** Bootstrap nhận qua env nếu muốn auto-update:
   ```bash
   sudo DUCKDNS_DOMAIN=soleil DUCKDNS_TOKEN=<token> bash bootstrap.sh
   ```
   → tạo systemd timer cập nhật IP mỗi 5 phút (resilience nếu IP đổi). Với Reserved IP thì optional.
3. Domain đầy đủ: `soleil.duckdns.org` — dùng cho Nginx vhost + Certbot ở GĐ1.

> Let's Encrypt **HTTP-01** chỉ cần: port 80 reachable + DNS phân giải đúng. DuckDNS thỏa cả hai.

---

## 4. Kiểm tra (verification) — từ máy bạn

```bash
# 4.1 SSH vào được (dùng user 'ubuntu' lúc đầu — Oracle image default)
ssh ubuntu@<VM_PUBLIC_IP> 'echo ok && lsb_release -ds && uname -m'
#   kỳ vọng: ok / Ubuntu 24.04 / aarch64

# 4.2 DNS phân giải đúng IP
dig +short soleil.duckdns.org            # phải == <VM_PUBLIC_IP>

# 4.3 Cổng 22 mở ở lớp cloud
nc -vz <VM_PUBLIC_IP> 22                  # 'succeeded'

# 4.4 (sau khi bootstrap + GĐ1) web reachable
curl -I http://soleil.duckdns.org         # 200/301 từ Nginx
```

Trên VM, xác nhận public IP khớp:
```bash
ssh ubuntu@<VM_PUBLIC_IP> 'curl -4 -fsS ifconfig.me; echo'
```

Nếu `dig` trống → DuckDNS chưa lưu / sai subdomain. Nếu SSH treo (không refuse) → thường do **NSG/Security List chưa mở 22** hoặc sai key.

---

## 5. Chạy bootstrap (chuyển sang phần tự động)

```bash
# copy script lên VM
scp deploy/bootstrap.sh ubuntu@<VM_PUBLIC_IP>:~
# chạy (chọn mode: docker = tái dùng docker-compose.prod.yml; native = cài thẳng)
ssh ubuntu@<VM_PUBLIC_IP>
sudo RUNTIME_MODE=docker bash bootstrap.sh
```

> ⚠️ **An toàn không tự khóa mình:** bootstrap hardening SSH (key-only, tắt root, `AllowUsers=deploy`). Sau khi chạy xong, **mở terminal thứ 2** và xác nhận `ssh deploy@<IP>` vào được **trước khi** đóng session hiện tại. Bootstrap đã validate `sshd -t` trước khi reload nên rủi ro thấp, nhưng vẫn nên test.

---

## Phụ lục A — Checklist bảo mật GĐ0

- [ ] SSH key **ed25519**, private key không rời máy bạn.
- [ ] NSG: 22 (khóa `/32` nếu được), 80, 443, ICMP type 3.
- [ ] Reserved public IP (DuckDNS không lệch khi reboot).
- [ ] DuckDNS token chỉ nằm trên VM; không commit, không paste chat.
- [ ] Repo `taucao-ruby/soleil-hostel` **public** để clone HTTPS không cần auth ở GĐ1 — nếu private, chuẩn bị **deploy key** (read-only) hoặc PAT trước.
- [ ] Sau bootstrap: chạy `deploy/verify.sh`, test `ssh deploy@IP` ở session mới.

## Phụ lục B — Sự cố thường gặp

| Triệu chứng | Nguyên nhân | Xử lý |
|-------------|-------------|-------|
| `ssh` treo, không phản hồi | NSG/Security List chưa mở 22 | Thêm ingress 22; kiểm tra NSG đã gắn VNIC. |
| `curl http://domain` timeout dù NSG mở 80 | **iptables host của OCI** chặn | Chạy `bootstrap.sh` (tự augment) hoặc xem Phụ lục C. |
| `dig` trống | DuckDNS chưa set IP / sai sub | Set lại current ip ở duckdns.org. |
| Lỡ tự khóa SSH | Sửa sshd sai | Dùng **Cloud Shell → instance console connection (serial)** của OCI để vào sửa. |
| `Out of host capacity` | Hết slot A1 trong AD | Đổi AD/region, retry. |

## Phụ lục C — Mở 80/443 ở host bằng tay (nếu không chạy bootstrap)

```bash
# Chèn TRƯỚC rule REJECT cuối chain (giữ nguyên rule OCI metadata/DHCP/ICMP)
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80  -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save     # ghi vào /etc/iptables/rules.v4
```
(Kiểm tra vị trí REJECT: `sudo iptables -L INPUT --line-numbers`.)
