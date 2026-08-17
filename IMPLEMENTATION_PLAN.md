# 1. Executive summary

This implementation uses the least-friction Tracker deployment path for Ubuntu: the official prebuilt Tracker app image plus Percona 8.0, running under Docker Compose on a single VM. For this environment, the public endpoint is `http://tracking.pinnaclemisr.com` and the desktop client must use `http://tracking.pinnaclemisr.com/api`.

The bundle in this workspace is already aligned to that path. It includes the runtime env template, Docker Compose stack, executable helper scripts, a manual validation checklist, and a first-report checklist. The only steps that cannot be completed from this local workspace are the VM-executed commands and the manual browser and desktop-client actions.

# 2. Assumptions

- The target host is an Ubuntu VM with sudo access.
- The VM IP is `172.16.70.66`.
- The public DNS name is `tracking.pinnaclemisr.com` and resolves to `172.16.70.66`.
- The goal is the fastest stable path to a working reporting environment, not a hardened production rollout.
- Deployment uses Docker and Docker Compose, not a source install.
- SMTP is not required for the first pass.
- Desktop users will use the official desktop binaries.
- HTTPS is intentionally deferred; first-pass validation is HTTP over the domain name.

# 3. Step-by-step implementation

## 3.1 Environment preparation

Run these on the Ubuntu VM in order.

```bash
uname -m
cat /etc/os-release
hostname -I
free -h
df -h
```

Purpose:
- `uname -m` confirms the VM architecture.
- `cat /etc/os-release` confirms the Ubuntu release.
- `hostname -I` confirms the VM IP.
- `free -h` checks memory.
- `df -h` checks available disk.

Install required base packages:

```bash
sudo apt update
sudo apt install -y ca-certificates curl gnupg lsb-release apt-transport-https software-properties-common ufw jq git openssl netcat-openbsd wget
```

Purpose:
- `curl`, `wget`, and `netcat-openbsd` are used for network checks.
- `ufw` manages the firewall.
- `jq` is used by the validation script.
- `git` is used if you want to inspect upstream repositories.
- `openssl` generates the Laravel app key.

Install Docker Engine and Docker Compose:

```bash
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
docker info
docker-compose version
```

Purpose:
- Adds Docker’s official repository.
- Installs Docker Engine and the Compose plugin.
- Enables Docker at boot and confirms it is working.

Check firewall status and open required ports:

```bash
sudo ufw status verbose
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
```

Purpose:
- Keeps SSH accessible.
- Opens HTTP for the web UI and API.
- Keeps MySQL internal; port `3306` is not exposed.

## 3.2 Clone and inspect the Tracker server repo

The deployment bundle in this workspace already chooses the least-friction path, but if you want to inspect upstream source for verification, run:

```bash
git clone https://github.com/Tracker-app/server-application.git
cd server-application
find . -maxdepth 2 \( -name 'README*' -o -name '.env*' -o -name 'docker-compose*.yml' -o -name 'compose*.yml' \)
```

Decision:
- Use the official prebuilt app image plus Percona 8.0.
- Do not use the repo’s more source-oriented compose path as the primary deployment path.

Why this is the least-friction path:
- It avoids building the Laravel app locally.
- It aligns with the current docs-backed image deployment model.
- It keeps the stack to two required services: app and db.

## 3.3 Configure environment

From this workspace on the VM:

```bash
cd /opt/Tracker
make init-env
```

Then edit `.env`.

Values you must replace manually:
- `APP_KEY`
- `DB_PASSWORD`
- `MYSQL_ROOT_PASSWORD`
- `APP_ADMIN_PASSWORD`

Generate the Laravel app key:

```bash
openssl rand -base64 32
```

Put it into `.env` as:

```text
APP_KEY=base64:YOUR_GENERATED_VALUE
```

Validate the env file:

```bash
make env-check
```

This deployment expects:
- Browser URL: `http://tracking.pinnaclemisr.com`
- Desktop URL: `http://tracking.pinnaclemisr.com/api`
- DB host: `db`
- Internal MySQL port: `3306`
- Timezone: `Africa/Cairo`

## 3.4 Docker deployment

Prepare directories and firewall rules:

```bash
sudo make prepare-host
```

Fix MySQL bind-mount ownership using the Percona mysql UID and GID:

```bash
sudo make fix-db-perms
```

Deploy the stack:

```bash
make deploy
```

What `make deploy` does:
- Validates `.env`
- Validates DNS
- Pulls images
- Starts containers
- Waits for DB health and app startup
- Validates `/api/status`, login, and `/api/auth/me`

Inspect health and logs:

```bash
docker-compose ps
docker-compose logs -f
docker-compose logs --tail=100 app
docker-compose logs --tail=100 db
```

Safe restart commands:

```bash
docker-compose restart app
docker-compose restart
docker-compose down
docker-compose up -d
```

