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


Recent changes
--------------
- Admin UI theme tokens aligned with the public site and dark-mode support added.
- Destructive actions in the admin UI use a muted red danger style (`btn-danger-soft` / `btn-danger-filled`).
- Hero content block now supports multiple images (slideshow). Uploaded images append to the hero images list and can be reordered in the editor using SortableJS.
- Image list endpoint filters out dotfiles and non-image extensions so `.htaccess` no longer appears in pickers.
- Change-password flow updates `admin/auth.json` when possible and bumps an internal password version to avoid accidental session breakage.
- Applications actions were consolidated into a single submenu and received keyboard/ARIA improvements. Client-side confirm handling is centralized (`data-confirm`).

Keep an eye on file permissions for `data/` and `uploads/images/` after migrations.
