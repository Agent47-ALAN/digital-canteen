<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/**
 * Local one-time page for the first administrator.
 * Delete this file after the account has been created.
 */
function request_is_local(): bool
{
    $address = $_SERVER['REMOTE_ADDR'] ?? '';

    return $address === '127.0.0.1' || $address === '::1';
}

function admin_account_exists(PDO $pdo): bool
{
    $statement = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'ADMIN'");

    return (int) $statement->fetchColumn() > 0;
}

if (!request_is_local()) {
    http_response_code(403);
    $pageTitle = 'Setup unavailable';
    $robotsNoIndex = true;
    require __DIR__ . '/../includes/header.php';
    echo '<section class="panel">';
    echo '<h1>Setup unavailable</h1>';
    echo '<p>The first administrator can only be created from the computer running XAMPP.</p>';
    echo '</section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$errors = [];
$created = false;
$databaseReady = true;
$adminExists = false;
$fullName = '';
$email = '';

try {
    $adminExists = admin_account_exists(db());
} catch (PDOException $exception) {
    error_log('First-admin setup could not read users: ' . $exception->getMessage());
    $databaseReady = false;
    $errors[] = 'The database tables are missing. Import database/canteen.sql in phpMyAdmin, then refresh this page.';
}

if ($databaseReady && !$adminExists && is_post()) {
    if (!csrf_verify()) {
        $errors[] = 'The form expired. Refresh the page and try again.';
    } else {
        $fullName = preg_replace('/\s+/u', ' ', post_value('full_name')) ?? '';
        $email = normalize_email(post_value('email'));
        $password = post_password('password');
        $confirmPassword = post_password('confirm_password');
        $errors = validate_new_account($fullName, $email, $password, $confirmPassword);

        if ($errors === []) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                $errors[] = 'The administrator account could not be created. Please try again.';
            } else {
                $pdo = db();
                $lockHeld = false;

                try {
                    $lockStatement = $pdo->query("SELECT GET_LOCK('digital_canteen_first_admin', 10)");
                    $lockHeld = (string) $lockStatement->fetchColumn() === '1';
                    $lockStatement->closeCursor();

                    if (!$lockHeld) {
                        $errors[] = 'Setup is busy. Wait a moment and try again.';
                    } elseif (admin_account_exists($pdo)) {
                        $adminExists = true;
                    } else {
                        $insert = $pdo->prepare(
                            'INSERT INTO users (full_name, email, password_hash, role, is_active)
                             VALUES (?, ?, ?, ?, 1)'
                        );
                        $insert->execute([$fullName, $email, $passwordHash, 'ADMIN']);
                        $created = true;
                        $adminExists = true;
                    }
                } catch (PDOException $exception) {
                    error_log('First-admin setup failed: ' . $exception->getMessage());
                    if ((string) $exception->getCode() === '23000') {
                        $errors[] = 'An account with this email already exists.';
                    } else {
                        $errors[] = 'The administrator account could not be created. Please try again.';
                    }
                } finally {
                    if ($lockHeld) {
                        $release = $pdo->query("SELECT RELEASE_LOCK('digital_canteen_first_admin')");
                        $release->closeCursor();
                    }
                }
            }
        }
    }
}

$pageTitle = 'Create the first administrator';
$robotsNoIndex = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="form-card">
    <p class="eyebrow">Local setup</p>
    <h1>Create the first administrator</h1>

    <?php if ($created): ?>
        <div class="alert alert-success" role="status">
            The administrator account has been created. Log in with the email and password you just chose.
        </div>
        <p>Delete this file now, so it cannot be opened again:</p>
        <p><code>C:\xampp\htdocs\digital_canteen\setup\create_first_admin.php</code></p>
        <p>The page also stops working once an administrator exists, but the file should still be removed.</p>
        <p><a class="button" href="<?= e(url('login.php')) ?>">Log in</a></p>
    <?php elseif ($adminExists): ?>
        <div class="alert alert-error" role="alert">
            An administrator account already exists. This setup page will not create another one.
        </div>
        <p>Delete this file:</p>
        <p><code>C:\xampp\htdocs\digital_canteen\setup\create_first_admin.php</code></p>
        <p><a class="button" href="<?= e(url('login.php')) ?>">Log in</a></p>
    <?php elseif (!$databaseReady): ?>
        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <p>Use this page once, on this computer, before any administrator exists. Choose the password here. It is not written in the project files.</p>
        <p>Afterward, delete <code>setup/create_first_admin.php</code>.</p>

        <?php if ($errors !== []): ?>
            <div class="alert alert-error" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('setup/create_first_admin.php')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" type="text" maxlength="100" autocomplete="name" required value="<?= e($fullName) ?>">
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" maxlength="255" autocomplete="username" required value="<?= e($email) ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" maxlength="72" autocomplete="new-password" required>
                <p class="hint">Use 8 to 72 characters.</p>
            </div>
            <div class="field">
                <label for="confirm_password">Confirm password</label>
                <input id="confirm_password" name="confirm_password" type="password" maxlength="72" autocomplete="new-password" required>
            </div>
            <button class="button" type="submit">Create administrator</button>
        </form>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
