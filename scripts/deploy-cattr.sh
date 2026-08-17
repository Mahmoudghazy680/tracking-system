#!/usr/bin/env bash
set -euo pipefail

workspace_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$workspace_dir"

./scripts/validate-env.sh
./scripts/check-dns.sh

docker-compose pull
docker-compose up -d

./scripts/wait-for-stack.sh
./scripts/validate-Tracker.sh

echo "Automated deployment steps completed"
echo "Complete the remaining manual checks in CHECKLIST.md"