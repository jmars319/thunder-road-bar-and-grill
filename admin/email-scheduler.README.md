Email Scheduler — admin/email-scheduler.README.md

Overview

This project integrates an Email Scheduler into the Thunder Road admin area. It provides:

- `lib/email_scheduler.php` — core scheduler class (SQLite-backed)
- `admin/api/email_api.php` — secured REST API (requires admin session + CSRF)
- `admin/email-scheduler.html` — admin UI for managing campaigns and configuration
- `cron/send_scheduled_emails.php` — CLI cron script to run every minute

Quick install

1. Ensure the server has PHP 7.4+ with extensions: PDO, sqlite, curl, libxml
2. Install PHPMailer (optional but recommended):

   From project root run:

   composer require phpmailer/phpmailer

   (If composer is not installed, install Composer first.)

3. Configure SMTP credentials:

   - Option A (recommended): Create `admin/auth.json` (untracked) with:

     {
       "smtp_username": "your-smtp-username",
       "smtp_password": "your-smtp-password"
     }

   - Option B: Use the admin UI -> Email Config tab to save SMTP settings.

4. Set up cron (example):

   Edit the crontab for the system user that runs PHP and add:

   * * * * * /usr/bin/php /full/path/to/your/site/cron/send_scheduled_emails.php

   Adjust the PHP binary path and site path as needed.

Usage

- Visit: https://your-site/admin/email-scheduler.html
- Create campaigns, add suppliers (via API), configure SMTP, and monitor logs.

Notes

- The system will attempt to use PHPMailer if `vendor/autoload.php` is present and PHPMailer is installed via Composer. If PHPMailer fails or is not present, the system falls back to PHP `mail()`.
- Database file: `data/email_scheduler.sqlite` (auto-created). Consider moving it outside webroot for extra security.
- The API requires admin login and CSRF tokens for state changes.

Security

- Keep SMTP credentials out of git. Use `admin/auth.json` for overrides or the admin UI which stores config in the DB.
- Remove public access to `admin/` directory and use HTTPS. Ensure only authorized admin sessions can access the UI.

Troubleshooting

- If emails do not send: check `cron/cron.log`, `data/email_scheduler.sqlite` email_logs table, and SMTP settings.
- For PHPMailer errors: check web server error logs and the PHPMailer exception message captured in logs.

