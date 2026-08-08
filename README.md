# Bookmarks

A self-hosted bookmark dashboard: dark Tailwind UI, SQLite-backed, with a full admin interface for organizing links into categories under one or more page sets (tabs). No build step — plain PHP + vanilla JS.

## Requirements

- PHP 8+ with PDO SQLite (`pdo_sqlite` extension)
- Apache with `mod_rewrite` and `mod_authz_host` (for pretty URLs and, optionally, IP-based access control)
- Optional: a Shibboleth SP (`mod_shib`) if you want Shibboleth-based login for restricted page sets

## Setup

1. **Get the code onto your server**, served by Apache with PHP enabled.

2. **Copy the two config templates** and fill in your own values (both are gitignored — they never get committed):

   ```bash
   cp .htaccess.example .htaccess
   cp config.example.php config.php
   ```

   - `.htaccess` controls Apache-level access to `admin.php`/`api.php` (always restricted) and the pretty-URL rewrite (`/some-slug` → `index.php?set=some-slug`).
   - `config.php` controls which visitors can view page sets that aren't marked "Public" — a trusted IP range and/or a list of Shibboleth usernames. See `access.php` for how it's used.

   If you don't need to restrict anything, you can leave `config.php`'s lists empty and just mark every page set "Public" in the admin UI — in that case you can also simplify `.htaccess` down to just the rewrite rule (step 2 comment in the template explains what to remove).

3. **Create the database and fix permissions** so Apache can write to it:

   ```bash
   ./setup.sh
   ```

   This creates `bookmarks.db` and `chgrp`s it (and the project directory) to `apache` — edit the group name in `setup.sh` first if your Apache runs as a different user/group (e.g. `www-data`). The schema itself is created automatically on first request (see `db.php`'s `init_schema()`), so `setup.sh` only needs to run once, to get permissions right. Delete `setup.sh` afterward if you like.

4. **Open `/admin.php`** and create your first page set (title + slug, and optionally check "Public"). Add categories and bookmarks from there.

5. **View it** at `/your-slug` (or `/index.php?set=your-slug`).

## Everyday use

- **Admin** (`/admin.php`): manage page sets, categories, and bookmarks. Drag to reorder, click into text to rename, blur to save — no separate "save" step.
- **Public vs. restricted page sets**: each page set has a "Public" toggle. Public sets are visible to anyone; everything else requires the trusted IP/Shibboleth identity configured in `config.php`. `admin.php`/`api.php` are always restricted, independent of that toggle.
- **Viewer** (`/your-slug`): read-only card grid with live search (matches label text and URL).

## More detail

See `CLAUDE.md` for the database schema, API reference, and file-by-file architecture notes.