Data persistence:
- `./storage` is mounted to `/app/storage`
- `./data` is mounted to `/var/lib/mysql`

## 3.5 Database initialization

Primary path:
- Let the app image bootstrap on first startup using the admin values from `.env`.

Fallback path if bootstrap fails:

```bash
make bootstrap
```

This runs the current manual fallback sequence:

```bash
docker-compose exec app php artisan key:generate --force
docker-compose exec app php artisan migrate --seed --seeder=InitialSeeder --force
docker-compose exec app php artisan Tracker:make:admin --email="$APP_ADMIN_EMAIL" --name="$APP_ADMIN_NAME" --password="$APP_ADMIN_PASSWORD"
```

Verification:
- `docker-compose ps` shows a healthy `db` and running `app`
- `make validate` succeeds
- Admin login works in the web UI

Current correction from older docs:
- Do not rely on deprecated auth refresh flows.
- Use `InitialSeeder` and `Tracker:make:admin` only as the fallback path when bootstrap does not complete on first run.

## 3.6 Web access validation

Browser URL:
- `http://tracking.pinnaclemisr.com/`

Curl tests:

```bash
curl -i http://tracking.pinnaclemisr.com/api/status
curl -i -X POST http://tracking.pinnaclemisr.com/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@tracking.pinnaclemisr.com","password":"REPLACE_WITH_STRONG_ADMIN_PASSWORD"}'
curl -i http://tracking.pinnaclemisr.com/api/auth/me \
  -H 'Authorization: Bearer REPLACE_WITH_TOKEN'
```

Troubleshooting guidance:
- Connection refused: check `docker-compose ps`, Docker service state, UFW rules, and `make diagnose`.
- 404: confirm you are using the site root in the browser and `/api` only in the desktop client or API tests.
- Blank page: check `docker-compose logs --tail=100 app` and test `/api/status` directly.
- Login failure: verify admin bootstrap completed or run `make bootstrap`.
- Redirect loops: check `APP_URL` and `FRONTEND_APP_URL` for exact domain alignment.

## 3.7 Desktop app integration

Use this server URL in the desktop client:

```text
http://tracking.pinnaclemisr.com/api
```

Do not use:
- `http://tracking.pinnaclemisr.com` in the desktop client
- `http://172.16.70.66` unless you intentionally fall back to raw-IP testing

Common detection issue:
- The desktop app validates the hostname as a Tracker instance, so the most common failure is entering the site root without `/api`.

Authentication:
- Use normal email and password login first.
- Do not start with SSO or more advanced flows.

Practical platform note:
- Linux, Windows, and macOS users should all use the same server URL ending in `/api`.

## 3.8 User setup

Minimum viable setup in the web UI:
- 1 admin
- 1 normal user
- 1 project
- 1 task assigned to that user

Role summary:
- `admin`: full system access
- `manager`: team and project management with limited administrative scope
- `user`: time tracking and assigned task work

Sample API flow after login:

```bash
curl -i http://tracking.pinnaclemisr.com/api/roles/list \
  -H 'Authorization: Bearer REPLACE_WITH_TOKEN'
curl -i -X POST http://tracking.pinnaclemisr.com/api/users/create \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer REPLACE_WITH_TOKEN' \
  -d '{"email":"user1@example.com","password":"STRONG_USER_PASSWORD","role_id":3,"name":"Test User"}'
```

If role IDs differ in your instance, use the `roles/list` response to choose the correct `role_id`.

## 3.9 Time tracking validation

Minimal end-to-end scenario:
1. Log in to the desktop client as the normal user.
2. Confirm the assigned task is visible.
3. Start tracking.
4. Let it run long enough to create at least one interval.
5. Stop tracking.
6. Confirm the interval appears in the backend.
7. If screenshots are enabled, confirm screenshots are visible in the web UI.

Verification checkpoints:
- Login succeeds in desktop.
- Task list loads.
- Tracking starts without client-side errors.
- The backend shows a new interval.
- Screenshots are stored only if enabled.

Deprecated route warning:
- Do not use old legacy screenshot-create routes in new automation.

## 3.10 Reporting setup

Fastest path to the first report:
- Use the dashboard report in the web UI.
- Start with CSV export.

Manual path:
1. Log in as admin.
2. Open dashboard reporting.
3. Set date range to today.
4. Filter to the test user and project.
5. Export CSV.

Current report-related endpoints to validate or automate exports:

```bash
curl -i http://tracking.pinnaclemisr.com/api/about/reports
curl -i -X POST http://tracking.pinnaclemisr.com/api/report/dashboard/download \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer REPLACE_WITH_TOKEN' \
  -d '{"format":"csv"}'
```

Known export formats from current source references:
- CSV
- XLSX
- PDF
- XLS
- ODS
- HTML

# 4. Configuration files

## 4.1 Sample `.env`

