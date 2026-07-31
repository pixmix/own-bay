# Currency feature

**Status: designed, built, deployed and verified in production on 2026-07-30.**

Raised 2026-07-30. Goal: move currency out of the single global site setting so
that it is chosen closer to the listing.

## Decision

1. **Per-item currency, with a global default.** `items.currency` carries each
   listing's currency. `users.last_currency` remembers what the admin last used
   and pre-fills their next new listing. The super-admin Settings field survives
   as the site default — used for a brand-new admin's first listing, and as the
   fallback anywhere an item's currency is missing. Rejected: per-admin-only
   (an admin cannot then list in two currencies) and per-item-with-no-global
   (a new admin's first listing would have no sensible default).

2. **Free-text symbol, unchanged.** The field stays a free-text symbol rather
   than becoming an ISO-4217 code with a symbol lookup. Accepted consequence:
   nothing prevents `€` and `EUR` both appearing across listings in one grid,
   and `mcp.php` reports whatever string was typed rather than a machine-
   readable code. Mitigation available if it becomes annoying: a `<datalist>`
   of symbols already in use, mirroring the existing tag autocomplete. Not
   built.

3. **A real migration runner.** `PRAGMA user_version` + an ordinal list of
   migration steps, run on every request against an existing database. This is
   the prerequisite — see the gap described in [[Architecture]] — and the
   currency schema change becomes migration step 1. Rejected: a one-off
   `ALTER TABLE` in the deploy package (the next schema change hits the same
   wall), and redeploying the database from local (destroys production
   listings and offers).

## As-is

Currency is one global constant for the whole site:

- `config.php:722` — `define('CURRENCY', $_settings['currency'] ?? '€')`, read
  from the `settings` table.
- Set only by a super-admin, in the Settings tab (`admin.php:508-509`, saved at
  `api.php:524`).
- Rendered as `CURRENCY . number_format($price, 2)` at **22 call sites**:
  `index.php` (grid), `item.php` (price tag, offer form, confirmation),
  `admin.php` (item list, offer list, price label), the offer and confirmation
  **emails** in `config.php:591-611`, and `mcp.php` (5 places, including the
  `submit_offer` tool schema and `get_site_info`).
- `items` has no currency column (see [[Architecture]]); every price is a bare
  REAL.

So today a site can only ever list in one currency, and only the super-admin can
choose it — which is wrong the moment a second seller lists in a different
currency.

## Options on the table

**A — per-admin currency.** A `currency` column on `users`. Each admin's
listings render in their own currency. Simple, one column, no per-item UI.
Weakness: an admin who genuinely sells in two currencies cannot.

**B — per-item currency with per-admin memory.** A `currency` column on `items`;
each listing carries its own. The admin's last-used currency is remembered and
pre-fills the next new listing. Currency becomes a property of the *item*, which
is what it actually is. This is Pietro's stated preference.

## Work either option implies

- Schema change on a database that **has no migration runner** — this is the
  blocker to solve first, see [[Architecture]] and the local deploy runbook.
- Backfill existing rows with the current global `€`.
- Replace the global constant at all 22 render sites, including the emails and
  the MCP server.
- Offers inherit their item's currency (an offer is always against one item),
  so the offer form, the offer list, the winner comparison and both emails must
  read it from the item.
- Decide the fate of the super-admin global setting: keep as the default for new
  listings and as a fallback, or remove.
- Public storefront now mixes currencies in one grid — prices are no longer
  comparable, which affects sorting and the "at or above listed price" offer
  rule only in presentation, not in logic (each offer is compared to its own
  item's price).

## Deployed

Live on 2h.mgzllc.com since 2026-07-30. Verified after deploy: all seven code
files at the expected sizes, database `integrity_check ok`, `user_version` 1,
`items.currency` and `users.last_currency` present, the single existing listing
backfilled to `€`, and offer/user counts unchanged. Storefront, item page and
privacy page all 200; admin redirects to login as expected; MCP reports
`default_currency` plus per-item `currency`.

## As built

Branch `feature/per-item-currency`.

- `config.php` — `schema_migrations()` + `run_migrations()` + `column_exists()`.
  `init_db()` baselines a fresh database at the latest version and runs pending
  migrations against an existing one. Migration 1 adds `items.currency` and
  `users.last_currency`, then backfills every existing listing from the
  `settings.currency` value.
- `config.php` — `item_currency()`, `format_price($item, $amount = null)`,
  `default_currency_for_user($user)`. All 22 former `CURRENCY` call sites now go
  through these; the constant survives only as the fallback inside them and as
  `default_currency` in the MCP site info.
- `admin.php` — currency input beside Price on the item form, pre-filled from
  the item (edit) or the admin's last-used currency (new). Settings field
  relabelled "Default currency symbol" with an explanatory hint.
- `api.php` — `save_item` persists the item's currency and writes
  `users.last_currency`.
- `mcp.php` — `list_items`/`get_item`/`submit_offer` report the item's own
  currency; `get_site_info` renames `currency` → `default_currency` and adds a
  note telling a model to read the item's field.

  This rename is **not** a protocol-level breaking change: the server declares
  no `outputSchema` and returns results as JSON inside an unstructured text
  block, so `tools/list` guarantees only `name`, `description` and
  `inputSchema`. A model-driven client reads the returned JSON and adapts. The
  descriptions are therefore the entire result-shape contract, which is why they
  are kept in step with the payload. Only a non-model client that hardcodes
  `get_site_info.currency` would notice.

## Verified

Against the local dev database, which had the same shape as production
(`user_version = 0`, no currency column, 55 items):

- Migration ran on first request, added both columns, backfilled all 55 items to
  `€`, left item count and content byte-identical to a pre-session backup.
- Re-running requests does not replay it — `user_version` stays at 1.
- Mixed currencies render per item on the storefront grid, the item detail page,
  the offer form label, the admin item list and the admin offers table.
- Save → memory loop: saving a listing in `CHF` stored `CHF` on the item, set
  `users.last_currency = CHF`, and pre-filled the next new-item form with `CHF`.
- Both offer emails carry the item's currency, subject line included — captured
  with `tools/scratch/fake-smtp.py` rather than sent, since the dev database
  holds live production SMTP credentials.
- MCP `list_items` and `get_item` report per-item currency.

Test data removed and SMTP settings restored afterwards. Consult the local
deploy runbook before shipping.
