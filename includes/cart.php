<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

/**
 * Session cart for customers (Phase 3).
 *
 * The session only remembers food item ids and quantities. Names, prices and
 * availability are always read from the database, so the browser can never
 * influence a price. The cart also remembers which customer owns it, so one
 * customer's cart is never shown to another customer who logs in on the same
 * browser session.
 */

const CART_MAX_QTY = 20;
const CART_MAX_LINES = 25;

/** @return array<int,int> food_item_id => quantity */
function cart_get(int $userId): array
{
    $cart = $_SESSION['cart'] ?? null;

    if (!is_array($cart) || ($cart['owner'] ?? null) !== $userId || !is_array($cart['items'] ?? null)) {
        return [];
    }

    $clean = [];
    foreach ($cart['items'] as $id => $quantity) {
        $id = (int) $id;
        $quantity = (int) $quantity;
        if ($id > 0 && $quantity >= 1 && $quantity <= CART_MAX_QTY) {
            $clean[$id] = $quantity;
        }
    }

    return $clean;
}

/** @param array<int,int> $items */
function cart_save(int $userId, array $items): void
{
    $_SESSION['cart'] = ['owner' => $userId, 'items' => $items];
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

/** Total number of portions in the cart, for the header badge. */
function cart_count(int $userId): int
{
    return array_sum(cart_get($userId));
}

/** Whole paise, so money is never added up as floating point. */
function price_to_cents(string $decimal): int
{
    return (int) round(((float) $decimal) * 100);
}

function cents_to_money(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

/** "1350.5" -> "\u20B91,350.50" for display on server-rendered pages. */
function money_text(string $decimal): string
{
    return "\u{20B9}" . number_format((float) $decimal, 2, '.', ',');
}

/**
 * Can this item be ordered right now? Returns the item name, or null.
 */
function orderable_item_name(PDO $pdo, int $foodItemId): ?string
{
    $statement = $pdo->prepare(
        'SELECT f.name
         FROM food_items f
         INNER JOIN categories c ON c.id = f.category_id
         WHERE f.id = ? AND f.is_active = 1 AND f.is_available = 1 AND c.is_active = 1
         LIMIT 1'
    );
    $statement->execute([$foodItemId]);
    $name = $statement->fetchColumn();

    return $name === false ? null : (string) $name;
}

/**
 * Build the priced cart from the database.
 * $lock = true takes shared row locks, used inside the checkout transaction so a
 * price cannot change between reading it and saving the order.
 *
 * A line with a problem (item removed, deactivated or unavailable) is flagged and
 * left out of the total. Checkout is blocked while any line has a problem.
 *
 * @param array<int,int> $items
 * @return array{lines:list<array<string,mixed>>,total_cents:int,total:string,count:int,has_issues:bool}
 */
function cart_lines(PDO $pdo, array $items, bool $lock = false): array
{
    $empty = ['lines' => [], 'total_cents' => 0, 'total' => '0.00', 'count' => 0, 'has_issues' => false];
    if ($items === []) {
        return $empty;
    }

    $ids = array_keys($items);
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $sql = 'SELECT f.id, f.name, f.price, f.is_available, f.is_active, c.is_active AS category_active
            FROM food_items f
            INNER JOIN categories c ON c.id = f.category_id
            WHERE f.id IN (' . $marks . ')' . ($lock ? ' LOCK IN SHARE MODE' : '');
    $statement = $pdo->prepare($sql);
    $statement->execute($ids);

    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $rows[(int) $row['id']] = $row;
    }

    $lines = [];
    $totalCents = 0;
    $count = 0;
    $hasIssues = false;

    foreach ($items as $id => $quantity) {
        $row = $rows[$id] ?? null;
        $problem = null;

        if ($row === null) {
            $name = 'Item no longer on the menu';
            $unitCents = 0;
            $problem = 'This item is no longer on the menu.';
        } else {
            $name = (string) $row['name'];
            $unitCents = price_to_cents((string) $row['price']);
            if ((int) $row['is_active'] !== 1 || (int) $row['category_active'] !== 1) {
                $problem = 'This item is no longer on the menu.';
            } elseif ((int) $row['is_available'] !== 1) {
                $problem = 'This item is currently unavailable.';
            }
        }

        $subtotalCents = $unitCents * $quantity;
        if ($problem === null) {
            $totalCents += $subtotalCents;
        } else {
            $hasIssues = true;
        }
        $count += $quantity;

        $lines[] = [
            'food_item_id' => $id,
            'name' => $name,
            'unit_price' => cents_to_money($unitCents),
            'quantity' => $quantity,
            'subtotal' => cents_to_money($subtotalCents),
            'problem' => $problem,
        ];
    }

    return [
        'lines' => $lines,
        'total_cents' => $totalCents,
        'total' => cents_to_money($totalCents),
        'count' => $count,
        'has_issues' => $hasIssues,
    ];
}

/** The cart as sent to the browser. */
function cart_payload(PDO $pdo, int $userId): array
{
    $cart = cart_lines($pdo, cart_get($userId));

    return [
        'lines' => $cart['lines'],
        'total' => $cart['total'],
        'count' => $cart['count'],
        'has_issues' => $cart['has_issues'],
        'can_checkout' => $cart['lines'] !== [] && !$cart['has_issues'],
    ];
}
