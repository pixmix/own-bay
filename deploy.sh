#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SITE_DIR="$SCRIPT_DIR/site"
PACKAGE="$SCRIPT_DIR/2h-deploy.sh"

usage() {
    cat <<EOF
Usage: $(basename "$0") [OPTIONS]

Build a self-extracting deployment archive for the 2nd Hand site.
The archive runs on the server to apply updates with a single command.

Options:
  --with-db          Include marketplace.db (overwrites server database)
  --with-images      Include uploads/ directory (item images)
  --title TEXT       Set site title in database after deploy
  --tagline TEXT     Set site tagline in database after deploy
  --owner TEXT       Set owner name (footer copyright, privacy policy)
  --list FILE        Inspect a built package and show equivalent build command
  --output FILE      Output file (default: 2h-deploy.sh)
  -h, --help         Show this help

Default (no flags): code-only update. On a fresh server, the setup
wizard runs on first visit. On an existing JSON-based server, data
is automatically migrated to SQLite on first page load.

Examples:
  $(basename "$0")
      Code-only update — PHP, JS, CSS

  $(basename "$0") --with-db --with-images
      Full deploy with database and images

  $(basename "$0") --title 'My Shop' --owner 'Jane Doe'
      Code update + set title and owner in database

  $(basename "$0") --list 2h-deploy.sh
      Show package contents and the command that built it

Deploy to server:
  1. Upload via SFTP:  put 2h-deploy.sh
  2. SSH and run:      cd ~/www/2h && bash ~/2h-deploy.sh
  Or with path:        bash 2h-deploy.sh ~/www/2h
EOF
    exit 0
}

WITH_DB=0
WITH_IMAGES=0
SET_TITLE=""
SET_TAGLINE=""
SET_OWNER=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --with-db)       WITH_DB=1; shift ;;
        --with-images)   WITH_IMAGES=1; shift ;;
        --title)         SET_TITLE="$2"; shift 2 ;;
        --tagline)       SET_TAGLINE="$2"; shift 2 ;;
        --owner)         SET_OWNER="$2"; shift 2 ;;
        --list)
            PKG="${2:?--list requires a file}"
            if [[ ! -f "$PKG" ]]; then
                echo "Error: file not found: $PKG"
                exit 1
            fi
            bash "$PKG" --list
            echo ""
            SKIP=$(awk '/^__ARCHIVE__$/{print NR + 1; exit 0}' "$PKG")
            LTMP=$(mktemp -d)
            trap 'rm -rf "$LTMP"' EXIT
            tail -n +"$SKIP" "$PKG" | tar -xzf - -C "$LTMP" 2>/dev/null
            HEADER=$(sed -n '1,/^__ARCHIVE__$/p' "$PKG")

            CMD="deploy.sh"
            [[ -f "$LTMP/data/marketplace.db" ]] && CMD="$CMD --with-db"
            [[ -d "$LTMP/uploads" ]] && CMD="$CMD --with-images"
            SET_T=$(echo "$HEADER" | grep '^SET_TITLE=' | head -1 | sed 's/^SET_TITLE=//' | tr -d '"')
            SET_TL=$(echo "$HEADER" | grep '^SET_TAGLINE=' | head -1 | sed 's/^SET_TAGLINE=//' | tr -d '"')
            SET_OW=$(echo "$HEADER" | grep '^SET_OWNER=' | head -1 | sed 's/^SET_OWNER=//' | tr -d '"')
            [[ -n "$SET_T" ]] && CMD="$CMD --title '$SET_T'"
            [[ -n "$SET_TL" ]] && CMD="$CMD --tagline '$SET_TL'"
            [[ -n "$SET_OW" ]] && CMD="$CMD --owner '$SET_OW'"
            echo "Equivalent build command:"
            echo "  $CMD"
            exit 0
            ;;
        --output)        PACKAGE="$2"; shift 2 ;;
        -h|--help)       usage ;;
        *) echo "Unknown option: $1"; usage ;;
    esac
done

# --- Build payload ---
TMPDIR=$(mktemp -d)
trap 'rm -rf "$TMPDIR"' EXIT

