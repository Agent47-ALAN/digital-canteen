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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · Digital Canteen</title>
    <?php if (!empty($robotsNoIndex)): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
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
                    <?php elseif ($currentUser['role'] === 'ADMIN'): ?>
                        <a href="<?= e(url('admin/categories.php')) ?>">Categories</a>
                        <a href="<?= e(url('admin/foods.php')) ?>">Food items</a>
                        <a href="<?= e(url('admin/staff.php')) ?>">Staff</a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('logout.php')) ?>">
                        <?= csrf_field() ?>
                        <button class="button-link" type="submit">Log out</button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(url('login.php')) ?>">Log in</a>
                    <a href="<?= e(url('register.php')) ?>">Create account</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="shell">
        <?php $flash = get_flash(); ?>
        <?php if ($flash): ?>
            <p class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>
