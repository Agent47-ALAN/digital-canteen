<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$currentUser = current_user();
if ($currentUser) {
    redirect(home_path_for_role($currentUser['role']) ?? 'login.php');
}

$errors = [];
$email = '';

if (is_post()) {
    if (!csrf_verify()) {
        $errors[] = 'The form expired. Refresh the page and try again.';
    } else {
        $email = normalize_email(post_value('email'));
        $password = post_password('password');

        if ($email === '' || $password === '' || strlen($password) > 72) {
            $errors[] = 'The email or password is incorrect.';
        } else {
            try {
                $statement = db()->prepare(
                    'SELECT id, full_name, email, password_hash, role, is_active
                     FROM users
                     WHERE email = ?
                     LIMIT 1'
                );
                $statement->execute([$email]);
                $account = $statement->fetch();

                $storedHash = is_array($account) ? (string) $account['password_hash'] : null;
                $passwordMatches = password_matches($password, $storedHash);

                if (!$account || !$passwordMatches) {
                    $errors[] = 'The email or password is incorrect.';
                } elseif ((int) $account['is_active'] !== 1) {
                    $errors[] = 'This account is inactive. Ask the canteen administrator for help.';
                } else {
                    $destination = home_path_for_role((string) $account['role']);
                    if ($destination === null || !session_regenerate_id(true)) {
                        $errors[] = 'Login could not be completed. Please try again.';
                    } else {
                        $_SESSION['user_id'] = (int) $account['id'];
                        unset($_SESSION['csrf_token']);
                        redirect($destination);
                    }
                }
            } catch (PDOException $exception) {
                error_log('Login failed: ' . $exception->getMessage());
                $errors[] = 'Login could not be completed. Please try again.';
            }
        }
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<section class="form-card">
    <p class="eyebrow">Account</p>
    <h1>Log in</h1>
    <p>Use the email and password for your canteen account. You will be taken to the page for your role.</p>

    <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('login.php')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="255" autocomplete="username" required value="<?= e($email) ?>">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" maxlength="72" autocomplete="current-password" required>
        </div>
        <button class="button" type="submit">Log in</button>
    </form>
    <p class="form-note">Need a customer account? <a href="<?= e(url('register.php')) ?>">Create one</a>.</p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
