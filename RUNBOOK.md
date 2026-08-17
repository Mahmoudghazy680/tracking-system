# Tracker VM Deployment Runbook

This package deploys Cattr to a single Ubuntu VM using Docker, the official prebuilt Cattr app image, and Percona 8.0.

Public endpoint:

- Site: http://tracking.pinnaclemisr.com
- Desktop client: http://tracking.pinnaclemisr.com/api
- VM IP: 172.16.70.66

Files in this bundle:

- `.env.example`: runtime variable template
- `docker-compose.yml`: app and database services
- `Makefile`: command shortcuts
- `CHECKLIST.md`: manual acceptance steps for UI, desktop, tracking, and reporting
- `scripts/`: helper scripts used by the Make targets

## 1. DNS prerequisite

Create an A record before starting:

- Host: tracking.pinnaclemisr.com
- Value: 172.16.70.66

Validate from a client machine:

```bash
nslookup tracking.pinnaclemisr.com
ping -c 1 tracking.pinnaclemisr.com
```

## 2. Host validation

Run on the Ubuntu VM:

```bash
uname -m
cat /etc/os-release
hostname -I
free -h
df -h
```

## 3. Install base packages

```bash
sudo apt update
sudo apt install -y ca-certificates curl gnupg lsb-release apt-transport-https software-properties-common ufw jq git openssl
```

## 4. Install Docker Engine and Compose plugin

```bash
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
docker info
docker-compose version
```

Optional:

```bash
sudo usermod -aG docker "$USER"
```

## 5. Firewall

Keep MySQL internal.

```bash
sudo ufw status verbose
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
```

If UFW is not enabled yet and you want it active:

```bash
sudo ufw enable
```

## 6. Copy this deployment package to the VM

Use any method you prefer. One example from your local machine:

```bash
scp -r . user@172.16.70.66:/opt/cattr
```

Then on the VM:

```bash
cd /opt/cattr
make init-env
```

## 7. Prepare secrets and persistent directories

Generate a Laravel-compatible app key:

```bash
openssl rand -base64 32
```

Put the output into `.env` as:

```text
APP_KEY=base64:YOUR_GENERATED_VALUE
```

Set strong values for:

- DB_PASSWORD
- MYSQL_ROOT_PASSWORD
- APP_ADMIN_PASSWORD

Validate the env file before starting:

```bash
make env-check
```

Create persistent directories:

```bash
mkdir -p storage data
```

Find the Percona mysql user UID and GID, then fix the MySQL data directory ownership:

```bash
docker run --rm percona:8.0 id mysql
sudo chown -R 1001:1001 data
```

If `id mysql` returns different values, use those values instead of `1001:1001`.

## 8. Start the stack

```bash
docker-compose pull
docker-compose up -d
./scripts/wait-for-stack.sh
docker-compose ps
docker-compose logs -f
```

Wait for the database healthcheck to pass and the app to finish bootstrapping.

## 9. Fallback bootstrap if first run did not finish correctly

Use this only if the admin account is not usable or logs show bootstrap did not complete.

```bash
docker-compose exec app php artisan key:generate --force
docker-compose exec app php artisan migrate --seed --seeder=InitialSeeder --force
docker-compose exec app php artisan cattr:make:admin \
  --email="admin@tracking.pinnaclemisr.com" \
  --name="Admin" \
  --password="REPLACE_WITH_STRONG_ADMIN_PASSWORD"
```

If you use `key:generate --force`, update `.env` with the resulting key if the container prints a different value.

## 10. Validate browser and API access

Browser:

- http://tracking.pinnaclemisr.com/

API checks:

```bash
curl -i http://tracking.pinnaclemisr.com/api/status
curl -i -X POST http://tracking.pinnaclemisr.com/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@tracking.pinnaclemisr.com","password":"REPLACE_WITH_STRONG_ADMIN_PASSWORD"}'
```

If login succeeds, use the bearer token from the response to verify the authenticated user:

```bash
curl -i http://tracking.pinnaclemisr.com/api/auth/me \
  -H 'Authorization: Bearer REPLACE_WITH_TOKEN'
```

Do not use deprecated `/api/auth/refresh` flows in new automation.

## 11. Create minimum working data

Use `CHECKLIST.md` for the operator acceptance path. In the web UI as admin:

1. Create one normal user.
2. Create one project.
3. Create one task assigned to that user.

For the first pass, direct user creation is lower friction than invitation-based onboarding.

## 12. Connect the desktop app

Use this server value in the desktop client:

```text
http://tracking.pinnaclemisr.com/api
```

Do not enter the site root without `/api`.

## 13. Validate time tracking

1. Log in as the normal user in the desktop app.
2. Confirm the assigned task is visible.
3. Start tracking and let it run long enough to produce at least one interval.
4. Stop tracking.
5. Confirm the interval appears in the backend.

If screenshots are enabled for the project or user, verify screenshots from the web UI. Avoid deprecated legacy screenshot-create routes in any automation.

## 14. Validate report export

Start with a dashboard CSV export for today's date range.

If troubleshooting export capability, check:

```bash
curl -i http://tracking.pinnaclemisr.com/api/about/reports
```

Expected outcome:

- The system returns a report file for download.
- The exported data includes the tracked interval.

## 15. Safe operations

Restart only the app:

```bash
docker-compose restart app
```

Restart the whole stack:

```bash
docker-compose restart
```

Stop and remove containers while keeping data:

```bash
docker-compose down
```

Start again:

```bash
docker-compose up -d
```

Do not remove volumes unless you intentionally want to rebuild from zero.

## 16. Common failures

- App not reachable: verify DNS, port 80, UFW rules, and that `docker-compose ps` shows the app running.
- DB connection failure: verify `DB_HOST=db`, matching DB passwords, and writable `data` directory ownership.
- Admin login failure: inspect first-run logs, then use the fallback artisan commands.
- Desktop cannot connect: use `http://tracking.pinnaclemisr.com/api`, not the site root.
- Reports missing data: confirm at least one tracked interval exists before exporting.
