<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

/**
 * Print one order (token, details, items, total, payment note).
 * Shared by the confirmation page and the order details page.
 * Every value is escaped with e().
 *
 * @param array<string,mixed> $order From order_for_user().
 */
function render_order_detail(array $order): void
{
    $method = (string) $order['payment_method'];
    $paymentStatus = (string) $order['payment_status'];
    ?>
    <section class="token-card" aria-label="Order token">
        <p class="token-label">Your token</p>
        <p class="token-number"><?= e((string) $order['token_number']) ?></p>
        <p class="token-hint">Show this token at the canteen counter.</p>
    </section>

    <section class="panel section-gap">
        <h2>Order details</h2>
        <div class="details">
            <div><span>Order placed</span><strong><?= e((string) $order['placed_at']) ?></strong></div>
            <div><span>Order status</span><strong><?= e((string) $order['status_label']) ?></strong></div>
            <div><span>Payment method</span><strong><?= e((string) $order['payment_method_label']) ?></strong></div>
            <div><span>Payment status</span><strong><?= e((string) $order['payment_status_label']) ?></strong></div>
        </div>

        <?php if ($method === 'CASH' && $paymentStatus === 'PENDING'): ?>
            <p class="alert alert-notice">Payment is pending. Please pay <?= e(money_text((string) $order['total_amount'])) ?> in cash at the canteen counter.</p>
        <?php elseif ($method === 'DEMO_ONLINE' && $paymentStatus === 'PAID'): ?>
            <p class="alert alert-success">Demo payment successful. This was a simulation, so no real money was transferred.</p>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Item</th><th>Unit price</th><th>Qty</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td data-label="Item"><?= e((string) $item['name']) ?></td>
                            <td data-label="Unit price"><?= e(money_text((string) $item['unit_price'])) ?></td>
                            <td data-label="Qty"><?= e((string) $item['quantity']) ?></td>
                            <td data-label="Subtotal"><?= e(money_text((string) $item['line_total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <th colspan="3" scope="row">Total</th>
                        <td data-label="Total"><?= e(money_text((string) $order['total_amount'])) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
    <?php
}
