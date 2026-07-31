<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'dashboard';

// ── Setup: create first admin account ──────────────────────────────

if ($action === 'setup') {
    if (count_active_admins() > 0) {
        header('Location: admin.php?action=login');
        exit;
    }
    $error = $_GET['error'] ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup — <?= htmlspecialchars(SITE_TITLE) ?></title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body class="admin-body">
        <div class="login-box">
            <h1><?= htmlspecialchars(SITE_TITLE) ?></h1>
            <p style="margin-bottom:1.5rem;color:var(--muted)">Create your admin account to get started.</p>
            <?php if ($error === 'email'): ?>
                <p class="error-msg">Please enter a valid email address.</p>
            <?php elseif ($error === 'short'): ?>
                <p class="error-msg">Password must be at least 8 characters.</p>
            <?php elseif ($error === 'mismatch'): ?>
                <p class="error-msg">Passwords do not match.</p>
            <?php endif; ?>
            <form method="POST" action="api.php">
                <input type="hidden" name="action" value="setup">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary">Create Account</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ── Login ───────────────────────────────────────────────────────────

if ($action === 'login') {
    if (count_active_admins() === 0) {
        header('Location: admin.php?action=setup');
        exit;
    }
    if (get_logged_in_user()) {
        header('Location: admin.php');
        exit;
    }
    $error = $_GET['error'] ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login — <?= htmlspecialchars(SITE_TITLE) ?></title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body class="admin-body">
        <div class="login-box">
            <h1><?= htmlspecialchars(SITE_TITLE) ?> Admin</h1>
            <?php if ($error === 'rate'): ?>
                <p class="error-msg">Too many failed attempts. Please try again later.</p>
            <?php elseif ($error): ?>
                <p class="error-msg">Invalid email or password.</p>
            <?php endif; ?>
            <form method="POST" action="api.php">
                <input type="hidden" name="action" value="login">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Log in</button>
            </form>
            <p style="margin-top:1rem;text-align:center">
                <a href="admin.php?action=forgot">Forgot password?</a>
                <?php if (registration_open()): ?>
                    &middot; <a href="admin.php?action=register">Create an account</a>
                <?php endif; ?>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ── Register ────────────────────────────────────────────────────────

if ($action === 'register') {
    if (get_logged_in_user()) {
        header('Location: admin.php');
        exit;
    }
    if (!registration_open()) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Registration Closed — <?= htmlspecialchars(SITE_TITLE) ?></title>
            <link rel="stylesheet" href="css/style.css">
        </head>
        <body class="admin-body">
            <div class="login-box">
                <h1><?= htmlspecialchars(SITE_TITLE) ?></h1>
                <p style="margin-bottom:1rem">Registration is currently closed.</p>
                <a href="admin.php?action=login" class="btn btn-primary">Back to login</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    $error = $_GET['error'] ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Register — <?= htmlspecialchars(SITE_TITLE) ?></title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body class="admin-body">
        <div class="login-box">
            <h1><?= htmlspecialchars(SITE_TITLE) ?></h1>
            <div class="privacy-disclosure">By registering, you acknowledge that the site administrator will be notified of your email address and when you add or remove items.</div>
            <?php if ($error === 'email'): ?>
                <p class="error-msg">Please enter a valid email address.</p>
            <?php elseif ($error === 'short'): ?>
                <p class="error-msg">Password must be at least 8 characters.</p>
            <?php elseif ($error === 'mismatch'): ?>
                <p class="error-msg">Passwords do not match.</p>
            <?php elseif ($error === 'taken'): ?>
                <p class="error-msg">An account with this email already exists.</p>
            <?php elseif ($error === 'rate'): ?>
                <p class="error-msg">Too many attempts. Please try again later.</p>
            <?php elseif ($error === 'smtp'): ?>
                <p class="error-msg">Could not send confirmation email. Please try again later or contact the site administrator.</p>
            <?php elseif ($error === 'expired'): ?>
                <p class="error-msg">Your confirmation code expired. Please register again.</p>
            <?php elseif ($error === 'closed'): ?>
                <p class="error-msg">Registration is currently closed.</p>
            <?php endif; ?>
            <form method="POST" action="api.php">
                <input type="hidden" name="action" value="register">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary">Register</button>
            </form>
            <p style="margin-top:1rem;text-align:center"><a href="admin.php?action=login">Already have an account? Log in</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ── Confirm registration ────────────────────────────────────────────

if ($action === 'confirm') {
    $confirm_email = $_GET['email'] ?? '';
    $error = $_GET['error'] ?? '';
    $resent = isset($_GET['resent']);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirm Account — <?= htmlspecialchars(SITE_TITLE) ?></title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body class="admin-body">
        <div class="login-box">
            <h1><?= htmlspecialchars(SITE_TITLE) ?></h1>
            <p style="margin-bottom:1rem;color:var(--muted)">A 6-digit code was sent to <strong><?= htmlspecialchars($confirm_email) ?></strong>. Enter it below to activate your account.</p>
            <?php if ($resent): ?>
                <div class="success-message"><p>A new code has been sent.</p></div>
            <?php endif; ?>
            <?php if ($error === 'wrong'): ?>
                <p class="error-msg">Invalid code. Please try again.</p>
            <?php elseif ($error === 'rate'): ?>
                <p class="error-msg">Too many attempts. Please wait before trying again.</p>
            <?php endif; ?>
            <form method="POST" action="api.php">
                <input type="hidden" name="action" value="confirm_registration">
                <input type="hidden" name="email" value="<?= htmlspecialchars($confirm_email) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="code">Confirmation code</label>
                    <input type="text" id="code" name="code" required pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="one-time-code" autofocus style="font-size:1.5rem;text-align:center;letter-spacing:0.5em">
                </div>
                <button type="submit" class="btn btn-primary">Confirm</button>
            </form>
            <form method="POST" action="api.php" style="margin-top:1rem;text-align:center">
                <input type="hidden" name="action" value="resend_confirmation">
                <input type="hidden" name="email" value="<?= htmlspecialchars($confirm_email) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn-link">Resend code</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ── Forgot password ─────────────────────────────────────────────────

if ($action === 'forgot') {
    $error = $_GET['error'] ?? '';
    $sent = isset($_GET['sent']);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Forgot Password — <?= htmlspecialchars(SITE_TITLE) ?></title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body class="admin-body">
        <div class="login-box">
            <h1><?= htmlspecialchars(SITE_TITLE) ?></h1>
            <?php if ($sent): ?>
                <div class="success-message"><p>If an account exists with that email, a reset code has been sent.</p></div>
            <?php endif; ?>
            <?php if ($error === 'rate'): ?>
                <p class="error-msg">Too many attempts. Please try again later.</p>
            <?php endif; ?>
            <p style="margin-bottom:1rem;color:var(--muted)">Enter your email to receive a password reset code.</p>
            <form method="POST" action="api.php">
                <input type="hidden" name="action" value="forgot_password">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary">Send Reset Code</button>
            </form>
            <p style="margin-top:1rem;text-align:center"><a href="admin.php?action=login">Back to login</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ── Reset password ──────────────────────────────────────────────────

if ($action === 'reset') {
    $reset_email = $_GET['email'] ?? '';
    $error = $_GET['error'] ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reset Password — <?= htmlspecialchars(SITE_TITLE) ?></title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body class="admin-body">
        <div class="login-box">
            <h1><?= htmlspecialchars(SITE_TITLE) ?></h1>
            <?php if (isset($_GET['sent'])): ?>
                <div class="success-message"><p>If an account exists with that email, a reset code has been sent.</p></div>
            <?php endif; ?>
            <p style="margin-bottom:1rem;color:var(--muted)">Enter the 6-digit code sent to <strong><?= htmlspecialchars($reset_email) ?></strong> and your new password.</p>
            <?php if ($error === 'wrong'): ?>
                <p class="error-msg">Invalid or expired code.</p>
            <?php elseif ($error === 'short'): ?>
                <p class="error-msg">Password must be at least 8 characters.</p>
            <?php elseif ($error === 'mismatch'): ?>
                <p class="error-msg">Passwords do not match.</p>
            <?php endif; ?>
            <form method="POST" action="api.php">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="email" value="<?= htmlspecialchars($reset_email) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="code">Reset code</label>
                    <input type="text" id="code" name="code" required pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="one-time-code" autofocus style="font-size:1.5rem;text-align:center;letter-spacing:0.5em">
                </div>
                <div class="form-group">
                    <label for="password">New password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm new password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </form>
            <p style="margin-top:1rem;text-align:center"><a href="admin.php?action=login">Back to login</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ── Authenticated views ─────────────────────────────────────────────

require_admin();

$current_user = get_logged_in_user();
$is_super = (bool)$current_user['is_super_admin'];

$items = $is_super ? get_all_items() : get_items_for_user($current_user['id']);
$all_tags = get_all_tags($items);
$edit_id = $_GET['edit'] ?? '';
$edit_item = null;
if ($edit_id) {
    $edit_item = get_item($edit_id);
    if ($edit_item && $edit_item['user_id'] !== $current_user['id']) {
        $edit_item = null;
    }
}

$view_offers_id = $_GET['offers'] ?? '';
$view_offers_item = null;
$view_offers = [];
if ($view_offers_id) {
    $view_offers_item = get_item($view_offers_id);
    if ($view_offers_item) {
        if ($view_offers_item['user_id'] !== $current_user['id'] && !$is_super) {
            $view_offers_item = null;
        } else {
            $view_offers = get_offers_for_item($view_offers_id);
            usort($view_offers, function($a, $b) use ($view_offers_item) {
                $price = $view_offers_item['price'];
                $a_above = $a['amount'] >= $price;
                $b_above = $b['amount'] >= $price;
                if ($a_above && !$b_above) return -1;
                if (!$a_above && $b_above) return 1;
                if ($a_above && $b_above) {
                    return strtotime($a['created_at']) - strtotime($b['created_at']);
                }
                return $b['amount'] <=> $a['amount'];
            });
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= htmlspecialchars(SITE_TITLE) ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="container">
            <h1><a href="admin.php">Admin Panel</a></h1>
            <nav>
                <span class="admin-user"><?= htmlspecialchars($current_user['email']) ?></span>
                <?php if ($is_super): ?>
                    <a href="admin.php?action=manage_users">Users</a>
                    <a href="admin.php?action=settings">Settings</a>
                <?php endif; ?>
                <a href="index.php">View Site</a>
                <a href="api.php?action=logout">Log out</a>
            </nav>
        </div>
    </header>

    <main class="container admin-main">
        <?php if (isset($_GET['saved']) && $action !== 'settings'): ?>
            <div class="success-message"><p>Item saved.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="success-message"><p><?= intval($_GET['deleted']) > 1 ? intval($_GET['deleted']) . ' items' : 'Item' ?> deleted.</p></div>
        <?php endif; ?>

        <?php if ($action === 'manage_users'):
            require_super_admin();
            $db = get_db();
            $all_users = $db->query('SELECT u.*, (SELECT COUNT(*) FROM items WHERE user_id = u.id) AS item_count FROM users u WHERE u.confirmed_at IS NOT NULL ORDER BY u.created_at')->fetchAll();
        ?>
            <section class="admin-section">
                <h2>Users (<?= count($all_users) ?>)</h2>
                <a href="admin.php" class="back-link">&larr; Back to items</a>

                <?php if (isset($_GET['removed'])): ?>
                    <div class="success-message"><p>User removed.</p></div>
                <?php endif; ?>

                <table class="offers-table" style="margin-top:1rem">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Items</th>
                            <th>Registered</th>
                            <th>Last login</th>
                            <th>Role</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= $u['item_count'] ?></td>
                                <td><?php if ($u['created_at']): ?><time class="local-date" datetime="<?= htmlspecialchars($u['created_at']) ?>"><?= date('Y-m-d', strtotime($u['created_at'])) ?></time><?php else: ?>—<?php endif; ?></td>
                                <td><?php if ($u['last_login_at']): ?><time class="local-time" datetime="<?= htmlspecialchars($u['last_login_at']) ?>"><?= date('Y-m-d H:i', strtotime($u['last_login_at'])) ?></time><?php else: ?>never<?php endif; ?></td>
                                <td><?= $u['is_super_admin'] ? 'Super-admin' : 'Admin' ?></td>
                                <td>
                                    <?php if ($u['id'] !== $current_user['id']): ?>
                                        <form method="POST" action="api.php" class="inline-form" onsubmit="return confirm('Remove <?= htmlspecialchars($u['email']) ?> and all their items? This cannot be undone.')">
                                            <input type="hidden" name="action" value="remove_admin">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-small btn-danger">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

        <?php elseif ($action === 'settings'):
            require_super_admin();
            $settings = load_settings();
        ?>
            <section class="admin-section">
                <h2>Settings</h2>
                <a href="admin.php" class="back-link">&larr; Back to items</a>

                <?php if (isset($_GET['saved'])): ?>
                    <div class="success-message"><p>Settings saved.</p></div>
                <?php endif; ?>
                <?php if (isset($_GET['test'])): ?>
                    <?php if ($_GET['test'] === 'ok'): ?>
                        <div class="success-message"><p>Test email sent to <?= htmlspecialchars($current_user['email']) ?>.</p></div>
                    <?php elseif ($_GET['test'] === 'no_config'): ?>
                        <div class="error-msg"><p>Configure SMTP host first.</p></div>
                    <?php else: ?>
                        <div class="error-msg"><p>Failed to send test email. Check your SMTP settings and server error log.</p></div>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="POST" action="api.php" class="item-form">
                    <input type="hidden" name="action" value="save_settings">
                    <?= csrf_field() ?>

                    <h3 style="margin: 0 0 1rem; font-weight: 500;">Site Identity</h3>

                    <div class="form-group">
                        <label for="site_title">Site title</label>
                        <input type="text" id="site_title" name="site_title" value="<?= htmlspecialchars($settings['site_title']) ?>" placeholder="2nd Hand">
                    </div>

                    <div class="form-group">
                        <label for="site_tagline">Tagline</label>
                        <input type="text" id="site_tagline" name="site_tagline" value="<?= htmlspecialchars($settings['site_tagline']) ?>" placeholder="Electronics, Tools, Home & Garage">
                    </div>

                    <div class="form-group">
                        <label for="owner_name">Owner name (shown in footer and privacy policy)</label>
                        <input type="text" id="owner_name" name="owner_name" value="<?= htmlspecialchars($settings['owner_name'] ?? '') ?>" placeholder="Your Name">
                    </div>

                    <div class="form-group">
                        <label for="currency">Default currency symbol</label>
                        <input type="text" id="currency" name="currency" value="<?= htmlspecialchars($settings['currency'] ?? '€') ?>" placeholder="€" style="max-width:5rem">
                        <small style="color:var(--muted)">Each listing carries its own currency. This is the starting value for an admin who has not posted an item yet.</small>
                    </div>

                    <h3 style="margin: 1.5rem 0 1rem; font-weight: 500;">Registration</h3>

                    <div class="form-group">
                        <label for="max_admins">Maximum admin accounts (0 = unlimited)</label>
                        <input type="number" id="max_admins" name="max_admins" min="0" value="<?= htmlspecialchars($settings['max_admins'] ?? '1') ?>">
                        <small style="color:var(--muted)">Set to 1 for personal use, 0 for community/makerspace.</small>
                    </div>

                    <div class="form-group">
                        <label><input type="checkbox" name="show_registration_link" value="1" <?= ($settings['show_registration_link'] ?? '0') === '1' ? 'checked' : '' ?>> Show "Become a seller" link on public page</label>
                    </div>

                    <h3 style="margin: 1.5rem 0 1rem; font-weight: 500;">SMTP Server</h3>

                    <div class="form-row">
                        <div class="form-group flex-2">
                            <label for="smtp_host">SMTP Host</label>
                            <input type="text" id="smtp_host" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host']) ?>" placeholder="smtp.example.com">
                        </div>
                        <div class="form-group flex-1">
                            <label for="smtp_port">Port</label>
                            <input type="number" id="smtp_port" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port']) ?>" placeholder="587">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="smtp_encryption">Encryption</label>
                        <select id="smtp_encryption" name="smtp_encryption" style="padding:0.6rem 0.8rem; border:1px solid var(--border); border-radius:var(--radius); font-size:0.95rem; width:100%;">
                            <option value="tls" <?= $settings['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS (port 587)</option>
                            <option value="ssl" <?= $settings['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL/TLS (port 465)</option>
                            <option value="none" <?= $settings['smtp_encryption'] === 'none' ? 'selected' : '' ?>>None (port 25)</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group flex-1">
                            <label for="smtp_user">SMTP Username</label>
                            <input type="text" id="smtp_user" name="smtp_user" value="<?= htmlspecialchars($settings['smtp_user']) ?>" placeholder="user@example.com">
                        </div>
                        <div class="form-group flex-1">
                            <label for="smtp_pass">SMTP Password</label>
                            <input type="text" id="smtp_pass" name="smtp_pass" autocomplete="off" placeholder="<?= empty($settings['smtp_pass']) ? '' : '(unchanged)' ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="smtp_from">From address</label>
                        <input type="text" id="smtp_from" name="smtp_from" value="<?= htmlspecialchars($settings['smtp_from']) ?>" placeholder="noreply@example.com">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                        <a href="api.php?action=test_email" class="btn btn-secondary">Send Test Email</a>
                    </div>
                </form>
            </section>

        <?php elseif ($view_offers_item): ?>
            <section class="admin-section">
                <h2>Offers for: <?= htmlspecialchars($view_offers_item['title']) ?>
                    <span class="price-inline"><?= htmlspecialchars(format_price($view_offers_item)) ?></span>
                </h2>
                <a href="admin.php" class="back-link">&larr; Back to items</a>

                <?php if (empty($view_offers)): ?>
                    <p class="empty-state">No offers for this item yet.</p>
                <?php else: ?>
                    <table class="offers-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $winner_marked = false; ?>
                            <?php foreach ($view_offers as $offer): ?>
                                <?php $is_winner = !$winner_marked && $offer['status'] === 'pending'; $winner_marked = $winner_marked || $is_winner; ?>
                                <tr class="<?= $is_winner ? 'offer-winner' : '' ?> <?= $offer['amount'] >= $view_offers_item['price'] ? 'offer-above-price' : 'offer-below-price' ?>">
                                    <td><?= htmlspecialchars($offer['id']) ?></td>
                                    <td class="offer-amount"><?= htmlspecialchars(format_price($view_offers_item, (float)$offer['amount'])) ?>
                                        <?php if ($is_winner): ?>
                                            <span class="badge badge-winner">Winner</span>
                                        <?php elseif ($offer['amount'] >= $view_offers_item['price']): ?>
                                            <span class="badge badge-green">&ge; price</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><time class="local-time" datetime="<?= htmlspecialchars($offer['created_at']) ?>"><?= date('Y-m-d H:i', strtotime($offer['created_at'])) ?></time></td>
                                    <td><?= htmlspecialchars($offer['status']) ?></td>
                                    <td>
                                        <form method="POST" action="api.php" class="inline-form" onsubmit="return confirm('Delete offer #<?= htmlspecialchars($offer['id']) ?>?')">
                                            <input type="hidden" name="action" value="delete_offer">
                                            <input type="hidden" name="offer_id" value="<?= htmlspecialchars($offer['id']) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

        <?php else: ?>

            <section class="admin-section">
                <h2><?= $edit_item ? 'Edit Item' : 'Add New Item' ?></h2>
                <form method="POST" action="api.php" enctype="multipart/form-data" class="item-form" id="item-form">
                    <input type="hidden" name="action" value="save_item">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($edit_item['id'] ?? '') ?>">
                    <?= csrf_field() ?>

                    <div class="form-row">
                        <div class="form-group flex-2">
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" required value="<?= htmlspecialchars($edit_item['title'] ?? '') ?>">
                        </div>
                        <div class="form-group flex-1">
                            <label for="price">Price</label>
                            <input type="number" id="price" name="price" required min="0.01" step="0.01" value="<?= htmlspecialchars($edit_item['price'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="flex:0 0 6rem">
                            <label for="item_currency">Currency</label>
                            <input type="text" id="item_currency" name="currency" required maxlength="8" placeholder="€"
                                   value="<?= htmlspecialchars(($edit_item['currency'] ?? '') ?: default_currency_for_user($current_user)) ?>">
                        </div>
                    </div>

                    <?php
                        $loc_default = default_location_for_user($current_user);
                        $loc_lat = $edit_item['latitude']  ?? $loc_default['lat'];
                        $loc_lon = $edit_item['longitude'] ?? $loc_default['lon'];
                        $loc_prec = normalise_precision(
                            $edit_item['location_precision'] ?? $loc_default['precision']
                        );
                    ?>
                    <div class="form-group location-group">
                        <label for="location_precision">Location</label>
                        <div class="location-row">
                            <select id="location_precision" name="location_precision">
                                <?php foreach (LOCATION_PRECISIONS as $key => $meta): ?>
                                    <option value="<?= $key ?>" <?= $loc_prec === $key ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($meta['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" id="latitude" name="latitude" placeholder="latitude"
                                   inputmode="decimal" autocomplete="off"
                                   value="<?= $loc_lat === null ? '' : htmlspecialchars((string)$loc_lat) ?>">
                            <input type="text" id="longitude" name="longitude" placeholder="longitude"
                                   inputmode="decimal" autocomplete="off"
                                   value="<?= $loc_lon === null ? '' : htmlspecialchars((string)$loc_lon) ?>">
                            <button type="button" class="btn btn-secondary" id="geolocate-btn">Use my location</button>
                        </div>
                        <small style="color:var(--muted)">
                            Buyers see the coordinates rounded to the precision you pick, never more exactly.
                            Paste coordinates from any map, or edit them by hand — whatever you save becomes
                            the default for your next listing. Leave them empty for no location.
                        </small>
                        <small id="geolocate-msg" style="display:none"></small>
                    </div>

                    <div class="form-group" style="position:relative">
                        <label>Tags</label>
                        <input type="hidden" id="tags" name="tags" value="<?= htmlspecialchars(implode(', ', get_item_tags($edit_item ?? []))) ?>">
                        <div class="tag-editor" id="tag-editor">
                            <div class="tag-chips" id="tag-chips"></div>
                            <input type="text" id="tag-input" autocomplete="off" placeholder="Type a tag…">
                        </div>
                        <div id="tag-suggestions" class="tag-suggestions"></div>
                        <?php
                            $db = get_db();
                            $all_tag_names = $db->query('SELECT DISTINCT tag FROM item_tags ORDER BY tag')->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <script>
                        (function() {
                            var allTags = <?= json_encode(array_values($all_tag_names)) ?>;
                            var hidden = document.getElementById('tags');
                            var input = document.getElementById('tag-input');
                            var chips = document.getElementById('tag-chips');
                            var box = document.getElementById('tag-suggestions');
                            var sel = -1;
                            var tags = hidden.value.split(',').map(function(t){return t.trim()}).filter(Boolean);

                            function sync() {
                                hidden.value = tags.join(', ');
                                chips.innerHTML = '';
                                tags.forEach(function(tag, i) {
                                    var chip = document.createElement('span');
                                    chip.className = 'tag-chip';
                                    chip.textContent = tag;
                                    var btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'tag-chip-remove';
                                    btn.textContent = '×';
                                    btn.onclick = function() { tags.splice(i, 1); sync(); input.focus(); };
                                    chip.appendChild(btn);
                                    chips.appendChild(chip);
                                });
                            }

                            function addTag(text) {
                                text = text.trim();
                                if (!text) return;
                                var lower = text.toLowerCase();
                                var exists = tags.some(function(t){return t.toLowerCase()===lower});
                                if (exists) return;
                                tags.push(text);
                                sync();
                                input.value = '';
                                closeSuggestions();
                            }

                            function closeSuggestions() { box.innerHTML=''; box.style.display='none'; sel=-1; }

                            function showSuggestions() {
                                var q = input.value.trim().toLowerCase();
                                if (!q) { closeSuggestions(); return; }
                                var used = tags.map(function(t){return t.toLowerCase()});
                                var matches = allTags.filter(function(t) {
                                    return t.toLowerCase().indexOf(q) === 0 && used.indexOf(t.toLowerCase()) === -1;
                                });
                                if (!matches.length) { closeSuggestions(); return; }
                                sel = -1;
                                box.innerHTML = matches.map(function(t, i) {
                                    return '<div class="tag-suggestion" data-index="'+i+'">'+t+'</div>';
                                }).join('');
                                box.style.display = 'block';
                            }

                            input.addEventListener('input', showSuggestions);
                            input.addEventListener('focus', showSuggestions);
                            input.addEventListener('blur', function() { setTimeout(closeSuggestions, 150); });

                            input.addEventListener('keydown', function(e) {
                                var items = box.querySelectorAll('.tag-suggestion');
                                if (e.key === ',' || e.key === 'Enter') {
                                    e.preventDefault();
                                    if (sel >= 0 && items.length) addTag(items[sel].textContent);
                                    else addTag(input.value);
                                    return;
                                }
                                if (e.key === 'Backspace' && !input.value && tags.length) {
                                    tags.pop(); sync(); return;
                                }
                                if (e.key === 'Tab' && sel >= 0 && items.length) {
                                    e.preventDefault(); addTag(items[sel].textContent); return;
                                }
                                if (!items.length) return;
                                if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel+1, items.length-1); }
                                else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(sel-1, 0); }
                                else return;
                                items.forEach(function(el,i){el.classList.toggle('active',i===sel)});
                            });

                            box.addEventListener('mousedown', function(e) {
                                var t = e.target.closest('.tag-suggestion');
                                if (t) { e.preventDefault(); addTag(t.textContent); }
                            });

                            document.getElementById('tag-editor').addEventListener('click', function() { input.focus(); });

                            sync();
                        })();
                        </script>
                    </div>

                    <div class="form-group">
                        <label for="description">Description (Markdown)</label>
                        <div class="editor-tabs">
                            <button type="button" class="tab-btn active" data-tab="edit">Edit</button>
                            <button type="button" class="tab-btn" data-tab="preview">Preview</button>
                        </div>
                        <textarea id="description" name="description" rows="6"><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
                        <div id="md-preview" class="md-preview" style="display:none"></div>
                    </div>

                    <div class="form-group">
                        <label>Images</label>
                        <div class="admin-image-slots" id="image-slots"></div>
                        <div style="margin-top:0.5rem">
                            <input type="file" id="image-upload" accept="image/jpeg,image/png,image/webp" style="display:none">
                            <button type="button" id="btn-add-image" class="btn btn-small">+ Add image</button>
                        </div>
                        <div id="slot-hidden-inputs"></div>
                        <div id="image-editor" class="image-editor" style="display:none">
                            <div class="form-group" style="margin-bottom:0.75rem">
                                <label for="slot-alt">Alt text</label>
                                <input type="text" id="slot-alt" placeholder="Defaults to item title">
                            </div>
                            <canvas id="editor-canvas"></canvas>
                            <div class="editor-controls">
                                <button type="button" id="btn-rotate-left" title="Rotate left 90°">↶ Rotate Left</button>
                                <button type="button" id="btn-rotate-right" title="Rotate right 90°">↷ Rotate Right</button>
                                <button type="button" id="btn-crop" title="Crop selection">Crop</button>
                                <button type="button" id="btn-remove-bg" title="Remove image background">Remove BG</button>
                                <button type="button" id="btn-reset" title="Reset to original">Reset</button>
                                <label>Max size: <input type="number" id="resize-max" value="1200" min="100" max="4000" step="100"> px</label>
                                <button type="button" id="btn-resize">Resize</button>
                                <button type="button" id="btn-apply" class="btn btn-primary">Apply to slot</button>
                            </div>
                            <p class="editor-hint">Click and drag on the image to select a crop area. Use the buttons to rotate or resize.</p>
                            <div id="bg-threshold-panel" class="bg-threshold-panel" style="display:none">
                                <label>Threshold: <input type="range" id="bg-threshold" min="0" max="255" value="128" step="1"> <span id="bg-threshold-val">128</span></label>
                                <label><input type="checkbox" id="bg-feather" checked> Soft edges</label>
                                <div class="bg-threshold-actions">
                                    <button type="button" id="btn-bg-accept" class="btn btn-primary btn-small">Accept</button>
                                    <button type="button" id="btn-bg-cancel" class="btn btn-secondary btn-small">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                        $existing_images = [];
                        $existing_alts = [];
                        if ($edit_item) {
                            $existing_images = get_item_images($edit_item);
                            $existing_alts = $edit_item['image_alts'] ?? [];
                        }
                    ?>
                    <script>
                    var initialImages = <?= json_encode(array_values($existing_images)) ?>;
                    var initialAlts = <?= json_encode(array_values($existing_alts)) ?>;
                    </script>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?= $edit_item ? 'Update Item' : 'Add Item' ?></button>
                        <a href="admin.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </section>

            <section class="admin-section">
                <h2><?= $is_super ? 'All Items' : 'My Items' ?> (<?= count($items) ?>)</h2>
                <?php if (!empty($items)):
                    $user_emails = [];
                    if ($is_super) {
                        $db = get_db();
                        foreach ($db->query('SELECT id, email FROM users')->fetchAll() as $u) {
                            $user_emails[$u['id']] = $u['email'];
                        }
                    }
                ?>
                    <div class="filter-bar filter-bar-admin">
                        <input type="text" id="admin-search" class="search-input" placeholder="Search items...">
                        <?php if ($all_tags): ?>
                            <div class="tag-filters" id="admin-tag-filters">
                                <button class="tag-pill active" data-tag="">All</button>
                                <?php foreach ($all_tags as $tag => $count): ?>
                                    <button class="tag-pill" data-tag="<?= htmlspecialchars($tag) ?>"><?= htmlspecialchars($tag) ?> <span class="tag-count"><?= $count ?></span></button>
                                <?php endforeach; ?>
                            </div>
                            <button class="tag-expand" id="admin-tag-expand" style="display:none">&hellip;</button>
                        <?php endif; ?>
                    </div>
                    <div class="bulk-actions">
                        <label class="bulk-toggle"><input type="checkbox" id="toggle-all"> Select all</label>
                        <button type="button" id="btn-delete-selected" class="btn btn-small btn-danger" disabled>Delete selected</button>
                    </div>
                    <form id="bulk-delete-form" method="POST" action="api.php" style="display:none">
                        <input type="hidden" name="action" value="delete_selected">
                        <?= csrf_field() ?>
                        <div id="bulk-ids"></div>
                    </form>
                    <div class="admin-item-list" id="admin-item-list">
                        <?php foreach ($items as $item):
                            $offer_count = count_offers_for_item($item['id']);
                            $item_tags = get_item_tags($item);
                            $is_own = $item['user_id'] === $current_user['id'];
                        ?>
                            <div class="admin-item-card <?= $item['status'] !== 'available' ? 'item-sold' : '' ?>"
                                 data-tags="<?= htmlspecialchars(implode(',', $item_tags)) ?>"
                                 data-search="<?= htmlspecialchars(strtolower($item['title'] . ' ' . ($item['description'] ?? '') . ' ' . implode(' ', $item_tags))) ?>">
                                <input type="checkbox" class="item-select" value="<?= htmlspecialchars($item['id']) ?>">
                                <div class="admin-item-thumb">
                                    <?php $item_imgs = get_item_images($item); ?>
                                    <?php if ($item_imgs): ?>
                                        <img src="<?= htmlspecialchars($item_imgs[0]) ?>" alt="<?= htmlspecialchars(get_image_alt($item, 0)) ?>">
                                    <?php else: ?>
                                        <div class="no-image">No image</div>
                                    <?php endif; ?>
                                </div>
                                <div class="admin-item-info">
                                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                                    <span class="price-inline"><?= htmlspecialchars(format_price($item)) ?></span>
                                    <span class="status-badge status-<?= $item['status'] ?>"><?= $item['status'] ?></span>
                                    <?php if ($offer_count > 0): ?>
                                        <span class="offer-badge"><?= $offer_count ?> offer<?= $offer_count > 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                    <?php if ($is_super && !$is_own): ?>
                                        <span class="item-owner"><?= htmlspecialchars($user_emails[$item['user_id']] ?? '') ?></span>
                                    <?php endif; ?>
                                    <?php foreach ($item_tags as $tag): ?>
                                        <span class="item-tag"><?= htmlspecialchars($tag) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="admin-item-actions">
                                    <a href="admin.php?offers=<?= urlencode($item['id']) ?>" class="btn btn-small">Offers (<?= $offer_count ?>)</a>
                                    <?php if ($is_own): ?>
                                        <a href="admin.php?edit=<?= urlencode($item['id']) ?>" class="btn btn-small">Edit</a>
                                    <?php endif; ?>
                                    <form method="POST" action="api.php" class="inline-form" onsubmit="return confirm('Delete this item and its images?')">
                                        <input type="hidden" name="action" value="delete_item">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-small btn-danger"><?= $is_own ? 'Delete' : 'Remove' ?></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="empty-state filter-empty" id="admin-filter-empty" style="display:none">No items match your search.</p>
                    <script>
                    (function() {
                        var toggleAll = document.getElementById('toggle-all');
                        var boxes = document.querySelectorAll('.item-select');
                        var btnDelete = document.getElementById('btn-delete-selected');
                        var bulkForm = document.getElementById('bulk-delete-form');
                        var bulkIds = document.getElementById('bulk-ids');

                        function updateBtn() {
                            var checked = document.querySelectorAll('.item-select:checked');
                            btnDelete.disabled = checked.length === 0;
                            btnDelete.textContent = checked.length > 0 ? 'Delete selected (' + checked.length + ')' : 'Delete selected';
                        }

                        toggleAll.addEventListener('change', function() {
                            boxes.forEach(function(cb) {
                                if (cb.closest('.admin-item-card').style.display !== 'none') {
                                    cb.checked = toggleAll.checked;
                                }
                            });
                            updateBtn();
                        });

                        boxes.forEach(function(cb) {
                            cb.addEventListener('change', updateBtn);
                        });

                        btnDelete.addEventListener('click', function() {
                            var checked = document.querySelectorAll('.item-select:checked');
                            if (checked.length === 0) return;
                            if (!confirm('Delete ' + checked.length + ' item(s) and their images? This cannot be undone.')) return;
                            bulkIds.innerHTML = '';
                            checked.forEach(function(cb) {
                                var input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'ids[]';
                                input.value = cb.value;
                                bulkIds.appendChild(input);
                            });
                            bulkForm.submit();
                        });
                    })();
                    </script>
                <?php else: ?>
                    <p class="empty-state">No items yet. Add one above.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <script src="js/admin.js"></script>
</body>
</html>
