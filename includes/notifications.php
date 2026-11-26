<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/push.php';

/**
 * Notification engine (Phase 5.1).
 *
 * WHO is told about WHAT:
 *   - The customer who owns the order hears about every event on THEIR order.
 *   - Every active kitchen staff member and every active admin hears about
 *     every event, EXCEPT the person who just did the action (they already know).
 *   - Nobody else is ever notified.
 *
 * HOW it is stored: one row in `notifications` per person, so each person can
 * read or clear their own copy without affecting anyone else.
 *
 * NO DUPLICATES: each row carries an event_key such as 'order:42:ORDER_READY'.
 * (user_id, event_key) is UNIQUE in the database, so the same event can never
 * notify the same person twice, even if the code runs twice or at the same time.
 *
 * SAFETY: notifications are created AFTER the order action has succeeded and been
 * saved. A problem in here is only logged. It can never undo or block an order,
 * a payment or a kitchen status change (see notify_order_event_safe()).
 */

const NOTIFICATION_EVENTS = [
    'ORDER_PLACED',
    'ORDER_PREPARING',
    'ORDER_READY',
    'ORDER_COLLECTED',
    'PAYMENT_CASH_CONFIRMED',   // ready for the future "confirm cash" feature
    'ORDER_CANCELLED',          // ready for the future "cancel order" feature
];

const NOTIFICATION_LIST_DEFAULT = 20;
const NOTIFICATION_LIST_MAX = 50;

/** Shorten text to fit its database column. */
function notification_clip(string $text, int $max): string
{
    return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . "\u{2026}" : $text;
}

/**
 * Everything the messages need about one order, in a single query.
 *
 * @return array<string,mixed>|null
 */
