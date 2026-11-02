# Relief Inventory Tracker

Relief Inventory Tracker manages beneficiaries, relief packages, inventory, deliveries, distributions, QR check-in, and staff announcements. The repository contains the Laravel web application and API. The beneficiary mobile app source is maintained separately, so update and verify its API integration in that app's repository.

## Requirements

- PHP version and extensions required by `composer.json`
- Composer
- Node.js and npm
- MySQL or another database supported by Laravel

## Local setup

1. Install PHP dependencies with `composer install`.
2. Copy `.env.example` to `.env` and set the local database connection and `APP_URL`. Keep `.env` private; it contains credentials and application secrets.
3. Generate the local application key with `php artisan key:generate`.
4. Create the configured database, then run `php artisan migrate`.
5. Install and build frontend assets with `npm install` and `npm run build` (use `npm run dev` while developing).
6. Start the app with `php artisan serve`, or use the project's configured local web server.

For local demonstration data, review `DatabaseSeeder` before running `php artisan db:seed`. For an admin-only setup, set a unique `ADMIN_SEED_PASSWORD` in the local secret environment and run `php artisan db:seed --class="Database\\Seeders\\AdminOnlySeeder"`. Do not put that password in source control or share it in screenshots or logs.

## Main workflows

- **Admin:** manage users, beneficiaries, inventory, packages, deliveries, distributions, QR codes, and announcements.
- **Staff and volunteers:** use authorized staff views and QR check-in workflows for distribution handoff.
- **Beneficiary API:** mobile clients authenticate through the API and retrieve account, package, distribution, and notification information. Beneficiary resources include an optional Sitio/Purok value where the signed-in role is allowed to see it. Keep the mobile client's API base URL, response handling, and cached account details aligned with the API resources in this repository.
- **Announcements:** admins and staff can publish from the staff portal. Authenticated mobile clients can fetch published announcements with `GET /api/announcements`; the response contains `data` items with `id`, `title`, `body`, `author`, and ISO-8601 `published_at` fields (up to the latest 50).
- **Distribution windows:** pending assignments reserve stock. A pending assignment past its deadline is cancelled by the scheduled expiry command, reserved stock is restored, and the beneficiary is notified. A distribution is not confirmed as picked up until staff complete the QR check-in flow.
- **QR replacement:** reporting a QR as lost and replacing it revokes the prior QR credential. Treat exported QR images as private beneficiary credentials.

## Scheduler

Laravel must invoke its scheduler every minute for overdue distribution cleanup to run. The project schedule also prunes expired Sanctum tokens daily. On a local Windows Laragon machine, create a Task Scheduler task with a one-minute repeat trigger and this action, which starts the scheduler without opening a console window:

```text
wscript.exe "<project path>\scripts\run-laravel-scheduler.vbs" "<full path to php.exe>"
```

Production servers should use their process manager or cron instead; for cron, run `php /absolute/path/to/project/artisan schedule:run` once per minute. See [DEPLOYMENT.md](DEPLOYMENT.md) for the release checklist. Check `php artisan schedule:list` to confirm the overdue distribution command appears every minute.

## Development and release workflow

Use [PILOT_GUIDE.md](PILOT_GUIDE.md) for the barangay staging pilot and its one-claim-per-day acceptance check. Use [DEPLOYMENT.md](DEPLOYMENT.md) for controlled Render migrations and production release steps.

- Make changes on a reviewed branch and inspect `git status` and `git diff` before committing.
- Keep `.env`, database exports, logs, and beneficiary QR images out of public source control.
- Review generated files separately from application code. QR images may be beneficiary-specific credentials and should only be transferred through an approved, access-controlled process.
- Install locked dependencies (`composer install` and `npm ci`) and build assets (`npm run build`) for release.
- Back up the production database before migrations and follow the checks in [DEPLOYMENT.md](DEPLOYMENT.md).

## Useful commands

```sh
php artisan route:list
php artisan schedule:list
php artisan migrate:status
```

Run the project test suite with `php artisan test` when requested or as part of the team's release process.
