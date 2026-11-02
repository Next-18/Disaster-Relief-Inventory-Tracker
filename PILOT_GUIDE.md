# Barangay staging pilot

Use this guide for a sample-data pilot of one barangay. Do not load real beneficiary data until the privacy officer approves the formal PIA and the production controls in `PRIVACY_REVIEW.md` are verified.

## Staging setup

1. Create a separate Render web service and a separate PostgreSQL database for staging. Keep them separate from production.
2. Set staging environment variables in Render: `APP_ENV=staging`, `APP_DEBUG=false`, a staging-only `APP_KEY`, staging `APP_URL`, staging database credentials, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, and a unique `ADMIN_SEED_PASSWORD`.
3. Configure a separate private QR storage bucket for staging. Never reuse the production bucket or production QR credentials.
4. Deploy the application. Run `php artisan migrate --force` against the staging database, then run `php artisan db:seed --force` against that same staging database. The seeder supplies sample beneficiaries and inventory only outside production.
5. Set the Barangay, Municipality, and Province in Admin Settings. Use only fictional/sample records during the pilot.

## Pilot checks

- Create, edit, search, view, and export beneficiaries with and without a Sitio/Purok value.
- Confirm staff can see Sitio/Purok during an authorized QR check-in, while beneficiary API responses remain role-limited.
- Receive stock, create package recipes, assign pending packages, release by QR, and confirm the inventory changes once.
- Confirm the current policy: one released package per beneficiary per local calendar date; duplicate assignments of the same package for that beneficiary and date are rejected. Get explicit barangay approval before using this rule for a real relief event.
- Test cancellation and deadline expiry; check stock restoration and audit records.
- Confirm staff and volunteer permissions with separate accounts.
- Verify the Render scheduler runs every minute and verify a database backup can be restored to a separate staging database.
- Confirm the distribution site has a working internet connection for live QR verification and recording.

## Production release gate

Move to production only after the barangay accepts the claim rule, the privacy officer approves the PIA and privacy notice, retention periods are documented, production storage and credentials are verified, a production backup has been restore-tested, and legacy QR exposure has been reviewed. Keep sample data in staging only.
