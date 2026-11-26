<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/api.php';
require_once __DIR__ . '/../../includes/push.php';

// Any signed-in user may manage ONLY their own device subscriptions.
$user = api_require_user();
$method = api_require_method('GET', 'POST');
$userId = (int) $user['id'];

try {
    $pdo = db();

    if ($method === 'GET') {
        $rawEndpoint = is_string($_GET['endpoint'] ?? null) ? trim($_GET['endpoint']) : '';
        $endpoint = $rawEndpoint !== '' ? push_validate_endpoint($rawEndpoint) : null;
        $status = push_subscription_status($pdo, $userId, $endpoint);

        json_response(true, 'Push status loaded.', [
            'subscribed' => $status['subscribed'],
            'count' => $status['count'],
            'public_key' => vapid_public_key(),
        ]);
    }

    api_require_csrf();
    $action = post_value('action');

    if ($action === 'subscribe') {
        $endpoint = push_validate_endpoint(post_value('endpoint'));
        $publicKey = push_validate_key(post_value('public_key'));
        $authToken = push_validate_key(post_value('auth_token'));

        if ($endpoint === null || $publicKey === null || $authToken === null) {
            json_response(false, 'The browser push subscription data is not valid.', null, 422);
        }

        push_subscribe($pdo, $userId, $endpoint, $publicKey, $authToken);
        $status = push_subscription_status($pdo, $userId, $endpoint);

        json_response(true, 'Push notifications enabled on this device.', [
            'subscribed' => true,
            'count' => $status['count'],
        ]);
    }

    if ($action === 'unsubscribe') {
        $endpoint = push_validate_endpoint(post_value('endpoint'));
        if ($endpoint === null) {
            json_response(false, 'The browser push endpoint is not valid.', null, 422);
        }

        push_unsubscribe($pdo, $userId, $endpoint);
        $status = push_subscription_status($pdo, $userId, null);

        json_response(true, 'Push notifications disabled on this device.', [
            'subscribed' => false,
            'count' => $status['count'],
        ]);
    }

    json_response(false, 'Unknown action.', null, 400);
} catch (Throwable $exception) {
    api_server_error($exception);
}
