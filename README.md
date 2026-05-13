# Own Bay

A self-hosted marketplace for selling second-hand items. No database required — all data is stored in JSON files. Built with PHP 8.2, it runs on any shared hosting or inside a Docker container.

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
- **Fully configurable** — site title, tagline, owner name, and SMTP settings via admin UI

## Quick Start

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/install/) (for local development)
- Or a shared hosting account with PHP 8.2+, Apache, and the GD extension

### Local Development with Docker

```bash
git clone https://github.com/pixmix/own-bay.git
cd own-bay
docker compose up -d
```

Open [http://localhost:8082](http://localhost:8082).

The `site/` directory is volume-mounted into the container, so any file edits are reflected immediately — no rebuild required.

Data files (`items.json`, `offers.json`, `settings.json`) and the `uploads/` directory are created automatically on first use.

**Admin panel:** [http://localhost:8082/admin.php](http://localhost:8082/admin.php)
Default password: `changeme`

**Stop the container:**

```bash
docker compose down
```

### Shared Hosting

1. Upload the contents of `site/` to your web root
2. Ensure `data/` and `uploads/` directories exist and are writable by the web server
3. Navigate to `/admin.php` and log in (default password: `changeme`)
4. Go to **Settings** to configure your site title, owner name, and SMTP

No manual file copying is needed — the application creates its data files with sensible defaults on first run. A `settings.example.json` is included in the repository for reference.

## First-Use Walkthrough

After logging in to the admin panel for the first time:

1. **Change the admin password** — use the deploy script (`./deploy.sh --password`) or replace the bcrypt hash in `config.php` directly. On shared hosting without CLI access, generate a hash with an online bcrypt tool and paste it into the `ADMIN_PASSWORD_HASH` constant.
2. **Go to Settings** — set your site title, tagline, and owner name.
3. **Configure SMTP** (optional) — enter your SMTP server details to enable email notifications. Use the **Send Test Email** button to verify the configuration works.
4. **Add your first item** — fill in the title, price, description (Markdown supported), and upload one or more images. Use the built-in editor to crop, rotate, or resize.

## Configuration

All settings are managed through the admin panel under **Settings**:

| Setting | Description |
|---------|-------------|
| Site title | Shown in the header, page titles, and email subjects |
| Tagline | Subtitle displayed below the title on every page |
| Owner name | Displayed in the footer copyright and the privacy policy |
| Notification email | Where you receive offer alerts |
| SMTP host | Your mail server address (e.g. `smtp.example.com`) |
| SMTP port | Usually 587 (STARTTLS), 465 (SSL), or 25 (none) |
| SMTP username / password | Credentials for your mail server |
| SMTP encryption | STARTTLS, SSL/TLS, or None |
| From address | The sender address on outgoing emails |

### Admin Password

The password is stored as a bcrypt hash in `config.php`. To change it:

```bash
# Interactive prompt (requires Docker or local PHP)
./deploy.sh --password

# Inline
./deploy.sh --password 'YourNewPassword'
```

On shared hosting without shell access, generate a bcrypt hash (many free online tools exist) and replace the value in the `ADMIN_PASSWORD_HASH` constant in `config.php`.

## Deploy Script

The deploy script builds a self-extracting shell archive. Upload it to your server and run it with a single command to apply updates.

```bash
# Code-only update — preserves data, images, and password on the server
./deploy.sh

# Include item catalogue and images
./deploy.sh --with-items --with-images

# Set site identity
./deploy.sh --title "My Store" --tagline "Great deals" --owner "Jane Doe"

# Include SMTP/email configuration (treat the package as sensitive!)
./deploy.sh --with-settings

# Full deploy with new password
./deploy.sh --with-items --with-images --with-settings --password

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
| `--with-items` | `data/items.json` (the item catalogue) |
| `--with-offers` | `data/offers.json` (buyer offers) |
| `--with-settings` | `data/settings.json` (SMTP credentials, site identity) |
| `--with-images` | `uploads/` directory (all item images) |
| `--password` | The admin password hash in `config.php` |

**Note:** `--with-settings` bundles your SMTP credentials into the archive. Handle the resulting package with the same care as a password file.

A timestamped backup of the previous code is created automatically before each deploy.

## Backup and Maintenance

All persistent data lives in two places:

- **`site/data/`** — `items.json`, `offers.json`, `settings.json`
- **`site/uploads/`** — item images

To back up your site, copy these two directories. To restore, put them back. There is no database to dump or migrate.

### Upgrading

Pull the latest code, rebuild the deploy package, and push it to your server:

```bash
git pull
./deploy.sh
# Upload and run 2h-deploy.sh on the server
```

This updates only PHP, JS, and CSS. Your data, images, and settings are preserved.

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
    ├── data/
    │   ├── settings.example.json  # Reference template
    │   ├── items.json             # Item catalogue (auto-created)
    │   ├── offers.json            # Buyer offers (auto-created)
    │   └── settings.json          # Site config + SMTP (auto-created)
    └── uploads/           # Item images
```

## Offer Priority Rules

1. Buyers can offer any amount (above or below the listed price)
2. The first offer at or above the listed price takes absolute priority
3. Below-price offers are ranked highest-first, at the seller's discretion
4. Items remain visible until manually removed after a completed sale

## Requirements

- PHP 8.2+ with the GD extension
- Apache with `mod_rewrite` (or equivalent URL rewriting)
- Writable `data/` and `uploads/` directories
- SMTP server for email notifications (optional)

## Licence

This project is licensed under the [GNU General Public License v3.0](LICENCE).

Original concept and direction by Pietro Mincuzzi. Design and implementation by MGZ Consulting LLC.

Content copyright on each installation is configurable via the owner name setting and belongs to the respective site operator.
