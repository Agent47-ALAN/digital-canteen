<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/api.php';
require_once __DIR__ . '/../../includes/catalog.php';

api_require_role('ADMIN');
$method = api_require_method('GET', 'POST');

try {
    $pdo = db();

    if ($method === 'GET') {
        $filters = [
            'category_id' => api_int((string) ($_GET['category_id'] ?? '')) ?? 0,
            'q' => clean_search(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''),
        ];
        json_response(true, 'Food items loaded.', [
            'items' => food_rows($pdo, $filters, false),
            'categories' => category_rows($pdo),
        ]);
    }

    api_require_csrf();
    $action = post_value('action');

    if ($action === 'save') {
        $rawId = post_value('id');
        $id = null;
        if ($rawId !== '') {
            $id = api_int($rawId);
            if ($id === null) {
                json_response(false, 'That food item could not be found.', null, 422);
            }
        }

        $input = validate_food_input();
        if ($input['errors'] !== []) {
            json_response(false, implode(' ', $input['errors']), null, 422);
        }

        $result = save_food($pdo, $id, $input['data']);
        if ($result['errors'] !== []) {
            json_response(false, implode(' ', $result['errors']), null, 422);
        }

        json_response(true, $id === null ? 'Food item added.' : 'Food item updated.', ['id' => $result['id']]);
    }

    if ($action === 'set_active' || $action === 'set_available') {
        $id = api_int(post_value('id'));
        $flag = parse_flag(post_value('value'));
        if ($id === null || $flag === null || !record_exists($pdo, 'food_items', $id)) {
            json_response(false, 'That food item could not be found.', null, 422);
        }

        if ($action === 'set_active') {
            $statement = $pdo->prepare('UPDATE food_items SET is_active = ? WHERE id = ?');
            $message = $flag === 1 ? 'Food item activated.' : 'Food item deactivated.';
        } else {
            $statement = $pdo->prepare('UPDATE food_items SET is_available = ? WHERE id = ?');
            $message = $flag === 1 ? 'Food item marked available.' : 'Food item marked unavailable.';
        }
        $statement->execute([$flag, $id]);
        json_response(true, $message, ['id' => $id]);
    }

    json_response(false, 'Unknown action.', null, 400);
} catch (PDOException $exception) {
    api_server_error($exception);
}
