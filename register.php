<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$currentUser = current_user();
if ($currentUser) {
    redirect(home_path_for_role($currentUser['role']) ?? 'login.php');
}

$errors = [];
$fullName = '';
$email = '';

if (is_post()) {
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
                $errors[] = 'The account could not be created. Please try again.';
            } else {
                try {
                    $existing = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                    $existing->execute([$email]);

                    if ($existing->fetch()) {
                        $errors[] = 'An account with this email already exists.';
                    } else {
                        // Public registration is always a customer. Any posted role is ignored.
                        $insert = db()->prepare(
                            'INSERT INTO users (full_name, email, password_hash, role)
                             VALUES (?, ?, ?, ?)'
                        );
                        $insert->execute([$fullName, $email, $passwordHash, 'CUSTOMER']);
                        set_flash('success', 'Your account has been created. You can log in now.');
                        redirect('login.php');
                    }
                } catch (PDOException $exception) {
                    error_log('Customer registration failed: ' . $exception->getMessage());
                    if ((string) $exception->getCode() === '23000') {
                        $errors[] = 'An account with this email already exists.';
                    } else {
                        $errors[] = 'The account could not be created. Please try again.';
                    }
                }
            }
        }
    }
}

$pageTitle = 'Create an account';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
<section class="form-card">
    <div class="auth-logo-wrap">
        <img class="auth-logo" src="<?= e(url('assets/branding/campusbite-logo.svg')) ?>" alt="CampusBite — Eat, Connect, Belong" width="210" height="102">
    </div>
    <p class="eyebrow">Customer account</p>
    <h1>Create an account</h1>
    <p>Students and college staff register here for CampusBite. This page cannot create a kitchen or administrator account.</p>

    <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('register.php')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="full_name">Full name</label>
            <input id="full_name" name="full_name" type="text" maxlength="100" autocomplete="name" required value="<?= e($fullName) ?>">
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="255" autocomplete="email" required value="<?= e($email) ?>">
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
        <button class="button" type="submit">Create account</button>
    </form>
    <p class="form-note">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a>.</p>
</section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
