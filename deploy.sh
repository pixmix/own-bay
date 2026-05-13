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
  --with-items       Include items.json (overwrites server catalogue)
  --with-offers      Include offers.json (overwrites server offers)
  --with-settings    Include settings.json (overwrites SMTP config)
  --with-images      Include uploads/ directory (item images)
  --title TEXT       Set site title in settings
  --tagline TEXT     Set site tagline in settings
  --owner TEXT       Set owner name (footer copyright, privacy policy)
  --password [PASS]  Set admin password (prompts if PASS omitted)
  --no-password      Preserve server's existing password (default)
  --list FILE        Inspect a built package and show equivalent build command
  --output FILE      Output file (default: 2h-deploy.sh)
  -h, --help         Show this help

Default (no flags): code-only update that preserves the server's
password, data files, and images.

Examples:
  $(basename "$0")
      Code-only update — just PHP, JS, CSS

  $(basename "$0") --with-items --with-images
      Update code + catalogue + images, preserve password

  $(basename "$0") --with-items --with-settings --password 's3cret'
      Full deploy with items, SMTP config, and new password

  $(basename "$0") --list 2h-deploy.sh
      Show package contents and the command that built it

Deploy to server:
  1. Upload via SFTP:  put 2h-deploy.sh
  2. SSH and run:      cd ~/www/2h && bash ~/2h-deploy.sh
  Or with path:        bash 2h-deploy.sh ~/www/2h
EOF
    exit 0
}

WITH_ITEMS=0
WITH_OFFERS=0
WITH_SETTINGS=0
WITH_IMAGES=0
SET_PASSWORD=0
PASSWORD=""
SET_TITLE=""
SET_TAGLINE=""
SET_OWNER=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --with-items)    WITH_ITEMS=1; shift ;;
        --with-offers)   WITH_OFFERS=1; shift ;;
        --with-settings) WITH_SETTINGS=1; shift ;;
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
            # Delegate to the package's own --list for the inventory
            bash "$PKG" --list
            echo ""
            # Reconstruct the equivalent build command
            SKIP=$(awk '/^__ARCHIVE__$/{print NR + 1; exit 0}' "$PKG")
            LTMP=$(mktemp -d)
            trap 'rm -rf "$LTMP"' EXIT
            tail -n +"$SKIP" "$PKG" | tar -xzf - -C "$LTMP" 2>/dev/null
            HEADER=$(sed -n '1,/^__ARCHIVE__$/p' "$PKG")
            PRESERVE=$(echo "$HEADER" | grep '^PRESERVE_PASSWORD=' | head -1 | cut -d= -f2)

            CMD="deploy.sh"
            [[ -f "$LTMP/data/items.json" ]] && CMD="$CMD --with-items"
            [[ -f "$LTMP/data/offers.json" ]] && CMD="$CMD --with-offers"
            # --with-settings if settings.json has SMTP config (not just title/tagline)
            if [[ -f "$LTMP/data/settings.json" ]] && grep -q '"smtp_host"' "$LTMP/data/settings.json" 2>/dev/null; then
                CMD="$CMD --with-settings"
            fi
            [[ -d "$LTMP/uploads" ]] && CMD="$CMD --with-images"
            # title/tagline if non-default
            if [[ -f "$LTMP/data/settings.json" ]]; then
                T=$(grep '"site_title"' "$LTMP/data/settings.json" 2>/dev/null | sed 's/.*: *"//;s/".*//') || true
                TL=$(grep '"site_tagline"' "$LTMP/data/settings.json" 2>/dev/null | sed 's/.*: *"//;s/".*//') || true
                OW=$(grep '"owner_name"' "$LTMP/data/settings.json" 2>/dev/null | sed 's/.*: *"//;s/".*//') || true
                [[ -n "$T" && "$T" != "2nd Hand" ]] && CMD="$CMD --title '$T'"
                [[ -n "$TL" && "$TL" != "Electronics, Tools, Home & Garage" ]] && CMD="$CMD --tagline '$TL'"
                [[ -n "$OW" ]] && CMD="$CMD --owner '$OW'"
            fi
            [[ "${PRESERVE:-}" == "0" ]] && CMD="$CMD --password '...'"
            echo "Equivalent build command:"
            echo "  $CMD"
            exit 0
            ;;
        --password)
            SET_PASSWORD=1
            if [[ -n "${2:-}" && "${2:-}" != --* ]]; then
                PASSWORD="$2"; shift 2
            else
                shift
            fi
            ;;
        --no-password)   shift ;;
        --output)        PACKAGE="$2"; shift 2 ;;
        -h|--help)       usage ;;
        *) echo "Unknown option: $1"; usage ;;
    esac
