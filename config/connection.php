<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

/**
 * Local XAMPP database settings.
 * The default XAMPP account is root with an empty password.
 * Change these values here if your MySQL account is different.
 * Do not put this account on a public server.
 */
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'digital_canteen';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

/**
 * Shared database connection.
 * The connection is opened on the first call and reused after that.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+05:30'");
    } catch (PDOException $exception) {
        error_log('Digital Canteen database connection failed: ' . $exception->getMessage());
        render_database_error();
    }

    return $pdo;
}

/**
 * A safe message when MySQL cannot be reached.
 * The real database error stays in the PHP error log.
 */
function render_database_error(): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Database unavailable · Digital Canteen</title>';
    echo '<link rel="stylesheet" href="/digital_canteen/assets/css/main.css">';
    echo '</head>';
    echo '<body>';
    echo '<main class="shell">';
    echo '<section class="panel">';
    echo '<p class="eyebrow">Digital Canteen</p>';
    echo '<h1>The database is not available</h1>';
    echo '<p>Start MySQL in the XAMPP Control Panel. Then import <strong>database/canteen.sql</strong> in phpMyAdmin. The database name must be digital_canteen.</p>';
    echo '<p>If MySQL is already running, check the account in <strong>config/connection.php</strong>.</p>';
    echo '</section>';
    echo '</main>';
    echo '</body>';
    echo '</html>';
    exit;
}
