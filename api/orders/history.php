<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/api.php';
require_once __DIR__ . '/../../includes/orders.php';

// The customer is always the logged-in user. No customer id is read from the request.
$user = api_require_role('CUSTOMER');
api_require_method('GET');
$userId = (int) $user['id'];

try {
    $pdo = db();

    if (isset($_GET['id'])) {
        $orderId = api_int(is_string($_GET['id']) ? $_GET['id'] : '');
        $order = $orderId === null ? null : order_for_user($pdo, $orderId, $userId);

        if ($order === null) {
            json_response(false, 'Order not found.', null, 404);
        }

        json_response(true, 'Order loaded.', ['order' => $order]);
    }

    $page = api_int(is_string($_GET['page'] ?? null) ? $_GET['page'] : '') ?? 1;
    $result = orders_for_user($pdo, $userId, $page);

    json_response(true, 'Orders loaded.', [
        'orders' => $result['orders'],
        'has_more' => $result['has_more'],
        'page' => $page,
    ]);
} catch (PDOException $exception) {
    api_server_error($exception);
}
