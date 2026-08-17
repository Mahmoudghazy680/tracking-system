# First Report Checklist

Use this after the stack is reachable and the admin account can log in.

## Preconditions

- The site opens at http://tracking.pinnaclemisr.com/
- `make validate` succeeds
- At least one normal user exists
- At least one project exists
- At least one task is assigned to that user
- The user has tracked enough time to create at least one interval

## Fastest path

1. Log in to the web UI as admin.
2. Open the dashboard report view.
3. Set the date range to today.
4. Filter to the test user and test project.
5. Export CSV first.

## Expected result

- A downloadable report file is generated.
- The file contains the tracked interval for the selected user and project.

## If export fails

- Confirm tracking data exists in the backend.
- Confirm the admin account has report access.
- Check report capabilities with `curl -i http://tracking.pinnaclemisr.com/api/about/reports`.
- Check app logs with `docker-compose logs --tail=100 app`.