```dotenv
DOMAIN=YOUR_DOMAIN
VM_IP=YOUR_VM_IP

APP_NAME=Pin-Tracker
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:REPLACE_WITH_GENERATED_APP_KEY
APP_URL=http://YOUR_DOMAIN
FRONTEND_APP_URL=http://YOUR_DOMAIN
APP_TIMEZONE=Africa/Cairo

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=Tracker
DB_USERNAME=root
DB_PASSWORD=STRONG_DB_PASSWORD

MYSQL_DATABASE=Tracker
MYSQL_ROOT_PASSWORD=STRONG_DB_PASSWORD

APP_ADMIN_NAME=Admin
APP_ADMIN_EMAIL=admin@YOUR_DOMAIN
APP_ADMIN_PASSWORD=STRONG_ADMIN_PASSWORD

LOG_CHANNEL=stack
LOG_LEVEL=info
```

For this environment, the concrete values are already prepared in [.env.example](.env.example).

## 4.2 Sample `docker-compose.yml`

```yaml
services:
  app:
    image: registry.git.amazingcat.net/Tracker/core/app:latest
    restart: unless-stopped
    env_file:
      - ./.env
    depends_on:
      db:
        condition: service_healthy
    ports:
      - "80:80"
    volumes:
      - ./storage:/app/storage

  db:
    image: percona:8.0
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: ${MYSQL_DATABASE}
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    command:
      - --default-authentication-plugin=mysql_native_password
    healthcheck:
      test:
        - CMD-SHELL
        - mysqladmin ping -h 127.0.0.1 -uroot -p$$MYSQL_ROOT_PASSWORD --silent
      interval: 10s
      timeout: 5s
      retries: 12
      start_period: 30s
    volumes:
      - ./data:/var/lib/mysql
```

The active version is in [docker-compose.yml](docker-compose.yml).

# 5. Validation steps

Post-install validation checklist:

1. `make env-check` passes.
2. `make dns-check` passes.
3. `make deploy` completes successfully.
4. `docker-compose ps` shows a healthy database and a running app.
5. `make validate` succeeds.
6. The site opens in the browser.
7. Admin login works.
8. A normal user, project, and task exist.
9. The desktop client connects using `http://tracking.pinnaclemisr.com/api`.
10. A tracked interval appears in the backend.
11. A dashboard CSV export completes.

First report checklist:
- Use [FIRST_REPORT_CHECKLIST.md](FIRST_REPORT_CHECKLIST.md).

# 6. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Docker container crashes | Bad env values, failed bootstrap, bad bind-mount permissions, DB unavailable | Check `docker-compose logs --tail=100 app` and `db`, run `make env-check`, run `sudo make fix-db-perms` |
| App not reachable | Docker not running, stack not started, nothing listening on port 80, UFW issue | Run `make diagnose`, `docker-compose ps`, `systemctl status docker`, `sudo ufw status verbose` |
| Connection refused | No service bound to 80 or stack failed at startup | Start the stack with `make deploy`, then inspect `docker-compose ps` and logs |
| 404 | Wrong URL path or wrong client URL | Browser uses `http://tracking.pinnaclemisr.com/`; desktop uses `http://tracking.pinnaclemisr.com/api` |
| Blank page | Frontend loads but backend/API is failing | Test `/api/status` directly and inspect app logs |
| Redirect loops | `APP_URL` or `FRONTEND_APP_URL` mismatch | Set both URLs to the same public origin and redeploy |
| DB connection issues | Password mismatch, wrong host, unwritable data mount | Ensure `DB_HOST=db`, passwords match, and run `sudo make fix-db-perms` |
| Migrations fail | DB not healthy, reused broken DB state, bootstrap incomplete | Wait for DB health, inspect logs, then run `make bootstrap` if needed |
| Admin login fails | First-run bootstrap did not complete | Run `make bootstrap` and re-run `make validate` |
| Desktop client cannot connect | Wrong server URL, site root entered instead of `/api`, network path issue | Use `http://tracking.pinnaclemisr.com/api` and test `curl -i http://tracking.pinnaclemisr.com/api/status` |
| Reports not generated | No tracked intervals, missing permissions, storage issue | Confirm intervals exist, check `/api/about/reports`, inspect app logs |
| Storage or screenshots not persisting | Missing storage mount or wrong filesystem permissions | Verify `./storage:/app/storage` and ensure the mount is writable |

# 7. Next actions

1. Copy this workspace to the Ubuntu VM at `172.16.70.66`.
2. Run the deployment flow in order: `make init-env`, edit `.env`, `make env-check`, `sudo make install-docker`, `sudo make prepare-host`, `sudo make fix-db-perms`, `make deploy`.
3. Complete the manual acceptance items in [CHECKLIST.md](CHECKLIST.md).
4. Complete the report export verification in [FIRST_REPORT_CHECKLIST.md](FIRST_REPORT_CHECKLIST.md).
5. If the site is unreachable at any point, run `make diagnose` on the VM and inspect logs immediately.
