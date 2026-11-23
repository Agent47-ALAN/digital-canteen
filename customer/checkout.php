<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/orders.php';
require_role('CUSTOMER');

$user = current_user();
$userId = (int) ($user['id'] ?? 0);

try {
    $cart = cart_lines(db(), cart_get($userId));
} catch (PDOException $exception) {
    error_log('Checkout page failed: ' . $exception->getMessage());
    $cart = ['lines' => [], 'total_cents' => 0, 'total' => '0.00', 'count' => 0, 'has_issues' => false];
}

// One checkout token per checkout attempt. It is used up only when an order is saved,
// so a refresh or a second click can never create a second order.
$sessionToken = $_SESSION['checkout_token'] ?? null;
if (!is_string($sessionToken) || preg_match('/^[0-9a-f]{32}$/', $sessionToken) !== 1) {
    $sessionToken = bin2hex(random_bytes(16));
    $_SESSION['checkout_token'] = $sessionToken;
}

$canCheckout = $cart['lines'] !== [] && !$cart['has_issues'];

$pageTitle = 'Checkout';
require __DIR__ . '/../includes/header.php';
?>
<div id="app"
     data-csrf="<?= e(csrf_token()) ?>"
     data-api="<?= e(url('api/customer/checkout.php')) ?>"
     data-token="<?= e($sessionToken) ?>"
     data-login="<?= e(url('login.php')) ?>">
    <section class="panel">
        <p class="eyebrow">Customer</p>
        <h1>Checkout</h1>
        <p>Check your order, choose how you will pay, and confirm.</p>
    </section>

<?php if ($cart['lines'] === []): ?>
    <section class="panel section-gap">
        <h2>Your cart is empty</h2>
        <p>There is nothing to check out yet.</p>
        <div class="actions">
            <a class="button" href="<?= e(url('customer/menu.php')) ?>">Browse the menu</a>
        </div>
    </section>
<?php elseif ($cart['has_issues']): ?>
    <section class="panel section-gap">
        <h2>Some items need your attention</h2>
        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($cart['lines'] as $line): ?>
                    <?php if ($line['problem'] !== null): ?>
                        <li><strong><?= e((string) $line['name']) ?></strong>: <?= e((string) $line['problem']) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <p>Remove these items from your cart to continue.</p>
        <div class="actions">
            <a class="button" href="<?= e(url('customer/cart.php')) ?>">Go to cart</a>
        </div>
    </section>
<?php else: ?>
    <section class="panel section-gap">
        <h2>Order summary</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Item</th><th>Unit price</th><th>Qty</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($cart['lines'] as $line): ?>
                        <tr>
                            <td data-label="Item"><?= e((string) $line['name']) ?></td>
                            <td data-label="Unit price"><?= e(money_text((string) $line['unit_price'])) ?></td>
                            <td data-label="Qty"><?= e((string) $line['quantity']) ?></td>
                            <td data-label="Subtotal"><?= e(money_text((string) $line['subtotal'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <th colspan="3" scope="row">Total</th>
                        <td data-label="Total"><?= e(money_text($cart['total'])) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="hint">The final amount is calculated again on the server from the current menu prices when you confirm.</p>
    </section>

    <div id="status" class="alert" role="status" aria-live="polite" hidden></div>

    <section class="panel section-gap">
        <form id="checkout-form" novalidate>
            <fieldset class="method-list">
                <legend>Payment method</legend>
                <label class="method">
                    <input type="radio" name="payment_method" value="CASH" checked>
                    <span><strong>Cash Payment</strong><small>Place the order now and pay in cash at the canteen counter. Payment stays pending until you pay.</small></span>
                </label>
                <label class="method">
                    <input type="radio" name="payment_method" value="DEMO_ONLINE">
                    <span><strong>Demo Online Payment</strong><small>A simulated online payment for this college demonstration.</small></span>
                </label>
            </fieldset>

            <div id="demo-panel" class="demo-panel" hidden>
                <p class="demo-banner">DEMO PAYMENT &mdash; NO REAL MONEY WILL BE TRANSFERRED</p>
                <p class="demo-amount">Amount payable: <strong><?= e(money_text($cart['total'])) ?></strong></p>
                <fieldset class="method-list">
                    <legend>Demo outcome (for the presentation)</legend>
                    <label class="method">
                        <input type="radio" name="demo_outcome" value="success" checked>
                        <span><strong>Simulate a successful payment</strong></span>
                    </label>
                    <label class="method">
                        <input type="radio" name="demo_outcome" value="failure">
                        <span><strong>Simulate a failed payment</strong></span>
                    </label>
                </fieldset>
                <p class="hint">No card number, UPI PIN or bank detail is asked for. This is not a real payment gateway.</p>
            </div>

            <div id="payment-failed" class="alert alert-error" role="alert" hidden>
                <p id="payment-failed-text"></p>
                <button id="use-cash" class="button button-secondary button-small" type="button">Pay with Cash instead</button>
            </div>

            <div class="actions">
                <a class="button button-secondary" href="<?= e(url('customer/cart.php')) ?>">Back to cart</a>
                <button id="place-button" class="button" type="submit">Confirm order</button>
            </div>
        </form>
    </section>
<?php endif; ?>
</div>
<?php if ($canCheckout): ?>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/checkout.js')) ?>"></script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
