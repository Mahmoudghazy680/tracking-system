# Desktop Client Setup & Time Tracking Guide

This guide covers how to install the Cattr desktop client on user devices and connect it to your server at `172.16.70.66`.

## Prerequisites

Before connecting the desktop client, confirm:
✅ The web UI is accessible at http://172.16.70.66  
✅ You're logged in as admin  
✅ You have created:
  - A test user (e.g., `testuser@cattr.local`)
  - A project (e.g., "Test Project")
  - A task assigned to that user (e.g., "Sample Task")

## 1. Install the Desktop Client

### Windows

1. Download the Cattr desktop client from: https://cattr.app/download or from your organization's software repository.
2. Run the installer (`.exe` file).
3. Follow the installation wizard.
4. Launch the application.

### macOS

1. Download the Cattr desktop client from: https://cattr.app/download
2. Open the downloaded `.dmg` file.
3. Drag the Cattr icon to the **Applications** folder.
4. Open **Applications** and double-click **Cattr**.

### Linux

1. Download the Cattr AppImage or .deb from: https://cattr.app/download
2. If using AppImage:
   ```bash
   chmod +x Cattr-*.AppImage
   ./Cattr-*.AppImage
   ```
3. If using .deb:
   ```bash
   sudo apt install ./Cattr-*.deb
   cattr
   ```

## 2. Connect to Your Cattr Server

### Step 1: Enter the server URL

When the desktop client launches for the first time, you'll see a **Server URL** input field.

**Important:** Use one of these URL formats:

```
http://172.16.70.66
```

or

```
http://172.16.70.66/api/
```

**Do NOT use any of these:**
- ❌ `http://172.16.70.66/api` (without trailing slash can fail hostname validation on some desktop builds)
- ❌ `http://tracking.pinnaclemisr.com/api` (domain not yet configured)
- ❌ `192.168.x.x/api` (wrong IP address)

### Step 2: Click "Verify" or "Next"

The client will contact the server and validate that it's a Cattr instance.

**If validation fails:**
- Confirm you can ping `172.16.70.66` from your machine.
- Confirm you have a network path to the VM.
- Check that the `/api` suffix is included.
- Run `docker-compose ps` on the VM to confirm containers are running.

### Step 3: Log in with the test user

When the validation succeeds, you'll see a login prompt.

**Enter:**
- **Email:** `testuser@cattr.local`
- **Password:** `TestPass#2025!`

(Use whatever credentials you created when you set up the test user in the web UI.)

### Step 4: Verify the task list

After login, you should see a task list. You should see:
- **Sample Task** (or whatever task name you created)
- Status showing "Assigned"
- Project name: "Test Project"

**If you don't see any tasks:**
- Confirm the task is assigned to the test user (check in web UI: **Tasks** → verify assignment)
- Confirm the task status is Active (not archived or closed)
- Refresh the client by logging out and back in

## 3. Track Time on a Task

Once you see the task list:

### Start tracking

1. Find **Sample Task** in the list.
2. Click the **Start** button (triangle/play icon).
3. The timer should start running.
4. At the top of the window, you should see elapsed time (e.g., "0:00:30").

### Stop tracking

1. After at least 1 minute of tracking, click the **Stop** button (square icon).
2. The client will ask for any activity notes (optional).
3. Submit the tracked time.

### Verify the interval was recorded

1. Open http://172.16.70.66 in a browser.
2. Log in as admin.
3. Click **Time Intervals** (left sidebar).
4. You should see a new entry with:
   - **User:** Test User
   - **Task:** Sample Task
   - **Duration:** The elapsed time you just tracked
   - **Date:** Today

## 4. Generate a Report

### From the web UI

1. Open http://172.16.70.66 in a browser.
2. Log in as admin.
3. Click **Reports** (left sidebar).
4. Click **Dashboard**.
5. In the filters:
   - Set **Date From:** Today
   - Set **Date To:** Today
   - Select **User:** Test User
   - Select **Project:** Test Project
6. Click **Generate Report**.
7. You should see:
   - The tracked task
   - The elapsed time
   - Any notes you added

### Export to CSV

1. After generating the report, click **Export**.
2. Choose **CSV**.
3. You should get a downloadable file with the tracked data.

## Common Issues & Troubleshooting

| Issue | Solution |
|-------|----------|
| Client says "Cannot connect" | Use `http://172.16.70.66` or `http://172.16.70.66/api/`. Verify network connectivity to 172.16.70.66. |
| Client says "Invalid credentials" | Double-check email and password match exactly. Confirm the user exists in the web UI. |
| No tasks appear after login | Confirm the task is assigned to the logged-in user. Check task status is Active. Try logging out and back in. |
| Tracked time doesn't appear in reports | Wait a few seconds for the backend to process. Refresh the report. Confirm the user and project filters match the task. |
| Desktop client can't be installed | Download from https://cattr.app/download. Check your device has write permissions. Try a different download method. |
| Screenshots don't appear | Check in **Admin** → **Projects** → project settings to confirm screenshots are enabled. Check user settings under **Users** → user settings. |
| App monitoring is enabled but no software/programs appear | Open desktop logs and check for `ffi_bindings.node is not a valid Win32 application`. If present: uninstall client, remove local app directory, reinstall the latest Windows installer matching the OS architecture (`x64` recommended), then retest task tracking for 2-3 minutes. |

## Multi-user setup

Once you confirm the setup works with one test user:

1. Create additional users in the web UI (**Users** → **+ New User**)
2. Create tasks and assign them to those users
3. Each user logs into the desktop client with their own credentials
4. Each user can track time independently

## Admin considerations

Only admin users can:
- Create and manage users, projects, and tasks
- View all reports and tracked time
- Change system settings
- Create backups

Regular users can only:
- Track time on their assigned tasks
- View their own reports (if enabled)
- Change their own password

## Network requirements

- The desktop client needs **outbound HTTPS/HTTP access** to 172.16.70.66 on **port 80** (HTTP)
- Firewall rules on the VM already allow this (checked during setup)
- If users are on a different network, they need network connectivity to the VM
- Once DNS is configured, users can use the domain name instead of the IP address

## Screenshot capture (optional)

The Cattr desktop client can capture screenshots of activity. To use this:

1. During tracking, the client periodically captures screenshots
2. These are stored on the server and viewable in the web UI
3. Users can disable screenshots in their settings if desired
4. Admin can disable screenshots system-wide or per-project

For this first pass, screenshots are enabled by default.

## Next steps

After confirming the first tracking session works:

1. **Test multi-user tracking** with a second user
2. **Test reporting** with multiple task/user combinations
3. **Configure domain name** (update DNS for `tracking.pinnaclemisr.com`)
4. **Add SSL/HTTPS** (optional, recommended for production)
5. **Backup database** regularly
6. **Monitor disk usage** of the `/data` and `/storage` directories

## Getting help

**Server-side issues:**
- Run `docker-compose ps` on the VM to check container status
- Run `docker-compose logs app` to see app errors
- Run `docker-compose logs db` to see database errors

**Client-side issues:**
- Check the client's log files (usually in `~/.cattr/logs` or application data directory)
- Confirm the server URL and credentials are correct
- Try installing the latest version from https://cattr.app/download
