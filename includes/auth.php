<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

/**
 * Start the application session.
 * The cookie is limited to this project and is not readable by JavaScript.
 */
function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('DIGITAL_CANTEEN');

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => APP_BASE . '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    send_security_headers();
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

/**
 * The signed-in user is always read from the database.
 * The browser is trusted only for the session id, never for a role or price.
 *
 * @return array{id:int,full_name:string,email:string,role:string,is_active:mixed}|null
 */
function current_user(): ?array
{
    static $resolved = false;
    static $user = null;

    if ($resolved) {
        return $user;
    }

    $resolved = true;
    $userId = $_SESSION['user_id'] ?? null;

    if (is_string($userId) && ctype_digit($userId)) {
        $userId = (int) $userId;
    }

    if (!is_int($userId) || $userId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, full_name, email, role, is_active
         FROM users
         WHERE id = ?
         LIMIT 1'
    );
    $statement->execute([$userId]);
    $row = $statement->fetch();

    if (
        !$row
        || (int) $row['is_active'] !== 1
        || home_path_for_role((string) $row['role']) === null
    ) {
        unset($_SESSION['user_id']);
        return null;
    }

    $row['id'] = (int) $row['id'];
    $user = $row;

    return $user;
}

function require_login(): void
{
    if (current_user() === null) {
        set_flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

/**
 * Stop the page unless the signed-in account has this exact role.
 */
function require_role(string $role): void
{
    require_login();
    $user = current_user();

    if ($user === null || $user['role'] !== $role) {
        render_forbidden();
    }
}

function render_forbidden(): void
{
    http_response_code(403);
    $pageTitle = 'Access denied';
    require __DIR__ . '/header.php';
    echo '<section class="panel">';
    echo '<p class="eyebrow">Protected page</p>';
    echo '<h1>Access denied</h1>';
    echo '<p>Your account does not have permission to open this page.</p>';
    echo '<p><a class="button" href="' . e(url('index.php')) . '">Back to home</a></p>';
    echo '</section>';
    require __DIR__ . '/footer.php';
    exit;
}

function destroy_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => (string) $params['path'],
            'domain' => (string) $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ]);
    }

    session_destroy();
}

start_app_session();
