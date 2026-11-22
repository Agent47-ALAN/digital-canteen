<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

/**
 * Menu data (categories and food items) for Phase 2.
 * Every query is prepared. Prices are validated and stored on the server only.
 */

/** Collapse runs of whitespace and trim. Input must already be valid UTF-8. */
function clean_text(string $value): string
{
    return preg_replace('/\s+/u', ' ', trim($value)) ?? '';
}

function text_is_valid(string $value): bool
{
    return mb_check_encoding($value, 'UTF-8') && preg_match('/[\p{Cc}\p{Cf}]/u', $value) !== 1;
}

/** Only the exact strings "1" and "0" are accepted for on/off fields. */
function parse_flag(string $value): ?int
{
    return match ($value) {
        '1' => 1,
        '0' => 0,
        default => null,
    };
}

/**
 * Check one text field and return the cleaned value.
 * An optional field may be empty. The error goes into $errors.
 *
 * @param list<string> $errors
 */
function validate_text_field(string $raw, string $label, int $min, int $max, array &$errors): string
{
    if (!text_is_valid($raw)) {
        $errors[] = $label . ' contains characters that are not allowed.';
        return '';
    }

    $value = clean_text($raw);
    $length = mb_strlen($value);

    if ($length < $min || $length > $max) {
        $errors[] = $min > 0
            ? $label . ' must be ' . $min . ' to ' . $max . ' characters.'
            : $label . ' can be at most ' . $max . ' characters.';
    }

    return $value;
}

/** Escape LIKE wildcards so a search for "50%" matches the text and not everything. */
function like_pattern(string $text): string
{
    return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text) . '%';
}

/** Clean a search text from the browser: valid UTF-8, one line, at most 100 characters. */
function clean_search(string $raw): string
{
    if (!text_is_valid($raw)) {
        return '';
    }

    return mb_substr(clean_text($raw), 0, 100);
}

/* ---------------------------------------------------------------- Categories */

/**
 * @return list<array{id:int,name:string,description:string,sort_order:int,is_active:bool,item_count:int}>
 */
function category_rows(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT c.id, c.name, c.description, c.sort_order, c.is_active,
                (SELECT COUNT(*) FROM food_items f WHERE f.category_id = c.id) AS item_count
         FROM categories c
         ORDER BY c.sort_order, c.name'
    )->fetchAll();

    return array_map(static fn (array $r): array => [
        'id' => (int) $r['id'],
        'name' => (string) $r['name'],
        'description' => (string) ($r['description'] ?? ''),
        'sort_order' => (int) $r['sort_order'],
        'is_active' => (int) $r['is_active'] === 1,
        'item_count' => (int) $r['item_count'],
    ], $rows);
}

/**
 * Validate the posted category form.
 *
 * @return array{errors:list<string>,data:array{name:string,description:string,sort_order:int,is_active:int}}
 */
function validate_category_input(): array
{
    $errors = [];
    $name = validate_text_field(post_value('name'), 'The category name', 2, 100, $errors);
    $description = validate_text_field(post_value('description'), 'The description', 0, 255, $errors);

    $sortRaw = post_value('sort_order');
    $sortOrder = 0;
    if ($sortRaw !== '') {
        if (!ctype_digit($sortRaw) || strlen($sortRaw) > 4) {
            $errors[] = 'The display order must be a whole number from 0 to 9999.';
        } else {
            $sortOrder = (int) $sortRaw;
        }
    }

    $isActive = parse_flag(post_value('is_active'));
    if ($isActive === null) {
        $errors[] = 'Choose whether the category is active.';
        $isActive = 1;
    }

    return [
        'errors' => $errors,
        'data' => [
            'name' => $name,
            'description' => $description,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ],
    ];
}

function record_exists(PDO $pdo, string $table, int $id): bool
{
    if ($table !== 'categories' && $table !== 'food_items') {
        throw new InvalidArgumentException('Unknown table.');
    }

    $statement = $pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE id = ? LIMIT 1');
    $statement->execute([$id]);

    return $statement->fetchColumn() !== false;
}

