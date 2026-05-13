<?php
require_once __DIR__ . '/config.php';

$id = $_GET['id'] ?? '';
$item = get_item($id);

if (!$item || $item['status'] !== 'available') {
    header('HTTP/1.1 404 Not Found');
    echo '<h1>Item not found</h1><p><a href="index.php">Back to listings</a></p>';
    exit;
}

$offers = get_offers_for_item($id);
$offer_count = count($offers);
$has_above_price = false;
foreach ($offers as $o) {
    if ($o['amount'] >= $item['price']) { $has_above_price = true; break; }
}

$success = isset($_GET['offered']);
$offer_flash = null;
if ($success && isset($_SESSION['offer_flash'])) {
    $offer_flash = $_SESSION['offer_flash'];
    unset($_SESSION['offer_flash']);
}
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($item['title']) ?> — <?= SITE_TITLE ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1><a href="index.php"><?= htmlspecialchars(SITE_TITLE) ?></a></h1>
            <p class="tagline"><?= htmlspecialchars(SITE_TAGLINE) ?></p>
        </div>
    </header>

    <main class="container">
        <a href="index.php" class="back-link">&larr; Back to all items</a>

        <article class="item-detail">
            <?php $images = get_item_images($item); ?>
            <div class="item-detail-image">
                <?php if (count($images) > 1): ?>
                    <?php $alts = array_map(fn($i) => get_image_alt($item, $i), array_keys($images)); ?>
                    <div class="gallery">
                        <img src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($alts[0]) ?>" id="gallery-main" class="gallery-main">
                        <button class="gallery-arrow gallery-prev" id="gallery-prev">&lsaquo;</button>
                        <button class="gallery-arrow gallery-next" id="gallery-next">&rsaquo;</button>
                    </div>
                    <div class="gallery-thumbs" id="gallery-thumbs">
                        <?php foreach ($images as $i => $img): ?>
                            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($alts[$i]) ?>" class="gallery-thumb<?= $i === 0 ? ' active' : '' ?>" data-index="<?= $i ?>">
                        <?php endforeach; ?>
                    </div>
                    <script>
                    (function() {
                        var images = <?= json_encode(array_values($images)) ?>;
                        var alts = <?= json_encode(array_values($alts)) ?>;
                        var idx = 0;
                        var main = document.getElementById('gallery-main');
                        var thumbs = document.querySelectorAll('.gallery-thumb');
                        function show(i) {
                            idx = (i + images.length) % images.length;
                            main.src = images[idx];
                            main.alt = alts[idx];
                            thumbs.forEach(function(t, j) { t.classList.toggle('active', j === idx); });
                        }
                        document.getElementById('gallery-prev').addEventListener('click', function() { show(idx - 1); });
                        document.getElementById('gallery-next').addEventListener('click', function() { show(idx + 1); });
                        thumbs.forEach(function(t) { t.addEventListener('click', function() { show(parseInt(this.dataset.index)); }); });
                    })();
                    </script>
                <?php elseif (count($images) === 1): ?>
                    <img src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars(get_image_alt($item, 0)) ?>">
                <?php endif; ?>
            </div>

            <div class="item-detail-content">
                <h2><?= htmlspecialchars($item['title']) ?></h2>
                <div class="price-tag"><?= CURRENCY ?><?= number_format($item['price'], 2) ?></div>

                <div class="description">
                    <?= parse_markdown($item['description']) ?>
                </div>

                <div class="offer-status">
                    <?php if ($offer_count > 0): ?>
                        <p class="offer-count"><?= $offer_count ?> offer<?= $offer_count > 1 ? 's' : '' ?> received</p>
                        <?php if ($has_above_price): ?>
                            <p class="offer-above">An offer at or above the listed price has been received. New offers above the listed price will be queued.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="no-offers">No offers yet — be the first!</p>
                    <?php endif; ?>
                </div>

                <?php if ($success): ?>
                    <div class="offer-confirmed">
                        <div class="success-message">
                            <p><strong>Offer submitted!</strong></p>
                            <?php if ($offer_flash): ?>
                                <p>Your offer of <?= CURRENCY ?><?= number_format($offer_flash['amount'], 2) ?> has been sent to the seller. A confirmation has been sent to your email.</p>
                            <?php else: ?>
                                <p>Your offer has been sent to the seller.</p>
                            <?php endif; ?>
                        </div>

                        <?php if ($offer_flash): ?>
                            <div class="offer-details-readonly">
                                <div class="form-group">
                                    <label>Your email</label>
                                    <input type="email" value="<?= htmlspecialchars($offer_flash['email']) ?>" disabled>
                                </div>
                                <div class="form-group">
                                    <label>Your offer (<?= CURRENCY ?>)</label>
                                    <input type="text" value="<?= number_format($offer_flash['amount'], 2) ?>" disabled>
                                </div>
                            </div>
                        <?php endif; ?>

                        <p class="privacy-note">Your email address was sent directly to the seller and is not stored on this website.</p>
                        <a href="index.php" class="btn btn-primary btn-back-large">&larr; Browse all items</a>
                    </div>
                <?php else: ?>
                    <?php if ($error === 'too_fast'): ?>
                        <div class="error-msg"><p>Please wait a few seconds before submitting another offer.</p></div>
                    <?php endif; ?>

                    <form class="offer-form" id="offer-form" method="POST" action="api.php">
                        <input type="hidden" name="action" value="submit_offer">
                        <input type="hidden" name="item_id" value="<?= htmlspecialchars($item['id']) ?>">

                        <h3>Make an offer</h3>

                        <div class="form-group">
                            <label for="email">Your email</label>
                            <input type="email" id="email" name="email" required placeholder="your@email.com">
                        </div>

                        <div class="form-group">
                            <label for="amount">Your offer (<?= CURRENCY ?>)</label>
                            <input type="number" id="amount" name="amount" required min="0.01" step="0.01" placeholder="<?= number_format($item['price'], 2) ?>">
                        </div>

                        <div style="position:absolute;left:-9999px" aria-hidden="true">
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="rules-reminder">
                            <p><small>The first offer at or above the listed price takes priority. Below-price offers may be accepted at the seller's discretion. You will only be contacted if your offer is selected.</small></p>
                        </div>

                        <p class="privacy-note"><small>Your email address is sent directly to the seller and is not stored on this website.</small></p>

                        <button type="submit" class="btn btn-primary">Submit Offer</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    </main>

    <footer>
        <div class="container">
            <p><?php if (OWNER_NAME): ?>&copy; <?= date('Y') ?> <?= htmlspecialchars(OWNER_NAME) ?> &middot; <?php endif; ?><a href="privacy.php">Privacy Policy</a></p>
            <p class="footer-credit">Design by MGZ Consulting LLC</p>
        </div>
    </footer>
</body>
</html>
