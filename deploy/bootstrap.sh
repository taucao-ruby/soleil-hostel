#!/usr/bin/env bash
# =============================================================================
# Soleil Hostel — VM bootstrap (Giai doan 0.5: hardening + runtime layer)
# Target: Oracle Cloud Ampere A1 (aarch64) + Ubuntu 24.04 LTS
# =============================================================================
# WHAT THIS DOES (idempotent, safe to re-run):
#   1. System update + timezone + persistent journald + time sync
#   2. Dedicated non-root deploy user (sudo, key-only) — copies your SSH key
#   3. SSH hardening (validated with `sshd -t` BEFORE reload — no lockout)
#   4. Host firewall — adaptive:
#        - OCI Ubuntu images ship iptables rules (allow 22 + REJECT rest).
#          We AUGMENT them with 80/443 and persist (keeps OCI metadata/DHCP/ICMP).
#        - If no OCI ruleset is found, we fall back to UFW.
#   5. fail2ban (sshd jail, systemd backend)
#   6. Swap file (Vite/ARM OOM insurance) + vm.swappiness tuning
#   7. Kernel sysctl hardening
#   8. unattended-upgrades (security auto-patching)
#   9. Runtime layer (RUNTIME_MODE):
#        docker (default) -> Docker Engine + compose plugin + Nginx + Certbot
#        native           -> php8.3-fpm+ext, Composer, Node 22 + pnpm,
#                            PostgreSQL 16 (+btree_gist), Redis, Nginx, Certbot
#        none             -> hardening only, no runtimes
#  10. Optional DuckDNS auto-updater (only if DUCKDNS_* env vars are set)
#
# WHAT THIS DOES NOT DO (that is Giai doan 1 — app deploy):
#   git clone, .env.production, build images / pnpm build, issue TLS cert,
#   write the Nginx vhost, run migrations. See deploy/reference/.
#
# USAGE (run on the VM as the `ubuntu` user):
#   curl/scp this file to the VM, then:
#     sudo RUNTIME_MODE=docker bash bootstrap.sh
#   Common overrides:
#     sudo DEPLOY_USER=deploy SWAP_SIZE=4G RUNTIME_MODE=native bash bootstrap.sh
#     sudo SSH_ALLOW_CIDR=203.0.113.7/32 bash bootstrap.sh   # tighten SSH source
#     sudo DUCKDNS_DOMAIN=soleil DUCKDNS_TOKEN=xxxx bash bootstrap.sh
#
# !! AFTER RUNNING: open a SECOND terminal and confirm `ssh <DEPLOY_USER>@<IP>`
#    works BEFORE closing this session. SSH hardening is applied here.
# =============================================================================

set -Eeuo pipefail

# --------------------------- Tunables (env-overridable) ----------------------
DEPLOY_USER="${DEPLOY_USER:-deploy}"
TIMEZONE="${TIMEZONE:-Asia/Ho_Chi_Minh}"
SWAP_SIZE="${SWAP_SIZE:-4G}"
SWAPPINESS="${SWAPPINESS:-10}"
RUNTIME_MODE="${RUNTIME_MODE:-docker}"          # docker | native | none
PHP_VERSION="${PHP_VERSION:-8.3}"
NODE_MAJOR="${NODE_MAJOR:-22}"
PNPM_VERSION="${PNPM_VERSION:-9.15.9}"
SSH_ALLOW_CIDR="${SSH_ALLOW_CIDR:-}"            # e.g. 203.0.113.7/32 ; empty = any
SSH_PORT="${SSH_PORT:-22}"
HARDEN_SSH="${HARDEN_SSH:-true}"
SETUP_FIREWALL="${SETUP_FIREWALL:-true}"
SETUP_FAIL2BAN="${SETUP_FAIL2BAN:-true}"
SETUP_SWAP="${SETUP_SWAP:-true}"
SETUP_SYSCTL="${SETUP_SYSCTL:-true}"
SETUP_UNATTENDED="${SETUP_UNATTENDED:-true}"
# Native-mode DB bootstrap (optional; only created if DB_PASSWORD is set)
DB_NAME="${DB_NAME:-soleil_hostel}"
DB_USER="${DB_USER:-soleil}"
DB_PASSWORD="${DB_PASSWORD:-}"
# DuckDNS updater (optional)
DUCKDNS_DOMAIN="${DUCKDNS_DOMAIN:-}"
DUCKDNS_TOKEN="${DUCKDNS_TOKEN:-}"