echo "Assembling payload..."

# Code files (always included)
for f in "$SITE_DIR"/*.php; do
    [ -f "$f" ] && cp "$f" "$TMPDIR"/
done
cp "$SITE_DIR"/.htaccess "$TMPDIR"/ 2>/dev/null || true
cp "$SITE_DIR"/.user.ini "$TMPDIR"/ 2>/dev/null || true
cp -r "$SITE_DIR"/css "$TMPDIR"/
cp -r "$SITE_DIR"/js "$TMPDIR"/
find "$TMPDIR" -name '*.bak' -delete
echo "  + code files (PHP, JS, CSS)"

# Database (optional)
if [[ $WITH_DB -eq 1 ]]; then
    DB_FILE="$SITE_DIR/data/marketplace.db"
    if [[ ! -f "$DB_FILE" ]]; then
        echo "Error: marketplace.db not found. Run the site locally first to create it."
        exit 1
    fi
    mkdir -p "$TMPDIR/data"
    cp "$DB_FILE" "$TMPDIR/data/"
    echo "  + marketplace.db"
fi

# Images (optional)
if [[ $WITH_IMAGES -eq 1 ]]; then
    cp -r "$SITE_DIR/uploads" "$TMPDIR"/
    COUNT=$(find "$TMPDIR/uploads" -type f 2>/dev/null | wc -l)
    echo "  + uploads/ ($COUNT images)"
fi

# Create tar.gz payload
TAR_TMP=$(mktemp)
tar -czf "$TAR_TMP" -C "$TMPDIR" .

# --- Generate self-extracting script ---
MANIFEST="code"
[[ $WITH_DB -eq 1 ]] && MANIFEST="$MANIFEST, database"
[[ $WITH_IMAGES -eq 1 ]] && MANIFEST="$MANIFEST, images"
[[ -n "$SET_TITLE" ]] && MANIFEST="$MANIFEST, title"
[[ -n "$SET_TAGLINE" ]] && MANIFEST="$MANIFEST, tagline"
[[ -n "$SET_OWNER" ]] && MANIFEST="$MANIFEST, owner"

BUILT=$(date '+%Y-%m-%d %H:%M')

# Part 1: shebang
cat > "$PACKAGE" <<'SCRIPT_HEAD'
#!/bin/bash
set -euo pipefail
SCRIPT_HEAD

# Part 2: baked-in configuration (expanded at build time)
cat >> "$PACKAGE" <<SCRIPT_VARS
MANIFEST="$MANIFEST"
BUILT="$BUILT"
SET_TITLE="$SET_TITLE"
SET_TAGLINE="$SET_TAGLINE"
SET_OWNER="$SET_OWNER"
SCRIPT_VARS

# Part 3: script body (literal — no expansion)
cat >> "$PACKAGE" <<'SCRIPT_BODY'

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    echo "2nd Hand deployment archive (built: $BUILT)"
    echo "Contents: $MANIFEST"
    echo ""
    echo "Usage: $0 [site-directory]"
    echo "  Deploys to the given directory (default: current directory)"
    echo "  Creates a backup before overwriting"
    echo ""
    echo "  $0 --list     Show package contents"
    exit 0
fi

if [[ "${1:-}" == "--list" ]]; then
    SIZE=$(du -h "$0" | cut -f1)
    echo "=== Package: $(basename "$0") ($SIZE) ==="
    echo "Built:    $BUILT"
    echo "Contents: $MANIFEST"
    echo ""
    SKIP=$(awk '/^__ARCHIVE__$/{print NR + 1; exit 0}' "$0")
    LTMP=$(mktemp -d)
    trap 'rm -rf "$LTMP"' EXIT
    tail -n +"$SKIP" "$0" | tar -xzf - -C "$LTMP" 2>/dev/null
    echo "Files:"
    (cd "$LTMP" && find . -type f | sort | sed 's|^\./|  |')
    echo ""
    if [[ -f "$LTMP/data/marketplace.db" ]]; then
        SIZE_DB=$(du -h "$LTMP/data/marketplace.db" | cut -f1)
        echo "Database: $SIZE_DB"
    fi
    if [[ -d "$LTMP/uploads" ]]; then
        COUNT=$(find "$LTMP/uploads" -type f | wc -l)
        echo "Images: $COUNT"
    fi
    [[ -n "$SET_TITLE" ]] && echo "Title: $SET_TITLE"
    [[ -n "$SET_TAGLINE" ]] && echo "Tagline: $SET_TAGLINE"
    [[ -n "$SET_OWNER" ]] && echo "Owner: $SET_OWNER"
    exit 0
fi

SITE_DIR="${1:-.}"

if [[ ! -f "$SITE_DIR/config.php" ]]; then
    echo "Error: config.php not found in $SITE_DIR"
    echo "Are you in the right directory? Run: $0 --help"
    exit 1
fi

echo "=== 2nd Hand Deploy (built: $BUILT) ==="
echo "Target:   $(cd "$SITE_DIR" && pwd)"
echo "Contents: $MANIFEST"
echo ""

# Backup current files
BACKUP="$SITE_DIR/.backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP"
for f in "$SITE_DIR"/*.php "$SITE_DIR"/.htaccess "$SITE_DIR"/.user.ini; do
    [ -f "$f" ] && cp "$f" "$BACKUP"/
done
[ -d "$SITE_DIR/css" ] && cp -r "$SITE_DIR/css" "$BACKUP"/
[ -d "$SITE_DIR/js" ] && cp -r "$SITE_DIR/js" "$BACKUP"/
echo "Backup: $BACKUP"

# Extract archive
echo "Extracting..."
SKIP=$(awk '/^__ARCHIVE__$/{print NR + 1; exit 0}' "$0")
tail -n +"$SKIP" "$0" | tar -xzf - --no-same-owner --no-same-permissions -C "$SITE_DIR"

# Ensure directories exist
mkdir -p "$SITE_DIR/data" "$SITE_DIR/uploads"

# Apply title/tagline/owner to database via PHP
if [[ -n "$SET_TITLE" || -n "$SET_TAGLINE" || -n "$SET_OWNER" ]]; then
    PHP_BIN=$(command -v php 2>/dev/null || echo "")
    if [[ -n "$PHP_BIN" ]]; then
        "$PHP_BIN" -r '
            $db_file = $argv[1] . "/data/marketplace.db";
            if (!file_exists($db_file)) exit(0);
            $db = new PDO("sqlite:" . $db_file);
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
            if ($argv[2] !== "") { $stmt->execute(["site_title", $argv[2]]); echo "  Title: " . $argv[2] . "\n"; }
            if ($argv[3] !== "") { $stmt->execute(["site_tagline", $argv[3]]); echo "  Tagline: " . $argv[3] . "\n"; }
            if ($argv[4] !== "") { $stmt->execute(["owner_name", $argv[4]]); echo "  Owner: " . $argv[4] . "\n"; }
        ' -- "$SITE_DIR" "$SET_TITLE" "$SET_TAGLINE" "$SET_OWNER"
    else
        echo "Warning: PHP not found. Title/tagline/owner not applied — update via admin panel."
    fi
fi

echo ""
echo "Done. Site updated successfully."
exit 0

__ARCHIVE__
SCRIPT_BODY

# Append binary payload
cat "$TAR_TMP" >> "$PACKAGE"
rm -f "$TAR_TMP"
chmod +x "$PACKAGE"

SIZE=$(du -h "$PACKAGE" | cut -f1)
echo ""
echo "=== Package ready: $(basename "$PACKAGE") ($SIZE) ==="
echo ""
echo "Deploy to server:"
echo "  1. Upload via SFTP:  put $(basename "$PACKAGE")"
echo "  2. SSH and run:      cd ~/www/2h && bash ~/$(basename "$PACKAGE")"
echo "  Or with path:        bash $(basename "$PACKAGE") ~/www/2h"
echo "  Show info:           bash $(basename "$PACKAGE") --help"
echo "  Inspect contents:    bash $(basename "$PACKAGE") --list"
