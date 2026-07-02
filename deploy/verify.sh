#!/usr/bin/env bash
# =============================================================================
# Soleil Hostel — post-bootstrap verification (read-only, safe)
# Run on the VM:  bash deploy/verify.sh   (sudo not required, some checks need it)
# =============================================================================
set -uo pipefail
g=$'\e[1;32m'; y=$'\e[1;33m'; r=$'\e[1;31m'; b=$'\e[1;34m'; n=$'\e[0m'
pass(){ printf '%s[PASS]%s %s\n' "$g" "$n" "$*"; }
warn(){ printf '%s[WARN]%s %s\n' "$y" "$n" "$*"; }
fail(){ printf '%s[FAIL]%s %s\n' "$r" "$n" "$*"; }
sec(){ printf '\n%s== %s ==%s\n' "$b" "$*" "$n"; }
have(){ command -v "$1" >/dev/null 2>&1; }

sec "System"
. /etc/os-release 2>/dev/null || true
[[ "${VERSION_ID:-}" == "24.04" ]] && pass "Ubuntu 24.04" || warn "OS=${PRETTY_NAME:-?}"
[[ "$(uname -m)" == "aarch64" ]] && pass "arch aarch64 (Ampere A1)" || warn "arch=$(uname -m)"
timedatectl show -p NTPSynchronized --value 2>/dev/null | grep -q yes && pass "clock NTP-synced" || warn "clock not synced"

sec "Swap"
if swapon --show=NAME --noheadings 2>/dev/null | grep -q .; then
  pass "swap active: $(swapon --show=SIZE --noheadings | head -n1) (swappiness=$(cat /proc/sys/vm/swappiness))"
else fail "no swap — Vite build may OOM on A1"; fi

sec "SSH hardening"
sshd -T 2>/dev/null | grep -qi 'permitrootlogin no'      && pass "root login disabled"       || warn "root login not disabled"
sshd -T 2>/dev/null | grep -qi 'passwordauthentication no' && pass "password auth disabled"   || warn "password auth still on"
[[ -f /etc/ssh/sshd_config.d/60-soleil-hardening.conf ]]  && pass "hardening drop-in present"  || warn "no hardening drop-in"

sec "Firewall"
if [[ -f /etc/iptables/rules.v4 ]] && iptables -L INPUT 2>/dev/null | grep -qE 'REJECT|DROP'; then
  for p in 80 443; do
    iptables -C INPUT -p tcp --dport "$p" -m conntrack --ctstate NEW -j ACCEPT 2>/dev/null \
      && pass "iptables allows $p" || fail "iptables MISSING $p (host will block it)"
  done
elif have ufw && ufw status 2>/dev/null | grep -qi active; then
  pass "UFW active"; ufw status 2>/dev/null | grep -E '80|443' || warn "80/443 not seen in ufw"
else warn "no recognizable firewall state (need sudo?)"; fi

sec "fail2ban"
systemctl is-active --quiet fail2ban && pass "fail2ban running ($(fail2ban-client status sshd 2>/dev/null | awk -F: '/Currently banned/{print $2}' | xargs) banned)" || warn "fail2ban not active"

sec "Auto-updates"
systemctl is-enabled --quiet unattended-upgrades 2>/dev/null && pass "unattended-upgrades enabled" || warn "auto security updates off"

sec "Runtimes"
if have docker; then
  docker info >/dev/null 2>&1 && pass "Docker up: $(docker --version | awk '{print $3}' | tr -d ,)" || warn "docker installed but daemon down / need group re-login"
  docker compose version >/dev/null 2>&1 && pass "compose plugin: $(docker compose version --short)" || warn "compose plugin missing"
fi
have php      && pass "PHP $(php -r 'echo PHP_VERSION;')"            || true
have composer && pass "Composer $(composer --version 2>/dev/null | awk '{print $3}')" || true
have node     && pass "Node $(node -v)"                              || true
have pnpm     && pass "pnpm $(pnpm -v)"                              || true
have psql     && pass "PostgreSQL client $(psql --version | awk '{print $3}')" || true
systemctl is-active --quiet postgresql 2>/dev/null && pass "PostgreSQL server running" || true
systemctl is-active --quiet redis-server 2>/dev/null && pass "Redis running (bind: $(grep -m1 '^bind' /etc/redis/redis.conf 2>/dev/null || echo '?'))" || true
have nginx && { systemctl is-active --quiet nginx && pass "Nginx running" || warn "nginx installed, not running"; }
have certbot && pass "Certbot present" || true

sec "Network / DNS"
PUB=$(curl -4 -fsS --max-time 5 ifconfig.me 2>/dev/null || echo '?')
echo "  public IP: $PUB"
if have dig && [[ -n "${1:-}" ]]; then
  R=$(dig +short "$1" | tail -n1)
  [[ "$R" == "$PUB" ]] && pass "DNS $1 -> $R matches" || warn "DNS $1 -> ${R:-none} (expected $PUB)"
else warn "pass your domain to check DNS:  bash verify.sh soleil.duckdns.org"; fi
ss -ltnH 2>/dev/null | grep -qE ':80\b'  && pass "something listening on :80"  || warn "nothing on :80 (ok before GĐ1)"
ss -ltnH 2>/dev/null | grep -qE ':443\b' && pass "something listening on :443" || warn "nothing on :443 (ok before TLS)"

printf '\n%sVerification done. WARN before GĐ1 (vhost/cert/app) is expected.%s\n' "$b" "$n"
