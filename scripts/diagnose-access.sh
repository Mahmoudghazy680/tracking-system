#!/usr/bin/env bash
set -euo pipefail

workspace_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$workspace_dir"

echo "== Host =="
hostname -I || true
echo

echo "== Port listeners for 80 and 443 =="
if command -v ss >/dev/null 2>&1; then
  ss -ltnp '( sport = :80 or sport = :443 )' || true
else
  netstat -ltnp 2>/dev/null | grep -E ':(80|443)\s' || true
fi
echo

echo "== Docker service =="
if command -v systemctl >/dev/null 2>&1; then
  systemctl is-active docker || true
  systemctl status docker --no-pager -l || true
fi
echo

echo "== Compose status =="
docker-compose ps || true
echo

echo "== App logs =="
docker-compose logs --tail=100 app || true
echo

echo "== DB logs =="
docker-compose logs --tail=100 db || true
echo

echo "== Firewall =="
if command -v ufw >/dev/null 2>&1; then
  ufw status verbose || true
fi
echo

echo "== Local HTTP check =="
if command -v wget >/dev/null 2>&1; then
  wget -S -O - http://127.0.0.1/ 2>&1 | head -n 20 || true
else
  echo "wget not installed"
fi
