<?php
require_once __DIR__ . '/config.php';

$items = get_all_available_items();
$all_tags = get_all_tags($items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(SITE_TITLE) ?></title>
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
        <section class="rules-banner">
            <details>
                <summary>How offers work</summary>
                <div class="rules-content">
                    <p>You are free to make an offer above or below the listed price.</p>
                    <ul>
                        <li>The <strong>first highest offer</strong> has priority.</li>
                        <li>However, once an offer <strong>meets or exceeds the listed price</strong>, that offer takes absolute priority — even if a higher offer arrives later.</li>
                        <li>Items remain visible until a sale is finalised.</li>
                        <li>A sale happens after the seller contacts the winning offerer by email and payment is received.</li>
                    </ul>
                </div>
            </details>
        </section>

        <?php if (!empty($items)): ?>
            <div class="filter-bar">
                <input type="text" id="search-input" class="search-input" placeholder="Search items...">
                <?php if ($all_tags): ?>
                    <div class="tag-filters" id="tag-filters">
                        <button class="tag-pill active" data-tag="">All</button>
                        <?php foreach ($all_tags as $tag => $count): ?>
                            <button class="tag-pill" data-tag="<?= htmlspecialchars($tag) ?>"><?= htmlspecialchars($tag) ?> <span class="tag-count"><?= $count ?></span></button>
                        <?php endforeach; ?>
                    </div>
                    <button class="tag-expand" id="tag-expand" style="display:none">&hellip;</button>
                <?php endif; ?>
            </div>

            <div class="item-grid" id="item-grid">
                <?php foreach ($items as $item): ?>
                    <?php $imgs = get_item_images($item); $tags = get_item_tags($item); ?>
                    <a href="item.php?id=<?= urlencode($item['id']) ?>" class="item-card"
                       data-tags="<?= htmlspecialchars(implode(',', $tags)) ?>"
                       data-search="<?= htmlspecialchars(strtolower($item['title'] . ' ' . ($item['description'] ?? '') . ' ' . implode(' ', $tags))) ?>">
                        <div class="item-image">
                            <?php if ($imgs): ?>
                                <img src="<?= htmlspecialchars($imgs[0]) ?>" alt="<?= htmlspecialchars(get_image_alt($item, 0)) ?>" loading="lazy">
                            <?php endif; ?>
                        </div>
                        <div class="item-info">
                            <h2><?= htmlspecialchars($item['title']) ?></h2>
                            <span class="price"><?= CURRENCY ?><?= number_format($item['price'], 2) ?></span>
                        </div>
                        <?php if ($tags): ?>
                            <div class="item-tags">
                                <?php foreach ($tags as $tag): ?>
                                    <span class="item-tag"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <p class="empty-state filter-empty" id="filter-empty" style="display:none">No items match your search.</p>

            <script>
            (function() {
                var search = document.getElementById('search-input');
                var pills = document.querySelectorAll('.tag-pill');
                var allBtn = document.querySelector('.tag-pill[data-tag=""]');
                var cards = document.querySelectorAll('.item-card');
                var empty = document.getElementById('filter-empty');
                var activeTags = [];

                function filter() {
                    var q = search.value.toLowerCase().trim();
                    var visible = 0;
                    cards.forEach(function(card) {
                        var matchTag = !activeTags.length || activeTags.some(function(t) {
                            return (',' + card.dataset.tags + ',').indexOf(',' + t + ',') !== -1;
                        });
                        var matchSearch = !q || card.dataset.search.indexOf(q) !== -1;
                        var show = matchTag && matchSearch;
                        card.style.display = show ? '' : 'none';
                        if (show) visible++;
                    });
                    empty.style.display = visible ? 'none' : '';
                }

                search.addEventListener('input', filter);
                pills.forEach(function(pill) {
                    pill.addEventListener('click', function() {
                        var tag = this.dataset.tag;
                        if (!tag) {
                            activeTags = [];
                            pills.forEach(function(p) { p.classList.remove('active'); });
                            allBtn.classList.add('active');
                        } else {
                            allBtn.classList.remove('active');
                            var idx = activeTags.indexOf(tag);
                            if (idx !== -1) { activeTags.splice(idx, 1); this.classList.remove('active'); }
                            else { activeTags.push(tag); this.classList.add('active'); }
                            if (!activeTags.length) allBtn.classList.add('active');
                        }
                        filter();
                    });
                });
            })();
            (function() {
                var tf = document.getElementById('tag-filters');
                var btn = document.getElementById('tag-expand');
                if (!tf || !btn) return;
                if (tf.scrollHeight > tf.clientHeight + 2) btn.style.display = '';
                btn.addEventListener('click', function() {
                    tf.classList.toggle('expanded');
                    btn.textContent = tf.classList.contains('expanded') ? 'Less' : '…';
                });
            })();
            </script>
        <?php else: ?>
            <p class="empty-state">No items available at the moment. Check back soon!</p>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p><?php if (OWNER_NAME): ?>&copy; <?= date('Y') ?> <?= htmlspecialchars(OWNER_NAME) ?> &middot; <?php endif; ?><a href="privacy.php">Privacy Policy</a><?php if (get_setting('show_registration_link', '0') === '1' && registration_open()): ?> &middot; <a href="admin.php?action=register">Become a seller</a><?php endif; ?></p>
            <p class="footer-credit">Design by MGZ Consulting LLC</p>
        </div>
    </footer>
</body>
</html>
