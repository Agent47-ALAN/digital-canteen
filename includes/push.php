<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../config/vapid.php';

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Validate a browser push endpoint URL (must be HTTPS).
 */
function push_validate_endpoint(string $endpoint): ?string
{
    $endpoint = trim($endpoint);
    if ($endpoint === '' || strlen($endpoint) > 2048) {
        return null;
    }

    if (!str_starts_with($endpoint, 'https://') || filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
        return null;
    }

    return $endpoint;
}

/**
 * Validate a Base64 / Base64URL subscription key (p256dh or auth).
 */
function push_validate_key(string $value): ?string
{
    $value = trim($value);
    if (preg_match('/^[A-Za-z0-9_+\/=-]{16,255}$/', $value) !== 1) {
        return null;
    }

    return $value;
}

/**
 * Save or update a device push subscription for the logged-in user.
 * Supports multiple devices per user; endpoint_hash is UNIQUE per browser endpoint.
 */
function push_subscribe(PDO $pdo, int $userId, string $endpoint, string $publicKey, string $authToken): void
{
    $hash = hash('sha256', $endpoint);

    $statement = $pdo->prepare(
        'INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, public_key, auth_token)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             user_id = VALUES(user_id),
             endpoint = VALUES(endpoint),
             public_key = VALUES(public_key),
             auth_token = VALUES(auth_token)'
    );
    $statement->execute([$userId, $endpoint, $hash, $publicKey, $authToken]);
}

/**
 * Remove one device subscription owned by this user.
 */
function push_unsubscribe(PDO $pdo, int $userId, string $endpoint): bool
{
    $hash = hash('sha256', $endpoint);

    $statement = $pdo->prepare(
        'DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?'
    );
    $statement->execute([$hash, $userId]);

    return $statement->rowCount() > 0;
}

/**
 * Return subscription status for the current user (and optional current browser endpoint).
 *
 * @return array{subscribed:bool,count:int}
 */
function push_subscription_status(PDO $pdo, int $userId, ?string $endpoint = null): array
{
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?');
    $countStmt->execute([$userId]);
    $count = (int) $countStmt->fetchColumn();

    $subscribed = false;
    if ($endpoint !== null && $endpoint !== '') {
        $hash = hash('sha256', $endpoint);
        $checkStmt = $pdo->prepare(
            'SELECT 1 FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ? LIMIT 1'
        );
        $checkStmt->execute([$hash, $userId]);
        $subscribed = $checkStmt->fetchColumn() !== false;
    }

    return [
        'subscribed' => $subscribed,
        'count' => $count,
    ];
}

/**
 * Remove expired (404 / 410) push subscriptions by their SHA-256 endpoint hashes.
 *
 * @param list<string> $hashes
 */
function push_delete_expired_by_hashes(PDO $pdo, array $hashes): void
{
    $hashes = array_values(array_unique(array_filter($hashes, static fn (string $h): bool => $h !== '')));
    if ($hashes === []) {
        return;
    }

    $marks = implode(',', array_fill(0, count($hashes), '?'));
    $statement = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash IN (' . $marks . ')');
    $statement->execute($hashes);
}

/**
 * Send Web Push messages for newly created notifications.
 * Each message is delivered to all active device subscriptions of its target user_id.
 *
 * @param list<array{user_id:int,order_id:?int,event_type:string,title:string,message:string,url:string}> $messages
 */
function push_send_batch(PDO $pdo, array $messages): void
{
    if ($messages === []) {
        return;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('Web Push skipped: vendor/autoload.php not found.');
        return;
    }
    require_once $autoload;

    $userIds = array_values(array_unique(array_map(
        static fn (array $m): int => (int) $m['user_id'],
        $messages
    )));
    if ($userIds === []) {
        return;
    }

    $marks = implode(',', array_fill(0, count($userIds), '?'));
    $subStmt = $pdo->prepare(
        'SELECT user_id, endpoint, endpoint_hash, public_key, auth_token
         FROM push_subscriptions
         WHERE user_id IN (' . $marks . ')'
    );
    $subStmt->execute($userIds);
    $rows = $subStmt->fetchAll();

    if ($rows === []) {
        return;
    }

    $subsByUser = [];
    foreach ($rows as $row) {
        $subsByUser[(int) $row['user_id']][] = $row;
    }

    if (PHP_OS_FAMILY === 'Windows' && getenv('OPENSSL_CONF') === false) {
        $cnf = 'C:/xampp/php/extras/openssl/openssl.cnf';
        if (is_file($cnf)) {
            @putenv('OPENSSL_CONF=' . $cnf);
        }
    }

    $webPush = new WebPush(['VAPID' => vapid_auth_config()], ['TTL' => 300]);
    $webPush->setReuseVAPIDHeaders(true);

    $queued = 0;
    foreach ($messages as $msg) {
        $uid = (int) $msg['user_id'];
        if (empty($subsByUser[$uid])) {
            continue;
        }

        $tag = $msg['order_id'] !== null
            ? 'order-' . $msg['order_id'] . '-' . strtolower($msg['event_type'])
            : 'dc-notif';

        $payload = json_encode([
            'title' => (string) $msg['title'],
            'body' => (string) $msg['message'],
            'url' => (string) $msg['url'],
            'tag' => $tag,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            continue;
        }

        foreach ($subsByUser[$uid] as $subRow) {
            $subscription = Subscription::create([
                'endpoint' => (string) $subRow['endpoint'],
                'publicKey' => (string) $subRow['public_key'],
                'authToken' => (string) $subRow['auth_token'],
                'contentEncoding' => 'aes128gcm',
            ]);
            $webPush->queueNotification($subscription, $payload);
            $queued++;
        }
    }

    if ($queued === 0) {
        return;
    }

    $expiredHashes = [];
    foreach ($webPush->flush() as $report) {
        if ($report->isSuccess()) {
            continue;
        }

        if ($report->isSubscriptionExpired()) {
            $expiredHashes[] = hash('sha256', $report->getEndpoint());
        } else {
            error_log('Web Push delivery failed for ' . $report->getEndpoint() . ': ' . $report->getReason());
        }
    }

    if ($expiredHashes !== []) {
        push_delete_expired_by_hashes($pdo, $expiredHashes);
    }
}

/**
 * Safe wrapper: push failures are logged and can never break orders, payments, or kitchen updates.
 *
 * @param list<array{user_id:int,order_id:?int,event_type:string,title:string,message:string,url:string}> $messages
 */
function push_send_batch_safe(PDO $pdo, array $messages): void
{
    try {
        push_send_batch($pdo, $messages);
    } catch (Throwable $exception) {
        error_log('Web Push batch error: ' . $exception->getMessage());
    }
}