/**
 * Create ($id null) or update a category.
 *
 * @param array{name:string,description:string,sort_order:int,is_active:int} $data
 * @return array{errors:list<string>,id:int}
 */
function save_category(PDO $pdo, ?int $id, array $data): array
{
    if ($id !== null && !record_exists($pdo, 'categories', $id)) {
        return ['errors' => ['That category no longer exists.'], 'id' => 0];
    }

    $duplicate = $pdo->prepare('SELECT 1 FROM categories WHERE name = ? AND id <> ? LIMIT 1');
    $duplicate->execute([$data['name'], $id ?? 0]);
    if ($duplicate->fetchColumn() !== false) {
        return ['errors' => ['A category with this name already exists.'], 'id' => 0];
    }

    $description = $data['description'] === '' ? null : $data['description'];

    try {
        if ($id === null) {
            $statement = $pdo->prepare(
                'INSERT INTO categories (name, description, sort_order, is_active) VALUES (?, ?, ?, ?)'
            );
            $statement->execute([$data['name'], $description, $data['sort_order'], $data['is_active']]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $statement = $pdo->prepare(
                'UPDATE categories SET name = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?'
            );
            $statement->execute([$data['name'], $description, $data['sort_order'], $data['is_active'], $id]);
        }
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() === '23000') {
            return ['errors' => ['A category with this name already exists.'], 'id' => 0];
        }
        throw $exception;
    }

    return ['errors' => [], 'id' => $id];
}

/* ---------------------------------------------------------------- Food items */

/**
 * Food items with their category. $publicOnly keeps only what a customer may see.
 *
 * @param array{category_id?:int,q?:string} $filters
 * @return list<array{id:int,category_id:int,category_name:string,category_active:bool,name:string,description:string,price:string,is_available:bool,is_active:bool}>
 */
function food_rows(PDO $pdo, array $filters, bool $publicOnly): array
{
    $sql = 'SELECT f.id, f.category_id, c.name AS category_name, c.is_active AS category_active,
                   f.name, f.description, f.price, f.is_available, f.is_active
            FROM food_items f
            INNER JOIN categories c ON c.id = f.category_id
            WHERE 1 = 1';
    $params = [];

    if ($publicOnly) {
        $sql .= ' AND f.is_active = 1 AND c.is_active = 1';
    }

    if (!empty($filters['category_id'])) {
        $sql .= ' AND f.category_id = ?';
        $params[] = (int) $filters['category_id'];
    }

    if (isset($filters['q']) && $filters['q'] !== '') {
        $sql .= " AND (f.name LIKE ? ESCAPE '!' OR f.description LIKE ? ESCAPE '!')";
        $pattern = like_pattern($filters['q']);
        $params[] = $pattern;
        $params[] = $pattern;
    }

    $sql .= ' ORDER BY c.sort_order, c.name, f.name, f.id';
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return array_map(static fn (array $r): array => [
        'id' => (int) $r['id'],
        'category_id' => (int) $r['category_id'],
        'category_name' => (string) $r['category_name'],
        'category_active' => (int) $r['category_active'] === 1,
        'name' => (string) $r['name'],
        'description' => (string) ($r['description'] ?? ''),
        'price' => number_format((float) $r['price'], 2, '.', ''),
        'is_available' => (int) $r['is_available'] === 1,
        'is_active' => (int) $r['is_active'] === 1,
    ], $statement->fetchAll());
}

/**
 * Active categories that have at least one visible item, for the customer filter.
 *
 * @return list<array{id:int,name:string}>
 */
function menu_category_rows(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT c.id, c.name
         FROM categories c
         WHERE c.is_active = 1
           AND EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.is_active = 1)
         ORDER BY c.sort_order, c.name'
    )->fetchAll();

    return array_map(static fn (array $r): array => [
        'id' => (int) $r['id'],
        'name' => (string) $r['name'],
    ], $rows);
}

