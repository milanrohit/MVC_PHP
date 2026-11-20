#!/usr/bin/env bash
# apply-and-verify.sh  (v2)
# Run from the project root (C:\project\MVC_PHP) in Git Bash:
#   bash apply-and-verify.sh            # full rebuild (--no-cache), then tests
#   QUICK=1 bash apply-and-verify.sh    # reuse build cache (faster)
#   bash apply-and-verify.sh myservice  # service name defaults to "app"
#
# Always uses ONLY docker-compose.yml (the hardened base). The dev override
# file is never loaded, so results reflect the hardened setup.

set -u
SERVICE="${1:-app}"
DF="docker/php/Dockerfile"
INI="docker/php/security_php2shell.ini"
DC="docker compose -f docker-compose.yml"
pass=0
fail=0

ok()  { echo "  [PASS] $1"; pass=$((pass + 1)); }
bad() { echo "  [FAIL] $1"; fail=$((fail + 1)); }
run() { $DC exec -T "$SERVICE" "$@"; }

echo "== 1. Static checks =="
[ -f docker-compose.yml ] || { echo "docker-compose.yml not found - run from the project root."; exit 1; }
[ -f "$DF" ]  || { echo "$DF not found."; exit 1; }
[ -f "$INI" ] || { echo "$INI not found."; exit 1; }

if grep -qE '^DB_ROOT_PASSWORD=.+' .env 2>/dev/null; then
  ok ".env defines DB_ROOT_PASSWORD"
else
  bad ".env is missing DB_ROOT_PASSWORD (add: echo 'DB_ROOT_PASSWORD=secret' >> .env)"
fi

prod_line=$(grep -nE '^FROM .* AS production' "$DF" | head -1 | cut -d: -f1)
copy_lines=$(grep -nE '^COPY .*security_php2shell' "$DF" | cut -d: -f1)
n=$(printf '%s\n' "$copy_lines" | grep -c . || true)
if [ -n "$prod_line" ] && [ "$n" -eq 1 ] && [ "$copy_lines" -gt "$prod_line" ]; then
  ok "hardening ini is copied once, in the production stage ($DF line $copy_lines)"
else
  bad "hardening ini must be copied exactly once, after 'FROM ... AS production' in $DF (found $n)"
fi

if grep -nE '^[^;#].*\\$' "$INI" >/dev/null; then
  bad "ini has a line ending in a backslash (PHP ini has no line continuation):"
  grep -nE '^[^;#].*\\$' "$INI"
else
  ok "ini has no backslash line continuation"
fi

grep -q "^allow_url_fopen *= *Off" "$INI" && ok "ini sets allow_url_fopen = Off" || bad "ini is missing allow_url_fopen = Off"

cfg=$($DC config 2>&1) || { bad "docker-compose.yml does not parse:"; echo "$cfg"; exit 1; }
echo "$cfg" | grep -q "type: bind" && bad "base compose has a bind mount (code would be writable)" || ok "base compose has no bind mounts"
echo "$cfg" | grep -q "custom_dev" && bad "base compose mounts custom_dev.ini (re-enables allow_url_fopen)" || ok "base compose does not mount custom_dev.ini"
echo "$cfg" | grep -q "target: 8080" && ok "app port maps to Apache's 8080" || bad "app port should target 8080 (production Apache listens on 8080)"

echo
echo "== 2. Rebuild and start =="
BUILD_FLAGS="--no-cache"
[ "${QUICK:-0}" = "1" ] && BUILD_FLAGS=""
if $DC build $BUILD_FLAGS "$SERVICE" && $DC up -d --force-recreate --wait --wait-timeout 90 "$SERVICE"; then
  ok "image built and container is healthy"
else
  bad "build/start failed - fix the error above, then re-run"
  exit 1
fi

echo
echo "== 3. Runtime hardening tests =="

out=$(run php -r 'system("id");' 2>&1)
echo "$out" | grep -q "undefined function system" && ok "system() is disabled" || bad "system() still available: $out"

out=$(run php -r 'echo `id`;' 2>&1)
echo "$out" | grep -q "undefined function shell_exec" && ok "backtick/shell_exec is disabled" || bad "backtick still works: $out"

out=$(run php -r 'readfile("/etc/passwd");' 2>&1)
echo "$out" | grep -q "open_basedir" && ok "open_basedir blocks /etc/passwd" || bad "/etc/passwd readable: $out"

out=$(run php -r 'echo ini_get("allow_url_fopen") ? "ON" : "OFF";' 2>&1)
[ "$out" = "OFF" ] && ok "allow_url_fopen is OFF" || bad "allow_url_fopen is $out (another ini or setting is overriding it)"

out=$(run php -r 'echo ini_get("expose_php") ? "ON" : "OFF";' 2>&1)
[ "$out" = "OFF" ] && ok "expose_php is OFF" || bad "expose_php is $out"

out=$(run php -r 'echo ini_get("display_errors") ? "ON" : "OFF";' 2>&1)
[ "$out" = "OFF" ] && ok "display_errors is OFF" || bad "display_errors is $out"

uid=$(run id -u 2>&1 | tr -d '\r')
[ "$uid" != "0" ] && ok "container runs as non-root (uid $uid)" || bad "container is running as root"

out=$(run sh -c 'touch /var/www/html/.w 2>/dev/null && { rm -f /var/www/html/.w; echo WRITABLE; } || echo READONLY' 2>&1)
[ "$out" = "READONLY" ] && ok "application code directory is read-only" || bad "code directory is WRITABLE (bind mount or root user?)"

out=$(run sh -c 'touch /var/www/html/storage/logs/.t && rm -f /var/www/html/storage/logs/.t && echo OK' 2>&1)
[ "$out" = "OK" ] && ok "storage/logs is writable (PHP error log works)" || bad "storage/logs not writable (does the Dockerfile create storage/logs?): $out"

hdr=$(run curl -sI http://localhost:8080/ 2>&1)
if [ -z "$hdr" ]; then
  bad "no HTTP response from localhost:8080 inside the container"
else
  echo "$hdr" | grep -qi '^x-powered-by' && bad "X-Powered-By header is exposed" || ok "no X-Powered-By header"
  echo "$hdr" | grep -qiE '^server: apache/[0-9]' && bad "Apache version is exposed in Server header" || ok "Server header does not leak the version"
fi

APP_PORT=$(grep -E '^APP_PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r')
APP_PORT="${APP_PORT:-8088}"
code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${APP_PORT}/" 2>/dev/null || true)
if [ -n "$code" ] && [ "$code" != "000" ]; then
  ok "host port ${APP_PORT} reaches the app (HTTP $code)"
else
  bad "nothing answers on host port ${APP_PORT} (check the port mapping)"
fi

echo
echo "== Summary: $pass passed, $fail failed =="
if [ "$fail" -gt 0 ]; then
  echo
  echo "-- Diagnostics (paste this output if you need help) --"
  run php --ini
  run sh -c 'grep -rn "allow_url_fopen" /usr/local/etc/php/ 2>/dev/null'
  echo "$cfg" | grep -nE "user:|target:|type:|read_only|\.ini" || true
  exit 1
fi
echo
echo "Hardened setup verified. Back to dev mode (adds bind mounts + phpMyAdmin):"
echo "  docker compose up -d --force-recreate"