function notification_order_context(PDO $pdo, int $orderId): ?array
{
    $statement = $pdo->prepare(
        'SELECT o.id, o.user_id, o.total_amount,
                u.full_name AS customer_name, u.is_active AS customer_active,
                t.token_number,
                p.method AS payment_method,
                (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
         FROM orders o
         INNER JOIN users u ON u.id = o.user_id
         LEFT JOIN tokens t ON t.order_id = o.id
         LEFT JOIN payments p ON p.order_id = o.id
         WHERE o.id = ?
         LIMIT 1'
    );
    $statement->execute([$orderId]);
    $row = $statement->fetch();

    return $row ?: null;
}

/**
 * The title and message for one event. $audience is CUSTOMER (the order's owner)
 * or STAFF (kitchen and admin share the same wording).
 *
 * @param array<string,mixed> $ctx From notification_order_context().
 * @return array{title:string,message:string}
 */
function notification_content(string $event, string $audience, array $ctx): array
{
    $token = (string) ($ctx['token_number'] ?? '') !== '' ? (string) $ctx['token_number'] : '#' . $ctx['id'];
    $name = (string) $ctx['customer_name'];
    $total = money_text(number_format((float) $ctx['total_amount'], 2, '.', ''));
    $items = (int) $ctx['item_count'];
    $isCash = ($ctx['payment_method'] ?? '') === 'CASH';
    $methodLabel = payment_method_label((string) ($ctx['payment_method'] ?? ''));

    if ($audience === 'CUSTOMER') {
        $texts = [
            'ORDER_PLACED' => [
                'Order placed',
                'Your order ' . $token . ' has been placed. Total ' . $total . '. '
                    . ($isCash ? 'Please pay in cash at the counter.' : 'Your demo payment was successful.'),
            ],
            'ORDER_PREPARING' => ['Your order is being prepared', 'The kitchen has started preparing order ' . $token . '.'],
            'ORDER_READY' => ['Your order is ready!', 'Order ' . $token . ' is ready. Show your token at the counter to collect it.'],
            'ORDER_COLLECTED' => ['Order collected', 'Order ' . $token . ' has been collected. Enjoy your meal!'],
            'PAYMENT_CASH_CONFIRMED' => ['Cash payment received', 'We received your cash payment of ' . $total . ' for order ' . $token . '.'],
            'ORDER_CANCELLED' => ['Order cancelled', 'Order ' . $token . ' was cancelled.'],
        ];
    } else {
        $texts = [
            'ORDER_PLACED' => [
                'New order ' . $token,
                $name . ' placed order ' . $token . ': ' . $items . ($items === 1 ? ' item, ' : ' items, ') . $total . ', ' . $methodLabel . '.',
            ],
            'ORDER_PREPARING' => ['Order ' . $token . ' is being prepared', 'Order ' . $token . ' for ' . $name . ' is now being prepared.'],
            'ORDER_READY' => ['Order ' . $token . ' is ready', 'Order ' . $token . ' for ' . $name . ' is ready for collection.'],
            'ORDER_COLLECTED' => ['Order ' . $token . ' collected', 'Order ' . $token . ' for ' . $name . ' has been collected.'],
            'PAYMENT_CASH_CONFIRMED' => ['Cash payment received', 'Cash payment of ' . $total . ' confirmed for order ' . $token . ' (' . $name . ').'],
            'ORDER_CANCELLED' => ['Order ' . $token . ' cancelled', 'Order ' . $token . ' for ' . $name . ' was cancelled.'],
        ];
    }

    [$title, $message] = $texts[$event];

    return [
        'title' => notification_clip($title, 120),
        'message' => notification_clip($message, 255),
    ];
}

/**
 * Who should be told about this event.
 *
 * @param array<string,mixed> $ctx
 * @return list<array{user_id:int,audience:string}>
 */
function notification_recipients(PDO $pdo, array $ctx, ?int $actorId): array
{
    $recipients = [];

    // The customer who owns the order (only if their account is still active).
    if ((int) $ctx['customer_active'] === 1) {
        $recipients[] = ['user_id' => (int) $ctx['user_id'], 'audience' => 'CUSTOMER', 'role' => 'CUSTOMER'];
    }

    // All active kitchen staff and admins, except whoever just did the action.
    $staff = $pdo->query(
        "SELECT id, role FROM users WHERE is_active = 1 AND role IN ('KITCHEN_STAFF', 'ADMIN') ORDER BY id"
    )->fetchAll();
    foreach ($staff as $row) {
        $id = (int) $row['id'];
        if ($id !== $actorId && $id !== (int) $ctx['user_id']) {
            $recipients[] = ['user_id' => $id, 'audience' => 'STAFF', 'role' => (string) $row['role']];
        }
    }

    return $recipients;
}

/**
 * Save one notification. Returns its id, or null when this person already has a
 * notification for this exact event (the database UNIQUE key refused the copy).
 */
function notification_store(
    PDO $pdo,
    int $userId,
    int $orderId,
    string $event,
    string $eventKey,
    string $title,
    string $message
): ?int {
    try {
        $pdo->prepare(
            'INSERT INTO notifications (user_id, order_id, event_type, event_key, title, message)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $orderId, $event, $eventKey, $title, $message]);
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            return null;
        }
        throw $exception;
    }

    return (int) $pdo->lastInsertId();
}

/**
 * Create the notifications for one order event.
 * Returns the ones that were newly created (the push step in 5.4 will use this list).
 *
 * @return list<array{id:int,user_id:int}>
 */
function notify_order_event(PDO $pdo, int $orderId, string $event, ?int $actorId = null): array
{
    if (!in_array($event, NOTIFICATION_EVENTS, true)) {
        throw new InvalidArgumentException('Unknown notification event.');
    }

    $ctx = notification_order_context($pdo, $orderId);
    if ($ctx === null) {
        return [];
    }

    $eventKey = 'order:' . $orderId . ':' . $event;
    $created = [];
    $pushMessages = [];

    foreach (notification_recipients($pdo, $ctx, $actorId) as $recipient) {
        $content = notification_content($event, $recipient['audience'], $ctx);

        try {
            $id = notification_store(
                $pdo, $recipient['user_id'], $orderId, $event, $eventKey, $content['title'], $content['message']
            );
        } catch (PDOException $exception) {
            // One person's problem must not stop the others from being told.
            error_log('Notification for user ' . $recipient['user_id'] . ' failed: ' . $exception->getMessage());
            continue;
        }

        if ($id !== null) {
            $created[] = ['id' => $id, 'user_id' => $recipient['user_id']];
            $targetRole = $recipient['role'];
            $pushMessages[] = [
                'user_id' => $recipient['user_id'],
                'order_id' => $orderId,
                'event_type' => $event,
                'title' => $content['title'],
                'message' => $content['message'],
                'url' => notification_target_url($targetRole, $orderId),
            ];
        }
    }

    if ($pushMessages !== []) {
        push_send_batch_safe($pdo, $pushMessages);
    }

    return $created;
}

/**
 * The version the order and kitchen code calls. It cannot throw: a notification
 * problem is written to the error log and the real action carries on untouched.
 *
 * @return list<array{id:int,user_id:int}>
 */
function notify_order_event_safe(PDO $pdo, int $orderId, string $event, ?int $actorId = null): array
{
    try {
        return notify_order_event($pdo, $orderId, $event, $actorId);
    } catch (Throwable $exception) {
        error_log('Notification ' . $event . ' for order ' . $orderId . ' failed: ' . $exception->getMessage());

        return [];
    }
}

/* ------------------------------------------------------------ Reading and managing */

/** The page a notification should open for this role. */
function notification_target_url(string $role, ?int $orderId): string
{
    return match ($role) {
        'CUSTOMER' => $orderId !== null
            ? url('customer/order.php?id=' . $orderId)
            : url('customer/orders.php'),
        'KITCHEN_STAFF' => url('kitchen/dashboard.php'),
        'ADMIN' => url('admin/dashboard.php'),
        default => url('index.php'),
    };
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function notification_to_array(array $row, string $role): array
{
    $orderId = $row['order_id'] === null ? null : (int) $row['order_id'];

    return [
        'id' => (int) $row['id'],
        'order_id' => $orderId,
        'event_type' => (string) $row['event_type'],
        'title' => (string) $row['title'],
        'message' => (string) $row['message'],
        'is_read' => (int) $row['is_read'] === 1,
        'created_at' => format_order_time((string) $row['created_at']),
        'url' => notification_target_url($role, $orderId),
    ];
}

/**
 * One page of THIS user's notifications, newest first.
 * $beforeId = older than that id (for "load more"); $sinceId = newer than that id
 * (for polling). The user id always comes from the session, never from the browser.
 *
 * @return array{notifications:list<array<string,mixed>>,has_more:bool}
 */
function notification_list(
    PDO $pdo,
    int $userId,
    string $role,
    int $limit,
    ?int $beforeId,
    ?int $sinceId,
    bool $unreadOnly
): array {
    $limit = max(1, min($limit, NOTIFICATION_LIST_MAX));
    $sql = 'SELECT id, order_id, event_type, title, message, is_read, created_at
            FROM notifications
            WHERE user_id = ?';
    $params = [$userId];

    if ($beforeId !== null) {
        $sql .= ' AND id < ?';
        $params[] = $beforeId;
    }
    if ($sinceId !== null) {
        $sql .= ' AND id > ?';
        $params[] = $sinceId;
    }
    if ($unreadOnly) {
        $sql .= ' AND is_read = 0';
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . ($limit + 1);

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll();

    $hasMore = count($rows) > $limit;
    $rows = array_slice($rows, 0, $limit);

    return [
        'notifications' => array_map(static fn (array $r): array => notification_to_array($r, $role), $rows),
        'has_more' => $hasMore,
    ];
}

function notification_unread_count(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $statement->execute([$userId]);

    return (int) $statement->fetchColumn();
}

/** The newest notification id this user has (0 if none). Lets the browser notice new ones. */
function notification_latest_id(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM notifications WHERE user_id = ?');
    $statement->execute([$userId]);

    return (int) $statement->fetchColumn();
}

/** Does this notification exist AND belong to this user? */
function notification_owned(PDO $pdo, int $userId, int $notificationId): bool
{
    $statement = $pdo->prepare('SELECT 1 FROM notifications WHERE id = ? AND user_id = ? LIMIT 1');
    $statement->execute([$notificationId, $userId]);

    return $statement->fetchColumn() !== false;
}

/** Mark one of this user's notifications as read. False if it is not theirs / not found. */
function notification_mark_read(PDO $pdo, int $userId, int $notificationId): bool
{
    if (!notification_owned($pdo, $userId, $notificationId)) {
        return false;
    }
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
        ->execute([$notificationId, $userId]);

    return true;
}

function notification_mark_all_read(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $statement->execute([$userId]);

    return $statement->rowCount();
}

/** Delete one of this user's notifications. False if it is not theirs / not found. */
function notification_clear(PDO $pdo, int $userId, int $notificationId): bool
{
    $statement = $pdo->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
    $statement->execute([$notificationId, $userId]);

    return $statement->rowCount() === 1;
}

function notification_clear_all(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare('DELETE FROM notifications WHERE user_id = ?');
    $statement->execute([$userId]);

    return $statement->rowCount();
}
