# Own Bay

A self-hosted marketplace for selling second-hand items. Built with PHP 8.2 and SQLite, it runs on any shared hosting or inside a Docker container — no server to administer, no external services.

**Live demo:** [2h.mgzllc.com](https://2h.mgzllc.com)

## Features

- **Public storefront** with responsive card grid, search, and tag filtering
- **Item detail pages** with multi-image gallery (arrows, thumbnails)
- **Offer system** — buyers submit offers; priority rules favour the first offer at or above the listed price
- **Admin panel** — full CRUD for items, offer management, site settings
- **Image editor** — crop, rotate, resize, and AI background removal (client-side, no server processing)
- **Multi-image support** — upload, reorder, and independently edit multiple images per item
- **Markdown descriptions** with live preview
- **SMTP email notifications** — seller alerts and buyer confirmations
- **Privacy by design** — buyer email addresses are never stored on the server
- **Self-extracting deploy script** — package and ship updates with a single command
- **Multi-seller** — self-registration with email confirmation, per-seller item isolation, and a super-admin role
- **Per-item currency** — each listing carries its own currency symbol, defaulting to the seller's last choice
- **Per-item location** — optional coordinates published rounded to a precision the seller picks (~100 m, ~1 km, or none), with a `geo:` link that opens the viewer's own map app
- **MCP server** — an open JSON-RPC endpoint so AI assistants can browse the catalogue and submit offers
- **Fully configurable** — site title, tagline, owner name, currency, and SMTP settings via admin UI

## Quick Start

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/install/) (for local development)
- Or a shared hosting account with PHP 8.2+, Apache, and the GD + PDO SQLite extensions

### Local Development with Docker

```bash
git clone https://github.com/pixmix/own-bay.git
cd own-bay
docker compose up -d
```

