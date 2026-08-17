#!/usr/bin/env bash
set -euo pipefail

workspace_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
data_dir="$workspace_dir/data"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is required before setting Percona permissions" >&2
  exit 1
fi

uid_gid="$(docker run --rm percona:8.0 id -u mysql):$(docker run --rm percona:8.0 id -g mysql)"

echo "Percona mysql uid:gid is $uid_gid"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run this script with sudo to apply ownership to $data_dir" >&2
  exit 1
fi

mkdir -p "$data_dir"
chown -R "$uid_gid" "$data_dir"

echo "Applied ownership $uid_gid to $data_dir"
