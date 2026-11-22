<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CUSTOMER');

$pageTitle = 'Menu';
require __DIR__ . '/../includes/header.php';
?>
<div id="app"
     data-api="<?= e(url('api/customer/menu.php')) ?>"
     data-login="<?= e(url('login.php')) ?>">
    <section class="panel">
        <p class="eyebrow">Customer</p>
        <h1>Today&rsquo;s menu</h1>
        <p>Browse what the canteen serves. Ordering will be added in a later phase.</p>
        <form id="menu-form" class="menu-search" role="search">
            <label for="menu-search">Search the menu</label>
            <input id="menu-search" type="search" maxlength="100" placeholder="Search by name or description" autocomplete="off">
        </form>
        <div id="menu-chips" class="chips" role="group" aria-label="Filter by category"></div>
    </section>

    <div id="status" class="alert" role="status" aria-live="polite" hidden></div>

    <p id="menu-count" class="menu-count" aria-live="polite"></p>
    <div id="menu-grid" class="menu-grid" aria-busy="true">
        <p class="empty-state">Loading the menu&hellip;</p>
    </div>
    <noscript><p class="alert alert-error">JavaScript is required to show the menu.</p></noscript>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/menu.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
