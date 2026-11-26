<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

const VAPID_SUBJECT = 'mailto:admin@digitalcanteen.local';
const VAPID_PUBLIC_KEY = 'BGBGlp5syMiQIg5TdaBz9tsucWtOId77cTl_TpCTln36hD3ucPkC_AAEhzB582whakG81hyDr6cU_pYLpQNhq9c';
const VAPID_PRIVATE_KEY_FILE = 'C:/xampp/digital_canteen_secrets/vapid_private.php';

/**
 * Public VAPID key safe to expose to the browser.
 */
function vapid_public_key(): string
{
    return VAPID_PUBLIC_KEY;
}

/**
 * Full VAPID auth array for Minishlink\WebPush\WebPush (server-side only).
 *
 * @return array{subject:string,publicKey:string,privateKey:string}
 */
function vapid_auth_config(): array
{
    if (!is_file(VAPID_PRIVATE_KEY_FILE)) {
        throw new RuntimeException('VAPID private key file is missing.');
    }

    $secret = require VAPID_PRIVATE_KEY_FILE;
    $privateKey = is_array($secret) ? ($secret['privateKey'] ?? '') : '';
    if (!is_string($privateKey) || $privateKey === '') {
        throw new RuntimeException('VAPID private key is invalid.');
    }

    return [
        'subject' => VAPID_SUBJECT,
        'publicKey' => VAPID_PUBLIC_KEY,
        'privateKey' => $privateKey,
    ];
}
