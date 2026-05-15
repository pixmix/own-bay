<?php
define('DATA_DIR', __DIR__ . '/data');
define('UPLOADS_DIR', __DIR__ . '/uploads');
define('DB_FILE', DATA_DIR . '/marketplace.db');

// Legacy — used only during migration from JSON to SQLite
define('ADMIN_PASSWORD_HASH', '$2y$10$YR1BdGf2bn7x.Kyw5DcMEufMOuZ9Jx10c0nY/hPz7IO726P.Jh5wC');

$SETTINGS_DEFAULTS = [
    'site_title' => '2nd Hand',
    'site_tagline' => 'Electronics, Tools, Home & Garage',
    'owner_name' => '',
    'notification_email' => '',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_encryption' => 'tls',
    'smtp_from' => 'noreply@example.com',
    'max_admins' => '1',
    'show_registration_link' => '0',
    'currency' => '€',
];

// ── Database ────────────────────────────────────────────────────────

function get_db(): PDO {
    static $db = null;
    if ($db) return $db;
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys=ON');
    return $db;
}

function init_db(): void {
    if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0755, true);
    $exists = file_exists(DB_FILE);
    $db = get_db();
    if (!$exists) {
        create_schema($db);
        $json_items = DATA_DIR . '/items.json';
        if (file_exists($json_items)) {
            migrate_from_json($db);
        } else {
            seed_defaults($db);
        }
    }
}

