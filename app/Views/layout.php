<?php

declare(strict_types=1);

$homePrefix = $isHome ? '' : '/';
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$activePath = is_string($requestPath) ? '/' . trim(rawurldecode($requestPath), '/') : '/';
$activePath = $activePath === '/' ? '/' : rtrim($activePath, '/');
$isActive = static fn (string $path): bool => $activePath === $path;
$isSectionActive = static fn (string $path): bool => $activePath === $path || str_starts_with($activePath, $path . '/');
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
            <?php if (!is_array($currentUser)): ?>
                <a class="nav-link" href="<?= $homePrefix ?>#why">Why connect</a>
                <a class="nav-link" href="<?= $homePrefix ?>#journey">The journey</a>
                <a class="nav-link" href="<?= $homePrefix ?>#memory-lane">Memory lane</a>
                <a class="nav-link" href="/login"<?= $isActive('/login') ? ' aria-current="page"' : '' ?>>Sign in</a>
                <a class="nav-cta" href="/register"<?= $isActive('/register') ? ' aria-current="page"' : '' ?>>Join the network <span aria-hidden="true">↗</span></a>
            <?php else: ?>
                <a class="nav-link" href="/dashboard"<?= $isActive('/dashboard') ? ' aria-current="page"' : '' ?>>Dashboard</a>
                <?php
                $exploreSections = [
                    '/directory' => 'Directory',
                    '/events' => 'Events',
                    '/community' => 'Community',
                    '/memory-lane' => 'Memory Lane',
                    '/notifications' => 'Notifications',
                ];
                $activeExploreLabel = null;
                foreach ($exploreSections as $sectionPath => $sectionLabel) {
                    if ($isSectionActive($sectionPath)) {
                        $activeExploreLabel = $sectionLabel;
                        break;
                    }
                }
                ?>
                <details class="nav-group">
                    <summary<?= $activeExploreLabel !== null ? ' class="nav-group-current"' : '' ?>>Explore<?= $activeExploreLabel !== null ? ': ' . \AlumniConnect\Support\View::escape($activeExploreLabel) : '' ?></summary>
                    <div class="nav-group-menu">
                        <a href="/directory"<?= $isSectionActive('/directory') ? ' aria-current="page"' : '' ?>>Directory</a>
                        <a href="/events"<?= $isSectionActive('/events') ? ' aria-current="page"' : '' ?>>Events</a>
                        <a href="/community"<?= $isSectionActive('/community') ? ' aria-current="page"' : '' ?>>Community</a>
                        <a href="/memory-lane"<?= $isSectionActive('/memory-lane') ? ' aria-current="page"' : '' ?>>Memory Lane</a>
                        <a href="/notifications"<?= $isSectionActive('/notifications') ? ' aria-current="page"' : '' ?>>Notifications</a>
                    </div>
                </details>
                <?php if (\AlumniConnect\Security\Auth::hasRole($currentUser, 'admin')): ?>
                    <?php
                    $adminSections = [
                        '/admin/users' => 'Accounts',
                        '/admin/operations' => 'Operations',
                        '/admin/audit' => 'Audit log',
                    ];
                    $activeAdminLabel = null;
                    foreach ($adminSections as $sectionPath => $sectionLabel) {
                        if ($isActive($sectionPath)) {
                            $activeAdminLabel = $sectionLabel;
                            break;
                        }
                    }
                    ?>
                    <details class="nav-group">
                        <summary<?= $activeAdminLabel !== null ? ' class="nav-group-current"' : '' ?>>Admin tools<?= $activeAdminLabel !== null ? ' — ' . \AlumniConnect\Support\View::escape($activeAdminLabel) : '' ?></summary>
                        <div class="nav-group-menu">
                            <a href="/admin/users"<?= $isActive('/admin/users') ? ' aria-current="page"' : '' ?>>Account audit</a>
                            <a href="/admin/operations"<?= $isActive('/admin/operations') ? ' aria-current="page"' : '' ?>>Admin operations</a>
                            <a href="/admin/audit"<?= $isActive('/admin/audit') ? ' aria-current="page"' : '' ?>>Audit log</a>
                        </div>
                    </details>
                <?php endif; ?>
                <a class="nav-link" href="/profile"<?= $isActive('/profile') ? ' aria-current="page"' : '' ?>>My profile</a>
                <form class="nav-logout" action="/logout" method="post">
                    <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                    <button class="nav-logout-button" type="submit">Sign out</button>
                </form>
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