LOG_FILE="/var/log/soleil-bootstrap.log"
export DEBIAN_FRONTEND=noninteractive

# --------------------------- Pretty logging ----------------------------------
c_reset=$'\e[0m'; c_blue=$'\e[1;34m'; c_green=$'\e[1;32m'; c_yellow=$'\e[1;33m'; c_red=$'\e[1;31m'
log()  { printf '%s[*]%s %s\n' "$c_blue"  "$c_reset" "$*" | tee -a "$LOG_FILE"; }
ok()   { printf '%s[+]%s %s\n' "$c_green" "$c_reset" "$*" | tee -a "$LOG_FILE"; }
warn() { printf '%s[!]%s %s\n' "$c_yellow" "$c_reset" "$*" | tee -a "$LOG_FILE"; }
die()  { printf '%s[x]%s %s\n' "$c_red"   "$c_reset" "$*" | tee -a "$LOG_FILE"; exit 1; }
section(){ printf '\n%s==== %s ====%s\n' "$c_blue" "$*" "$c_reset" | tee -a "$LOG_FILE"; }
trap 'die "Failed at line $LINENO. See $LOG_FILE"' ERR

# --------------------------- Preflight ---------------------------------------
[[ $EUID -eq 0 ]] || die "Run as root: sudo bash bootstrap.sh"
touch "$LOG_FILE"; chmod 600 "$LOG_FILE"
log "Bootstrap started $(date -Is)"

ARCH="$(dpkg --print-architecture)"
. /etc/os-release 2>/dev/null || true
[[ "${VERSION_ID:-}" == "24.04" ]] || warn "Expected Ubuntu 24.04 (found ${PRETTY_NAME:-unknown}) — continuing."
[[ "$ARCH" == "arm64" ]] || warn "Expected arm64/Ampere A1 (found $ARCH) — continuing."
case "$RUNTIME_MODE" in docker|native|none) ;; *) die "RUNTIME_MODE must be docker|native|none";; esac
log "Mode=$RUNTIME_MODE  user=$DEPLOY_USER  arch=$ARCH  tz=$TIMEZONE"

apt_install(){ apt-get install -y --no-install-recommends "$@"; }

# --------------------------- 1. System base ----------------------------------
section "1/9  System update + base tooling"
apt-get update -y
apt-get upgrade -y
apt_install ca-certificates curl gnupg lsb-release apt-transport-https \
            git unzip zip jq htop ncdu vim chrony software-properties-common
timedatectl set-timezone "$TIMEZONE" || warn "Could not set timezone."
systemctl enable --now chrony >/dev/null 2>&1 || true   # accurate clock = valid TLS + logs
# Persistent, size-capped journald
mkdir -p /etc/systemd/journald.conf.d
cat >/etc/systemd/journald.conf.d/00-soleil.conf <<'EOF'
[Journal]
Storage=persistent
SystemMaxUse=500M
EOF
systemctl restart systemd-journald || true
ok "Base system ready."

# --------------------------- 2. Deploy user ----------------------------------
section "2/9  Dedicated deploy user: $DEPLOY_USER"
SUDO_HOME_USER="${SUDO_USER:-ubuntu}"
if ! id -u "$DEPLOY_USER" >/dev/null 2>&1; then
  adduser --disabled-password --gecos "" "$DEPLOY_USER"
  ok "Created user $DEPLOY_USER"
else
  log "User $DEPLOY_USER already exists."
