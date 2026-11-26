<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CUSTOMER');

$pageTitle = 'Menu';
require __DIR__ . '/../includes/header.php';
?>
<div id="app"
     data-api="<?= e(url('api/customer/menu.php')) ?>"
     data-cart-api="<?= e(url('api/customer/cart.php')) ?>"
     data-image-base="<?= e(url('assets/images/menu/')) ?>"
     data-csrf="<?= e(csrf_token()) ?>"
     data-login="<?= e(url('login.php')) ?>">
    <section class="panel">
        <p class="eyebrow">Customer</p>
        <h1>Today&rsquo;s menu</h1>
        <p>Browse what the canteen serves, add items to your cart, then check out. <a href="<?= e(url('customer/cart.php')) ?>">View your cart</a></p>
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
    <div id="dish-modal" class="dish-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="dish-modal-title" hidden>
        <div class="dish-modal">
            <div class="dish-modal-head">
                <h2 id="dish-modal-title" class="dish-modal-title"></h2>
                <button id="dish-modal-close" class="button button-secondary button-small" type="button" aria-label="Close dish details">Close</button>
            </div>
            <p id="dish-modal-price" class="price dish-modal-price"></p>
            <p id="dish-modal-desc" class="dish-modal-desc"></p>
        </div>
    </div>
    <noscript><p class="alert alert-error">JavaScript is required to show the menu.</p></noscript>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/menu.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
