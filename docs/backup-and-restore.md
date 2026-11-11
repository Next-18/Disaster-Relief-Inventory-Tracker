# Database backup and restore runbook

Use this procedure for the production PostgreSQL database and private QR storage.
Run commands from a trusted operator workstation with `pg_dump`, `pg_restore`,
and GnuPG installed. Do not run a restore against production as a test.

## Configure backups

1. Enable the database provider's automated backups or point-in-time recovery for
   the production database. Confirm the retention period and recovery window in
   the provider console; these depend on the database plan.
2. Keep an encrypted logical export outside the application container before
   every migration and on a regular schedule. Render's web container filesystem
   is not a durable backup destination.
3. Store the database encryption passphrase in an approved secrets vault,
   separately from the backup files. Limit backup access to named operators.
4. Back up or enable object versioning for the private QR storage bucket too.
   A PostgreSQL dump does not contain those QR objects.
5. Set a retention policy with the barangay data owner. A practical starting
   point is 30 daily copies and 12 month-end copies, subject to approved privacy
   and retention rules.

## Create an encrypted logical backup

Set `DATABASE_URL` only in the trusted workstation's secret environment. Avoid
putting the database URL or password directly in shell history.

```sh
backup_name="relieftrack-$(date -u +%Y%m%d-%H%M%S).dump"
pg_dump --format=custom --no-owner --no-acl --file="$backup_name" "$DATABASE_URL"
gpg --symmetric --cipher-algo AES256 --output "$backup_name.gpg" "$backup_name"
gpg --decrypt "$backup_name.gpg" | pg_restore --list - >/dev/null
```

After verifying the encrypted copy and uploading it to the approved restricted
backup store, securely remove the unencrypted dump from the workstation. Record
the UTC date, database identifier, file size, and verification result without
recording credentials or the encryption passphrase.

## Test a restore into staging

Create an empty staging database first, and set `RESTORE_DATABASE_URL` to that
database only. Decrypt the backup into `pg_restore` without writing a plaintext
dump file:

```sh
gpg --decrypt "$backup_name.gpg" \
  | pg_restore --clean --if-exists --no-owner --no-acl \
      --dbname="$RESTORE_DATABASE_URL" -
```

Then verify the application can connect, the expected barangays and recent
records exist, administrator sign-in works, and a sample QR object can be
retrieved from the separately restored or versioned private bucket. Record the
restore duration and issues. Keep staging isolated from production users and
mobile clients.

## Before a production migration or recovery

1. Take and verify a fresh encrypted backup.
2. Confirm the target database and migration or incident scope with the
   responsible operator.
3. Restore to a separate database first when practical and check the result.
4. For an actual production recovery, use the provider's documented recovery
   process, then point the application at the restored database through its
   secret settings. Never overwrite the only good backup.
5. After recovery, verify authentication, barangay scoping, inventory,
   distributions, audit records, and private QR access before reopening traffic.

Run a staging restore at least monthly and before major releases. A backup is
not considered verified until a restore has succeeded.
