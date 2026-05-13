<?php
define('DATA_DIR', __DIR__ . '/data');
define('UPLOADS_DIR', __DIR__ . '/uploads');
define('ITEMS_FILE', DATA_DIR . '/items.json');
define('OFFERS_FILE', DATA_DIR . '/offers.json');
define('SETTINGS_FILE', DATA_DIR . '/settings.json');

function load_settings(): array {
    $defaults = [
        'site_title' => '2nd Hand',
        'site_tagline' => 'Electronics, Tools, Home & Garage',
        'owner_name' => '',
        'notification_email' => '',
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_encryption' => 'tls',
        'smtp_from' => 'noreply@example.com',
    ];
    if (!file_exists(SETTINGS_FILE)) return $defaults;
    $data = json_decode(file_get_contents(SETTINGS_FILE), true);
    return is_array($data) ? array_merge($defaults, $data) : $defaults;
}

function save_settings(array $settings): void {
    file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$_settings = load_settings();
define('SITE_TITLE', $_settings['site_title']);
define('SITE_TAGLINE', $_settings['site_tagline']);
define('OWNER_NAME', $_settings['owner_name']);
define('SITE_URL', ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('CURRENCY', '€');
define('ADMIN_PASSWORD_HASH', '$2y$10$YR1BdGf2bn7x.Kyw5DcMEufMOuZ9Jx10c0nY/hPz7IO726P.Jh5wC');

session_start();

function is_admin(): bool {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function require_admin(): void {
    if (!is_admin()) {
        header('Location: admin.php?action=login');
        exit;
    }
}

function load_items(): array {
    if (!file_exists(ITEMS_FILE)) return [];
    $data = json_decode(file_get_contents(ITEMS_FILE), true);
    return is_array($data) ? $data : [];
}

function save_items(array $items): void {
    file_put_contents(ITEMS_FILE, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function load_offers(): array {
    if (!file_exists(OFFERS_FILE)) return [];
    $data = json_decode(file_get_contents(OFFERS_FILE), true);
    return is_array($data) ? $data : [];
}

function save_offers(array $offers): void {
    file_put_contents(OFFERS_FILE, json_encode($offers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function get_item(string $id): ?array {
    $items = load_items();
    foreach ($items as $item) {
        if ($item['id'] === $id) return $item;
    }
    return null;
}

function get_offers_for_item(string $item_id): array {
    $offers = load_offers();
    $result = [];
    foreach ($offers as $offer) {
        if ($offer['item_id'] === $item_id) $result[] = $offer;
    }
    return $result;
}

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
    $tags = [];
    foreach ($items as $item) {
        foreach (get_item_tags($item) as $tag) {
            $tags[$tag] = ($tags[$tag] ?? 0) + 1;
        }
    }
    ksort($tags);
    return $tags;
}

function send_offer_notification(array $item, float $amount, string $buyer_email, int $offer_id): bool {
    $settings = load_settings();
    if (empty($settings['notification_email']) || empty($settings['smtp_host'])) return false;

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

    return smtp_send(
        $settings['smtp_host'],
        (int)$settings['smtp_port'],
        $settings['smtp_user'],
        $settings['smtp_pass'],
        $settings['smtp_encryption'],
        $settings['smtp_from'],
        $settings['notification_email'],
        $subject,
        $body
    );
}

function send_offer_confirmation(array $item, float $amount, string $buyer_email, int $offer_id): bool {
    $settings = load_settings();
    if (empty($settings['smtp_host'])) return false;

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

    return smtp_send(
        $settings['smtp_host'],
        (int)$settings['smtp_port'],
        $settings['smtp_user'],
        $settings['smtp_pass'],
        $settings['smtp_encryption'],
        $settings['smtp_from'],
        $buyer_email,
        $subject,
        $body
    );
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
