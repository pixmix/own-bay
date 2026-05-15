<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — <?= SITE_TITLE ?></title>
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

        <article class="privacy-policy">
            <h2>Privacy Policy</h2>
            <p class="privacy-updated">Last updated: <?= date('F Y') ?></p>

            <h3>Who we are</h3>
            <p>This website is operated<?php if (OWNER_NAME): ?> by <?= htmlspecialchars(OWNER_NAME) ?><?php endif; ?> for the purpose of selling second-hand items. It is a simple marketplace — not a commercial platform.</p>

            <h3>Seller accounts</h3>
            <p>If you register as a seller, the following information is stored on this server:</p>
            <ul>
                <li>Your <strong>email address</strong> (used for login, offer notifications, and account recovery).</li>
                <li>A <strong>hashed password</strong> — your actual password is never stored.</li>
                <li>Your <strong>items, images, and offers</strong> are associated with your account.</li>
            </ul>
            <p>The site administrator can see your email address and the items you have listed. Your email address is not displayed publicly on the site.</p>

            <h3>Buyer offers</h3>
            <p>When you submit an offer on an item as a buyer:</p>
            <ul>
                <li>Your email address is used immediately to send two emails: a confirmation to you and a notification to the item's seller.</li>
                <li>Your email address is <strong>not recorded</strong> in any file, database, or log on this server.</li>
                <li>Only the offer amount, date, and a sequential reference number are stored, with no link to your identity.</li>
                <li>The seller receives your email address solely via the notification email.</li>
            </ul>

            <h3>Cookies</h3>
            <p>This website uses a single cookie:</p>
            <ul>
                <li><strong>PHPSESSID</strong> — a standard PHP session cookie. It is used for login sessions and to prevent rapid repeated submissions. It does not track you across visits, does not contain personal information, and is deleted when you close your browser.</li>
            </ul>
            <p>This website does not use analytics cookies, advertising cookies, or any third-party tracking.</p>

            <h3>Third parties</h3>
            <p>No personal data is shared with, sold to, or processed by any third party. No external analytics, advertising, or tracking services are used on this website.</p>

            <h3>Your rights</h3>
            <p>If you have a seller account, you can manage your items and offers through the admin panel. To delete your account, contact the site administrator. Buyer offer data contains no personal information on this server, so there is nothing to access, correct, or delete.</p>

            <h3>Contact</h3>
            <p><?php if (OWNER_NAME): ?><?= htmlspecialchars(OWNER_NAME) ?> — reachable<?php else: ?>The site owner is reachable<?php endif; ?> via the email address shown in offer confirmation emails.</p>
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
