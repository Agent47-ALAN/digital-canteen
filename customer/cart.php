<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CUSTOMER');

$pageTitle = 'Your cart';
require __DIR__ . '/../includes/header.php';
?>
<div id="app"
     data-csrf="<?= e(csrf_token()) ?>"
     data-api="<?= e(url('api/customer/cart.php')) ?>"
     data-login="<?= e(url('login.php')) ?>">
    <section class="panel">
        <p class="eyebrow">Customer</p>
        <h1>Your cart</h1>
        <p>Review your items, change quantities, then go to checkout. Prices are always taken from the menu.</p>
    </section>

    <div id="status" class="alert" role="status" aria-live="polite" hidden></div>

    <section id="cart-filled" class="panel section-gap" hidden>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Item</th><th>Unit price</th><th>Quantity</th><th>Subtotal</th><th>Remove</th></tr>
                </thead>
                <tbody id="cart-rows"></tbody>
                <tfoot>
                    <tr class="total-row">
                        <th colspan="3" scope="row">Total</th>
                        <td id="cart-total" data-label="Total"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="actions">
            <a class="button button-secondary" href="<?= e(url('customer/menu.php')) ?>">Continue shopping</a>
            <a id="checkout-link" class="button" href="<?= e(url('customer/checkout.php')) ?>">Proceed to checkout</a>
            <button id="checkout-blocked" class="button" type="button" disabled hidden>Proceed to checkout</button>
        </div>
    </section>

    <section id="cart-empty" class="panel section-gap" hidden>
        <h2>Your cart is empty</h2>
        <p>Add something tasty from the menu to get started.</p>
        <div class="actions">
            <a class="button" href="<?= e(url('customer/menu.php')) ?>">Browse the menu</a>
        </div>
    </section>

    <p id="cart-loading" class="empty-state">Loading your cart&hellip;</p>
    <noscript><p class="alert alert-error">JavaScript is required to use the cart.</p></noscript>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/cart.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
