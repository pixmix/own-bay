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
  `price` (REAL), `currency`, `latitude`, `longitude`, `location_precision`,
  `status`, `created_at`. Currency and location both belong to the *item*, not
  the site — see [[Currency feature]] and [[Location feature]]. Coordinates are
  stored at full precision and only ever published rounded to
  `location_precision` (`none` / `100m` / `1km`).
- `users` — also carries `last_currency`, `last_latitude`, `last_longitude` and
  `last_location_precision`: the admin's most recent choices, used to pre-fill
  their next listing.
- `offers` — belongs to an item, stores `amount` + `status` only. Buyer email is
  never persisted (privacy by design); it is used in-request to send the
  confirmation and then discarded.
- `settings` — flat key/value store, loaded once into `$_settings` and frozen
  into constants (`SITE_TITLE`, `CURRENCY`, `OWNER_NAME`, …) near the bottom of
  `config.php`.

## Schema evolution

`create_schema()` describes the current schema and runs **only on a brand-new
database**. Existing databases are brought up to it by `schema_migrations()` —
an ordinal list of steps applied by `run_migrations()` from `init_db()` on every
request, tracked in SQLite's `PRAGMA user_version`.

```php
$exists = file_exists(DB_FILE);
if (!$exists) {
    create_schema($db);
    $db->exec('PRAGMA user_version = ' . count(schema_migrations()));  // baseline
} else {
    run_migrations($db);
}
```

Rules that matter:

- **Append steps; never reorder or remove one.** The array index *is* the
  version. Migration 1 added per-item currency, migration 2 per-item location.
- Each step runs in a transaction and rolls back on failure.
- A fresh database is baselined at the latest version, so steps are not replayed
  against a schema that already has them.
- Guard DDL with `column_exists()` so a partially-applied migration recovers.
- **There is no down-migration.** Back up the database before shipping one.

This did not exist before 2026-07-30: `init_db()` only called `create_schema()`
when the file was absent, so a new column reached fresh installs and never an
existing one. Any schema-dependent feature would have deployed broken.

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
