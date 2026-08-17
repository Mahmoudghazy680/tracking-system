# End-to-End Validation Checklist

Use this checklist to confirm your Cattr deployment is fully functional, from server setup through first report generation.

## Phase 1: Server Deployment ✅

- [x] Docker installed on Ubuntu VM
- [x] Docker Compose running
- [x] Firewall allows port 22 (SSH) and 80 (HTTP)
- [x] MySQL internal port 3306 is not exposed
- [x] Bind mounts created: `./storage` and `./data`
- [x] Percona database running and healthy
- [x] Cattr app container running
- [x] Migrations and seeders completed on startup
- [x] Admin user created automatically: `admin@tracking.pinnaclemisr.com`

## Phase 2: Web UI Access ✅

**Checkpoint:** Browser can access the Cattr login page.

- [x] Open http://172.16.70.66 in a browser
- [x] Cattr login page loads
- [x] Admin login works with: `admin@tracking.pinnaclemisr.com` / `CattrAdmin#2025!`
- [x] After login, admin dashboard appears
- [x] Navigation menu shows: Users, Projects, Tasks, Time Intervals, Reports, Settings

## Phase 3: API Validation ✅

**Checkpoint:** REST API responds to curl requests.

- [x] `curl http://172.16.70.66/api/status` returns HTTP 200 with `{"status":200,"success":true}`
- [x] POST `/api/auth/login` with admin credentials returns a bearer token
- [x] GET `/api/auth/me` with bearer token returns authenticated user object
- [x] API is accessible from the same machine as the app
- [x] API is accessible from other machines (using the VM IP)

## Phase 4: Database & Data Model ✅

**Checkpoint:** Database correctly initialized with schema and base data.

- [x] Migrations table exists and shows all migrations completed
- [x] Seeders completed: Priorities, Statuses, CompanyManagement
- [x] Admin user record exists in database
- [x] Default company exists
- [x] Default project types exist
- [x] Time interval table exists and is empty (awaiting first tracking)

## Phase 5: Test Data Creation 🔄 **YOU ARE HERE**

**Checkpoint:** Create at least one normal user, one project, and one task.

### Create a test user

- [ ] Log in to web UI as admin
- [ ] Click **Users** → **+ New User**
- [ ] Create user:
  - Full Name: `Test User`
  - Email: `testuser@cattr.local`
  - Password: `TestPass#2025!`
- [ ] Save the user
- [ ] Confirm user appears in the user list

### Create a test project

- [ ] Click **Projects** → **+ New Project**
- [ ] Create project:
  - Name: `Test Project`
  - (leave other settings as defaults)
- [ ] Save the project
- [ ] Confirm project appears in the project list

### Create a test task

- [ ] Click **Tasks** → **+ New Task**
- [ ] Create task:
  - Name: `Sample Task`
  - Project: `Test Project` (select from dropdown)
  - Assigned To: `Test User` (select from dropdown)
- [ ] Save the task
- [ ] Confirm task appears in the task list
- [ ] Confirm task shows as "Assigned" to Test User in Test Project

## Phase 6: Desktop Client Installation

**Checkpoint:** Official Cattr desktop client installed on a user machine.

- [ ] Download desktop client from https://cattr.app/download
- [ ] Install on user machine (Windows, macOS, or Linux)
- [ ] Launch the application

## Phase 7: Desktop Client Connection

**Checkpoint:** Desktop client successfully connects to the Cattr server.

- [ ] Client prompts for server URL on first launch
- [ ] Enter exactly: `http://172.16.70.66/api` (with `/api` suffix)
- [ ] Client validates the server
- [ ] Client shows login prompt
- [ ] Log in with test user: `testuser@cattr.local` / `TestPass#2025!`
- [ ] After login, task list appears
- [ ] **Sample Task** is visible in the task list
- [ ] Task status shows "Assigned"
- [ ] Project name shows "Test Project"

## Phase 8: Time Tracking Validation

**Checkpoint:** Successfully track time on a task and see data in backend.

### Desktop client tracking

- [ ] In the desktop client, click **Start** on Sample Task
- [ ] Timer increments (shows elapsed time)
- [ ] Let it run for at least 1 minute
- [ ] Click **Stop**
- [ ] (Optional) Add notes if prompted
- [ ] Submit the tracked time
- [ ] Desktop confirms the time was recorded

### Verify in backend

- [ ] Open web UI: http://172.16.70.66
- [ ] Log in as admin
- [ ] Click **Time Intervals**
- [ ] A new entry appears with:
  - User: Test User
  - Task: Sample Task
  - Project: Test Project
  - Duration: The time you just tracked (e.g., 1 minute)
  - Date: Today

## Phase 9: Screenshots (if enabled)

**Checkpoint (optional):** Screenshots captured during tracking are visible.

- [ ] During tracking, the desktop client captures screenshots
- [ ] After stopping tracking, check the web UI for screenshots:
  - Click **Projects** → Test Project → **Screenshots**
  - Or click **Time Intervals** → the interval row → view screenshots
- [ ] Screenshots from the tracked session appear
- [ ] Each screenshot shows the timestamp and activity

**If screenshots don't appear:**
- Check **Projects** → Test Project → settings to confirm screenshots are enabled
- Check **Users** → Test User → settings to confirm screenshots are enabled for that user
- Check `/app/storage/screenshots` directory has files in the container

