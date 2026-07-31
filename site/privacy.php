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
                <li>If you choose to add a location to a listing, the <strong>coordinates you last used</strong> are remembered on your account so they can pre-fill your next listing. You can overwrite or clear them at any time.</li>
            </ul>
            <p>The site administrator can see your email address and the items you have listed. Your email address is not displayed publicly on the site.</p>

            <h3>Item locations</h3>
            <p>Sellers may optionally attach coordinates to a listing so buyers know roughly where an item is. This is entirely optional — a listing with no location is the default.</p>
            <ul>
                <li>The seller chooses how precise the published location is: <strong>approximately 100&nbsp;m</strong>, <strong>approximately 1&nbsp;km</strong>, or <strong>no location at all</strong>.</li>
                <li>Coordinates are <strong>published rounded</strong> to that chosen precision. A more exact position is never shown on the site, sent in an email, or returned by the API — so a listing does not reveal a precise address.</li>
                <li>Published coordinates are <strong>visible to anyone</strong> viewing the listing, as they are part of the public item page.</li>
                <li>A seller can remove a location at any time by clearing the coordinates or setting the precision to “no location”.</li>
            </ul>
            <p>Sellers may fill the coordinates in by hand, or use the optional “Use my location” button. That button uses your browser's built-in geolocation feature, which <strong>always asks your permission first</strong> and can be declined — the coordinates can simply be typed instead. This site does <strong>not</strong> attempt to infer anyone's location from their IP address, and does not track the location of visitors browsing the site.</p>
            <p>The “Open in maps” link on a listing is a standard <code>geo:</code> link handled by your own device and whichever map application you have chosen. This website does not load any map, script, or image from a map provider, so no map company is told which listings you look at. If you follow that link, the coordinates are passed to your own map application.</p>

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

            <h3>Machine-readable listings</h3>
            <p>This site publishes the same public listing information — titles, descriptions, prices, tags, images and any location a seller has chosen to share — through an open API endpoint, so that software and AI assistants can browse the catalogue. It exposes only what is already on the public pages: no email addresses, no account details, and no more location precision than the seller selected.</p>
            <p>Offers can also be submitted through that endpoint. When they are, they follow exactly the same rules as offers made through the website: the buyer's email address is used to send the two notification emails and is not recorded on this server.</p>

            <h3>Third parties</h3>
            <p>No personal data is shared with, sold to, or processed by any third party. No external analytics, advertising, or tracking services are used on this website.</p>

            <h3>Your rights</h3>
            <p>If you have a seller account, you can manage your items and offers through the admin panel — including adding, changing, or removing the location on any listing, and the remembered coordinates on your account. To delete your account, contact the site administrator. Buyer offer data contains no personal information on this server, so there is nothing to access, correct, or delete.</p>

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
