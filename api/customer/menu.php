<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/api.php';
require_once __DIR__ . '/../../includes/catalog.php';

api_require_role('CUSTOMER');
api_require_method('GET');

try {
    $pdo = db();
    $filters = [
        'category_id' => api_int((string) ($_GET['category_id'] ?? '')) ?? 0,
        'q' => clean_search(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''),
    ];

    $items = food_rows($pdo, $filters, true);
    json_response(true, 'Menu loaded.', [
        'categories' => menu_category_rows($pdo),
        'items' => $items,
        'count' => count($items),
    ]);
} catch (PDOException $exception) {
    api_server_error($exception);
}