fi
usermod -aG sudo "$DEPLOY_USER"
# Passwordless sudo for automation (key-only login already enforces identity).
echo "$DEPLOY_USER ALL=(ALL) NOPASSWD:ALL" >/etc/sudoers.d/90-"$DEPLOY_USER"
chmod 440 /etc/sudoers.d/90-"$DEPLOY_USER"
visudo -cf /etc/sudoers.d/90-"$DEPLOY_USER" >/dev/null || die "sudoers syntax error"

# Copy authorized_keys from the invoking user (or ubuntu) so we never lock out.
SRC_KEYS=""
for u in "$SUDO_HOME_USER" ubuntu root; do
  if [[ -f "/home/$u/.ssh/authorized_keys" ]]; then SRC_KEYS="/home/$u/.ssh/authorized_keys"; break
  elif [[ "$u" == root && -f /root/.ssh/authorized_keys ]]; then SRC_KEYS="/root/.ssh/authorized_keys"; break; fi
done
install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh"
if [[ -n "$SRC_KEYS" ]]; then
  touch "/home/$DEPLOY_USER/.ssh/authorized_keys"
  # merge + dedupe
  cat "$SRC_KEYS" "/home/$DEPLOY_USER/.ssh/authorized_keys" 2>/dev/null \
    | sort -u >"/home/$DEPLOY_USER/.ssh/authorized_keys.tmp"
  mv "/home/$DEPLOY_USER/.ssh/authorized_keys.tmp" "/home/$DEPLOY_USER/.ssh/authorized_keys"
  chown -R "$DEPLOY_USER:$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh"
  chmod 600 "/home/$DEPLOY_USER/.ssh/authorized_keys"
  ok "Installed SSH key(s) for $DEPLOY_USER (from $SRC_KEYS)"
else
  warn "No source authorized_keys found. Add a key for $DEPLOY_USER BEFORE SSH hardening locks out password login."