Open [http://localhost:8082](http://localhost:8082).

The `site/` directory is volume-mounted into the container, so any file edits are reflected immediately — no rebuild required.

The SQLite database (`site/data/marketplace.db`) and the `uploads/` directory are created automatically on first use. Schema changes in later versions are applied automatically on the first request after an update.

**Admin panel:** [http://localhost:8082/admin.php](http://localhost:8082/admin.php)
On first visit a setup form asks you to create the first account; that account becomes the super-admin.

**Stop the container:**

```bash
docker compose down
```

### Shared Hosting

1. Upload the contents of `site/` to your web root
2. Ensure `data/` and `uploads/` directories exist and are writable by the web server
3. Navigate to `/admin.php` — a setup form creates the first (super-admin) account
4. Go to **Settings** to configure your site title, owner name, currency, and SMTP

No manual file copying is needed — the application creates its database with sensible defaults on first run.

## First-Use Walkthrough

After logging in to the admin panel for the first time:

1. **Create your account** — the setup form on first visit creates the super-admin. Passwords are stored as bcrypt hashes; there is no shared site password.
2. **Go to Settings** — set your site title, tagline, owner name, and default currency.
3. **Configure SMTP** (optional) — enter your SMTP server details to enable email notifications. Use the **Send Test Email** button to verify the configuration works.
4. **Add your first item** — fill in the title, price, currency, description (Markdown supported), and upload one or more images. Use the built-in editor to crop, rotate, or resize.
5. **Optionally add a location** — pick a precision (~100 m or ~1 km) and either type coordinates or use the browser's location button. Buyers see the coordinates rounded to that precision; leave it as "No location" to publish nothing.

## Configuration

All settings are managed through the admin panel under **Settings**:

| Setting | Description |
|---------|-------------|
| Site title | Shown in the header, page titles, and email subjects |
| Tagline | Subtitle displayed below the title on every page |
| Owner name | Displayed in the footer copyright and the privacy policy |
| Default currency | Starting currency for a seller who has not listed anything yet; each item stores its own |
| Maximum admin accounts | How many seller accounts may exist (0 = unlimited) |
| Notification email | Where you receive offer alerts |
| SMTP host | Your mail server address (e.g. `smtp.example.com`) |
| SMTP port | Usually 587 (STARTTLS), 465 (SSL), or 25 (none) |
| SMTP username / password | Credentials for your mail server |
| SMTP encryption | STARTTLS, SSL/TLS, or None |
| From address | The sender address on outgoing emails |

### Accounts

Each seller has their own email + password login; passwords are stored as bcrypt hashes in the database. The first account created is the super-admin, who can manage settings and remove other accounts. Additional sellers can self-register when **Maximum admin accounts** allows it, confirming their address with an emailed code. A forgotten password is reset with an emailed code — there is no shared site password to rotate.

## Deploy Script

The deploy script builds a self-extracting shell archive. Upload it to your server and run it with a single command to apply updates.

```bash
# Code-only update — preserves data, images, and password on the server
./deploy.sh

# Include the database and images
./deploy.sh --with-db --with-images

# Set site identity
./deploy.sh --title "My Store" --tagline "Great deals" --owner "Jane Doe"

# Full deploy with database and images
./deploy.sh --with-db --with-images

# Inspect a previously built package
./deploy.sh --list 2h-deploy.sh
```

**Deploy to server:**

1. Upload via SFTP: `put 2h-deploy.sh`
2. SSH and run: `cd ~/www/mysite && bash ~/2h-deploy.sh`

Run `./deploy.sh --help` for the full list of options.

### What the deploy script preserves

By default (no flags), only code files are updated. The server's data, images, settings, and password are left untouched. Each flag explicitly opts in to overwriting:

| Flag | Overwrites |
|------|-----------|
| `--with-db` | `data/marketplace.db` — **everything**: items, offers, accounts and settings |
| `--with-images` | `uploads/` directory (all item images) |

**Note:** `--with-db` replaces the server's live database with your local one, including accounts and SMTP credentials. It is rarely what you want against a running site — the default code-only deploy leaves all data intact. Treat a package built with it as sensitive.

A timestamped backup of the previous code is created automatically before each deploy.

## Backup and Maintenance

All persistent data lives in two places:

- **`site/data/marketplace.db`** — items, offers, accounts and settings (SQLite)
- **`site/uploads/`** — item images

To back up your site, copy both. To restore, put them back. Copy the `-wal` and `-shm` files alongside the database if they are present, or take the backup with the site briefly idle.

**Before upgrading across a schema change, back the database up first** — migrations are applied automatically on the first request after new code lands, and there is no automatic downgrade.

### Upgrading

Pull the latest code, rebuild the deploy package, and push it to your server:

```bash
git pull
./deploy.sh
# Upload and run 2h-deploy.sh on the server
```

This updates only PHP, JS, and CSS. Your data, images, and settings are preserved. Any schema change ships with the code and applies itself on the first request — back up `marketplace.db` first.

## Project Structure

```
own-bay/
├── docker-compose.yml     # Local dev: PHP 8.2 + Apache on port 8082
├── Dockerfile             # Container with PHP, GD, mod_rewrite
├── deploy.sh              # Build self-extracting deployment archives
├── LICENCE                # GNU GPL v3
└── site/
    ├── index.php          # Public item grid with search and tag filtering
    ├── item.php           # Item detail with gallery and offer form
    ├── admin.php          # Admin: items CRUD, offers, settings
    ├── api.php            # API: login, offers, save/delete, SMTP test
    ├── config.php         # Constants, helpers, SMTP client
    ├── privacy.php        # Privacy policy (uses configurable owner name)
    ├── .htaccess          # Blocks direct access to data/
    ├── .user.ini          # PHP upload limits for shared hosting
    ├── css/style.css      # All styles
    ├── js/admin.js        # Image editor, markdown preview, slot management
    ├── mcp.php            # MCP server: JSON-RPC endpoint for AI assistants
    ├── data/
    │   └── marketplace.db  # Items, offers, accounts, settings (auto-created)
    └── uploads/           # Item images
```

## Offer Priority Rules

1. Buyers can offer any amount (above or below the listed price)
2. The first offer at or above the listed price takes absolute priority
3. Below-price offers are ranked highest-first, at the seller's discretion
4. Items remain visible until manually removed after a completed sale

## Privacy

A privacy policy is served at `/privacy.php` and adapts to your configured owner
name. It describes what this software actually does:

- Buyer email addresses are **never stored** — they are used in-request to send
  the confirmation and seller notification, then discarded.
- Seller accounts store an email address and a bcrypt password hash.
- Item locations are optional, chosen by the seller, and **published rounded**
  to the precision they pick (~100 m, ~1 km, or not at all).
- The optional location button uses the browser's own geolocation API, which
  always asks permission. No IP-based location lookup is performed.
- No analytics, no advertising, no third-party scripts, and no map provider is
  contacted — the "open in maps" link is a `geo:` URI handled by the visitor's
  own device.
- One cookie: `PHPSESSID`, for login sessions and offer rate-limiting.

If you run this publicly, review that page against your own jurisdiction's
requirements before relying on it — it is written to describe the software, not
to be legal advice.

## Requirements

- PHP 8.2+ with the **GD** and **PDO SQLite** extensions
- Apache with `mod_rewrite` (or equivalent URL rewriting)
- Writable `data/` and `uploads/` directories (SQLite needs write access to the
  directory, not just the database file, in order to manage its journal)
- SMTP server for email notifications (optional, but required for seller
  registration and password resets)
- HTTPS if you want the admin location button to work — browsers only expose
  geolocation on secure origins

## Licence

This project is licensed under the [GNU General Public License v3.0](LICENCE).

Original concept and direction by Pietro Mincuzzi. Design and implementation by MGZ Consulting LLC.

Content copyright on each installation is configurable via the owner name setting and belongs to the respective site operator.
