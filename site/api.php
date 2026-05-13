<?php
require_once __DIR__ . '/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'login':
        $password = $_POST['password'] ?? '';
        if (password_verify($password, ADMIN_PASSWORD_HASH)) {
            $_SESSION['admin'] = true;
            header('Location: admin.php');
        } else {
            header('Location: admin.php?action=login&error=1');
        }
        exit;

    case 'logout':
        session_destroy();
        header('Location: index.php');
        exit;

    case 'submit_offer':
        $item_id = $_POST['item_id'] ?? '';
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $amount = floatval($_POST['amount'] ?? 0);

        if (!empty($_POST['website'] ?? '')) {
            header('Location: item.php?id=' . urlencode($item_id) . '&offered=1');
            exit;
        }

        if (!$item_id || !$email || $amount <= 0) {
            header('HTTP/1.1 400 Bad Request');
            echo 'Invalid offer data.';
            exit;
        }

        $now = time();
        if (isset($_SESSION['last_offer_time']) && ($now - $_SESSION['last_offer_time']) < 5) {
            header('Location: item.php?id=' . urlencode($item_id) . '&error=too_fast');
            exit;
        }

        $item = get_item($item_id);
        if (!$item || $item['status'] !== 'available') {
            header('HTTP/1.1 404 Not Found');
            echo 'Item not found.';
            exit;
        }

        $offers = load_offers();
        $max_id = 0;
        foreach ($offers as $o) {
            if (is_numeric($o['id']) && intval($o['id']) > $max_id) {
                $max_id = intval($o['id']);
            }
        }
        $offer_id = $max_id + 1;

        $offers[] = [
            'id' => $offer_id,
            'item_id' => $item_id,
            'amount' => $amount,
            'created_at' => date('c'),
            'status' => 'pending',
        ];
        save_offers($offers);

        $_SESSION['last_offer_time'] = $now;
        $_SESSION['offer_flash'] = [
            'email' => $email,
            'amount' => $amount,
            'offer_id' => $offer_id,
        ];

        send_offer_notification($item, $amount, $email, $offer_id);
        send_offer_confirmation($item, $amount, $email, $offer_id);

        header('Location: item.php?id=' . urlencode($item_id) . '&offered=1');
        exit;

    case 'save_item':
        require_admin();
        $id = $_POST['id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $tags_raw = trim($_POST['tags'] ?? '');
        $tags = $tags_raw ? array_values(array_unique(array_filter(array_map('trim', explode(',', $tags_raw))))) : [];
        $slot_alts = $_POST['slot_alts'] ?? [];

        if (!$title || $price <= 0) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Title and price are required.']);
            exit;
        }

        $items = load_items();
        $is_new = empty($id);

        if ($is_new) {
            $id = generate_id();
            $new_item = [
                'id' => $id,
                'title' => $title,
                'description' => $description,
                'price' => $price,
                'tags' => $tags,
                'images' => [],
                'image_alts' => [],
                'created_at' => date('c'),
                'status' => 'available',
            ];
        }

        $slot_paths = $_POST['slot_paths'] ?? [];
        $slot_data = $_POST['slot_data'] ?? [];
        $new_images = [];

        $old_images = [];
        if (!$is_new) {
            $existing = get_item($id);
            if ($existing) $old_images = get_item_images($existing);
        }

        $count = max(count($slot_paths), count($slot_data));
        for ($i = 0; $i < $count; $i++) {
            $data = $slot_data[$i] ?? '';
            $path = $slot_paths[$i] ?? '';

            if (!empty($data) && preg_match('/^data:image\/(jpeg|png|webp);base64,/', $data, $matches)) {
                $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
                $raw = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $data));
                $filename = $id . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                file_put_contents(UPLOADS_DIR . '/' . $filename, $raw);
                $new_images[] = 'uploads/' . $filename;
            } elseif (!empty($path)) {
                $new_images[] = $path;
            }
        }

        foreach ($old_images as $old) {
            if (!in_array($old, $new_images) && file_exists(__DIR__ . '/' . $old)) {
                @unlink(__DIR__ . '/' . $old);
            }
        }

        $image_alts = array_map('trim', array_slice($slot_alts, 0, count($new_images)));
        while (count($image_alts) < count($new_images)) $image_alts[] = '';

        if ($is_new) {
            $new_item['images'] = $new_images;
            $new_item['image_alts'] = $image_alts;
            $items[] = $new_item;
        } else {
            foreach ($items as &$item) {
                if ($item['id'] === $id) {
                    $item['title'] = $title;
                    $item['description'] = $description;
                    $item['price'] = $price;
                    $item['tags'] = $tags;
                    $item['images'] = $new_images;
                    $item['image_alts'] = $image_alts;
                    unset($item['image']);
                    break;
                }
            }
            unset($item);
        }

        save_items($items);
        header('Location: admin.php?saved=' . urlencode($id));
        exit;

    case 'delete_item':
        require_admin();
        $id = $_POST['id'] ?? '';
        if (!$id) { header('HTTP/1.1 400 Bad Request'); exit; }

        $items = load_items();
        $items = array_values(array_filter($items, fn($i) => $i['id'] !== $id));
        save_items($items);

        header('Location: admin.php?deleted=1');
        exit;

    case 'delete_offer':
        require_admin();
        $offer_id = $_POST['offer_id'] ?? '';
        if (!$offer_id) { header('HTTP/1.1 400 Bad Request'); exit; }

        $offers = load_offers();
        $offers = array_values(array_filter($offers, fn($o) => strval($o['id']) !== strval($offer_id)));
        save_offers($offers);

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'admin.php'));
        exit;

    case 'save_settings':
        require_admin();
        $settings = load_settings();
        $settings['site_title'] = trim($_POST['site_title'] ?? '') ?: '2nd Hand';
        $settings['site_tagline'] = trim($_POST['site_tagline'] ?? '');
        $settings['owner_name'] = trim($_POST['owner_name'] ?? '');
        $settings['notification_email'] = trim($_POST['notification_email'] ?? '');
        $settings['smtp_host'] = trim($_POST['smtp_host'] ?? '');
        $settings['smtp_port'] = intval($_POST['smtp_port'] ?? 587);
        $settings['smtp_user'] = trim($_POST['smtp_user'] ?? '');
        if (!empty($_POST['smtp_pass'])) {
            $settings['smtp_pass'] = $_POST['smtp_pass'];
        }
        $settings['smtp_encryption'] = in_array($_POST['smtp_encryption'] ?? '', ['none', 'tls', 'ssl']) ? $_POST['smtp_encryption'] : 'tls';
        $settings['smtp_from'] = trim($_POST['smtp_from'] ?? '') ?: 'noreply@example.com';
        save_settings($settings);
        header('Location: admin.php?action=settings&saved=1');
        exit;

    case 'test_email':
        require_admin();
        $settings = load_settings();
        if (empty($settings['notification_email']) || empty($settings['smtp_host'])) {
            header('Location: admin.php?action=settings&test=no_config');
            exit;
        }
        $ok = smtp_send(
            $settings['smtp_host'],
            (int)$settings['smtp_port'],
            $settings['smtp_user'],
            $settings['smtp_pass'],
            $settings['smtp_encryption'],
            $settings['smtp_from'],
            $settings['notification_email'],
            'Test email from ' . SITE_TITLE,
            "This is a test email from your 2nd Hand site.\nIf you received this, email notifications are working correctly."
        );
        header('Location: admin.php?action=settings&test=' . ($ok ? 'ok' : 'fail'));
        exit;

    case 'get_offers':
        require_admin();
        $item_id = $_GET['item_id'] ?? '';
        $item = get_item($item_id);
        if (!$item) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Item not found']);
            exit;
        }
        $offers = get_offers_for_item($item_id);
        usort($offers, fn($a, $b) => $b['amount'] <=> $a['amount']);
        header('Content-Type: application/json');
        echo json_encode(['item' => $item, 'offers' => $offers]);
        exit;

    default:
        header('HTTP/1.1 400 Bad Request');
        echo 'Unknown action.';
        exit;
}
