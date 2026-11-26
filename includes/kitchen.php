<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/orders.php';

/**
 * Kitchen order management (Phase 4).
 *
 * Reads the kitchen queue and moves orders through
 * PLACED -> PREPARING -> READY -> COLLECTED.
 *
 * The kitchen can ONLY change orders.status. It never touches payments,
 * order items, totals or tokens. Status labels, payment labels and time
 * formatting are reused from includes/orders.php.
 */

/** The only allowed moves. Anything else is rejected. */
const KITCHEN_FLOW = [
    'PLACED' => 'PREPARING',
    'PREPARING' => 'READY',
    'READY' => 'COLLECTED',
];

/** Orders the kitchen is still working on (the default queue). */
const KITCHEN_ACTIVE_STATUSES = ['PLACED', 'PREPARING', 'READY'];

/** Values accepted by the ?status= filter. */
const KITCHEN_FILTER_STATUSES = ['PLACED', 'PREPARING', 'READY', 'COLLECTED', 'CANCELLED'];

const KITCHEN_DEFAULT_LIMIT = 50;
const KITCHEN_MAX_LIMIT = 100;

/** The notification event for each status the kitchen can set. */
const KITCHEN_STATUS_EVENTS = [
    'PREPARING' => 'ORDER_PREPARING',
    'READY' => 'ORDER_READY',
    'COLLECTED' => 'ORDER_COLLECTED',
];

/** A kitchen action that was refused for a reason staff can understand. */
final class KitchenException extends RuntimeException
{
    public function __construct(string $message, private int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->httpStatus;
    }
}

function kitchen_next_status(string $current): ?string
{
    return KITCHEN_FLOW[$current] ?? null;
}

/** Text for the action button that moves an order INTO $nextStatus. */
function kitchen_action_label(string $nextStatus): string
{
    return match ($nextStatus) {
        'PREPARING' => 'Start preparing',
        'READY' => 'Mark ready',
        'COLLECTED' => 'Mark collected',
        default => '',
    };
}

/**
 * Order counts per status, for the column headers.
 *
 * @return array<string,int>
 */
function kitchen_status_counts(PDO $pdo): array
{
    $counts = array_fill_keys(KITCHEN_FILTER_STATUSES, 0);

    foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status')->fetchAll() as $row) {
        $counts[(string) $row['status']] = (int) $row['total'];
    }

    return $counts;
}

/**
 * One page of kitchen orders, newest first, with customer, token, payment and items.
 *
 * Two queries only, however many orders there are:
 *   1. the orders joined to users, tokens and payments;
 *   2. every item of those orders in one IN (...) query.
 *
 * $status null means the active queue (PLACED, PREPARING, READY).
 * The customer's email and id are never selected: the kitchen only needs the name.
 *
 * @return array{orders:list<array<string,mixed>>,has_more:bool}
 */
function kitchen_orders(PDO $pdo, ?string $status, int $page, int $limit): array
{
    $limit = max(1, min($limit, KITCHEN_MAX_LIMIT));
    $page = max(1, min($page, 1000));
    $offset = ($page - 1) * $limit;

    $where = "o.status IN ('PLACED', 'PREPARING', 'READY')";
    $params = [];
    if ($status !== null) {
        if (!in_array($status, KITCHEN_FILTER_STATUSES, true)) {
            throw new InvalidArgumentException('Unknown status filter.');
        }
        $where = 'o.status = ?';
        $params[] = $status;
    }

    $statement = $pdo->prepare(
        'SELECT o.id, o.status, o.total_amount, o.placed_at, o.updated_at,
                o.estimated_prep_minutes, o.estimated_ready_at,
                UNIX_TIMESTAMP(o.estimated_ready_at) AS estimated_ready_epoch,
                UNIX_TIMESTAMP(NOW()) AS server_now_epoch,
                TIMESTAMPDIFF(MINUTE, o.placed_at, NOW()) AS age_minutes,
                u.full_name AS customer_name,
                t.token_number,
                p.method AS payment_method, p.status AS payment_status
         FROM orders o
         INNER JOIN users u ON u.id = o.user_id
         LEFT JOIN tokens t ON t.order_id = o.id
         LEFT JOIN payments p ON p.order_id = o.id
         WHERE ' . $where . '
         ORDER BY o.placed_at DESC, o.id DESC
         LIMIT ' . ($limit + 1) . ' OFFSET ' . $offset
    );
    $statement->execute($params);
    $rows = $statement->fetchAll();

    $hasMore = count($rows) > $limit;
    $rows = array_slice($rows, 0, $limit);

    $itemsByOrder = [];
    if ($rows !== []) {
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $items = $pdo->prepare(
            'SELECT order_id, item_name, unit_price, quantity, line_total
             FROM order_items
             WHERE order_id IN (' . $marks . ')
             ORDER BY order_id, id'
        );
        $items->execute($ids);

        foreach ($items->fetchAll() as $item) {
            $itemsByOrder[(int) $item['order_id']][] = [
                'name' => (string) $item['item_name'],
                'unit_price' => number_format((float) $item['unit_price'], 2, '.', ''),
                'quantity' => (int) $item['quantity'],
                'line_total' => number_format((float) $item['line_total'], 2, '.', ''),
            ];
        }
    }

    $orders = [];
    foreach ($rows as $row) {
        $orderId = (int) $row['id'];
        $items = $itemsByOrder[$orderId] ?? [];

        $order = order_summary_from_row($row);
        $order['item_count'] = array_sum(array_column($items, 'quantity'));
        $order['customer_name'] = (string) $row['customer_name'];
        $order['updated_at'] = format_order_time((string) $row['updated_at']);
        $order['age_minutes'] = max(0, (int) $row['age_minutes']);
        $order['items'] = $items;

        $next = kitchen_next_status((string) $row['status']);
        $order['next_status'] = $next;
        $order['next_label'] = $next === null ? null : kitchen_action_label($next);

        $orders[] = $order;
    }

    return ['orders' => $orders, 'has_more' => $hasMore];
}

