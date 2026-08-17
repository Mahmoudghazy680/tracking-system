# Trakcker Deployment Bundle

This workspace contains a first-pass Docker deployment bundle for Cattr using:

- tracking.pinnaclemisr.com
- 172.16.70.66
- official prebuilt Cattr app image
- Percona 8.0

## Files

- `IMPLEMENTATION_PLAN.md`: complete implementation guide in the requested 1-7 structure
- `docker-compose.yml`: two-service stack for app and database
- `.env.example`: environment template with required variables
- `RUNBOOK.md`: end-to-end manual runbook for Ubuntu deployment
- `CHECKLIST.md`: manual UI, desktop, tracking, and report acceptance checklist
- `FIRST_REPORT_CHECKLIST.md`: shortest path to the first successful report export
- `Makefile`: shortcuts for common deployment operations
- `scripts/`: helper scripts for DNS validation, Docker install, host prep, env validation, stack readiness, bootstrap, deployment, diagnostics, and API validation

## Quick start

1. Copy this bundle to the Ubuntu VM.
2. Run `make init-env`.
3. Edit `.env` and replace the placeholder secrets.
4. Run `make env-check`.
5. Run `make dns-check`.
6. Run `sudo make install-docker`.
7. Run `sudo make prepare-host`.
8. Run `sudo make fix-db-perms`.
9. Run `make deploy`.
10. Complete the manual acceptance items in `CHECKLIST.md`.

If the site is unreachable, run `make diagnose` on the VM.

If you need the full written guide in one place, use `IMPLEMENTATION_PLAN.md`.

For the full flow and fallback commands, use `RUNBOOK.md`.
