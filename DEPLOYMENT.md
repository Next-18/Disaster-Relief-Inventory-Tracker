# Production deployment checklist

This project is currently configured for local development. Do not copy the local `.env` into a public server unchanged.

## Before the first release

- Provision a production database and a dedicated database account with only the permissions the application needs. Do not use MySQL `root`.
- Set production environment values in the server's secret store or `.env`: `APP_ENV=production`, `APP_DEBUG=false`, the public `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, and a production-only `APP_KEY`.
- Never commit or share `.env`, `APP_KEY`, database passwords, or `ADMIN_SEED_PASSWORD`. Generate the production key once; do not regenerate it during routine releases.
- Set `SANCTUM_TOKEN_EXPIRATION` to the desired token lifetime in minutes (the default in this project is 43200 minutes / 30 days). Configure the host scheduler to run Laravel's scheduler at least once per minute so expired-token pruning and overdue distribution cleanup run.
- New passwords must be at least 12 characters and include uppercase, lowercase, and numeric characters. Existing passwords remain usable until changed.
- Point the mobile app's API base URL to the production HTTPS API. Keep the local LAN URL for local development only.
- Create encrypted, access-controlled database backups and verify a restore before storing real beneficiary records.
- Review the hosting provider's privacy, data retention, access control, and incident response arrangements with the barangay's responsible privacy officer.

## Beneficiary QR storage

- Configure the private `qr_codes` storage disk using the Supabase S3-compatible Storage API.
- Set these environment variables in Render:
  - `QR_STORAGE_ACCESS_KEY_ID`
  - `QR_STORAGE_SECRET_ACCESS_KEY`
  - `QR_STORAGE_REGION`
  - `QR_STORAGE_BUCKET`
  - `QR_STORAGE_ENDPOINT`
  - `QR_STORAGE_USE_PATH_STYLE_ENDPOINT=true`
- Use the exact S3 endpoint and region shown in your Supabase Storage S3 settings. Do not use Cloudflare R2 credentials or endpoint values.
- Keep the `relieftrack-private-qr` bucket private. Never expose the S3 secret key or storage credentials in Git, frontend code, or public URLs.
- New QR SVGs are stored under random object keys in the private bucket. Admin images and downloads are streamed through authenticated, administrator-only Laravel routes. No public object URL is created.
- Existing database QR filenames still refer to legacy `public/qr-codes` files and will not be served by the new protected routes. Back up the database and legacy files, then use the admin lost/replacement workflow to revoke and regenerate each existing QR. Verify the new QR and scan flow before removing any legacy copies.
- The legacy files are intentionally not migrated or deleted by this code change. The repository tracks QR files; `.dockerignore` excludes `public/qr-codes` from future Docker build contexts, but it does not remove files from Git history or make an existing public deployment private. Handle public copies and Git history as a separate, approved cleanup after backup and credential rotation.
## Build and release

### Render web service

1. Connect the GitHub repository to Render and create a Docker web service using the repository's root `Dockerfile`.
2. Configure the production environment variables in Render:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_KEY` with a production-only generated key
   - `APP_URL` with the Render HTTPS URL
   - `DB_CONNECTION=pgsql`
   - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_SSLMODE=require` using the Supabase connection details
   - `SESSION_DRIVER=database`
   - `SESSION_LIFETIME=120`
   - `SESSION_ENCRYPT=true`
   - `SESSION_SECURE_COOKIE=true`
   - `CACHE_STORE=database`
   - `QUEUE_CONNECTION=sync` until a queue worker is configured
   - `FILESYSTEM_DISK=local`
   - All required `QR_STORAGE_*` variables listed above
3. Let Render build the Dockerfile. It installs production Composer dependencies and builds the Vue/Vite assets. Do not configure a separate manual Composer or npm build command for this Docker deployment.
4. The container startup command only prepares Laravel and starts Apache. It does not migrate or seed the database. Configure `php artisan migrate --force` as a Render pre-deploy command when that feature is available. Otherwise, run it as a controlled one-off command against the production database after taking a backup and before sending traffic to code that requires the new schema.

### Database and initial administrator

5. Before migrating, back up the production database and inspect its existing schema and migration history. Review each pending migration for compatibility before running `php artisan migrate --force`.
6. For the initial administrator only, set a unique `ADMIN_SEED_PASSWORD` of at least 12 characters in Render's environment settings. Then run:

   `php artisan db:seed --class="Database\Seeders\AdminOnlySeeder" --force`

   The dedicated seeder creates the administrator only if the account does not already exist. It does not reset an existing admin password. Remove the setup credential from Render after initial access is established. Do not run the demonstration `DatabaseSeeder` against production.

### Barangay staging pilot

Before loading real beneficiary records, create a separate Render staging web service and a separate staging database. Configure staging with `APP_ENV=staging`, `APP_DEBUG=false`, its own `APP_KEY`, its own database credentials, its staging `APP_URL`, secure cookies, and a unique `ADMIN_SEED_PASSWORD`. Use a separate private QR storage bucket for staging so sample QR images cannot mix with production credentials.

Run migrations against staging, then seed only that staging database with sample records:

`php artisan db:seed --force`

Because staging is not production, `DatabaseSeeder` creates the local demo administrator and demonstration data. Never point this command at the production database. Use the staging pilot to confirm that the optional Sitio/Purok field is usable, the one-released-package-per-beneficiary-per-local-day rule matches barangay policy, and delivery, reservation, check-in, cancellation, exports, and audit history work for staff roles.

The current claim rule is enforced in `DistributionStockService`: a beneficiary may have only one released claim on a local calendar date. The service also prevents duplicate assignments of the same package to the same beneficiary for that date. Keep this rule for the pilot only if the barangay approves it; a per-event policy would require a separate event/assistance-round identifier and a code change.

### Scheduler

7. Create a separate Render Cron Job using the same application repository, Dockerfile, and required production environment variables.
8. Configure its command as:

   `php artisan package:discover --ansi && php artisan config:cache && php artisan schedule:run`

   Set the Cron Job schedule to run once per minute. This runs overdue distribution cleanup every minute and expired Sanctum token pruning daily.

9. Keep `QUEUE_CONNECTION=sync` while the application has no background jobs requiring a worker. If queued jobs are introduced later, configure and monitor a separate worker.

10. Serve the web application and API exclusively over HTTPS.
## Before exposing it to users

- Verify administrator, staff, volunteer, and beneficiary access with separate accounts; confirm volunteers see only the minimum beneficiary details needed for check-in.
- Verify mobile login throttling, token expiry, logout, password change, and token revocation.
- Verify delivery receiving, stock reservation, release, and audit history using a staging database first.
- Confirm that the barangay has approved the claim rule and that the pilot site has working internet for online QR verification and check-in.
- Confirm error pages do not expose stack traces, logs are access-controlled, and the `/up` health endpoint is monitored.
- Complete and obtain privacy officer approval for the formal PIA, privacy notice, and retention schedule before importing real beneficiary data.
