# Thunder Road Bar & Grill — Local Development

Small dev README with the quick commands and notes you'll need when working locally.

## Requirements
- PHP 8.x installed and available on your PATH (php command).
- A modern browser for testing (Chrome, Firefox, Safari).

## Quick start (dev server)
From the project root run one of these (zsh):

Bind to localhost only (recommended):

```bash
# Thunder Road Bar & Grill — Quick Start

Minimal instructions for local development.

Requirements
- PHP 8.x available on your PATH.
- Modern browser for testing (Chrome, Firefox, Safari).

Quick start (dev server)
From the project root run:

```bash
php -S 127.0.0.1:8000 -t .
```

Then open http://localhost:8000/ in your browser.

Stopping the server
```bash
pkill -f "php -S 127.0.0.1:8000" || true
```

Admin
- Admin UI: `/admin/index.php`
- Config: `admin/config.php`

Storage
- Applications: `data/applications.json` (archived to `data/archives/` when rotated)
- Reservations audit: `data/reservation-audit.json`

Contact form
- Processor: `contact.php` — change recipient by editing the `$to` variable in that file.

Assets & build
- CSS: `assets/css/styles.css`
- JS: `assets/js/main.js`

Notes
- A canonical web manifest is at `/site.webmanifest`. Favicons are kept in `uploads/images/favicon-set/`.
- Keep sensitive files out of source control (see `.gitignore` for `admin/auth.json` and `vendor/`).

That's it — use `README.md` for the fuller, living documentation if you need operational notes.
# MailHog UI available at http://localhost:8025