fi
KEYCOUNT=$(grep -cE '^(ssh-|ecdsa-|sk-)' "/home/$DEPLOY_USER/.ssh/authorized_keys" 2>/dev/null || true)
KEYCOUNT=${KEYCOUNT//[^0-9]/}; KEYCOUNT=${KEYCOUNT:-0}

# --------------------------- 3. SSH hardening --------------------------------
if [[ "$HARDEN_SSH" == "true" ]]; then
  section "3/9  SSH hardening (key-only, no root)"
  if [[ "$KEYCOUNT" -lt 1 ]]; then
    warn "Refusing to disable password auth: $DEPLOY_USER has 0 keys. Skipping SSH hardening."
  else
    install -d -m 755 /etc/ssh/sshd_config.d
    cat >/etc/ssh/sshd_config.d/60-soleil-hardening.conf <<EOF
# Managed by Soleil bootstrap.sh — hardened SSH
Port ${SSH_PORT}
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
ChallengeResponseAuthentication no
PubkeyAuthentication yes
AuthenticationMethods publickey
MaxAuthTries 3
MaxSessions 5
LoginGraceTime 30
X11Forwarding no
AllowTcpForwarding no
AllowAgentForwarding no
PermitEmptyPasswords no
ClientAliveInterval 300
ClientAliveCountMax 2
AllowUsers ${DEPLOY_USER}
# Modern crypto only
KexAlgorithms curve25519-sha256,curve25519-sha256@libssh.org,diffie-hellman-group16-sha512
Ciphers chacha20-poly1305@openssh.com,aes256-gcm@openssh.com,aes128-gcm@openssh.com
MACs hmac-sha2-512-etm@openssh.com,hmac-sha2-256-etm@openssh.com
EOF
    if sshd -t; then
      systemctl reload ssh 2>/dev/null || systemctl restart ssh 2>/dev/null || \
        systemctl restart sshd 2>/dev/null || warn "Could not reload ssh; reload manually."
      ok "SSH hardened. AllowUsers=$DEPLOY_USER, key-only, root login disabled."
      warn "ACTION: in a NEW terminal verify  ssh ${DEPLOY_USER}@<IP>  before closing this session."
    else
      rm -f /etc/ssh/sshd_config.d/60-soleil-hardening.conf
      die "sshd config invalid — reverted. Not touching live SSH."
    fi
  fi
fi

# --------------------------- 4. Host firewall --------------------------------
if [[ "$SETUP_FIREWALL" == "true" ]]; then
  section "4/9  Host firewall (adaptive: OCI iptables augment / UFW fallback)"
  ensure_iptables_accept(){ # $1=cmd(iptables|ip6tables) $2=port
    local IPT="$1" port="$2" pos
    "$IPT" -C INPUT -p tcp --dport "$port" -m conntrack --ctstate NEW -j ACCEPT 2>/dev/null && return 0
    pos=$("$IPT" -L INPUT --line-numbers 2>/dev/null | awk '/REJECT|DROP/{print $1; exit}')
    if [[ -n "${pos:-}" ]]; then
      "$IPT" -I INPUT "$pos" -p tcp --dport "$port" -m conntrack --ctstate NEW -j ACCEPT
    else
      "$IPT" -A INPUT -p tcp --dport "$port" -m conntrack --ctstate NEW -j ACCEPT
    fi
  }
  if [[ -f /etc/iptables/rules.v4 ]] && iptables -L INPUT 2>/dev/null | grep -qE 'REJECT|DROP'; then
    log "OCI iptables ruleset detected — augmenting with 80/443 (preserves metadata/DHCP/ICMP)."
    for p in 80 443 "$SSH_PORT"; do ensure_iptables_accept iptables "$p"; done
    if command -v ip6tables >/dev/null && ip6tables -L INPUT 2>/dev/null | grep -qE 'REJECT|DROP'; then
      for p in 80 443 "$SSH_PORT"; do ensure_iptables_accept ip6tables "$p"; done
    fi
    apt_install netfilter-persistent iptables-persistent || true
    netfilter-persistent save || warn "netfilter-persistent save failed."
    ok "iptables now allows ${SSH_PORT}/80/443 (saved)."
  else
    log "No OCI ruleset — using UFW."
    apt_install ufw
    ufw --force reset >/dev/null 2>&1 || true
    ufw default deny incoming; ufw default allow outgoing
    if [[ -n "$SSH_ALLOW_CIDR" ]]; then ufw allow from "$SSH_ALLOW_CIDR" to any port "$SSH_PORT" proto tcp
    else ufw allow "$SSH_PORT"/tcp; fi
    ufw allow 80/tcp; ufw allow 443/tcp
    ufw --force enable
    ok "UFW enabled (deny-in/allow-out; ${SSH_PORT},80,443 open)."
  fi
  [[ -n "$SSH_ALLOW_CIDR" ]] && warn "NOTE: source-restrict SSH at the OCI NSG layer too (ingress 22 from $SSH_ALLOW_CIDR)."
fi

# --------------------------- 5. fail2ban -------------------------------------
if [[ "$SETUP_FAIL2BAN" == "true" ]]; then
  section "5/9  fail2ban (sshd jail)"
  apt_install fail2ban
  cat >/etc/fail2ban/jail.local <<EOF
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
backend  = systemd
[sshd]
enabled = true
port    = ${SSH_PORT}
EOF
  systemctl enable --now fail2ban
  systemctl restart fail2ban
  ok "fail2ban active (ban 1h after 5 fails in 10m)."
fi

# --------------------------- 6. Swap -----------------------------------------
if [[ "$SETUP_SWAP" == "true" ]]; then
  section "6/9  Swap ($SWAP_SIZE) — Vite/ARM OOM insurance"
  if swapon --show=NAME --noheadings | grep -q '/swapfile'; then
    log "Swap already active."
  else
    if fallocate -l "$SWAP_SIZE" /swapfile 2>/dev/null; then :; else
      dd if=/dev/zero of=/swapfile bs=1M count="$(( ${SWAP_SIZE%G} * 1024 ))" status=none; fi
    chmod 600 /swapfile; mkswap /swapfile >/dev/null; swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >>/etc/fstab
    ok "Swap on."
  fi
  sysctl -qw vm.swappiness="$SWAPPINESS" || true
fi

# --------------------------- 7. sysctl hardening -----------------------------
if [[ "$SETUP_SYSCTL" == "true" ]]; then
  section "7/9  Kernel sysctl hardening"
  cat >/etc/sysctl.d/60-soleil-hardening.conf <<EOF
net.ipv4.tcp_syncookies = 1
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1
net.ipv4.icmp_echo_ignore_broadcasts = 1
net.ipv4.conf.all.accept_redirects = 0
net.ipv6.conf.all.accept_redirects = 0
net.ipv4.conf.all.send_redirects = 0
net.ipv4.conf.all.accept_source_route = 0
net.ipv6.conf.all.accept_source_route = 0
net.ipv4.conf.all.log_martians = 1
kernel.randomize_va_space = 2
fs.protected_hardlinks = 1
fs.protected_symlinks = 1
vm.swappiness = ${SWAPPINESS}
vm.vfs_cache_pressure = 50
fs.inotify.max_user_watches = 524288
EOF
  sysctl --system >/dev/null
  ok "sysctl hardening applied."
fi

# --------------------------- 8. unattended-upgrades --------------------------
if [[ "$SETUP_UNATTENDED" == "true" ]]; then
  section "8/9  Unattended security upgrades"
  apt_install unattended-upgrades
  cat >/etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::Download-Upgradeable-Packages "1";
APT::Periodic::AutocleanInterval "7";
EOF
  systemctl enable --now unattended-upgrades >/dev/null 2>&1 || true
  ok "Security auto-updates enabled (no auto-reboot)."
fi

# --------------------------- 9. Runtime layer --------------------------------
section "9/9  Runtime layer ($RUNTIME_MODE)"
install_nginx_certbot(){
  apt_install nginx certbot python3-certbot-nginx
  systemctl enable --now nginx
  ok "Nginx + Certbot installed (vhost/cert = Giai doan 1)."
}

if [[ "$RUNTIME_MODE" == "docker" ]]; then
  if ! command -v docker >/dev/null 2>&1; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=${ARCH} signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" \
      >/etc/apt/sources.list.d/docker.list
    apt-get update -y
    apt_install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  fi
  systemctl enable --now docker
  usermod -aG docker "$DEPLOY_USER"
  ok "Docker $(docker --version | awk '{print $3}' | tr -d ,) + compose plugin ready. ($DEPLOY_USER in docker group)"
  install_nginx_certbot
  warn "Giai doan 1: deploy via docker-compose.prod.yml behind host Nginx — see deploy/reference/."

elif [[ "$RUNTIME_MODE" == "native" ]]; then
  # PHP 8.3 + Laravel 12 extensions
  apt_install "php${PHP_VERSION}-cli" "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-pgsql" \
    "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-zip" "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-intl" "php${PHP_VERSION}-redis" "php${PHP_VERSION}-opcache" \
    "php${PHP_VERSION}-readline"
  systemctl enable --now "php${PHP_VERSION}-fpm"
  # Composer (signature-verified)
  if ! command -v composer >/dev/null 2>&1; then
    EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    ACTUAL="$(php -r "echo hash_file('sha384','/tmp/composer-setup.php');")"
    [[ "$EXPECTED" == "$ACTUAL" ]] || die "Composer installer checksum mismatch."
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
  fi
  # Node + pnpm (corepack pins the repo's pnpm version)
  if ! command -v node >/dev/null 2>&1 || [[ "$(node -v | cut -dv -f2 | cut -d. -f1)" != "$NODE_MAJOR" ]]; then
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
    apt_install nodejs
  fi
  corepack enable || true
  corepack prepare "pnpm@${PNPM_VERSION}" --activate || warn "corepack pnpm pin failed (non-fatal)."
  # PostgreSQL 16 (+ btree_gist for the booking exclusion constraint)
  apt_install postgresql postgresql-contrib
  systemctl enable --now postgresql
  if [[ -n "$DB_PASSWORD" ]]; then
    sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1 || \
      sudo -u postgres psql -c "CREATE ROLE ${DB_USER} LOGIN PASSWORD '${DB_PASSWORD}';"
    sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1 || \
      sudo -u postgres createdb -O "${DB_USER}" "${DB_NAME}"
    sudo -u postgres psql -d "${DB_NAME}" -c "CREATE EXTENSION IF NOT EXISTS btree_gist;"
    ok "PostgreSQL role ${DB_USER} + db ${DB_NAME} + btree_gist ready."
  else
    warn "DB_PASSWORD not set — created PostgreSQL but no role/db. (btree_gist needed at GĐ1.)"
  fi
  # Redis (localhost-only; app requires REDIS_PASSWORD at GĐ1)
  apt_install redis-server
  sed -i 's/^# *supervised .*/supervised systemd/; s/^supervised .*/supervised systemd/' /etc/redis/redis.conf || true
  grep -q '^bind 127.0.0.1 ::1' /etc/redis/redis.conf || sed -i 's/^bind .*/bind 127.0.0.1 ::1/' /etc/redis/redis.conf || true
  systemctl enable --now redis-server
  ok "PHP ${PHP_VERSION} + Composer + Node ${NODE_MAJOR}/pnpm + PostgreSQL + Redis installed."
  install_nginx_certbot
  warn "Giai doan 1: clone repo, build (pnpm), .env.production, vhost, certbot, migrate — see deploy/reference/."
else
  log "RUNTIME_MODE=none — hardening only, no runtimes installed."
fi

# --------------------------- Optional: DuckDNS updater -----------------------
if [[ -n "$DUCKDNS_DOMAIN" && -n "$DUCKDNS_TOKEN" ]]; then
  section "Optional  DuckDNS auto-updater"
  install -d -m 700 /opt/duckdns
  cat >/opt/duckdns/update.sh <<EOF
#!/usr/bin/env bash
curl -fsS "https://www.duckdns.org/update?domains=${DUCKDNS_DOMAIN}&token=${DUCKDNS_TOKEN}&ip=" \
  -o /opt/duckdns/last.log 2>&1
EOF
  chmod 700 /opt/duckdns/update.sh
  cat >/etc/systemd/system/duckdns.service <<'EOF'
[Unit]
Description=DuckDNS IP update
After=network-online.target
Wants=network-online.target
[Service]
Type=oneshot
ExecStart=/opt/duckdns/update.sh
EOF
  cat >/etc/systemd/system/duckdns.timer <<'EOF'
[Unit]
Description=Run DuckDNS update every 5 min
[Timer]
OnBootSec=60
OnUnitActiveSec=5min
[Install]
WantedBy=timers.target
EOF
  systemctl daemon-reload
  systemctl enable --now duckdns.timer
  /opt/duckdns/update.sh || warn "Initial DuckDNS update failed — check token."
  ok "DuckDNS updater armed for ${DUCKDNS_DOMAIN}.duckdns.org (every 5 min)."
fi

# --------------------------- Summary -----------------------------------------
section "DONE"
cat <<EOF | tee -a "$LOG_FILE"
Bootstrap complete.  Mode=$RUNTIME_MODE  User=$DEPLOY_USER
  - SSH:        key-only, root disabled, AllowUsers=$DEPLOY_USER (keys: $KEYCOUNT)
  - Firewall:   $( [[ -f /etc/iptables/rules.v4 ]] && echo 'iptables (OCI) + 80/443' || echo 'UFW deny-in' )
  - fail2ban:   $(systemctl is-active fail2ban 2>/dev/null || echo n/a)
  - Swap:       $(swapon --show=SIZE --noheadings 2>/dev/null | head -n1 || echo none)
  - Runtimes:   $RUNTIME_MODE

NEXT (do NOT close this session yet):
  1) New terminal:  ssh ${DEPLOY_USER}@<VM_PUBLIC_IP>   <-- must succeed
  2) Run:           bash deploy/verify.sh
  3) Giai doan 1 (app deploy): deploy/reference/GIAI_DOAN_1_NEXT.md
EOF
ok "All done. Log: $LOG_FILE"
