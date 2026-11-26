<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/api.php';
require_once __DIR__ . '/../../includes/notifications.php';

// Any signed-in role may use this endpoint, but only ever for THEIR OWN notifications.
// The user id comes from the session. No user id is read from the request.
$user = api_require_user();
$method = api_require_method('GET', 'POST');
$userId = (int) $user['id'];
$role = (string) $user['role'];

/** An optional positive-number query parameter. Present but invalid gives a 422. */
function notification_id_param(string $name): ?int
{
    if (!isset($_GET[$name])) {
        return null;
    }

    $value = is_string($_GET[$name]) ? api_int($_GET[$name]) : null;
    if ($value === null) {
        json_response(false, 'The ' . $name . ' value is not valid.', null, 422);
    }

    return $value;
}

try {
    $pdo = db();

    if ($method === 'GET') {
        // Cheap check for the bell badge: just the numbers.
        if (($_GET['count_only'] ?? '') === '1') {
            json_response(true, 'OK', [
                'unread_count' => notification_unread_count($pdo, $userId),
                'latest_id' => notification_latest_id($pdo, $userId),
            ]);
        }

        $beforeId = notification_id_param('before_id');
        $sinceId = notification_id_param('since_id');
        if ($beforeId !== null && $sinceId !== null) {
            json_response(false, 'Use either before_id or since_id, not both.', null, 422);
        }

        $limit = api_int(is_string($_GET['limit'] ?? null) ? $_GET['limit'] : '') ?? NOTIFICATION_LIST_DEFAULT;
        $limit = min($limit, NOTIFICATION_LIST_MAX);
        $unreadOnly = ($_GET['unread_only'] ?? '') === '1';

        $result = notification_list($pdo, $userId, $role, $limit, $beforeId, $sinceId, $unreadOnly);

        json_response(true, 'Notifications loaded.', [
            'notifications' => $result['notifications'],
            'has_more' => $result['has_more'],
            'unread_count' => notification_unread_count($pdo, $userId),
            'latest_id' => notification_latest_id($pdo, $userId),
        ]);
    }

    api_require_csrf();
    $action = post_value('action');

    if ($action === 'mark_read' || $action === 'clear') {
        $id = api_int(post_value('id'));
        if ($id === null) {
            json_response(false, 'That notification could not be found.', null, 422);
        }

        $done = $action === 'mark_read'
            ? notification_mark_read($pdo, $userId, $id)
            : notification_clear($pdo, $userId, $id);

        // Someone else's notification looks exactly like one that does not exist.
        if (!$done) {
            json_response(false, 'Notification not found.', null, 404);
        }

        json_response(true, $action === 'mark_read' ? 'Marked as read.' : 'Notification cleared.', [
            'unread_count' => notification_unread_count($pdo, $userId),
        ]);
    }

    if ($action === 'mark_all_read') {
        $changed = notification_mark_all_read($pdo, $userId);
        json_response(true, 'All notifications marked as read.', ['changed' => $changed, 'unread_count' => 0]);
    }

    if ($action === 'clear_all') {
        $removed = notification_clear_all($pdo, $userId);
        json_response(true, 'Notification history cleared.', ['removed' => $removed, 'unread_count' => 0]);
    }

    json_response(false, 'Unknown action.', null, 400);
} catch (Throwable $exception) {
    api_server_error($exception);
}
