<?php
require_once __DIR__ . '/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'login':
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!check_rate_limit($ip, 900, 5, $email)) {
            header('Location: admin.php?action=login&error=rate');
            exit;
        }

        $user = get_user_by_email($email);
        if ($user && $user['is_active'] && $user['confirmed_at'] && password_verify($password, $user['password_hash'])) {
            log_login_attempt($ip, $email, true);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];

            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $db = get_db();
                $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                   ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            $db = get_db();
            $db->prepare("UPDATE users SET last_login_at = datetime('now') WHERE id = ?")->execute([$user['id']]);

            header('Location: admin.php');
        } else {
            log_login_attempt($ip, $email, false);
            header('Location: admin.php?action=login&error=1');
        }
        exit;

    case 'setup':
        if (count_active_admins() > 0) {
            header('Location: admin.php?action=login');
            exit;
        }
        verify_csrf();
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: admin.php?action=setup&error=email');
            exit;
        }
        if (strlen($password) < 8) {
            header('Location: admin.php?action=setup&error=short');
            exit;
        }
        if ($password !== $confirm) {
            header('Location: admin.php?action=setup&error=mismatch');
            exit;
        }

        $db = get_db();
        $db->prepare("INSERT INTO users (email, password_hash, is_super_admin, confirmed_at) VALUES (?, ?, 1, datetime('now'))")
           ->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$db->lastInsertId();

        header('Location: admin.php');
        exit;

    case 'register':
        verify_csrf();
        if (!registration_open()) {
            header('Location: admin.php?action=register&error=closed');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!check_rate_limit($ip, 3600, 10, $email)) {
            header('Location: admin.php?action=register&error=rate');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: admin.php?action=register&error=email');
            exit;
        }
        if (strlen($password) < 8) {
            header('Location: admin.php?action=register&error=short');
            exit;
        }
        if ($password !== $confirm) {
            header('Location: admin.php?action=register&error=mismatch');
            exit;
        }

        $existing = get_user_by_email($email);
        if ($existing) {
            if ($existing['confirmed_at']) {
                header('Location: admin.php?action=register&error=taken');
                exit;
            }
            $db = get_db();
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$existing['id']]);
        }

        $code = generate_confirmation_code();
        $code_hash = password_hash($code, PASSWORD_DEFAULT);
        $expires = date('Y-m-d\TH:i:s+00:00', time() + 7200);

        $db = get_db();
        $db->prepare('INSERT INTO users (email, password_hash, confirmation_code, confirmation_expires_at) VALUES (?, ?, ?, ?)')
           ->execute([$email, password_hash($password, PASSWORD_DEFAULT), $code_hash, $expires]);

        log_login_attempt($ip, $email, false);

        if (!send_confirmation_email($email, $code)) {
            $db->prepare('DELETE FROM users WHERE email = ? AND confirmed_at IS NULL')->execute([$email]);
            header('Location: admin.php?action=register&error=smtp');
            exit;
        }

        header('Location: admin.php?action=confirm&email=' . urlencode($email));
        exit;

    case 'confirm_registration':
        verify_csrf();
        $email = trim($_POST['email'] ?? '');
        $code = trim($_POST['code'] ?? '');

        $user = get_user_by_email($email);
        if (!$user || $user['confirmed_at']) {
            header('Location: admin.php?action=login');
            exit;
        }

        if ($user['confirmation_expires_at'] && strtotime($user['confirmation_expires_at']) < time()) {
            $db = get_db();
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);
            header('Location: admin.php?action=register&error=expired');
            exit;
        }

        if (!password_verify($code, $user['confirmation_code'])) {
            header('Location: admin.php?action=confirm&email=' . urlencode($email) . '&error=wrong');
            exit;
        }

        $db = get_db();
        $db->prepare("UPDATE users SET confirmed_at = datetime('now'), confirmation_code = NULL, confirmation_expires_at = NULL WHERE id = ?")
           ->execute([$user['id']]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];

        $admin = get_super_admin();
        if ($admin && $admin['id'] !== $user['id']) {
            send_superadmin_notification(
                'New user registered — ' . SITE_TITLE,
                "A new user has registered on " . SITE_TITLE . ".\n\n"
                . "Email: {$email}\n"
                . "Date: " . date('Y-m-d H:i:s') . "\n\n"
                . "Total active users: " . count_active_admins() . "\n"
            );
        }

        header('Location: admin.php');
        exit;

    case 'resend_confirmation':
        verify_csrf();
        $email = trim($_POST['email'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!check_rate_limit($ip, 900, 5, $email)) {
            header('Location: admin.php?action=confirm&email=' . urlencode($email) . '&error=rate');
            exit;
        }

        $user = get_user_by_email($email);
        if (!$user || $user['confirmed_at']) {
            header('Location: admin.php?action=login');
            exit;
        }

        $code = generate_confirmation_code();
        $code_hash = password_hash($code, PASSWORD_DEFAULT);
        $expires = date('Y-m-d\TH:i:s+00:00', time() + 7200);

        $db = get_db();
        $db->prepare('UPDATE users SET confirmation_code = ?, confirmation_expires_at = ? WHERE id = ?')
           ->execute([$code_hash, $expires, $user['id']]);

        log_login_attempt($ip, $email, false);
        send_confirmation_email($email, $code);

        header('Location: admin.php?action=confirm&email=' . urlencode($email) . '&resent=1');
        exit;

    case 'forgot_password':
        verify_csrf();
        $email = trim($_POST['email'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!check_rate_limit($ip, 900, 5, $email)) {
            header('Location: admin.php?action=forgot&error=rate');
            exit;
        }

        log_login_attempt($ip, $email, false);

        $user = get_user_by_email($email);
        if ($user && $user['confirmed_at'] && $user['is_active']) {
            $code = generate_confirmation_code();
            $code_hash = password_hash($code, PASSWORD_DEFAULT);
            $expires = date('Y-m-d\TH:i:s+00:00', time() + 1800);

            $db = get_db();
            $db->prepare('UPDATE users SET reset_code = ?, reset_expires_at = ? WHERE id = ?')
               ->execute([$code_hash, $expires, $user['id']]);

            $subject = 'Password reset — ' . SITE_TITLE;
            $body = "Your password reset code is: {$code}\n\n"
                . "This code expires in 30 minutes.\n\n"
                . "If you did not request this, you can safely ignore this email.\n";
            send_email($user['email'], $subject, $body);
        }

        header('Location: admin.php?action=reset&email=' . urlencode($email) . '&sent=1');
        exit;

    case 'reset_password':
        verify_csrf();
        $email = trim($_POST['email'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        $redir = 'admin.php?action=reset&email=' . urlencode($email);

        if (strlen($password) < 8) {
            header('Location: ' . $redir . '&error=short');
            exit;
        }
        if ($password !== $confirm) {
            header('Location: ' . $redir . '&error=mismatch');
            exit;
        }

        $user = get_user_by_email($email);
        if (!$user || !$user['reset_code'] || !$user['reset_expires_at']) {
            header('Location: ' . $redir . '&error=wrong');
            exit;
        }

        if (strtotime($user['reset_expires_at']) < time()) {
            $db = get_db();
            $db->prepare('UPDATE users SET reset_code = NULL, reset_expires_at = NULL WHERE id = ?')
               ->execute([$user['id']]);
            header('Location: ' . $redir . '&error=wrong');
            exit;
        }

        if (!password_verify($code, $user['reset_code'])) {
            header('Location: ' . $redir . '&error=wrong');
            exit;
        }

        $db = get_db();
        $db->prepare('UPDATE users SET password_hash = ?, reset_code = NULL, reset_expires_at = NULL WHERE id = ?')
           ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];

        header('Location: admin.php');
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

        $db = get_db();
        $db->prepare('INSERT INTO offers (item_id, amount, status) VALUES (?, ?, ?)')
           ->execute([$item_id, $amount, 'pending']);
        $offer_id = (int)$db->lastInsertId();

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
        verify_csrf();
        require_admin();
        $user = get_logged_in_user();
        $id = $_POST['id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $currency = trim($_POST['currency'] ?? '') ?: default_currency_for_user($user);

        // Location: the precision the admin picked only stands if usable
        // coordinates actually came with it — an empty or malformed pair
        // resolves to 'none' rather than silently publishing a stale position.
        $lat = parse_coord($_POST['latitude']  ?? null, 90);
        $lon = parse_coord($_POST['longitude'] ?? null, 180);
        $precision = normalise_precision($_POST['location_precision'] ?? 'none');
        if ($lat === null || $lon === null) {
            $lat = $lon = null;
            $precision = 'none';
        }

        $tags_raw = trim($_POST['tags'] ?? '');
        $tags = $tags_raw ? array_values(array_unique(array_filter(array_map('trim', explode(',', $tags_raw))))) : [];
        $slot_alts = $_POST['slot_alts'] ?? [];

        if (!$title || $price <= 0) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Title and price are required.']);
            exit;
        }

        $db = get_db();
        $is_new = empty($id);

        if ($is_new) {
            $id = generate_id();
        } else {
            $existing = get_item($id);
            if (!$existing) {
                header('HTTP/1.1 404 Not Found');
                echo 'Item not found.';
                exit;
            }
            if ($existing['user_id'] !== $user['id']) {
                header('HTTP/1.1 403 Forbidden');
                echo 'Access denied.';
                exit;
            }
        }

        $slot_paths = $_POST['slot_paths'] ?? [];
        $slot_data = $_POST['slot_data'] ?? [];
        $new_images = [];

        $old_images = [];
        if (!$is_new) {
            $old_images = get_item_image_paths($id);
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
            $db->prepare('INSERT INTO items (id, user_id, title, description, price, currency, latitude, longitude, location_precision, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$id, $user['id'], $title, $description, $price, $currency, $lat, $lon, $precision, 'available', date('c')]);
        } else {
            $db->prepare('UPDATE items SET title = ?, description = ?, price = ?, currency = ?, latitude = ?, longitude = ?, location_precision = ? WHERE id = ?')
               ->execute([$title, $description, $price, $currency, $lat, $lon, $precision, $id]);
        }

        // Remember this admin's currency so it pre-fills their next listing.
        $db->prepare('UPDATE users SET last_currency = ? WHERE id = ?')->execute([$currency, $user['id']]);

        // Remember the location too — including one typed by hand, which is why
        // this records whatever was saved rather than only geolocated values.
        // A listing saved with no location leaves the previous default intact,
        // so clearing one item does not wipe the admin's remembered position.
        if ($lat !== null && $lon !== null) {
            $db->prepare('UPDATE users SET last_latitude = ?, last_longitude = ?, last_location_precision = ? WHERE id = ?')
               ->execute([$lat, $lon, $precision, $user['id']]);
        }

        save_item_tags($id, $tags);
        save_item_images($id, $new_images, $image_alts);

        header('Location: admin.php?saved=' . urlencode($id));
        exit;

    case 'delete_item':
        verify_csrf();
        require_admin();
        $user = get_logged_in_user();
        $id = $_POST['id'] ?? '';
        if (!$id) { header('HTTP/1.1 400 Bad Request'); exit; }

        $item = get_item($id);
        if (!$item) { header('HTTP/1.1 404 Not Found'); exit; }
        if ($item['user_id'] !== $user['id'] && !$user['is_super_admin']) {
            header('HTTP/1.1 403 Forbidden');
            echo 'Access denied.';
            exit;
        }

        delete_item_files($id);
        $db = get_db();
        $db->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);

        header('Location: admin.php?deleted=1');
        exit;

    case 'delete_selected':
        verify_csrf();
        require_admin();
        $user = get_logged_in_user();
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            header('Location: admin.php');
            exit;
        }

        $db = get_db();
        $deleted = 0;
        foreach ($ids as $id) {
            $item = get_item($id);
            if (!$item) continue;
            if ($item['user_id'] !== $user['id'] && !$user['is_super_admin']) continue;
            delete_item_files($id);
            $db->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
            $deleted++;
        }

        header('Location: admin.php?deleted=' . $deleted);
        exit;

    case 'delete_offer':
        verify_csrf();
        require_admin();
        $user = get_logged_in_user();
        $offer_id = $_POST['offer_id'] ?? '';
        if (!$offer_id) { header('HTTP/1.1 400 Bad Request'); exit; }

        $db = get_db();
        $stmt = $db->prepare('SELECT o.*, i.user_id AS item_owner_id FROM offers o JOIN items i ON o.item_id = i.id WHERE o.id = ?');
        $stmt->execute([$offer_id]);
        $offer = $stmt->fetch();

        if (!$offer) { header('HTTP/1.1 404 Not Found'); exit; }
        if ($offer['item_owner_id'] !== $user['id'] && !$user['is_super_admin']) {
            header('HTTP/1.1 403 Forbidden');
            exit;
        }

        $db->prepare('DELETE FROM offers WHERE id = ?')->execute([$offer_id]);

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'admin.php'));
        exit;

    case 'remove_admin':
        verify_csrf();
        require_super_admin();
        $user = get_logged_in_user();
        $target_id = (int)($_POST['user_id'] ?? 0);

        if (!$target_id || $target_id === $user['id']) {
            header('HTTP/1.1 400 Bad Request');
            echo 'Cannot remove yourself.';
            exit;
        }

        $target = get_user($target_id);
        if (!$target) { header('HTTP/1.1 404 Not Found'); exit; }

        $db = get_db();
        $items = $db->prepare('SELECT id FROM items WHERE user_id = ?');
        $items->execute([$target_id]);
        foreach ($items->fetchAll(PDO::FETCH_COLUMN) as $item_id) {
            delete_item_files($item_id);
        }

        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$target_id]);

        header('Location: admin.php?action=manage_users&removed=1');
        exit;

    case 'save_settings':
        verify_csrf();
        require_super_admin();
        $settings = load_settings();
        $settings['site_title'] = trim($_POST['site_title'] ?? '') ?: '2nd Hand';
        $settings['site_tagline'] = trim($_POST['site_tagline'] ?? '');
        $settings['owner_name'] = trim($_POST['owner_name'] ?? '');
        $settings['currency'] = trim($_POST['currency'] ?? '') ?: '€';
        $settings['smtp_host'] = trim($_POST['smtp_host'] ?? '');
        $settings['smtp_port'] = intval($_POST['smtp_port'] ?? 587);
        $settings['smtp_user'] = trim($_POST['smtp_user'] ?? '');
        if (!empty($_POST['smtp_pass'])) {
            $settings['smtp_pass'] = $_POST['smtp_pass'];
        }
        $settings['smtp_encryption'] = in_array($_POST['smtp_encryption'] ?? '', ['none', 'tls', 'ssl']) ? $_POST['smtp_encryption'] : 'tls';
        $settings['smtp_from'] = trim($_POST['smtp_from'] ?? '') ?: 'noreply@example.com';
        $settings['max_admins'] = intval($_POST['max_admins'] ?? 1);
        $settings['show_registration_link'] = isset($_POST['show_registration_link']) ? '1' : '0';
        save_settings($settings);
        header('Location: admin.php?action=settings&saved=1');
        exit;

    case 'test_email':
        require_super_admin();
        $settings = load_settings();
        $user = get_logged_in_user();
        if (empty($settings['smtp_host'])) {
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
            $user['email'],
            'Test email from ' . SITE_TITLE,
            "This is a test email from your 2nd Hand site.\nIf you received this, email notifications are working correctly."
        );
        header('Location: admin.php?action=settings&test=' . ($ok ? 'ok' : 'fail'));
        exit;

    case 'get_offers':
        require_admin();
        $user = get_logged_in_user();
        $item_id = $_GET['item_id'] ?? '';
        $item = get_item($item_id);
        if (!$item) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Item not found']);
            exit;
        }
        if ($item['user_id'] !== $user['id'] && !$user['is_super_admin']) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['error' => 'Access denied']);
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
