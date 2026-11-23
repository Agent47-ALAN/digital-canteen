<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/api.php';
require_once __DIR__ . '/../../includes/cart.php';

$user = api_require_role('CUSTOMER');
$method = api_require_method('GET', 'POST');
$userId = (int) $user['id'];

/** A quantity from 1 to CART_MAX_QTY, or null. */
function cart_quantity_input(string $raw): ?int
{
    $quantity = api_int($raw);

    return ($quantity !== null && $quantity <= CART_MAX_QTY) ? $quantity : null;
}

try {
    $pdo = db();

    if ($method === 'GET') {
        json_response(true, 'Cart loaded.', ['cart' => cart_payload($pdo, $userId)]);
    }

    api_require_csrf();
    $action = post_value('action');
    $items = cart_get($userId);

    if ($action === 'add' || $action === 'set') {
        $foodId = api_int(post_value('food_item_id'));
        if ($foodId === null) {
            json_response(false, 'That food item could not be found.', null, 422);
        }

        $quantity = cart_quantity_input($action === 'add' && post_value('quantity') === '' ? '1' : post_value('quantity'));
        if ($quantity === null) {
            json_response(false, 'Choose a quantity from 1 to ' . CART_MAX_QTY . '.', null, 422);
        }

        $name = orderable_item_name($pdo, $foodId);
        if ($name === null) {
            json_response(false, 'Sorry, this item is not available right now. You can remove it from your cart.', null, 422);
        }

        if ($action === 'add') {
            if (!isset($items[$foodId]) && count($items) >= CART_MAX_LINES) {
                json_response(false, 'Your cart is full. Remove an item or check out first.', null, 422);
            }
            $newQuantity = ($items[$foodId] ?? 0) + $quantity;
            if ($newQuantity > CART_MAX_QTY) {
                json_response(false, 'You can order at most ' . CART_MAX_QTY . ' of one item.', null, 422);
            }
            $items[$foodId] = $newQuantity;
            $message = $name . ' added to your cart.';
        } else {
            if (!isset($items[$foodId])) {
                json_response(false, 'That item is not in your cart.', null, 422);
            }
            $items[$foodId] = $quantity;
            $message = 'Cart updated.';
        }

        cart_save($userId, $items);
        json_response(true, $message, ['cart' => cart_payload($pdo, $userId)]);
    }

    if ($action === 'remove') {
        $foodId = api_int(post_value('food_item_id'));
        if ($foodId === null || !isset($items[$foodId])) {
            json_response(false, 'That item is not in your cart.', null, 422);
        }

        unset($items[$foodId]);
        cart_save($userId, $items);
        json_response(true, 'Item removed.', ['cart' => cart_payload($pdo, $userId)]);
    }

    if ($action === 'clear') {
        cart_clear();
        json_response(true, 'Cart cleared.', ['cart' => cart_payload($pdo, $userId)]);
    }

    json_response(false, 'Unknown action.', null, 400);
} catch (PDOException $exception) {
    api_server_error($exception);
}