done

# --- Password ---
if [[ $SET_PASSWORD -eq 1 ]]; then
    if [[ -z "$PASSWORD" ]]; then
        read -rsp "Enter admin password: " PASSWORD
        echo
        read -rsp "Confirm password: " PASSWORD2
        echo
        if [[ "$PASSWORD" != "$PASSWORD2" ]]; then
            echo "Error: passwords do not match."
            exit 1
        fi
    fi
    if [[ -z "$PASSWORD" ]]; then
        echo "Error: password cannot be empty."
        exit 1
    fi
    HASH=$(printf '%s' "$PASSWORD" | docker exec -i 2nd-hand-web-1 php -r 'echo password_hash(file_get_contents("php://stdin"), PASSWORD_DEFAULT);' 2>/dev/null \
        || printf '%s' "$PASSWORD" | php -r 'echo password_hash(file_get_contents("php://stdin"), PASSWORD_DEFAULT);' 2>/dev/null)
    if [[ -z "$HASH" ]]; then
        echo "Error: could not generate password hash. Is Docker or PHP available?"
        exit 1
    fi
    echo "Password hash generated."
fi

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
echo "  + code files (PHP, JS, CSS)"

# Apply password hash
if [[ $SET_PASSWORD -eq 1 ]]; then
    ESCAPED_HASH=$(printf '%s\n' "$HASH" | sed 's/[&/\]/\\&/g')
    sed -i "s|define('ADMIN_PASSWORD_HASH', '.*');|define('ADMIN_PASSWORD_HASH', '$ESCAPED_HASH');|" "$TMPDIR/config.php"
    echo "  + new admin password"
fi

# Data files (optional — excluded files are simply not in the archive)
if [[ $WITH_ITEMS -eq 1 ]]; then
    mkdir -p "$TMPDIR/data"
    cp "$SITE_DIR/data/items.json" "$TMPDIR/data/"
    echo "  + items.json ($(grep -c '"id"' "$SITE_DIR/data/items.json" 2>/dev/null || echo 0) items)"
fi
if [[ $WITH_OFFERS -eq 1 ]]; then
    mkdir -p "$TMPDIR/data"
    cp "$SITE_DIR/data/offers.json" "$TMPDIR/data/"
    echo "  + offers.json"
fi
if [[ $WITH_SETTINGS -eq 1 ]]; then
    mkdir -p "$TMPDIR/data"
    cp "$SITE_DIR/data/settings.json" "$TMPDIR/data/"
    echo "  + settings.json (SMTP config)"
fi

