# Backup and Restore

SQLite: `php artisan paylity:backup-database`, `php artisan paylity:backup-verify`. Backups stored under `storage/app/backups/database`. MySQL: use mysqldump template from hosting provider; do not run shell dumps from web API.
