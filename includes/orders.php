<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/notifications.php';

/**
 * Orders, tokens and the DEMO payment simulator (Phase 3).
 *
 * ##########################################################################
 * # THIS IS A COLLEGE DEMONSTRATION. THERE IS NO REAL PAYMENT GATEWAY.     #
 * # "Demo online payment" never contacts a bank, card network or UPI app.  #
 * # No card number, UPI PIN, CVV or banking detail is asked for or stored. #
 * ##########################################################################
 *
 * How the demo payment works: the browser sends only a demo scenario
 * ("success" or "failure") so the presenter can show both outcomes. It never
 * sends a payment status. demo_payment_simulate() on the server alone decides
 * whether the payment is PAID, and an order row only exists if it says yes.
 * A real gateway would instead report the result through a signed
 * server-to-server callback, which a later project could plug in here.
 */

const PAYMENT_METHODS = ['CASH', 'DEMO_ONLINE'];
const ORDER_PAGE_SIZE = 10;
const DEFAULT_PREP_MINUTES = 15;

/** A problem the customer can understand and act on (bad cart, expired page...). */
final class CheckoutException extends RuntimeException
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

/** The simulated payment was declined. No order is created. */
final class PaymentDeclinedException extends RuntimeException
{
}

/** DEMO ONLY. Decides the simulated payment result on the server. Not a real gateway. */
function demo_payment_simulate(string $scenario): bool
{
    return $scenario === 'success';
}

/** Easy to read at the counter: no 0/O, 1/I/L. Example: DC-7K3PQ9 */
function generate_order_token(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $last = strlen($alphabet) - 1;
    $code = '';

    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, $last)];
    }

    return 'DC-' . $code;
}

function is_duplicate_key(PDOException $exception, string $keyName): bool
{
    return (int) ($exception->errorInfo[1] ?? 0) === 1062
        && str_contains($exception->getMessage(), $keyName);
}

function find_order_id_by_checkout_token(PDO $pdo, int $userId, string $checkoutToken): ?int
{
    $statement = $pdo->prepare('SELECT id FROM orders WHERE checkout_token = ? AND user_id = ? LIMIT 1');
    $statement->execute([$checkoutToken, $userId]);
    $id = $statement->fetchColumn();

    return $id === false ? null : (int) $id;
}

/**
 * Create the order, its items, the payment record and the token in ONE transaction.
 *
 * - Prices and availability are re-read from the database under shared locks.
 * - For DEMO_ONLINE the simulator runs here. If it declines, nothing is written.
 * - The same checkout token always returns the same order (no duplicates), which
 *   covers double clicks, refreshes and two tabs.
 * - A token collision (UNIQUE tokens.token_number) just draws another token.
 *
 * @param array<int,int> $cartItems
 * @return array{order_id:int,duplicate:bool}
 * @throws CheckoutException|PaymentDeclinedException|PDOException
 */
