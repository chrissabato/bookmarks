# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

A PHP bookmark dashboard for Willamette University Athletics staff. Dark Tailwind UI, SQLite-backed, with a full admin interface. Serves four page sets (Sports Info, Athletic Communications, Broadcast, Stadium).

## Stack

- PHP + Tailwind CDN (no build step, Apache/PHP server)
- SQLite via PDO (`bookmarks.db`)
- SortableJS CDN (drag-and-drop in admin)
- Vanilla JS (no framework)

## File Map

| File | Purpose |
|------|---------|
| `db.php` | PDO singleton (`get_db()`) + `init_schema()` — included by all other PHP files |
| `api.php` | JSON API for admin — all CRUD and reorder operations via `?action=` |
| `index.php` | Public viewer — reads DB, renders card grid, live search |
| `admin.php` | Admin SPA shell — all JS state management, no page reloads |
| `bookmarks.db` | SQLite database (blocked from direct HTTP access via `.htaccess`) |
| `migrate.php` | One-time JSON→SQLite migration (keep as reference; do not re-run against live DB) |
| `bearcats/`, `broadcast/`, `stadium/` | Legacy subdirectory URLs — each `index.php` redirects to `../index.php?set=<slug>` |

## Database Schema

```sql
page_sets  (id, slug TEXT UNIQUE, title, position)
categories (id, page_set_id FK→page_sets, name, position)
bookmarks  (id, category_id FK→categories, label, url, position)
```

All three tables use `position INTEGER` for ordering. Deletes cascade. `bookmarks.db` lives in the project root; the `.htaccess` `<Files>` block denies direct HTTP access to it.

## API (`api.php`)

Actions are passed as `?action=<entity>.<verb>`. GET for list operations, POST for all writes.

- **page_sets**: `list`, `create` (slug, title), `update` (id, slug?, title?), `delete` (id), `reorder` (ids JSON array)
- **categories**: `list` (page_set_id) → nested with bookmarks, `create`, `update`, `delete`, `reorder`
- **bookmarks**: `create`, `update`, `delete`, `reorder`, `move` (id, target_category_id)

Reorder operations accept a complete ordered `ids` JSON array and rewrite all `position` values in a transaction.

## Admin behavior

- On load: fetches `page_sets.list`, renders tab strip, loads first set's `categories.list`
- Category name: `contenteditable` div; `blur` → `categories.update`
- Bookmark fields: `<input>` elements; `change` event → `bookmarks.update`
- New bookmark: blank inline row appended; `blur` with both fields filled → `bookmarks.create`
- Cross-category drag: SortableJS `group:'bookmarks'`; fires `bookmarks.move` then `bookmarks.reorder`
- Page set manager: collapsible panel under "Manage Sets" button; SortableJS reorder + `contenteditable` rename + delete

## Viewer behavior

- Active page set determined by `?set=slug`; defaults to first by position
- Favicons via Google S2 (`https://www.google.com/s2/favicons?domain=…&sz=32`); `onerror` hides failures
- `javascript:` bookmarklet URLs are rendered without `target="_blank"` and without a favicon
- Live search filters both label text and URL; hides empty category cards

## Access control

`.htaccess` at root: campus IP range `158.104.0.0/16` OR Shibboleth user `csabato`.  
`bearcats/.htaccess`: `Satisfy Any` (open access).  
`broadcast/` and `stadium/`: inherit root restrictions.

## Special link types (preserved from original data)

- `javascript:` bookmarklets — stored verbatim, rendered without `target="_blank"`
- Protocol-relative URLs (`//wubearcats.com/admin`) — stored and rendered as-is
- Bare IP addresses without protocol — stored as-is (favicons fail silently)
