<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

$pageTitle = (isset($pageTitle) && is_string($pageTitle) && $pageTitle !== '')
    ? $pageTitle
    : 'Digital Canteen';
$currentUser = current_user();
$homePath = $currentUser ? home_path_for_role($currentUser['role']) : null;
$cartItemCount = 0;
if ($currentUser && $currentUser['role'] === 'CUSTOMER') {
    require_once __DIR__ . '/cart.php';
    $cartItemCount = cart_count((int) $currentUser['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1e5c40">
    <title><?= e($pageTitle) ?> · Digital Canteen</title>
    <?php if (!empty($robotsNoIndex)): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/main.css')) ?>">
</head>
<body>
    <header class="site-header">
        <div class="shell header-row">
            <a class="brand" href="<?= e(url('index.php')) ?>">Digital Canteen</a>
            <nav class="site-nav" aria-label="Main">
                <a href="<?= e(url('index.php')) ?>">Home</a>
                <?php if ($currentUser && $homePath): ?>
                    <a href="<?= e(url($homePath)) ?>">Dashboard</a>
                    <?php if ($currentUser['role'] === 'CUSTOMER'): ?>
                        <a href="<?= e(url('customer/menu.php')) ?>">Menu</a>
                        <a href="<?= e(url('customer/cart.php')) ?>">Cart <span class="cart-count" data-cart-count<?= $cartItemCount > 0 ? '' : ' hidden' ?>><?= e((string) $cartItemCount) ?></span></a>
                        <a href="<?= e(url('customer/orders.php')) ?>">My orders</a>
                    <?php elseif ($currentUser['role'] === 'ADMIN'): ?>
                        <a href="<?= e(url('admin/categories.php')) ?>">Categories</a>
                        <a href="<?= e(url('admin/foods.php')) ?>">Food items</a>
                        <a href="<?= e(url('admin/staff.php')) ?>">Staff</a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('logout.php')) ?>">
                        <?= csrf_field() ?>
                        <button class="button-link" type="submit">Log out</button>
                    </form>

                    <?php /* ===== Phase 5.2 — notification bell (logged-in users only) ===== */ ?>
                    <div class="bell-wrap"
                         id="notif-bell-wrap"
                         data-notif-api="<?= e(url('api/notifications/notifications.php')) ?>"
                         data-push-api="<?= e(url('api/notifications/push.php')) ?>"
                         data-sw-url="<?= e(url('sw.js')) ?>"
                         data-sw-scope="<?= e(url('')) ?>"
                         data-csrf="<?= e(csrf_token()) ?>"
                         data-login="<?= e(url('login.php')) ?>">
                        <button class="bell-button"
                                id="notif-bell-btn"
                                type="button"
                                aria-haspopup="true"
                                aria-expanded="false"
                                aria-controls="notif-panel"
                                aria-label="Notifications">
                            <svg class="bell-icon" aria-hidden="true" focusable="false"
                                 width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                            </svg>
                            <span class="notif-badge" id="notif-badge" hidden aria-live="off">0</span>
                        </button>

                        <div class="notif-panel" id="notif-panel" role="dialog"
                             aria-label="Notifications" aria-modal="false" hidden>
                            <div class="notif-panel-head">
                                <span class="notif-panel-title">Notifications</span>
                                <button class="button-link notif-mark-all-btn"
                                        id="notif-mark-all"
                                        type="button">Mark all as read</button>
                            </div>
                            <div class="notif-push-bar" id="notif-push-bar" hidden>
                                <span class="notif-push-status" id="notif-push-status">Browser push alerts</span>
                                <button class="button button-secondary button-small notif-push-btn"
                                        id="notif-push-btn"
                                        type="button">Enable alerts</button>
                            </div>
                            <div class="notif-list" id="notif-list" role="list"
                                 aria-live="polite" aria-label="Notification list">
                                <p class="notif-state">Loading&hellip;</p>
                            </div>
                            <div class="notif-panel-foot">
                                <button class="button button-secondary button-small"
                                        id="notif-load-more"
                                        type="button" hidden>Load more</button>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <a href="<?= e(url('login.php')) ?>">Log in</a>
                    <a href="<?= e(url('register.php')) ?>">Create account</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <?php if ($currentUser): ?>
    <script src="<?= e(url('assets/js/app.js')) ?>"></script>
    <script src="<?= e(url('assets/js/notifications.js')) ?>"></script>
    <?php endif; ?>
    <main class="shell">
        <?php $flash = get_flash(); ?>
        <?php if ($flash): ?>
            <p class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>
