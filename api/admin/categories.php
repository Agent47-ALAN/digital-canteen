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
        json_response(true, 'Categories loaded.', ['categories' => category_rows($pdo)]);
    }

    api_require_csrf();
    $action = post_value('action');

    if ($action === 'save') {
        $rawId = post_value('id');
        $id = null;
        if ($rawId !== '') {
            $id = api_int($rawId);
            if ($id === null) {
                json_response(false, 'That category could not be found.', null, 422);
            }
        }

        $input = validate_category_input();
        if ($input['errors'] !== []) {
            json_response(false, implode(' ', $input['errors']), null, 422);
        }

        $result = save_category($pdo, $id, $input['data']);
        if ($result['errors'] !== []) {
            json_response(false, implode(' ', $result['errors']), null, 422);
        }

        json_response(true, $id === null ? 'Category added.' : 'Category updated.', ['id' => $result['id']]);
    }

    if ($action === 'set_active') {
        $id = api_int(post_value('id'));
        $flag = parse_flag(post_value('value'));
        if ($id === null || $flag === null || !record_exists($pdo, 'categories', $id)) {
            json_response(false, 'That category could not be found.', null, 422);
        }

        $statement = $pdo->prepare('UPDATE categories SET is_active = ? WHERE id = ?');
        $statement->execute([$flag, $id]);
        json_response(true, $flag === 1 ? 'Category activated.' : 'Category deactivated.', ['id' => $id]);
    }

    json_response(false, 'Unknown action.', null, 400);
} catch (PDOException $exception) {
    api_server_error($exception);
}
