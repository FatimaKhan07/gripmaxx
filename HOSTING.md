# GripMaxx Hosting Notes

## Before you deploy

1. Set database credentials with environment variables, or create an untracked local file at `php/data/db_config.local.php`.
2. Keep secrets out of tracked files. Admin and SMTP secrets are now stored separately in `php/data/app_secrets.php`, which is ignored by Git.
3. Rotate any SMTP or admin credentials that were ever committed previously.

## Supported database environment variables

- `GRIPMAXX_DB_HOST`
- `GRIPMAXX_DB_PORT`
- `GRIPMAXX_DB_NAME`
- `GRIPMAXX_DB_USER`
- `GRIPMAXX_DB_PASSWORD`

## Supported app setting environment variables

- `GRIPMAXX_ADMIN_USERNAME`
- `GRIPMAXX_ADMIN_PASSWORD_HASH`
- `GRIPMAXX_STORE_EMAIL`
- `GRIPMAXX_SHIPPING_OPTION`
- `GRIPMAXX_SHIPPING_COST`
- `GRIPMAXX_SMTP_HOST`
- `GRIPMAXX_SMTP_PORT`
- `GRIPMAXX_SMTP_ENCRYPTION`
- `GRIPMAXX_SMTP_USERNAME`
- `GRIPMAXX_SMTP_PASSWORD`
- `GRIPMAXX_SMTP_FROM_EMAIL`
- `GRIPMAXX_SMTP_FROM_NAME`

## Database setup

Run one of these after switching to a fresh hosting database:

- CLI: `C:\xampp\php\php.exe php\migrate.php`
- Admin panel: open `/admin/database_setup.php` after logging in

## Local database config example

Create `php/data/db_config.local.php` with:

```php
<?php
return [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'gripmaxx',
    'user' => 'root',
    'password' => ''
];
```