/** The reason a move was refused, written for kitchen staff. */
function kitchen_transition_message(string $current, string $requested): string
{
    if ($current === 'CANCELLED') {
        return 'Cancelled orders cannot be prepared or collected.';
    }
    if ($current === 'COLLECTED') {
        return 'This order has already been collected and cannot be changed.';
    }
    if ($current === $requested) {
        return 'This order is already ' . order_status_label($current) . '. Refresh to see the latest status.';
    }

    $next = kitchen_next_status($current);

    return 'An order that is ' . order_status_label($current) . ' cannot be moved to '
        . order_status_label($requested) . '. The next step is ' . ($next === null ? 'none' : order_status_label($next)) . '.';
}

/**
 * Move ONE order one step forward.
 *
 * The order row is locked (SELECT ... FOR UPDATE) so two staff clicking at the
 * same moment are handled one after the other. The second one then sees the new
 * status, finds the move is no longer allowed, and is refused. The UPDATE also
 * repeats the status it expects, as a second safeguard.
 * orders.updated_at changes by itself (ON UPDATE CURRENT_TIMESTAMP).
 * $actorId is the staff member who clicked; they are not notified about their own action.
 *
 * @return array{order_id:int,previous_status:string,status:string,status_label:string,next_status:?string,next_label:?string}
 * @throws KitchenException|PDOException
 */
function kitchen_update_status(PDO $pdo, int $orderId, string $requested, ?int $actorId = null): array
{
    // PLACED and CANCELLED can never be set from the kitchen.
    if (!in_array($requested, ['PREPARING', 'READY', 'COLLECTED'], true)) {
        throw new KitchenException('That status cannot be set from the kitchen.', 422);
    }

    $pdo->beginTransaction();

    try {
        $lock = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
        $lock->execute([$orderId]);
        $current = $lock->fetchColumn();

        if ($current === false) {
            throw new KitchenException('Order not found.', 404);
        }
        $current = (string) $current;

        if (kitchen_next_status($current) !== $requested) {
            throw new KitchenException(kitchen_transition_message($current, $requested), 409);
        }

        $update = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND status = ?');
        $update->execute([$requested, $orderId, $current]);
        if ($update->rowCount() !== 1) {
            throw new KitchenException('The order changed at the same time. Please refresh and try again.', 409);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    // The change is saved. Tell the people involved; a problem here is only logged.
    notify_order_event_safe($pdo, $orderId, KITCHEN_STATUS_EVENTS[$requested], $actorId);

    $next = kitchen_next_status($requested);

    return [
        'order_id' => $orderId,
        'previous_status' => $current,
        'status' => $requested,
        'status_label' => order_status_label($requested),
        'next_status' => $next,
        'next_label' => $next === null ? null : kitchen_action_label($next),
    ];
}

/**
 * Extend the estimated ready time for an active order (PLACED or PREPARING).
 *
 * @return array{order_id:int,estimated_prep_minutes:int,estimated_ready_at:?string}
 * @throws KitchenException|PDOException
 */
function kitchen_extend_prep(PDO $pdo, int $orderId, int $addMinutes = 5): array
{
    if ($addMinutes < 1 || $addMinutes > 30) {
        throw new KitchenException('Choose a valid time adjustment.', 422);
    }

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
        $lock->execute([$orderId]);
        $current = $lock->fetchColumn();

        if ($current === false) {
            throw new KitchenException('Order not found.', 404);
        }
        if (!in_array((string) $current, ['PLACED', 'PREPARING'], true)) {
            throw new KitchenException('Preparation time can only be adjusted while an order is Placed or Preparing.', 409);
        }

        $update = $pdo->prepare(
            'UPDATE orders
             SET estimated_prep_minutes = COALESCE(estimated_prep_minutes, ?) + ?,
                 estimated_ready_at = DATE_ADD(GREATEST(COALESCE(estimated_ready_at, NOW()), NOW()), INTERVAL ? MINUTE)
             WHERE id = ?'
        );
        $update->execute([DEFAULT_PREP_MINUTES, $addMinutes, $addMinutes, $orderId]);

        $read = $pdo->prepare('SELECT estimated_prep_minutes, estimated_ready_at FROM orders WHERE id = ? LIMIT 1');
        $read->execute([$orderId]);
        $row = $read->fetch();

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'order_id' => $orderId,
        'estimated_prep_minutes' => (int) ($row['estimated_prep_minutes'] ?? DEFAULT_PREP_MINUTES),
        'estimated_ready_at' => format_ready_time(isset($row['estimated_ready_at']) ? (string) $row['estimated_ready_at'] : null),
    ];
}
