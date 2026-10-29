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

## Build and release

1. Install locked PHP dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader`.
2. Install locked frontend dependencies and build assets with `npm ci` and `npm run build`.
3. Run `php artisan migrate --force` after taking a backup.
4. For the initial administrator only, set a unique `ADMIN_SEED_PASSWORD` of at least 12 characters in the secret store, then run `php artisan db:seed --class="Database\Seeders\DatabaseSeeder" --force`. In production the seeder creates the administrator but skips the local demonstration beneficiaries and inventory. Rotate this setup credential after initial access.
5. Run `php artisan optimize` and configure the web server document root to Laravel's `public` directory. Serve the site and API only over HTTPS.
6. Configure a process monitor for queue workers if background jobs are used, and configure the Laravel scheduler for token pruning.

## Before exposing it to users

- Verify administrator, staff, volunteer, and beneficiary access with separate accounts; confirm volunteers see only the minimum beneficiary details needed for check-in.
- Verify mobile login throttling, token expiry, logout, password change, and token revocation.
- Verify delivery receiving, stock reservation, release, and audit history using a staging database first.
- Confirm error pages do not expose stack traces, logs are access-controlled, and the `/up` health endpoint is monitored.
- Complete a dependency vulnerability review and a privacy review before importing real beneficiary data.