## Phase 10: Report Generation

**Checkpoint:** Generate a report of tracked time and export it.

### Generate dashboard report

- [ ] Open web UI: http://172.16.70.66
- [ ] Log in as admin
- [ ] Click **Reports** → **Dashboard**
- [ ] Set filters:
  - **Date From:** Today (e.g., 2026-03-30)
  - **Date To:** Today
  - **User:** Test User
  - **Project:** Test Project
- [ ] Click **Generate Report** or **Refresh**
- [ ] The report displays:
  - Task: Sample Task
  - Duration: The exact time you tracked
  - User: Test User
  - Project: Test Project
  - Date: Today

### Export report to CSV

- [ ] Click **Export**
- [ ] Select format: **CSV**
- [ ] Browser downloads a CSV file
- [ ] Open the CSV file (in Excel, spreadsheet, or text editor)
- [ ] File contains:
  - Column headers (Task, User, Project, Duration, Date, etc.)
  - One row with the tracked interval data
  - The duration matches what you tracked (e.g., 1 minute 23 seconds)

### Export to other formats (optional)

- [ ] Try exporting as **XLSX** (Excel format)
- [ ] Try exporting as **PDF**
- [ ] Confirm all formats work and show the same data

## Phase 11: Multi-User Validation (optional)

**Checkpoint:** Confirm setup works with multiple users.

- [ ] Create a second test user (e.g., `anotheruser@cattr.local`)
- [ ] Create a different task and assign it to the second user
- [ ] Install the desktop client on another machine (or use the same machine with a different user account)
- [ ] Connect the desktop client to http://172.16.70.66/api
- [ ] Log in with the second user
- [ ] Confirm the second user sees only their assigned task(s)
- [ ] Track time with the second user
- [ ] Confirm both users' tracked times appear in reports when filtering by user

## Phase 12: Final Production Readiness Checks

### Persistence & Data Safety

- [ ] Confirm `./data` volume persists MySQL data
- [ ] Confirm `./storage` volume persists app uploads (screenshots, files, reports)
- [ ] Test container restart: `docker-compose restart app`
  - [ ] Web UI still accessible
  - [ ] All data still present
- [ ] Test full stack restart: `docker-compose down && docker-compose up -d`
  - [ ] All containers come back up
  - [ ] Admin login still works
  - [ ] All test data still present

### Logging & Monitoring

- [ ] Run `docker-compose logs app` and confirm no ERROR entries
- [ ] Run `docker-compose logs db` and confirm database is healthy
- [ ] Confirm app started with message: "Server running…"
- [ ] Confirm no migration or seeding errors in logs

### Security Baseline

- [ ] MySQL port 3306 is NOT exposed to host network
- [ ] Only ports 22 (SSH) and 80 (HTTP) are open in UFW
- [ ] Admin password is strong and unique: `CattrAdmin#2025!`
- [ ] Test user password is strong: `TestPass#2025!`
- [ ] `.env` file contains no placeholder values
- [ ] `.env` file is NOT committed to version control

### Firewall

- [ ] `sudo ufw status` shows:
  - `22/tcp ALLOW`
  - `80/tcp ALLOW`
- [ ] Port 443 (HTTPS) is NOT open (HTTP first-pass)
- [ ] Port 3306 (MySQL) is NOT open

### Storage

- [ ] Run: `df -h ./data ./storage` to confirm available space
- [ ] Both directories have sufficient free space (at least 10GB recommended)
- [ ] Permissions are correct: `ls -la | grep data storage`

## Phase 13: Documentation & Runbook

- [ ] All setup steps documented in [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md)
- [ ] Desktop client guide documented in [DESKTOP_CLIENT_GUIDE.md](DESKTOP_CLIENT_GUIDE.md)
- [ ] Quick-start guide documented in [QUICKSTART_SETUP.md](QUICKSTART_SETUP.md)
- [ ] Troubleshooting matrix in [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md#6-troubleshooting)
- [ ] Safe restart commands documented
- [ ] Team knows how to access the system and where documentation is

## 🎉 Complete!

If you have checked all boxes, your Cattr deployment is **fully functional and ready for production use**.

### Next Recommended Actions

1. **Configure domain name:**
   - Update DNS: `tracking.pinnaclemisr.com` → `172.16.70.66`
   - Update `.env`: `APP_URL=http://tracking.pinnaclemisr.com`
   - Update desktop client server URL (users will use domain instead of IP)

2. **Add SSL/TLS (HTTPS):**
   - Use Let's Encrypt for free certificates
   - Add reverse proxy (Nginx) with SSL terminator
   - Update APP_URL to `https://...`

3. **Enable SMTP (email invitations):**
   - Configure mail server settings in admin panel
   - Enable invitation-based onboarding instead of direct user creation

4. **Set up automated backups:**
   - Backup the `./data` directory (MySQL)
   - Backup the `./storage` directory (user uploads and reports)
   - Store backups off-site

5. **Monitor resource usage:**
   - Set up disk space monitoring (warn at 80% full)
   - Monitor Docker logs for errors
   - Monitor MySQL performance as user count grows

6. **Plan for growth:**
   - Document how to add more users
   - Document how to archive old projects
   - Plan database maintenance (cleanup old time intervals if needed)

Congratulations! You now have a working Cattr self-hosted instance. 🎊
