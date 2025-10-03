# Thunder Road Bar & Grill — Local Development

For a minimal quick-start, see `README-clean.md`.

Small dev README with the quick commands and notes you'll need when working locally.

## Requirements
- PHP 8.x installed and available on your PATH (php command).
- A modern browser for testing (Chrome, Firefox, Safari).

## Quick start (dev server)
From the project root run one of these (zsh):

Bind to localhost only (recommended):

```
# Thunder Road Bar & Grill — Local Development

Small dev README with the quick commands and notes you'll need when working locally.

## Requirements
- PHP 8.x installed and available on your PATH (php command).
- A modern browser for testing (Chrome, Firefox, Safari).

## Quick start (dev server)
From the project root run one of these (zsh):

Bind to localhost only (recommended):

```
php -S 127.0.0.1:8000 -t .
```

Bind to all interfaces (makes site available on your LAN — use with caution):

```
php -S 0.0.0.0:8000 -t .
```

Then open in your browser:

- http://localhost:8000/
- (or) http://127.0.0.1:8000/

Important: If you see a plain directory listing in the browser, make sure you are using the `http://` URL (not `file:///`) and that the server was started with the correct document root (the project root). If `http://localhost:8000/` works but `http://<your-ip>:8000/` does not, try starting the server with `0.0.0.0:8000`.

To stop a running PHP dev server (if started in the background), you can find and kill it:

```
pkill -f "php -S localhost:8000" || pkill -f "php -S 127.0.0.1:8000" || true
```

Or identify the process (macOS):

```
ps aux | grep "php -S"
```

## Admin area
- Admin UI: `/admin/index.php`
- Default config file: `admin/config.php` (credentials stored/managed by the admin UI). If you used the change-password screen it will write a hashed password into the config.
- Reservation audit: `/admin/reservation-audit.php` exposes a full reservation audit viewer and small management actions (download/clear).

## Contact form / email
- The server-side processor is `contact.php`. To change the recipient email, open `contact.php` and update the `$to` variable near the top of that file.
- On local machines, PHP's `mail()` may report success but messages often won't be delivered. For development use a local SMTP capture such as MailHog or a SMTP relay with PHPMailer.

Quick MailHog suggestion (macOS/Homebrew):

```
brew install mailhog
mailhog &
# MailHog UI available at http://localhost:8025
```

## Logs & storage
- Submissions (job applications) are logged to `data/applications.json` and archived into `data/archives/` when rotated.
- Reservation audit entries are stored in `data/reservation-audit.json` and can be viewed/downloaded/cleared from the admin UI.
- If you need to clear application logs, use the admin UI Export/Purge actions in `/admin`. The purge action will create a gzipped backup in `data/archives/` before clearing.

## CSS/JS notes
- Button styles live in `assets/css/styles.css` and the interactive behavior in `assets/js/main.js`.
- If you see unexpected hover/selection behavior on buttons, check `assets/css/styles.css` (we added fixes to keep button text readable on hover and to avoid global link hover rules affecting `.btn`).

## Troubleshooting checklist
- Use `curl -I http://localhost:8000` to verify the server is responding and check `X-Powered-By` header for PHP.
- If the stylesheet doesn't reflect changes, hard refresh the browser (Cmd+Shift+R) or clear cache.
- If you suspect JS/CSS is cached by the browser, open an Incognito/Private window to verify.

There is a `dev.sh` helper script at the project root to start the local dev server and related utilities; run `./dev.sh start` from the project root to use it.

---

Favicon & web manifest
----------------------
Favicons and platform icons are stored under `uploads/images/favicon-set/`. The site references a single canonical manifest at `/site.webmanifest` (placed at the repo root) which points to the icon files in that folder. To update icons:

- Replace the files in `uploads/images/favicon-set/` preserving the filenames the manifest expects (16/32/48/192/512, apple-touch-icon.png, mstile-150x150.png).
- If you prefer icons in `assets/images/`, move the files there and update the links in `index.php` and the paths in `/site.webmanifest` accordingly.

If you maintain multiple icon sizes or tools that generate icons, keep larger sizes (512) for maskable/Android usage and smaller sizes (16/32) for browser tabs.

Note: there's a deprecated manifest copy inside `uploads/images/favicon-set/site.webmanifest.deprecated` — the root `/site.webmanifest` is canonical.
