<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/api.php';
require_once __DIR__ . '/../../includes/orders.php';

$user = api_require_role('CUSTOMER');
api_require_method('POST');
api_require_csrf();
$userId = (int) $user['id'];

if (post_value('action') !== 'place') {
    json_response(false, 'Unknown action.', null, 400);
}

// The customer id comes from the session only. Nothing about price, total,
// payment status or order status is read from the request.
$method = post_value('payment_method');
if (!in_array($method, PAYMENT_METHODS, true)) {
    json_response(false, 'Choose a valid payment method.', null, 422);
}

$checkoutToken = post_value('checkout_token');
if (preg_match('/^[0-9a-f]{32}$/', $checkoutToken) !== 1) {
    json_response(false, 'This checkout page has expired. Refresh the page and try again.', null, 422);
}

// DEMO ONLY: the presenter picks which simulated outcome to show.
// This is a scenario for the simulator, never a payment status.
$demoScenario = '';
if ($method === 'DEMO_ONLINE') {
    $demoScenario = post_value('demo_outcome');
    if ($demoScenario !== 'success' && $demoScenario !== 'failure') {
        json_response(false, 'Choose a demo payment outcome.', null, 422);
    }
}

try {
    $pdo = db();

    // A repeat of an order that was already placed (double click, refresh, second
    // tab) returns that same order. It never creates another one.
    $already = find_order_id_by_checkout_token($pdo, $userId, $checkoutToken);
    if ($already !== null) {
        json_response(true, 'This order was already placed.', [
            'order_id' => $already,
            'redirect' => url('customer/order-success.php?id=' . $already),
        ]);
    }

    $sessionToken = $_SESSION['checkout_token'] ?? '';
    if (!is_string($sessionToken) || !hash_equals($sessionToken, $checkoutToken)) {
        json_response(false, 'This checkout page has expired. Refresh the page and try again.', null, 422);
    }

    $result = place_order($pdo, $userId, cart_get($userId), $method, $checkoutToken, $demoScenario);

    if (!$result['duplicate']) {
        // Only now, after the commit, is the cart emptied and the token used up.
        cart_clear();
        unset($_SESSION['checkout_token']);
    }

    $message = $method === 'DEMO_ONLINE'
        ? 'Demo payment successful. Your order has been placed.'
        : 'Order placed successfully.';
    if ($result['duplicate']) {
        $message = 'This order was already placed.';
    }

    json_response(true, $message, [
        'order_id' => $result['order_id'],
        'redirect' => url('customer/order-success.php?id=' . $result['order_id']),
    ]);
} catch (CheckoutException $exception) {
    json_response(false, $exception->getMessage(), null, $exception->status());
} catch (PaymentDeclinedException $exception) {
    json_response(
        false,
        'The demo payment failed. No order was created and nothing was charged. You can try again or choose Cash Payment.',
        null,
        402
    );
} catch (Throwable $exception) {
    api_server_error($exception);
}