# Apply title/tagline to settings (after --with-settings so it merges on top)
if [[ -n "$SET_TITLE" || -n "$SET_TAGLINE" || -n "$SET_OWNER" ]]; then
    mkdir -p "$TMPDIR/data"
    if [[ ! -f "$TMPDIR/data/settings.json" ]]; then
        SETTINGS_SRC="$SITE_DIR/data/settings.json"
        if [[ -f "$SETTINGS_SRC" ]]; then
            cp "$SETTINGS_SRC" "$TMPDIR/data/settings.json"
        else
            echo '{}' > "$TMPDIR/data/settings.json"
        fi
    fi
    SETTINGS_JSON=$(docker exec -i 2nd-hand-web-1 php -r '
        $s = json_decode(file_get_contents("php://stdin"), true) ?: [];
        if ($argv[1] !== "") $s["site_title"] = $argv[1];
        if ($argv[2] !== "") $s["site_tagline"] = $argv[2];
        if ($argv[3] !== "") $s["owner_name"] = $argv[3];
        echo json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    ' -- "$SET_TITLE" "$SET_TAGLINE" "$SET_OWNER" < "$TMPDIR/data/settings.json" 2>/dev/null \
    || php -r '
        $s = json_decode(file_get_contents("php://stdin"), true) ?: [];
        if ($argv[1] !== "") $s["site_title"] = $argv[1];
        if ($argv[2] !== "") $s["site_tagline"] = $argv[2];
        if ($argv[3] !== "") $s["owner_name"] = $argv[3];
        echo json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    ' -- "$SET_TITLE" "$SET_TAGLINE" "$SET_OWNER" < "$TMPDIR/data/settings.json")
    echo "$SETTINGS_JSON" > "$TMPDIR/data/settings.json"
    [[ -n "$SET_TITLE" ]] && echo "  + site title: $SET_TITLE"
    [[ -n "$SET_TAGLINE" ]] && echo "  + tagline: $SET_TAGLINE"
    [[ -n "$SET_OWNER" ]] && echo "  + owner: $SET_OWNER"
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
PRESERVE_PASSWORD=$( [[ $SET_PASSWORD -eq 1 ]] && echo 0 || echo 1 )

MANIFEST="code"
[[ $WITH_ITEMS -eq 1 ]] && MANIFEST="$MANIFEST, items"
[[ $WITH_OFFERS -eq 1 ]] && MANIFEST="$MANIFEST, offers"
[[ $WITH_SETTINGS -eq 1 ]] && MANIFEST="$MANIFEST, settings"
[[ $WITH_IMAGES -eq 1 ]] && MANIFEST="$MANIFEST, images"
[[ $SET_PASSWORD -eq 1 ]] && MANIFEST="$MANIFEST, new password" || MANIFEST="$MANIFEST (password preserved)"

BUILT=$(date '+%Y-%m-%d %H:%M')

# Part 1: shebang
cat > "$PACKAGE" <<'SCRIPT_HEAD'
#!/bin/bash
set -euo pipefail
SCRIPT_HEAD

# Part 2: baked-in configuration (expanded at build time)
cat >> "$PACKAGE" <<SCRIPT_VARS
PRESERVE_PASSWORD=$PRESERVE_PASSWORD
MANIFEST="$MANIFEST"
BUILT="$BUILT"
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
    [[ $PRESERVE_PASSWORD -eq 1 ]] && echo "Password: preserved from server" || echo "Password: new hash included"
    echo ""
    SKIP=$(awk '/^__ARCHIVE__$/{print NR + 1; exit 0}' "$0")
    LTMP=$(mktemp -d)
    trap 'rm -rf "$LTMP"' EXIT
    tail -n +"$SKIP" "$0" | tar -xzf - -C "$LTMP" 2>/dev/null
    echo "Files:"
    (cd "$LTMP" && find . -type f | sort | sed 's|^\./|  |')
    echo ""
    if [[ -f "$LTMP/data/settings.json" ]]; then
        echo "Settings (data/settings.json):"
        sed 's/^/  /' "$LTMP/data/settings.json"
        echo ""
    fi
    if [[ -f "$LTMP/data/items.json" ]]; then
        COUNT=$(grep -c '"id"' "$LTMP/data/items.json" 2>/dev/null || echo 0)
        echo "Items: $COUNT"
    fi
    if [[ -f "$LTMP/data/offers.json" ]]; then
        COUNT=$(grep -c '"id"' "$LTMP/data/offers.json" 2>/dev/null || echo 0)
        echo "Offers: $COUNT"
    fi
    if [[ -d "$LTMP/uploads" ]]; then
        COUNT=$(find "$LTMP/uploads" -type f | wc -l)
        echo "Images: $COUNT"
    fi
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

# Preserve password hash
HASH_TMP=""
if [[ $PRESERVE_PASSWORD -eq 1 ]]; then
    HASH_TMP=$(mktemp)
    grep "ADMIN_PASSWORD_HASH" "$SITE_DIR/config.php" > "$HASH_TMP" 2>/dev/null || true
fi

# Extract archive
echo "Extracting..."
SKIP=$(awk '/^__ARCHIVE__$/{print NR + 1; exit 0}' "$0")
tail -n +"$SKIP" "$0" | tar -xzf - --no-same-owner --no-same-permissions -C "$SITE_DIR"

# Restore password
if [[ $PRESERVE_PASSWORD -eq 1 && -s "${HASH_TMP:-}" ]]; then
    OLD_LINE=$(cat "$HASH_TMP")
    while IFS= read -r line || [[ -n "$line" ]]; do
        if [[ "$line" == *"ADMIN_PASSWORD_HASH"* ]]; then
            printf '%s\n' "$OLD_LINE"
        else
            printf '%s\n' "$line"
        fi
    done < "$SITE_DIR/config.php" > "$SITE_DIR/config.php.tmp"
    mv "$SITE_DIR/config.php.tmp" "$SITE_DIR/config.php"
    rm -f "$HASH_TMP"
    echo "Password: preserved"
else
    [[ $PRESERVE_PASSWORD -eq 0 ]] && echo "Password: updated"
    [ -n "${HASH_TMP:-}" ] && rm -f "$HASH_TMP"
fi

# Ensure directories exist
mkdir -p "$SITE_DIR/data" "$SITE_DIR/uploads"

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
