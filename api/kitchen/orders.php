<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/api.php';
require_once __DIR__ . '/../../includes/kitchen.php';

// Kitchen staff only. Customers and admins get 403, visitors get 401.
$user = api_require_role('KITCHEN_STAFF');
$method = api_require_method('GET', 'POST');

try {
    $pdo = db();

    if ($method === 'GET') {
        // ?status= is optional. Without it (or with ACTIVE) the active queue is returned.
        $rawStatus = is_string($_GET['status'] ?? null) ? trim($_GET['status']) : '';
        $status = null;
        if ($rawStatus !== '' && $rawStatus !== 'ACTIVE') {
            if (!in_array($rawStatus, KITCHEN_FILTER_STATUSES, true)) {
                json_response(false, 'Choose a valid status filter.', null, 422);
            }
            $status = $rawStatus;
        }

        $limit = api_int(is_string($_GET['limit'] ?? null) ? $_GET['limit'] : '') ?? KITCHEN_DEFAULT_LIMIT;
        $limit = min($limit, KITCHEN_MAX_LIMIT);
        $page = api_int(is_string($_GET['page'] ?? null) ? $_GET['page'] : '') ?? 1;

        $result = kitchen_orders($pdo, $status, $page, $limit);

        json_response(true, 'Orders loaded.', [
            'orders' => $result['orders'],
            'has_more' => $result['has_more'],
            'page' => min($page, 1000),
            'limit' => $limit,
            'filter' => $status ?? 'ACTIVE',
            'counts' => kitchen_status_counts($pdo),
        ]);
    }

    api_require_csrf();

    $action = post_value('action');
    if ($action !== 'update_status' && $action !== 'extend_prep') {
        json_response(false, 'Unknown action.', null, 400);
    }

    // Only an order id and the wanted status/adjustment are read. Nothing about payments,
    // items, totals or customers is accepted from the browser.
    $orderId = api_int(post_value('order_id'));
    if ($orderId === null) {
        json_response(false, 'That order could not be found.', null, 422);
    }

    if ($action === 'extend_prep') {
        $addMinutes = api_int(post_value('minutes')) ?? 5;
        $updated = kitchen_extend_prep($pdo, $orderId, $addMinutes);
        json_response(true, 'Estimated ready time updated (+' . $addMinutes . ' min).', ['order' => $updated]);
    }

    $result = kitchen_update_status($pdo, $orderId, post_value('status'), (int) $user['id']);

    json_response(true, 'Order moved to ' . $result['status_label'] . '.', ['order' => $result]);
} catch (KitchenException $exception) {
    json_response(false, $exception->getMessage(), null, $exception->status());
} catch (Throwable $exception) {
    api_server_error($exception);
}
