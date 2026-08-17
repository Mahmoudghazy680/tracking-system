# Cattr Quickstart Setup

Your Cattr server is running at **http://172.16.70.66**.

## Current Status

✅ Docker stack is running  
✅ Admin account created  
✅ API is responding  
✅ Web UI is accessible  
❌ Test data (users, projects, tasks) not yet created  
❌ Desktop client not yet connected  

## What the 404 at /api means

If you visit `http://172.16.70.66/api` in your browser, you see a 404. This is **normal and expected**. The `/api` endpoint is for programmatic access (REST calls), not for web browsing. The browser expects HTML; the API returns JSON.

The API itself is working fine. You just tested it from the command line with curl.

## Next: Create minimum working data

You need at least:
- 1 normal user (for tracking)
- 1 project
- 1 task assigned to that user

### Option A: From the web UI (simplest)

1. Open http://172.16.70.66 in your browser
2. Log in as admin: `admin@tracking.pinnaclemisr.com` / `CattrAdmin#2025!`
3. Go to **Users** → create a new user
4. Go to **Projects** → create a new project
5. Go to **Tasks** → create a task and assign it to the user

### Option B: Via API script

Run this on the VM to create everything at once:

```bash
cd /home/bsrd/cattr
bash scripts/create-test-data.sh
```

The script will:
- Log in as admin
- Create a test user: `testuser@cattr.local` / `TestPass#2025!`
- Create a test project: "Test Project"
- Create a test task: "Sample Task"
- Assign the task to the test user

## Step 1: Create test data

### Manual steps (recommended for first-time setup):

**Create a test user:**

1. Open http://172.16.70.66 in your browser
2. Log in as admin
3. Click **Users** (left sidebar)
4. Click **+ New User**
5. Fill in:
   - **Full Name:** Test User
   - **Email:** testuser@cattr.local
   - **Password:** TestPass#2025!
   - **Confirm Password:** TestPass#2025!
6. Click **Save**

**Create a project:**

1. Click **Projects** (left sidebar)
2. Click **+ New Project**
3. Fill in:
   - **Name:** Test Project
4. Click **Save**

**Create a task:**

1. Click **Tasks** (left sidebar)
2. Click **+ New Task**
3. Fill in:
   - **Name:** Sample Task
   - **Project:** Test Project (from dropdown)
   - **Assigned To:** Test User (from dropdown)
4. Click **Save**

You should now see the task under **Tasks**. If you don't see it, click **Filter** and make sure you're filtering to see all tasks or the Test Project specifically.

## Step 2: Install the desktop client

1. Download the official Cattr desktop client from https://cattr.app/ (or use your organization's binary).
2. Install it on your user machine.

## Step 3: Connect the desktop client

1. Launch the desktop client.
2. When prompted for the server URL, enter exactly:

```
http://172.16.70.66/api
```

**Do NOT use:**
- `http://172.16.70.66` (missing `/api`)
- `http://tracking.pinnaclemisr.com/api` (DNS not set up yet)
- `http://172.16.70.66/` (root, not the API)

3. Click **Next** or **Verify**.

The client will validate that the server is a Cattr instance and show a login prompt.

4. Log in with the test user credentials:
   - Email: `testuser@cattr.local`
   - Password: `TestPass#2025!`

5. You should see the test task in the task list.

## Step 4: Test time tracking

1. In the desktop client, find the **Sample Task**.
2. Click **Start** to begin tracking.
3. Let it run for at least 30 seconds.
4. Click **Stop**.

## Step 5: Verify in the web UI

1. Open http://172.16.70.66 in your browser.
2. Log in as admin.
3. Go to **Reports** → **Dashboard**.
4. You should see the tracked time for the test user.

## Step 6: Export a report

1. In the **Dashboard** report:
2. Set the date range to today.
3. Filter to the test user.
4. Click **Export** → **CSV**.
5. You should get a downloadable file with the tracked interval.

## If something doesn't work

**Desktop client can't connect:**
- Confirm you used `http://172.16.70.66/api` (with `/api`).
- Confirm you can ping 172.16.70.66 from your machine.
- Check the app logs: `docker-compose logs --tail=50 app`

**No tasks show in the desktop client:**
- Confirm the test user exists: http://172.16.70.66 → **Users**
- Confirm the task is assigned to the test user: http://172.16.70.66 → **Tasks**
- Confirm the task has a due date or is in an active status

**Reports show no data:**
- Confirm a tracked interval exists: http://172.16.70.66 → **Time Intervals**
- Confirm the user logged tracked time, not another user

## Credentials summary

**Admin (for web UI only):**
- Email: `admin@tracking.pinnaclemisr.com`
- Password: `CattrAdmin#2025!`
- Purpose: Create users, projects, tasks, view all reports

**Test user (for desktop client):**
- Email: `testuser@cattr.local`
- Password: `TestPass#2025!`
- Purpose: Track time and see assigned tasks in the desktop app

**Server URLs:**
- **Web UI:** http://172.16.70.66
- **Desktop API:** http://172.16.70.66/api
- **Domain:** tracking.pinnaclemisr.com (not yet active; waiting for DNS)