/**
 * Validate the posted food form. The category is checked later against the database.
 *
 * @return array{errors:list<string>,data:array{category_id:int,name:string,description:string,price:string,is_available:int,is_active:int}}
 */
function validate_food_input(): array
{
    $errors = [];
    $name = validate_text_field(post_value('name'), 'The item name', 2, 150, $errors);
    $description = validate_text_field(post_value('description'), 'The description', 0, 500, $errors);

    $categoryId = api_int(post_value('category_id'));
    if ($categoryId === null) {
        $errors[] = 'Choose a category.';
        $categoryId = 0;
    }

    $priceRaw = post_value('price');
    $price = '0.00';
    if (preg_match('/^\d{1,5}(\.\d{1,2})?$/', $priceRaw) !== 1 || (float) $priceRaw <= 0) {
        $errors[] = 'Enter a price greater than 0 and up to 99999.99, with at most 2 decimal places.';
    } else {
        $price = number_format((float) $priceRaw, 2, '.', '');
    }

    $isAvailable = parse_flag(post_value('is_available'));
    $isActive = parse_flag(post_value('is_active'));
    if ($isAvailable === null || $isActive === null) {
        $errors[] = 'Choose the availability and active status.';
        $isAvailable = $isAvailable ?? 1;
        $isActive = $isActive ?? 1;
    }

    return [
        'errors' => $errors,
        'data' => [
            'category_id' => $categoryId,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'is_available' => $isAvailable,
            'is_active' => $isActive,
        ],
    ];
}

/**
 * Create ($id null) or update a food item.
 *
 * @param array{category_id:int,name:string,description:string,price:string,is_available:int,is_active:int} $data
 * @return array{errors:list<string>,id:int}
 */
function save_food(PDO $pdo, ?int $id, array $data): array
{
    $currentCategory = null;
    if ($id !== null) {
        $existing = $pdo->prepare('SELECT category_id FROM food_items WHERE id = ? LIMIT 1');
        $existing->execute([$id]);
        $found = $existing->fetchColumn();
        if ($found === false) {
            return ['errors' => ['That food item no longer exists.'], 'id' => 0];
        }
        $currentCategory = (int) $found;
    }

    $category = $pdo->prepare('SELECT is_active FROM categories WHERE id = ? LIMIT 1');
    $category->execute([$data['category_id']]);
    $categoryActive = $category->fetchColumn();
    if ($categoryActive === false) {
        return ['errors' => ['The chosen category does not exist.'], 'id' => 0];
    }
    if ((int) $categoryActive !== 1 && $data['category_id'] !== $currentCategory) {
        return ['errors' => ['Choose an active category.'], 'id' => 0];
    }

    $duplicate = $pdo->prepare(
        'SELECT 1 FROM food_items WHERE category_id = ? AND name = ? AND id <> ? LIMIT 1'
    );
    $duplicate->execute([$data['category_id'], $data['name'], $id ?? 0]);
    if ($duplicate->fetchColumn() !== false) {
        return ['errors' => ['This category already has an item with that name.'], 'id' => 0];
    }

    $description = $data['description'] === '' ? null : $data['description'];

    if ($id === null) {
        $statement = $pdo->prepare(
            'INSERT INTO food_items (category_id, name, description, price, is_available, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $data['category_id'], $data['name'], $description,
            $data['price'], $data['is_available'], $data['is_active'],
        ]);
        $id = (int) $pdo->lastInsertId();
    } else {
        $statement = $pdo->prepare(
            'UPDATE food_items
             SET category_id = ?, name = ?, description = ?, price = ?, is_available = ?, is_active = ?
             WHERE id = ?'
        );
        $statement->execute([
            $data['category_id'], $data['name'], $description,
            $data['price'], $data['is_available'], $data['is_active'], $id,
        ]);
    }

    return ['errors' => [], 'id' => $id];
}
