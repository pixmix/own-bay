# Architecture

Verified against the working tree on 2026-07-30. Where this disagrees with
`README.md`, the readme is stale — see [[_Index]].

## Stack

PHP 8.2 + Apache + GD, SQLite (PDO, WAL, foreign keys on). No framework, no
build step, no package manager. Local dev is Docker on port 8082 with `site/`
volume-mounted, so edits are live without a rebuild.

## Files

| File | Role |
|------|------|
| `site/config.php` | Constants, DB bootstrap + schema, auth, CSRF, markdown, SMTP client |
| `site/index.php` | Public storefront — card grid, search, multi-tag filter |
| `site/item.php` | Item detail — image gallery, offer form |
| `site/admin.php` | Admin panel — items CRUD, offers, users, settings |
| `site/api.php` | POST endpoints — login, register, save/delete, settings, offers |
| `site/mcp.php` | JSON-RPC 2.0 Model Context Protocol server (5 tools) |
| `site/privacy.php` | Privacy policy, uses the configurable owner name |
| `site/js/admin.js` | Image editor, markdown preview, tag chips, image slots |

## Data model

Tables created in `create_schema()` (`config.php:53`): `users`, `items`,
`item_tags`, `item_images`, `offers`, `settings`, `login_attempts`.

- `items` — `id` (TEXT PK), `user_id` FK → `users`, `title`, `description`,
  `price` (REAL), `status`, `created_at`. **No currency column** — price is a
  bare number; the symbol is global. See [[Currency feature]].
- `offers` — belongs to an item, stores `amount` + `status` only. Buyer email is
  never persisted (privacy by design); it is used in-request to send the
  confirmation and then discarded.
- `settings` — flat key/value store, loaded once into `$_settings` and frozen
  into constants (`SITE_TITLE`, `CURRENCY`, `OWNER_NAME`, …) near the bottom of
  `config.php`.

## Schema evolution — a real gap

`init_db()` (`config.php:38`) calls `create_schema()` **only when the database
file does not exist**:

```php
$exists = file_exists(DB_FILE);
$db = get_db();
if (!$exists) { create_schema($db); … }
```

There is **no versioned migration mechanism** — no `PRAGMA user_version`, no
migrations table, no `ALTER TABLE` anywhere. `CREATE TABLE IF NOT EXISTS` adds
new *tables* on a fresh DB only, and never adds a *column* to an existing one.

Consequence: any schema change (such as adding a currency column) will not reach
the production database on deploy. This must be solved before the first schema
change ships — see [[Currency feature]].

## Auth and roles

Email + password (bcrypt, `password_needs_rehash()` auto-upgrade), session
holds `user_id`, `session_regenerate_id()` on login, rate limiting via
`login_attempts`. CSRF tokens on all POST forms. Two roles: regular admin
(owns their own items) and super-admin (`users.is_super_admin`) who can see and
delete everything, manage users, and edit site settings. First registered user
becomes super-admin. `max_admins` setting gates registration.

## Rendering conventions

Prices render as `CURRENCY . number_format($price, 2)` — one global constant
concatenated at every site (22 references across `config.php`, `admin.php`,
`index.php`, `item.php`, `mcp.php`). Dates are stored UTC and converted to the
viewer's timezone in JS via `Intl`, with server-rendered text as no-JS fallback.
