<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

/**
 * Small guards for the JSON endpoints in /api.
 * They reuse current_user(), csrf_verify() and json_response() from Phase 1.
 * Every failure is returned as JSON, never as an HTML page or a redirect.
 */

function api_require_method(string ...$allowed): string
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!in_array($method, $allowed, true)) {
        if (!headers_sent()) {
            header('Allow: ' . implode(', ', $allowed));
        }
        json_response(false, 'This request method is not allowed.', null, 405);
    }

    return $method;
}

/**
 * Return the signed-in user only when the role matches exactly.
 *
 * @return array{id:int,full_name:string,email:string,role:string,is_active:mixed}
 */
function api_require_role(string $role): array
{
    $user = current_user();

    if ($user === null) {
        json_response(false, 'Your session has ended. Please log in again.', null, 401);
    }

    if ($user['role'] !== $role) {
        json_response(false, 'Your account does not have permission for this action.', null, 403);
    }

    return $user;
}

/**
 * Any signed-in, active user (customer, kitchen staff or admin).
 * Used by endpoints that every role may call, such as notifications.
 *
 * @return array{id:int,full_name:string,email:string,role:string,is_active:mixed}
 */
function api_require_user(): array
{
    $user = current_user();

    if ($user === null) {
        json_response(false, 'Your session has ended. Please log in again.', null, 401);
    }

    return $user;
}

/** The token travels in the POST body, so the Phase 1 csrf_verify() is reused. */
function api_require_csrf(): void
{
    if (!csrf_verify()) {
        json_response(false, 'The page expired. Refresh the page and try again.', null, 403);
    }
}

function api_server_error(Throwable $exception): never
{
    error_log('Digital Canteen API error: ' . $exception->getMessage());
    json_response(false, 'Something went wrong on the server. Please try again.', null, 500);
}

/** A positive integer from POST or GET text, or null when it is not one. */
function api_int(string $value): ?int
{
    if ($value === '' || strlen($value) > 10 || !ctype_digit($value)) {
        return null;
    }

    $number = (int) $value;

    return $number > 0 ? $number : null;
}
