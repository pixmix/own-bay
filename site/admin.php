<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'dashboard';

if ($action === 'login') {
    $error = isset($_GET['error']);
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
            <?php if ($error): ?>
                <p class="error-msg">Wrong password.</p>
            <?php endif; ?>
            <form method="POST" action="api.php">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary">Log in</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_admin();

$items = load_items();
$all_tags = get_all_tags($items);
$edit_id = $_GET['edit'] ?? '';
$edit_item = null;
if ($edit_id) {
    $edit_item = get_item($edit_id);
}

$view_offers_id = $_GET['offers'] ?? '';
$view_offers_item = null;
$view_offers = [];
if ($view_offers_id) {
    $view_offers_item = get_item($view_offers_id);
    if ($view_offers_item) {
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
                <a href="admin.php?action=settings">Settings</a>
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
            <div class="success-message"><p>Item deleted.</p></div>
        <?php endif; ?>

        <?php if ($action === 'settings'):
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
                        <div class="success-message"><p>Test email sent successfully.</p></div>
                    <?php elseif ($_GET['test'] === 'no_config'): ?>
                        <div class="error-msg"><p>Configure notification email and SMTP host first.</p></div>
                    <?php else: ?>
                        <div class="error-msg"><p>Failed to send test email. Check your SMTP settings and server error log.</p></div>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="POST" action="api.php" class="item-form">
                    <input type="hidden" name="action" value="save_settings">

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

                    <h3 style="margin: 1.5rem 0 1rem; font-weight: 500;">Email Notifications</h3>

                    <div class="form-group">
                        <label for="notification_email">Notification email (where you receive offer alerts)</label>
                        <input type="email" id="notification_email" name="notification_email" value="<?= htmlspecialchars($settings['notification_email']) ?>" placeholder="you@example.com">
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
                            <input type="password" id="smtp_pass" name="smtp_pass" placeholder="<?= empty($settings['smtp_pass']) ? '' : '(unchanged)' ?>">
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
                    <span class="price-inline"><?= CURRENCY ?><?= number_format($view_offers_item['price'], 2) ?></span>
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
                            <?php foreach ($view_offers as $offer): ?>
                                <tr class="<?= $offer['amount'] >= $view_offers_item['price'] ? 'offer-above-price' : 'offer-below-price' ?>">
                                    <td><?= htmlspecialchars($offer['id']) ?></td>
                                    <td class="offer-amount"><?= CURRENCY ?><?= number_format($offer['amount'], 2) ?>
                                        <?php if ($offer['amount'] >= $view_offers_item['price']): ?>
                                            <span class="badge badge-green">&ge; price</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('Y-m-d H:i', strtotime($offer['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($offer['status']) ?></td>
                                    <td>
                                        <form method="POST" action="api.php" class="inline-form" onsubmit="return confirm('Delete offer #<?= htmlspecialchars($offer['id']) ?>?')">
                                            <input type="hidden" name="action" value="delete_offer">
                                            <input type="hidden" name="offer_id" value="<?= htmlspecialchars($offer['id']) ?>">
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

                    <div class="form-row">
                        <div class="form-group flex-2">
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" required value="<?= htmlspecialchars($edit_item['title'] ?? '') ?>">
                        </div>
                        <div class="form-group flex-1">
                            <label for="price">Price (<?= CURRENCY ?>)</label>
                            <input type="number" id="price" name="price" required min="0.01" step="0.01" value="<?= htmlspecialchars($edit_item['price'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="tags">Tags (comma-separated)</label>
                        <input type="text" id="tags" name="tags" value="<?= htmlspecialchars(implode(', ', get_item_tags($edit_item ?? []))) ?>" placeholder="electronics, tools, cables">
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
                <h2>All Items (<?= count($items) ?>)</h2>
                <?php if (!empty($items)): ?>
                    <div class="filter-bar filter-bar-admin">
                        <input type="text" id="admin-search" class="search-input" placeholder="Search items...">
                        <?php if ($all_tags): ?>
                            <div class="tag-filters" id="admin-tag-filters">
                                <button class="tag-pill active" data-tag="">All</button>
                                <?php foreach ($all_tags as $tag => $count): ?>
                                    <button class="tag-pill" data-tag="<?= htmlspecialchars($tag) ?>"><?= htmlspecialchars($tag) ?> <span class="tag-count"><?= $count ?></span></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="admin-item-list" id="admin-item-list">
                        <?php foreach ($items as $item): ?>
                            <?php
                                $item_offers = get_offers_for_item($item['id']);
                                $offer_count = count($item_offers);
                                $item_tags = get_item_tags($item);
                            ?>
                            <div class="admin-item-card <?= $item['status'] !== 'available' ? 'item-sold' : '' ?>"
                                 data-tags="<?= htmlspecialchars(implode(',', $item_tags)) ?>"
                                 data-search="<?= htmlspecialchars(strtolower($item['title'] . ' ' . ($item['description'] ?? '') . ' ' . implode(' ', $item_tags))) ?>">
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
                                    <span class="price-inline"><?= CURRENCY ?><?= number_format($item['price'], 2) ?></span>
                                    <span class="status-badge status-<?= $item['status'] ?>"><?= $item['status'] ?></span>
                                    <?php if ($offer_count > 0): ?>
                                        <span class="offer-badge"><?= $offer_count ?> offer<?= $offer_count > 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                    <?php foreach ($item_tags as $tag): ?>
                                        <span class="item-tag"><?= htmlspecialchars($tag) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="admin-item-actions">
                                    <a href="admin.php?offers=<?= urlencode($item['id']) ?>" class="btn btn-small">Offers (<?= $offer_count ?>)</a>
                                    <a href="admin.php?edit=<?= urlencode($item['id']) ?>" class="btn btn-small">Edit</a>
                                    <form method="POST" action="api.php" class="inline-form" onsubmit="return confirm('Delete this item and its images?')">
                                        <input type="hidden" name="action" value="delete_item">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                                        <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="empty-state filter-empty" id="admin-filter-empty" style="display:none">No items match your search.</p>
                <?php else: ?>
                    <p class="empty-state">No items yet. Add one above.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <script src="js/admin.js"></script>
</body>
</html>
