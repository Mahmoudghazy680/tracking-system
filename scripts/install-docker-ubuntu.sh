#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run this script with sudo" >&2
  exit 1
fi

install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
chmod a+r /etc/apt/keyrings/docker.gpg

source /etc/os-release

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $VERSION_CODENAME stable" > /etc/apt/sources.list.d/docker.list

apt update
apt install -y ca-certificates curl gnupg lsb-release apt-transport-https software-properties-common ufw jq git openssl
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
systemctl enable --now docker

docker info >/dev/null
docker-compose version

echo "Docker Engine and Docker Compose plugin are installed"
