# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

A PHP bookmark dashboard, originally built for Willamette University Athletics staff but designed to be self-hostable by anyone. Dark Tailwind UI, SQLite-backed, with a full admin interface. Page sets are created dynamically through the admin UI — there's no fixed list.

## Stack

- PHP + Tailwind CDN (no build step, Apache/PHP server)
- SQLite via PDO (`bookmarks.db`)
- SortableJS CDN (drag-and-drop in admin)
- Vanilla JS (no framework)

## File Map

| File | Purpose |
|------|---------|
| `db.php` | PDO singleton (`get_db()`) + `init_schema()` — included by all other PHP files |
| `access.php` | `check_access(bool $isPublic)` — viewer-side gate for non-public page sets, reads `config.php` |
| `index.php` | Public viewer — reads DB, renders card grid, live search |
| `admin/index.php` | Admin SPA shell — all JS state management, no page reloads |
| `admin/api.php` | JSON API for admin — all CRUD and reorder operations via `?action=` |
| `admin/.htaccess` | Gitignored — restricts `admin/` to trusted IP/Shibboleth. Copy from `admin/.htaccess.example` |
| `bookmarks.db` | SQLite database (blocked from direct HTTP access via `.htaccess`) |
| `config.php` | Gitignored — trusted IP ranges / Shibboleth usernames for restricted page sets. Copy from `config.example.php` |
| `.htaccess` | Gitignored — real Apache/Shibboleth rules for the root (pretty URLs, file blocking). Copy from `.htaccess.example` |
| `migrate.php` | One-time JSON→SQLite migration (keep as reference; do not re-run against live DB) |

## Database Schema

```sql
page_sets  (id, slug TEXT UNIQUE, title, position, is_public INTEGER DEFAULT 0)
categories (id, page_set_id FK→page_sets, name, position)
bookmarks  (id, category_id FK→categories, label, url, position, icon)
```

All three tables use `position INTEGER` for ordering. Deletes cascade. `bookmarks.db` lives in the project root; the `.htaccess` `<Files>` block denies direct HTTP access to it.

## API (`admin/api.php`)

Actions are passed as `?action=<entity>.<verb>`. GET for list operations, POST for all writes.

- **page_sets**: `list`, `create` (slug, title, is_public?), `update` (id, slug?, title?, is_public?), `delete` (id), `reorder` (ids JSON array)
- **categories**: `list` (page_set_id) → nested with bookmarks, `create`, `update`, `delete`, `reorder`
- **bookmarks**: `create`, `update`, `delete`, `reorder`, `move` (id, target_category_id)

Reorder operations accept a complete ordered `ids` JSON array and rewrite all `position` values in a transaction.

## Admin behavior

- On load: fetches `page_sets.list`, renders tab strip, loads first set's `categories.list`
- Category name: `contenteditable` div; `blur` → `categories.update`
- Bookmark fields: `<input>` elements; `change` event → `bookmarks.update`
- New bookmark: blank inline row appended; `blur` with both fields filled → `bookmarks.create`
- Cross-category drag: SortableJS `group:'bookmarks'`; fires `bookmarks.move` then `bookmarks.reorder`
- Page set manager: collapsible panel under "Manage Sets" button; SortableJS reorder + `contenteditable` rename + delete + per-row "Public" checkbox (`is_public`, wired to `page_sets.update`)
- New Page Set dialog also has a "Public" checkbox, passed to `page_sets.create`
- "Config" button toggles a read-only panel, rendered server-side in PHP, showing `config.php`'s trusted IP ranges and Shibboleth users, plus the viewer's own IP/`REMOTE_USER` and whether they match. Editing still happens in `config.php` on the server

## Viewer behavior

- Active page set determined by `?set=slug`; a root `.htaccess` rewrite also maps any `/some-slug` URL to `index.php?set=some-slug` (see Access control) so every page set gets a clean URL automatically — no per-set folders needed
- `index.php` calls `check_access((bool)$activeSet['is_public'])` right after loading the page set; non-public sets 403 unless the visitor's IP/Shibboleth identity clears `access.php`
- Favicons via Google S2 (`https://www.google.com/s2/favicons?domain=…&sz=32`); `onerror` hides failures
- `javascript:` bookmarklet URLs are rendered without `target="_blank"` and without a favicon
- Live search filters both label text and URL; hides empty category cards

## Access control

Two layers, both configured via gitignored files (copy from the `.example` templates):

- **`admin/`** — always restricted at the Apache level via `admin/.htaccess`, a *directory-scoped* file (campus IP range or Shibboleth user). Never affected by a page set's `is_public` flag. `admin.php`/`api.php` live in their own subdirectory specifically so this can be directory-scoped rather than `<FilesMatch>`-scoped in the root `.htaccess` — Shibboleth's `requireSession` redirect-to-login does not reliably trigger when scoped inside `<Files>`/`<FilesMatch>`, only at `<Directory>`/`<Location>` granularity (and `<Location>` isn't allowed in `.htaccess` at all). Learned this the hard way: a `<FilesMatch>`-scoped `requireSession 1` produced a bare 401 instead of an IdP redirect.
- **`index.php`** — no blanket Apache restriction; each page set's `is_public` column decides access. Non-public sets are checked in PHP by `access.php`, which reads trusted IP ranges / allowed Shibboleth usernames from `config.php`. An empty `shibboleth_users` list (with `config.php` present) means "any authenticated Shibboleth user" rather than "no one" — anonymous visitors get redirected to `/Shibboleth.sso/Login`, and once logged in are re-checked with no username restriction. A missing `config.php` file entirely means restricted sets just flat-deny (no login offered).
  - Root `.htaccess` needs a bare `Require shibboleth` alongside `ShibRequestSetting requireSession 0` for this to work at all — with no `Require` directive present anywhere in a directory, Apache skips the authentication phase entirely, so mod_shib never populates `REMOTE_USER` even with a fully valid session, regardless of `requireSession`. `Require shibboleth` + `requireSession 0` together is the standard "lazy session" idiom: it attaches identity when a session exists without forcing a login for anonymous requests (learned this the hard way — its absence caused an infinite login redirect loop, since `access.php` never saw a populated `REMOTE_USER` no matter how many times the user re-authenticated).

For a fresh self-hosted install: `cp .htaccess.example .htaccess`, `cp admin/.htaccess.example admin/.htaccess`, and `cp config.example.php config.php`, then fill in your own IP range/Shibboleth username (or leave both empty and mark every page set public).

## Special link types (preserved from original data)

- `javascript:` bookmarklets — stored verbatim, rendered without `target="_blank"`
- Protocol-relative URLs (`//wubearcats.com/admin`) — stored and rendered as-is
- Bare IP addresses without protocol — stored as-is (favicons fail silently)
