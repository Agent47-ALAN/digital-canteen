<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CUSTOMER');

$pageTitle = 'My orders';
require __DIR__ . '/../includes/header.php';
?>
<div id="app"
     data-api="<?= e(url('api/orders/history.php')) ?>"
     data-detail="<?= e(url('customer/order.php')) ?>"
     data-login="<?= e(url('login.php')) ?>">
    <section class="panel">
        <p class="eyebrow">Customer</p>
        <h1>My orders</h1>
        <p>Your most recent orders come first. Open an order to see everything in it.</p>
    </section>

    <div id="status" class="alert" role="status" aria-live="polite" hidden></div>

    <div id="order-list" class="order-list section-gap" aria-busy="true">
        <p class="empty-state">Loading your orders&hellip;</p>
    </div>
    <div class="actions">
        <button id="load-more" class="button button-secondary" type="button" hidden>Load more orders</button>
    </div>
    <noscript><p class="alert alert-error">JavaScript is required to show your orders.</p></noscript>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/orders.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