function create_schema(PDO $db): void {
    $db->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL,
            is_super_admin INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            confirmation_code TEXT,
            confirmation_expires_at TEXT,
            confirmed_at TEXT,
            reset_code TEXT,
            reset_expires_at TEXT,
            created_at TEXT NOT NULL DEFAULT (strftime(\'%Y-%m-%dT%H:%M:%S+00:00\',\'now\')),
            last_login_at TEXT
        );
        CREATE TABLE IF NOT EXISTS items (
            id TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT \'\',
            price REAL NOT NULL,
            status TEXT NOT NULL DEFAULT \'available\',
            created_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE INDEX IF NOT EXISTS idx_items_user ON items(user_id);
        CREATE INDEX IF NOT EXISTS idx_items_status ON items(status);
        CREATE TABLE IF NOT EXISTS item_tags (
            item_id TEXT NOT NULL,
            tag TEXT NOT NULL,
            PRIMARY KEY (item_id, tag),
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS item_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id TEXT NOT NULL,
            path TEXT NOT NULL,
            alt_text TEXT NOT NULL DEFAULT \'\',
            sort_order INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        );
        CREATE INDEX IF NOT EXISTS idx_item_images_item ON item_images(item_id);
        CREATE TABLE IF NOT EXISTS offers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id TEXT NOT NULL,
            amount REAL NOT NULL,
            status TEXT NOT NULL DEFAULT \'pending\',
            created_at TEXT NOT NULL DEFAULT (strftime(\'%Y-%m-%dT%H:%M:%S+00:00\',\'now\')),
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        );
        CREATE INDEX IF NOT EXISTS idx_offers_item ON offers(item_id);
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            email TEXT COLLATE NOCASE,
            attempted_at TEXT NOT NULL DEFAULT (strftime(\'%Y-%m-%dT%H:%M:%S+00:00\',\'now\')),
            success INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS idx_login_ip ON login_attempts(ip_address, attempted_at);
    ');
}

function seed_defaults(PDO $db): void {
    global $SETTINGS_DEFAULTS;
    $stmt = $db->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    foreach ($SETTINGS_DEFAULTS as $k => $v) {
        $stmt->execute([$k, $v]);
    }
}

function migrate_from_json(PDO $db): void {
    global $SETTINGS_DEFAULTS;

    $db->beginTransaction();
    try {
        // Settings
        $settings_file = DATA_DIR . '/settings.json';
        $settings = $SETTINGS_DEFAULTS;
        if (file_exists($settings_file)) {
            $data = json_decode(file_get_contents($settings_file), true);
            if (is_array($data)) $settings = array_merge($settings, $data);
        }
        $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
        foreach ($settings as $k => $v) {
            $stmt->execute([$k, (string)$v]);
        }

        // Super-admin user from legacy password + notification email
        $email = $settings['notification_email'] ?: 'admin@localhost';
        $db->prepare('INSERT INTO users (email, password_hash, is_super_admin, confirmed_at) VALUES (?, ?, 1, datetime(\'now\'))')
            ->execute([$email, ADMIN_PASSWORD_HASH]);
        $super_id = (int)$db->lastInsertId();

        // Items
        $items_file = DATA_DIR . '/items.json';
        $items = [];
        if (file_exists($items_file)) {
            $data = json_decode(file_get_contents($items_file), true);
            if (is_array($data)) $items = $data;
        }

        $item_stmt = $db->prepare('INSERT INTO items (id, user_id, title, description, price, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $tag_stmt = $db->prepare('INSERT OR IGNORE INTO item_tags (item_id, tag) VALUES (?, ?)');
        $img_stmt = $db->prepare('INSERT INTO item_images (item_id, path, alt_text, sort_order) VALUES (?, ?, ?, ?)');

        foreach ($items as $item) {
            $item_stmt->execute([
                $item['id'],
                $super_id,
                $item['title'],
                $item['description'] ?? '',
                (float)$item['price'],
                $item['status'] ?? 'available',
                $item['created_at'] ?? date('c'),
            ]);

            $tags = $item['tags'] ?? [];
            foreach ($tags as $tag) {
                $tag_stmt->execute([$item['id'], $tag]);
            }

            $images = $item['images'] ?? ($item['image'] ? [$item['image']] : []);
            $alts = $item['image_alts'] ?? [];
            foreach ($images as $i => $path) {
                $img_stmt->execute([$item['id'], $path, $alts[$i] ?? '', $i]);
            }
        }

        // Offers
        $offers_file = DATA_DIR . '/offers.json';
        if (file_exists($offers_file)) {
            $offers = json_decode(file_get_contents($offers_file), true);
            if (is_array($offers)) {
                $offer_stmt = $db->prepare('INSERT INTO offers (item_id, amount, status, created_at) VALUES (?, ?, ?, ?)');
                foreach ($offers as $offer) {
                    $offer_stmt->execute([
                        $offer['item_id'],
                        (float)$offer['amount'],
                        $offer['status'] ?? 'pending',
                        $offer['created_at'] ?? date('c'),
                    ]);
                }
            }
        }

        $db->commit();

        // Rename migrated files
        foreach (['items.json', 'offers.json', 'settings.json'] as $f) {
            $path = DATA_DIR . '/' . $f;
            if (file_exists($path)) @rename($path, $path . '.migrated');
        }
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

// ── Settings ────────────────────────────────────────────────────────

function load_settings(): array {
    global $SETTINGS_DEFAULTS;
    $db = get_db();
    $rows = $db->query('SELECT key, value FROM settings')->fetchAll();
    $settings = $SETTINGS_DEFAULTS;
    foreach ($rows as $row) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

function get_setting(string $key, string $default = ''): string {
    global $SETTINGS_DEFAULTS;
    $db = get_db();
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if ($row) return $row['value'];
    return $SETTINGS_DEFAULTS[$key] ?? $default;
}

function save_setting(string $key, string $value): void {
    $db = get_db();
    $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute([$key, $value]);
}

function save_settings(array $settings): void {
    $db = get_db();
    $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
    foreach ($settings as $k => $v) {
        $stmt->execute([$k, (string)$v]);
    }
}

// ── Auth & Session ──────────────────────────────────────────────────

function get_logged_in_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1 AND confirmed_at IS NOT NULL');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function is_admin(): bool {
    return get_logged_in_user() !== null;
}

function is_super_admin(): bool {
    $user = get_logged_in_user();
    return $user && $user['is_super_admin'];
}

function require_admin(): void {
    if (!is_admin()) {
        header('Location: admin.php?action=login');
        exit;
    }
}

function require_super_admin(): void {
    if (!is_super_admin()) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied.';
        exit;
    }
}

// ── CSRF ────────────────────────────────────────────────────────────

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): void {
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Invalid or expired form submission.';
        exit;
    }
}

// ── Items ───────────────────────────────────────────────────────────

function hydrate_item(array $row): array {
    $db = get_db();

    $stmt = $db->prepare('SELECT tag FROM item_tags WHERE item_id = ? ORDER BY tag');
    $stmt->execute([$row['id']]);
    $row['tags'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $db->prepare('SELECT path, alt_text FROM item_images WHERE item_id = ? ORDER BY sort_order');
    $stmt->execute([$row['id']]);
    $imgs = $stmt->fetchAll();
    $row['images'] = array_column($imgs, 'path');
    $row['image_alts'] = array_column($imgs, 'alt_text');

    return $row;
}

function get_item(string $id): ?array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM items WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? hydrate_item($row) : null;
}

function get_all_available_items(): array {
    $db = get_db();
    $rows = $db->query('SELECT * FROM items WHERE status = \'available\' ORDER BY created_at DESC')->fetchAll();
    return array_map('hydrate_item', $rows);
}

function get_items_for_user(int $user_id): array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM items WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$user_id]);
    return array_map('hydrate_item', $stmt->fetchAll());
}

function get_all_items(): array {
    $db = get_db();
    return array_map('hydrate_item', $db->query('SELECT * FROM items ORDER BY created_at DESC')->fetchAll());
}

function save_item_tags(string $item_id, array $tags): void {
    $db = get_db();
    $db->prepare('DELETE FROM item_tags WHERE item_id = ?')->execute([$item_id]);
    $stmt = $db->prepare('INSERT INTO item_tags (item_id, tag) VALUES (?, ?)');
    foreach ($tags as $tag) {
        if (trim($tag) !== '') $stmt->execute([$item_id, trim($tag)]);
    }
}

function save_item_images(string $item_id, array $paths, array $alts): void {
    $db = get_db();
    $db->prepare('DELETE FROM item_images WHERE item_id = ?')->execute([$item_id]);
    $stmt = $db->prepare('INSERT INTO item_images (item_id, path, alt_text, sort_order) VALUES (?, ?, ?, ?)');
    foreach ($paths as $i => $path) {
        $stmt->execute([$item_id, $path, $alts[$i] ?? '', $i]);
    }
}

function get_item_image_paths(string $item_id): array {
    $db = get_db();
    $stmt = $db->prepare('SELECT path FROM item_images WHERE item_id = ?');
    $stmt->execute([$item_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function delete_item_files(string $item_id): void {
    foreach (get_item_image_paths($item_id) as $path) {
        $full = __DIR__ . '/' . $path;
        if (file_exists($full)) @unlink($full);
    }
}

// ── Tags ────────────────────────────────────────────────────────────

function get_all_tags_from_items(array $items): array {
    $tags = [];
    foreach ($items as $item) {
        foreach ($item['tags'] ?? [] as $tag) {
            $tags[$tag] = ($tags[$tag] ?? 0) + 1;
        }
    }
    ksort($tags);
    return $tags;
}

// Backward-compatible aliases
function get_item_images(array $item): array {
    if (!empty($item['images'])) return $item['images'];
    if (!empty($item['image'])) return [$item['image']];
    return [];
}

function get_image_alt(array $item, int $index = 0): string {
    $alts = $item['image_alts'] ?? [];
    if (!empty($alts[$index])) return $alts[$index];
    if ($index === 0) return $item['title'];
    return $item['title'] . ' — ' . ($index + 1);
}

function get_item_tags(array $item): array {
    return $item['tags'] ?? [];
}

function get_all_tags(array $items): array {
    return get_all_tags_from_items($items);
}

// ── Offers ──────────────────────────────────────────────────────────

function get_offers_for_item(string $item_id): array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM offers WHERE item_id = ? ORDER BY created_at');
    $stmt->execute([$item_id]);
    return $stmt->fetchAll();
}

function count_offers_for_item(string $item_id): int {
    $db = get_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM offers WHERE item_id = ?');
    $stmt->execute([$item_id]);
    return (int)$stmt->fetchColumn();
}

// ── Users ───────────────────────────────────────────────────────────

function get_user(int $id): ?array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_user_by_email(string $email): ?array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

function get_super_admin(): ?array {
    $db = get_db();
    return $db->query('SELECT * FROM users WHERE is_super_admin = 1 LIMIT 1')->fetch() ?: null;
}

function count_active_admins(): int {
    $db = get_db();
    return (int)$db->query('SELECT COUNT(*) FROM users WHERE is_active = 1 AND confirmed_at IS NOT NULL')->fetchColumn();
}

function count_items_for_user(int $user_id): int {
    $db = get_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM items WHERE user_id = ?');
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

function registration_open(): bool {
    $max = (int)get_setting('max_admins', '1');
    if ($max === 0) return true;
    return count_active_admins() < $max;
}

// ── Cleanup ─────────────────────────────────────────────────────────

function cleanup_expired(): void {
    $db = get_db();
    // Unconfirmed registrations older than 2 hours
    $db->exec("DELETE FROM users WHERE confirmed_at IS NULL AND confirmation_expires_at < datetime('now')");
    // Login attempts older than 24 hours
    $db->exec("DELETE FROM login_attempts WHERE attempted_at < datetime('now', '-24 hours')");
}

// ── Rate limiting ───────────────────────────────────────────────────

function check_rate_limit(string $ip, int $window_seconds, int $max_attempts, ?string $email = null): bool {
    $db = get_db();
    if ($email) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at > datetime('now', '-' || ? || ' seconds')");
        $stmt->execute([$email, $window_seconds]);
        if ((int)$stmt->fetchColumn() >= $max_attempts) return false;
    }
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempted_at > datetime('now', '-' || ? || ' seconds')");
    $stmt->execute([$ip, $window_seconds]);
    return (int)$stmt->fetchColumn() < $max_attempts;
}

function log_login_attempt(string $ip, string $email, bool $success): void {
    $db = get_db();
    $db->prepare('INSERT INTO login_attempts (ip_address, email, success) VALUES (?, ?, ?)')->execute([$ip, $email, $success ? 1 : 0]);
}

// ── Helpers ─────────────────────────────────────────────────────────

function parse_markdown(string $text): string {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);
    $text = preg_replace('/^\- (.+)$/m', '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $text);
    $text = preg_replace('/\n{2,}/', '</p><p>', $text);
    $text = '<p>' . $text . '</p>';
    $text = str_replace('<p></p>', '', $text);
    $text = preg_replace('/<p>\s*(<h[1-3]>)/', '$1', $text);
    $text = preg_replace('/(<\/h[1-3]>)\s*<\/p>/', '$1', $text);
    $text = preg_replace('/<p>\s*(<ul>)/', '$1', $text);
    $text = preg_replace('/(<\/ul>)\s*<\/p>/', '$1', $text);
    return $text;
}

function generate_id(): string {
    return bin2hex(random_bytes(8));
}

function generate_confirmation_code(): string {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// ── Email ───────────────────────────────────────────────────────────

function send_email(string $to, string $subject, string $body): bool {
    $settings = load_settings();
    if (empty($settings['smtp_host'])) return false;
    return smtp_send(
        $settings['smtp_host'],
        (int)$settings['smtp_port'],
        $settings['smtp_user'],
        $settings['smtp_pass'],
        $settings['smtp_encryption'],
        $settings['smtp_from'],
        $to,
        $subject,
        $body
    );
}

function send_offer_notification(array $item, float $amount, string $buyer_email, int $offer_id): bool {
    $db = get_db();
    $stmt = $db->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$item['user_id']]);
    $owner_email = $stmt->fetchColumn();
    if (!$owner_email) return false;

    $above = $amount >= $item['price'] ? 'YES — at or above listed price' : 'no — below listed price';
    $subject = "Offer #{$offer_id}: {$item['title']} — " . CURRENCY . number_format($amount, 2);
    $body = "Offer #{$offer_id} on " . SITE_TITLE . "\n\n"
        . "Item: {$item['title']}\n"
        . "Listed price: " . CURRENCY . number_format($item['price'], 2) . "\n"
        . "Offer: " . CURRENCY . number_format($amount, 2) . "\n"
        . "Meets/exceeds price: {$above}\n"
        . "Buyer email: {$buyer_email}\n"
        . "Date: " . date('Y-m-d H:i:s') . "\n\n"
        . "Manage offers for this item:\n"
        . SITE_URL . "/admin.php?offers=" . urlencode($item['id']) . "\n";

    return send_email($owner_email, $subject, $body);
}

function send_offer_confirmation(array $item, float $amount, string $buyer_email, int $offer_id): bool {
    $subject = "Offer #{$offer_id} confirmed — {$item['title']}";
    $body = "Thank you for your offer on " . SITE_TITLE . ".\n\n"
        . "Offer: #{$offer_id}\n"
        . "Item: {$item['title']}\n"
        . "Your offer: " . CURRENCY . number_format($amount, 2) . "\n"
        . "Listed price: " . CURRENCY . number_format($item['price'], 2) . "\n\n"
        . "If your offer is selected, you will be contacted at this email address.\n"
        . "You can make additional offers at any time.\n\n"
        . "Your email address is not stored on our website — it was only\n"
        . "used to send this confirmation and to notify the seller.\n\n"
        . SITE_URL . "\n";

    return send_email($buyer_email, $subject, $body);
}

function send_confirmation_email(string $to, string $code): bool {
    $subject = 'Confirm your account — ' . SITE_TITLE;
    $body = "Your confirmation code is: {$code}\n\n"
        . "This code expires in 2 hours.\n\n"
        . "If you did not register for " . SITE_TITLE . ", you can safely ignore this email.\n";
    return send_email($to, $subject, $body);
}

function send_superadmin_notification(string $subject, string $body): bool {
    $admin = get_super_admin();
    if (!$admin) return false;
    return send_email($admin['email'], $subject, $body);
}

function smtp_send(string $host, int $port, string $user, string $pass, string $encryption, string $from, string $to, string $subject, string $body): bool {
    $timeout = 10;
    $body = str_replace("\r\n", "\n", $body);
    $body = str_replace("\n", "\r\n", $body);

    $ssl_ctx = stream_context_create(['ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => false,
        'allow_self_signed' => false,
    ]]);

    if ($encryption === 'ssl') {
        $socket = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ssl_ctx);
    } else {
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ssl_ctx);
    }
    if (!$socket) {
        error_log("SMTP connect failed: {$errstr} ({$errno})");
        return false;
    }

    stream_set_timeout($socket, $timeout);

    $read = function() use ($socket) {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    };

    $send = function(string $cmd) use ($socket, $read) {
        fwrite($socket, $cmd . "\r\n");
        return $read();
    };

    $read();
    $send("EHLO " . gethostname());

    if ($encryption === 'tls') {
        $send("STARTTLS");
        @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
        $send("EHLO " . gethostname());
    }

    if ($user && $pass) {
        $send("AUTH LOGIN");
        $send(base64_encode($user));
        $resp = $send(base64_encode($pass));
        if (strpos($resp, '235') === false) {
            error_log("SMTP AUTH failed: " . trim($resp));
            fclose($socket);
            return false;
        }
    }

    $send("MAIL FROM:<{$from}>");
    $send("RCPT TO:<{$to}>");
    $send("DATA");

    $headers = "From: " . SITE_TITLE . " <{$from}>\r\n"
        . "To: <{$to}>\r\n"
        . "Subject: {$subject}\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Date: " . date('r') . "\r\n";

    $resp = $send($headers . "\r\n" . $body . "\r\n.");

    $send("QUIT");
    fclose($socket);

    return strpos($resp, '250') !== false;
}

// ── Initialise ──────────────────────────────────────────────────────

init_db();

if (random_int(1, 20) === 1) cleanup_expired();

$_settings = load_settings();
define('SITE_TITLE', $_settings['site_title']);
define('SITE_TAGLINE', $_settings['site_tagline']);
define('OWNER_NAME', $_settings['owner_name'] ?? '');
define('SITE_URL', ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('CURRENCY', $_settings['currency'] ?? '€');

session_start();
