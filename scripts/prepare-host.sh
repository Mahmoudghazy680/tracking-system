#!/usr/bin/env bash
set -euo pipefail

workspace_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "Architecture: $(uname -m)"
echo "OS:"
cat /etc/os-release
echo "IP addresses:"
hostname -I
echo "Memory:"
free -h
echo "Disk:"
df -h "$workspace_dir"

mkdir -p "$workspace_dir/storage" "$workspace_dir/data"

if command -v ufw >/dev/null 2>&1; then
  if [[ "${EUID}" -ne 0 ]]; then
    echo "Skipping UFW changes because this script is not running as root"
  else
    ufw allow 22/tcp >/dev/null
    ufw allow 80/tcp >/dev/null
    echo "UFW rules ensured for 22/tcp and 80/tcp"
  fi
fi

if [[ ! -f "$workspace_dir/.env" ]]; then
  cp "$workspace_dir/.env.example" "$workspace_dir/.env"
  echo "Created $workspace_dir/.env from template"
fi

echo "Host preparation finished"
