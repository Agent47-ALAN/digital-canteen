<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

/** Public path of this project under the XAMPP web root. */
const APP_BASE = '/digital_canteen';

/**
 * Build a project URL such as /digital_canteen/login.php.
 */
function url(string $path = ''): string
{
    if ($path === '') {
        return APP_BASE . '/';
    }

    return APP_BASE . '/' . ltrim($path, '/');
}

/**
 * Send the browser to a page inside this project, then stop.
 */
function redirect(string $path): void
{
    if (preg_match('#^https?://#i', $path) === 1) {
        $location = $path;
    } else {
        $location = url($path);
    }

    header('Location: ' . $location);
    exit;
}

/**
 * Escape text before it is placed in HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Read a posted text field and trim the ends.
 * Do not use this for passwords, because spaces can be intentional.
 */
function post_value(string $key): string
{
    if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
        return '';
    }

    return trim($_POST[$key]);
}

/**
 * Read a posted password without trimming it.
 */
function post_password(string $key): string
{
    if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
        return '';
    }

    return $_POST[$key];
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

/**
 * JSON shape used by every API endpoint:
 * {"success":true,"message":"...","data":{}}
 * {"success":false,"message":"...","data":null}
 */
function json_response(bool $success, string $message, mixed $data = null, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $success ? ($data ?? new stdClass()) : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function csrf_token(): string
{
    if (
        !isset($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
        || strlen($_SESSION['csrf_token']) !== 64
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Read and remove the one-time message saved before a redirect.
 *
 * @return array{type:string,message:string}|null
 */
function get_flash(): ?array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $type = $flash['type'] ?? '';
    $message = $flash['message'] ?? '';

    if (!is_string($type) || !is_string($message) || $message === '') {
        return null;
    }

    if ($type !== 'success' && $type !== 'error') {
        $type = 'error';
    }

    return [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Check a password without treating a missing account as a match.
 * A stand-in hash keeps the failure path from skipping the slow check.
 */
function password_matches(string $password, ?string $hash): bool
{
    $standInHash = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy';
    $hashToCheck = (is_string($hash) && $hash !== '') ? $hash : $standInHash;
    $matches = password_verify($password, $hashToCheck);

    return is_string($hash) && $hash !== '' && $matches;
}

function role_label(string $role): string
{
    return match ($role) {
        'CUSTOMER' => 'Customer',
        'KITCHEN_STAFF' => 'Kitchen staff',
        'ADMIN' => 'Administrator',
        default => 'Unknown',
    };
}

function home_path_for_role(string $role): ?string
{
    return match ($role) {
        'CUSTOMER' => 'customer/dashboard.php',
        'KITCHEN_STAFF' => 'kitchen/dashboard.php',
        'ADMIN' => 'admin/dashboard.php',
        default => null,
    };
}

/**
 * Shared checks for a new customer or the first administrator.
 *
 * @return list<string>
 */
function validate_new_account(string $fullName, string $email, string $password, string $confirmPassword): array
{
    $errors = [];
    $fullName = preg_replace('/\s+/u', ' ', trim($fullName)) ?? '';

    if ($fullName === '' || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
        $errors[] = 'Enter your name using 2 to 100 characters.';
    } elseif (preg_match("/^[\\p{L}][\\p{L}\\s.'-]{1,99}$/u", $fullName) !== 1) {
        $errors[] = 'Use only letters, spaces, apostrophes, periods, and hyphens in the name.';
    }

    if (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Enter a valid email address.';
    }

    $passwordLength = strlen($password);
    if ($passwordLength < 8 || $passwordLength > 72) {
        $errors[] = 'Use a password between 8 and 72 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'The password and confirmation do not match.';
    }

    return $errors;
}
