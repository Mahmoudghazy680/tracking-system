#!/usr/bin/env bash
set -euo pipefail

domain="${DOMAIN:-tracking.pinnaclemisr.com}"
expected_ip="${VM_IP:-172.16.70.66}"

if ! command -v getent >/dev/null 2>&1; then
  echo "getent is required but not installed" >&2
  exit 1
fi

set +e
resolved_ips="$(getent ahostsv4 "$domain" 2>/dev/null | awk '{print $1}' | sort -u)"
getent_rc=$?
set -e

if [[ $getent_rc -ne 0 && -z "$resolved_ips" ]]; then
  echo "No A record found for $domain" >&2
  exit 1
fi

if [[ -z "$resolved_ips" ]]; then
  echo "No A record found for $domain" >&2
  exit 1
fi

echo "Resolved IPv4 addresses for $domain:"
echo "$resolved_ips"

if grep -Fxq "$expected_ip" <<<"$resolved_ips"; then
  echo "DNS check passed: $domain resolves to $expected_ip"
else
  echo "DNS check failed: expected $expected_ip" >&2
  exit 1
fi
