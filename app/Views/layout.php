<?php

declare(strict_types=1);

$homePrefix = $isHome ? '' : '/';
$flashes = $flashes ?? [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17191a">
    <meta name="description" content="A place for alumni to find their people, come back together, and open the next door for someone else.">
    <title><?= \AlumniConnect\Support\View::escape($pageTitle) ?></title>
    <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
    <?php if ($isHome): ?>
        <link rel="preload" href="/assets/images/campus.jpg" as="image" fetchpriority="high">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/site.css">
    <?php if (($usesQrCode ?? false) === true): ?>
        <script src="/assets/js/vendor/qrcode.min.js" defer></script>
    <?php endif; ?>
    <?php if (($usesEventTools ?? false) === true): ?>
        <script src="/assets/js/event-tools.js" defer></script>
    <?php endif; ?>
    <script src="/assets/js/site.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>
    <?php if ($isHome): ?>
        <div class="scroll-progress" aria-hidden="true"><span></span></div>
    <?php endif; ?>

    <header class="site-header">
        <a class="wordmark" href="/" aria-label="AlumniConnect home">
            <span class="wordmark-mark" aria-hidden="true">A<span>/</span>C</span>
            <span class="wordmark-name">Alumni<span>Connect</span></span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-navigation">
            <span class="menu-toggle-label">Menu</span>
            <span class="menu-toggle-icon" aria-hidden="true"><i></i><i></i></span>
        </button>
        <nav class="site-navigation" id="site-navigation" aria-label="Main navigation">
            <a href="<?= $homePrefix ?>#why">Why connect</a>
            <a href="<?= $homePrefix ?>#journey">The journey</a>
            <?php if (is_array($currentUser)): ?>
                <a href="/memory-lane">Memory lane</a>
            <?php else: ?>
                <a href="<?= $homePrefix ?>#memory-lane">Memory lane</a>
            <?php endif; ?>
            <?php if (is_array($currentUser)): ?>
                <a href="/dashboard">Dashboard</a>
                <a href="/directory">Directory</a>
                <a href="/events">Events</a>
                <a href="/community">Community</a>
                <a href="/notifications">Notifications</a>
                <?php if (\AlumniConnect\Security\Auth::hasRole($currentUser, 'admin')): ?>
                    <a href="/admin/users">Account audit</a>
                    <a href="/admin/operations">Admin operations</a>
                    <a href="/admin/audit">Audit log</a>
                <?php endif; ?>
                <a href="/profile">My profile</a>
                <form class="nav-logout" action="/logout" method="post">
                    <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                    <button class="nav-logout-button" type="submit">Sign out <span aria-hidden="true">↗</span></button>
                </form>
            <?php else: ?>
                <a href="/login">Sign in</a>
                <a class="nav-cta" href="/register">Join the network <span aria-hidden="true">↗</span></a>
            <?php endif; ?>
        </nav>
    </header>

    <main id="main">
        <?php foreach ($flashes as $kind => $message): ?>
            <div class="flash flash-<?= \AlumniConnect\Support\View::escape($kind) ?>" role="status">
                <?= \AlumniConnect\Support\View::escape($message) ?>
            </div>
        <?php endforeach; ?>
        <?= $pageContent ?>
    </main>

    <footer class="site-footer">
        <a class="wordmark footer-wordmark" href="/" aria-label="AlumniConnect, back to top">
            <span class="wordmark-mark" aria-hidden="true">A<span>/</span>C</span>
            <span class="wordmark-name">Alumni<span>Connect</span></span>
        </a>
        <p>Built for the people who made the place matter.</p>
        <a href="<?= $homePrefix ?>#top" class="footer-top">Back to top <span aria-hidden="true">↑</span></a>
        <?php if ($isHome): ?>
            <small>Illustrative landing-page imagery · Member and event data require sign-in</small>
        <?php else: ?>
            <small>AlumniConnect · Local development preview</small>
        <?php endif; ?>
    </footer>
</body>
</html>
