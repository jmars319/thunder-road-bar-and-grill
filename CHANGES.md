2025-10-02 — Rename storage for submissions

- Renamed submissions storage from `data/messages.json` to `data/applications.json`.
- Admin UI and export/archive code updated to use the new filename.
- Added compatibility fallback: admin code will attempt to read `applications.json` first, and fall back to `messages.json` if present (short-term safety). Archive loading checks both `applications-*.json.gz` and `messages-*.json.gz` patterns.
- A gzipped backup of the legacy `messages.json` was created and is stored in `data/archives/` before the migration.

Notes:
- This change is intended to be backward-compatible for a transition window; the compatibility fallback can be removed after deployment when you're confident there are no external references to the old filename.

2025-10-03 — Admin UX and content editing improvements

- Make About section editable from the Admin UI. Added `about.heading` and `about.body` placeholders to `data/content.json`, and updated `index.php` to render those fields (falls back to existing keys).
- Small visual refresh of admin styles (`assets/css/admin.css`): cleaner cards, inputs, and buttons for a modern look.
- Menu admin: ensured preview and public rendering show consistent price badges and that quantity option lists don't duplicate price text. Special formatting for `wings-tenders` badges (e.g. "3 for $6.00 / 5 for $9.00").
- Live admin preview: attempted a WYSIWYG iframe-based approach but left the preview behavior unchanged per user request; any uncommitted experiment was reverted.

Notes:
- All changes are present in the repo and were committed on `main`. The public content store remains `data/content.json` as the single source of truth.
