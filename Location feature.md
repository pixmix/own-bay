# Location feature

**Status: built and verified locally 2026-07-31. Not yet deployed.**

Listings can carry coordinates. The shape deliberately mirrors
[[Currency feature]]: the value belongs to the *item*, and the admin's last
choice becomes the default for their next listing.

## Decision

1. **Precision is the seller's choice per listing** — `none`, `~100 m`
   (3 decimals) or `~1 km` (2 decimals). Coordinates are **stored at full
   precision but only ever published rounded** to that choice. An item's
   location is usually the seller's home, and a public listing should not
   resolve to their doorstep; `none` emits nothing at all.

2. **Browser geolocation only for auto-fill.** Rejected IP geolocation on two
   grounds: it sends addresses to a third party, contradicting the site's
   no-tracking position, and behind a VPN it reports the exit node rather than
   the seller. Browser geolocation is permission-gated anyway, so the user is
   always asked.

3. **Coordinates stay hand-editable**, and a typed value is remembered as the
   next default exactly like a geolocated one — pasting from whatever map the
   seller already uses is a first-class path, not a fallback.

4. **A brand-new admin starts at `none` with empty fields.** Nothing is guessed.

5. **No map of our own, and no link to a particular provider.** Regions and
   users differ in which maps they can or prefer to use. Decimal degrees are the
   universal interchange format: buyers get selectable text, a copy button, and
   a `geo:` URI (RFC 5870) that opens the device's own default map app.

## Rules that are easy to get wrong

- **Saving without usable coordinates resolves precision to `none`** rather than
  publishing a stale position — but it does **not** wipe the admin's remembered
  default, so clearing one listing's location does not cost them the prefill on
  the next.
- `parse_coord()` tolerates decimal commas and whitespace and range-checks
  (±90 / ±180); anything else is treated as absent.
- Geolocation only nudges precision away from `none`; it never overwrites a
  choice the admin already made.

## As built

- `config.php` — `LOCATION_PRECISIONS`, `normalise_precision()`,
  `parse_coord()`, `item_location()` (returns already-rounded values),
  `format_coords()`, `geo_uri()`, `default_location_for_user()`.
- `admin.php` — precision selector, editable lat/lon inputs, "Use my location".
- `api.php` — save path enforces the coordinates/precision rule and records the
  admin's memory.
- `item.php` — public block: copyable coordinates, copy button, `geo:` link.
- `mcp.php` — `location` object (rounded pair, precision, coordinate string,
  `geo:` URI) on `list_items` and `get_item`; `null` when absent.
- `js/admin.js` — geolocation button, added as a **separate** `DOMContentLoaded`
  listener because the existing handler returns early when the image editor is
  absent, which has silently killed appended code before.
- Migration 2 — `items.latitude/longitude/location_precision` plus the matching
  `users.last_*`; existing listings default to `none`, no backfill needed.

## Verified

Against the dev database at `user_version 1` (production's shape):

- Migration applied on first request, all 55 items intact, all defaulted to
  `none`; `user_version` → 2.
- Stored `22.5431234, 114.0579876` at 100 m published as `22.543, 114.058`;
  the same item at 1 km published as `22.54, 114.06`; at `none` the block
  disappeared entirely. **Full precision never reached the page.**
- Save → remember → prefill loop works, including the precision.
- Empty coordinates with precision `100m` resolved to `none` and left the
  admin's remembered location intact.
- MCP reports the rounded pair with precision, and `null` for a location-less
  item.

Test rows removed afterwards; item checksums identical to the pre-feature
backup.
