<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/order_view.php';
require_role('CUSTOMER');

$user = current_user();
$orderId = api_int(is_string($_GET['id'] ?? null) ? $_GET['id'] : '');
$order = null;

if ($orderId !== null) {
    try {
        // Ownership is part of the query: another customer's id finds nothing.
        $order = order_for_user(db(), $orderId, (int) ($user['id'] ?? 0));
    } catch (PDOException $exception) {
        error_log('Order confirmation failed: ' . $exception->getMessage());
    }
}

if ($order === null) {
    http_response_code(404);
    $pageTitle = 'Order not found';
    require __DIR__ . '/../includes/header.php';
    ?>
    <section class="panel">
        <h1>Order not found</h1>
        <p>We could not find that order in your account.</p>
        <div class="actions">
            <a class="button" href="<?= e(url('customer/orders.php')) ?>">My orders</a>
        </div>
    </section>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = 'Order confirmed';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel success-banner">
    <p class="eyebrow">Order confirmed</p>
    <h1>Order placed successfully</h1>
    <p>Thank you! Your order is in the queue. Keep your token ready.</p>
</section>

<?php render_order_detail($order); ?>

<div class="actions section-gap">
    <a class="button" href="<?= e(url('customer/orders.php')) ?>">My orders</a>
    <a class="button button-secondary" href="<?= e(url('customer/menu.php')) ?>">Back to the menu</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
