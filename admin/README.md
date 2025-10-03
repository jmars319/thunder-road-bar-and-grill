Admin area notes

This folder contains simple admin utilities for editing site content and uploading images.

Important setup steps:

- Change the admin password hash in `config.php`.
  Generate a new hash locally (do NOT store the plaintext password in the repo):

```php
<?php
echo password_hash('YourSecurePasswordHere', PASSWORD_DEFAULT) . PHP_EOL;
```

Copy the output and replace the `ADMIN_PASSWORD_HASH` constant in `admin/config.php`.

- Ensure the web server/PHP process can write to these paths:
  - `data/content.json`
  - `uploads/images/`

- Reservation audit: recent reservation audit entries are stored in `data/reservation-audit.json`. A full viewer is available at `/admin/reservation-audit.php` that supports download and clearing of audit entries.

- CSRF protection: the admin endpoints now require a CSRF token on POST. Use `generate_csrf_token()` in PHP to embed the token in forms or send it via the `X-CSRF-Token` request header.

Note: some admin actions (download, purge, clear) are POST forms that use `csrf_input_field()` to include the token; when scripting admin actions, ensure the CSRF token is submitted.

- Image uploads and optimization use the GD extension. Install/enable the PHP GD extension if you plan to upload/optimize images.

Security recommendations:
- Avoid using the development `.htaccess` on production. Keep long Expires headers for static assets in production.
- Consider moving admin functionality behind basic auth or a separate secure host when in production.