function place_order(
    PDO $pdo,
    int $userId,
    array $cartItems,
    string $method,
    string $checkoutToken,
    string $demoScenario
): array {
    if (!in_array($method, PAYMENT_METHODS, true)) {
        throw new CheckoutException('Choose a valid payment method.');
    }

    $pdo->beginTransaction();

    try {
        $existing = find_order_id_by_checkout_token($pdo, $userId, $checkoutToken);
        if ($existing !== null) {
            $pdo->rollBack();
            return ['order_id' => $existing, 'duplicate' => true];
        }

        $cart = cart_lines($pdo, $cartItems, true);
        if ($cart['lines'] === []) {
            throw new CheckoutException('Your cart is empty.');
        }
        if ($cart['has_issues']) {
            throw new CheckoutException(
                'Some items in your cart are no longer available. Please review your cart.',
                409
            );
        }

        $paymentStatus = 'PENDING';
        $paymentNote = 'Cash to be paid at the canteen counter.';
        if ($method === 'DEMO_ONLINE') {
            if (!demo_payment_simulate($demoScenario)) {
                throw new PaymentDeclinedException('The demo payment failed.');
            }
            $paymentStatus = 'PAID';
            $paymentNote = 'Simulated payment. No real money was transferred.';
        }

        $total = $cart['total'];
        $prepMinutes = DEFAULT_PREP_MINUTES;

        try {
            $insertOrder = $pdo->prepare(
                'INSERT INTO orders (user_id, status, total_amount, checkout_token, estimated_prep_minutes, estimated_ready_at)
                 VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
            );
            $insertOrder->execute([$userId, 'PLACED', $total, $checkoutToken, $prepMinutes, $prepMinutes]);
        } catch (PDOException $exception) {
            if (is_duplicate_key($exception, 'uq_orders_checkout_token')) {
                // Another request with the same checkout token won the race.
                $pdo->rollBack();
                $winner = find_order_id_by_checkout_token($pdo, $userId, $checkoutToken);
                if ($winner !== null) {
                    return ['order_id' => $winner, 'duplicate' => true];
                }
            }
            throw $exception;
        }
        $orderId = (int) $pdo->lastInsertId();

        $insertItem = $pdo->prepare(
            'INSERT INTO order_items (order_id, food_item_id, item_name, unit_price, quantity, line_total)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($cart['lines'] as $line) {
            $insertItem->execute([
                $orderId,
                $line['food_item_id'],
                $line['name'],
                $line['unit_price'],
                $line['quantity'],
                $line['subtotal'],
            ]);
        }

        $pdo->prepare('INSERT INTO payments (order_id, amount, method, status, note) VALUES (?, ?, ?, ?, ?)')
            ->execute([$orderId, $total, $method, $paymentStatus, $paymentNote]);

        // A failed INSERT on a duplicate key only undoes that one statement, so the
        // transaction stays usable and we can simply try another token.
        $insertToken = $pdo->prepare('INSERT INTO tokens (order_id, token_number) VALUES (?, ?)');
        $saved = false;
        for ($attempt = 0; $attempt < 20 && !$saved; $attempt++) {
            try {
                $insertToken->execute([$orderId, generate_order_token()]);
                $saved = true;
            } catch (PDOException $exception) {
                if (!is_duplicate_key($exception, 'uq_tokens_number')) {
                    throw $exception;
                }
            }
        }
        if (!$saved) {
            throw new RuntimeException('Could not allocate a unique order token.');
        }

        $pdo->commit();

        // The order is saved. Now tell the people involved. This can never undo or
        // block the order: any problem is only written to the error log.
        notify_order_event_safe($pdo, $orderId, 'ORDER_PLACED', $userId);

        return ['order_id' => $orderId, 'duplicate' => false];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/* ------------------------------------------------------------ Reading orders */

function payment_method_label(string $method): string
{
    return match ($method) {
        'CASH' => 'Cash payment',
        'DEMO_ONLINE' => 'Demo online payment',
        'DEMO' => 'Demo payment',
        default => 'Unknown',
    };
}

function payment_status_label(string $status): string
{
    return match ($status) {
        'PENDING' => 'Pending',
        'PAID' => 'Paid',
        'FAILED' => 'Failed',
        default => 'Unknown',
    };
}

function order_status_label(string $status): string
{
    return match ($status) {
        'PLACED' => 'Placed',
        'PREPARING' => 'Preparing',
        'READY' => 'Ready',
        'COLLECTED' => 'Collected',
        'CANCELLED' => 'Cancelled',
        default => 'Unknown',
    };
}

/** Badge colour name for a status. Keep in step with DC.statusKind() in assets/js/app.js. */
function order_status_kind(string $status): string
{
    return match ($status) {
        'PLACED' => 'info',
        'PREPARING' => 'warn',
        'READY' => 'on',
        'COLLECTED' => 'muted',
        'CANCELLED' => 'off',
        default => 'muted',
    };
}

/** One friendly sentence for the customer about where their order is. */
function order_status_hint(string $status): string
{
    return match ($status) {
        'PLACED' => 'Your order has been received and is waiting for the kitchen.',
        'PREPARING' => 'The kitchen is preparing your order.',
        'READY' => 'Your order is ready! Show your token at the counter to collect it.',
        'COLLECTED' => 'You have collected this order. Enjoy your meal!',
        'CANCELLED' => 'This order was cancelled.',
        default => '',
    };
}

function format_order_time(string $databaseTime): string
{
    $date = DateTime::createFromFormat('Y-m-d H:i:s', $databaseTime);

    return $date instanceof DateTime ? $date->format('d M Y, h:i A') : $databaseTime;
}

function format_ready_time(?string $databaseTime): ?string
{
    if ($databaseTime === null || $databaseTime === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d H:i:s', $databaseTime);

    return $date instanceof DateTime ? $date->format('g:i A') : $databaseTime;
}

/**
 * Shape one joined order row for the pages and the JSON API.
 *
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function order_summary_from_row(array $row): array
{
    $method = (string) ($row['payment_method'] ?? '');
    $paymentStatus = (string) ($row['payment_status'] ?? '');
    $prepMinutes = isset($row['estimated_prep_minutes']) && $row['estimated_prep_minutes'] !== null
        ? (int) $row['estimated_prep_minutes']
        : null;
    $readyEpoch = isset($row['estimated_ready_epoch']) && $row['estimated_ready_epoch'] !== null
        ? (int) $row['estimated_ready_epoch']
        : null;
    $serverNowEpoch = isset($row['server_now_epoch']) && $row['server_now_epoch'] !== null
        ? (int) $row['server_now_epoch']
        : time();

    return [
        'id' => (int) $row['id'],
        'token_number' => (string) ($row['token_number'] ?? ''),
        'placed_at' => format_order_time((string) $row['placed_at']),
        'total_amount' => number_format((float) $row['total_amount'], 2, '.', ''),
        'item_count' => (int) ($row['item_count'] ?? 0),
        'status' => (string) $row['status'],
        'status_label' => order_status_label((string) $row['status']),
        'status_hint' => order_status_hint((string) $row['status']),
        'payment_method' => $method,
        'payment_method_label' => payment_method_label($method),
        'payment_status' => $paymentStatus,
        'payment_status_label' => payment_status_label($paymentStatus),
        'estimated_prep_minutes' => $prepMinutes,
        'estimated_ready_at' => format_ready_time(isset($row['estimated_ready_at']) ? (string) $row['estimated_ready_at'] : null),
        'estimated_ready_epoch' => $readyEpoch,
        'server_now_epoch' => $serverNowEpoch,
    ];
}

/**
 * One order WITH its items, but only if it belongs to $userId.
 * The ownership check is part of the query, so another customer's order id
 * simply finds nothing.
 *
 * @return array<string,mixed>|null
 */
function order_for_user(PDO $pdo, int $orderId, int $userId): ?array
{
    $statement = $pdo->prepare(
        'SELECT o.id, o.status, o.total_amount, o.placed_at,
                o.estimated_prep_minutes, o.estimated_ready_at,
                UNIX_TIMESTAMP(o.estimated_ready_at) AS estimated_ready_epoch,
                UNIX_TIMESTAMP(NOW()) AS server_now_epoch,
                t.token_number, p.method AS payment_method, p.status AS payment_status,
                (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
         FROM orders o
         LEFT JOIN tokens t ON t.order_id = o.id
         LEFT JOIN payments p ON p.order_id = o.id
         WHERE o.id = ? AND o.user_id = ?
         LIMIT 1'
    );
    $statement->execute([$orderId, $userId]);
    $row = $statement->fetch();
    if (!$row) {
        return null;
    }

    $order = order_summary_from_row($row);

    $items = $pdo->prepare(
        'SELECT item_name, unit_price, quantity, line_total FROM order_items WHERE order_id = ? ORDER BY id'
    );
    $items->execute([$orderId]);
    $order['items'] = array_map(static fn (array $i): array => [
        'name' => (string) $i['item_name'],
        'unit_price' => number_format((float) $i['unit_price'], 2, '.', ''),
        'quantity' => (int) $i['quantity'],
        'line_total' => number_format((float) $i['line_total'], 2, '.', ''),
    ], $items->fetchAll());

    return $order;
}

/**
 * A page of this customer's orders, newest first.
 *
 * @return array{orders:list<array<string,mixed>>,has_more:bool}
 */
function orders_for_user(PDO $pdo, int $userId, int $page): array
{
    $page = max(1, min($page, 1000));
    $limit = ORDER_PAGE_SIZE + 1;
    $offset = ($page - 1) * ORDER_PAGE_SIZE;

    $statement = $pdo->prepare(
        'SELECT o.id, o.status, o.total_amount, o.placed_at,
                o.estimated_prep_minutes, o.estimated_ready_at,
                UNIX_TIMESTAMP(o.estimated_ready_at) AS estimated_ready_epoch,
                UNIX_TIMESTAMP(NOW()) AS server_now_epoch,
                t.token_number, p.method AS payment_method, p.status AS payment_status,
                (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
         FROM orders o
         LEFT JOIN tokens t ON t.order_id = o.id
         LEFT JOIN payments p ON p.order_id = o.id
         WHERE o.user_id = ?
         ORDER BY o.placed_at DESC, o.id DESC
         LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $statement->execute([$userId]);
    $rows = $statement->fetchAll();

    $hasMore = count($rows) > ORDER_PAGE_SIZE;
    $rows = array_slice($rows, 0, ORDER_PAGE_SIZE);

    return [
        'orders' => array_map('order_summary_from_row', $rows),
        'has_more' => $hasMore,
    ];
}
