# Manual Acceptance Checklist

Use this checklist after `make deploy` succeeds.

## 1. Browser and API

- Open http://tracking.pinnaclemisr.com/ and confirm the Tracker login page loads.
- Run `make validate` and confirm API status, admin login, and `/api/auth/me` succeed.

## 2. Admin web UI

- Log in with the admin account from `.env`.
- Confirm the admin can access company settings, users, projects, tasks, and reports.

## 3. Minimum working data

- Create one normal user.
- Create one project.
- Create one task assigned to that user.
- Confirm the user is active.

## 4. Desktop connection

- Install the official desktop client on a user machine.
- Set the server URL to `http://tracking.pinnaclemisr.com/api`.
- Confirm the client accepts the hostname and allows login.

## 5. Tracking validation

- Log in as the normal user.
- Confirm the assigned task is visible.
- Start tracking and wait long enough for at least one interval to be created.
- Stop tracking.
- Confirm the interval appears in the backend.

## 6. Screenshot validation

- If screenshots are enabled, confirm screenshots are visible from the web UI.
- Do not use deprecated legacy screenshot-create routes in any follow-up automation.

## 7. Report validation

- Export a dashboard report for today's date range.
- Start with CSV.
- Confirm the exported file includes the tracked interval.

## 8. Completion criteria

- The site is reachable by domain name.
- API validation succeeds.
- Admin login works.
- Normal user login works in the desktop client.
- One tracked interval is visible in the backend.
- One report export completes successfully.
