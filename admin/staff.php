<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

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
                    $existing = db()->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
                    $existing->execute([$email]);

                    if ($existing->fetchColumn() !== false) {
                        $errors[] = 'An account with this email already exists.';
                    } else {
                        $insert = db()->prepare(
                            'INSERT INTO users (full_name, email, password_hash, role, is_active)
                             VALUES (?, ?, ?, ?, 1)'
                        );
                        $insert->execute([$fullName, $email, $passwordHash, 'KITCHEN_STAFF']);
                        set_flash('success', 'Kitchen staff account created for ' . $fullName . '.');
                        redirect('admin/staff.php');
                    }
                } catch (PDOException $exception) {
                    error_log('Kitchen staff creation failed: ' . $exception->getMessage());
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

$staff = [];
try {
    $staff = db()->query(
        "SELECT full_name, email, is_active, created_at
         FROM users
         WHERE role = 'KITCHEN_STAFF'
         ORDER BY created_at DESC, id DESC
         LIMIT 200"
    )->fetchAll();
} catch (PDOException $exception) {
    error_log('Kitchen staff list failed: ' . $exception->getMessage());
}

$pageTitle = 'Kitchen staff accounts';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <p class="eyebrow">Administrator</p>
    <h1>Kitchen staff accounts</h1>
    <p>Only administrators can create kitchen staff accounts. Choose a password and give it to the staff member in person. They should not share it.</p>
    <p><a href="<?= e(url('admin/dashboard.php')) ?>">&larr; Back to dashboard</a></p>
</section>

<section class="form-card section-gap">
    <h2>Create a kitchen staff account</h2>

    <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/staff.php')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="full_name">Full name</label>
            <input id="full_name" name="full_name" type="text" maxlength="100" autocomplete="off" required value="<?= e($fullName) ?>">
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="255" autocomplete="off" required value="<?= e($email) ?>">
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
        <button class="button" type="submit">Create staff account</button>
    </form>
</section>

<section class="panel section-gap">
    <h2>Existing kitchen staff</h2>
    <?php if ($staff === []): ?>
        <p class="empty-state">No kitchen staff accounts yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Status</th><th>Created</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $member): ?>
                        <tr>
                            <td data-label="Name"><?= e((string) $member['full_name']) ?></td>
                            <td data-label="Email"><?= e((string) $member['email']) ?></td>
                            <td data-label="Status">
                                <?php if ((int) $member['is_active'] === 1): ?>
                                    <span class="badge badge-on">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-off">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Created"><?= e(date('d M Y', strtotime((string) $member['created_at']) ?: 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
