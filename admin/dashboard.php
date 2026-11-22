<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$user = current_user();
if ($user === null) {
    redirect('login.php');
}

$pageTitle = 'Administration';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <p class="eyebrow">Administrator</p>
    <h1>Hello, <?= e($user['full_name']) ?></h1>
    <p>You are signed in as an administrator. Manage the menu and kitchen staff below. Reports will be added in a later phase.</p>
    <div class="actions">
        <a class="button" href="<?= e(url('admin/categories.php')) ?>">Manage categories</a>
        <a class="button" href="<?= e(url('admin/foods.php')) ?>">Manage food items</a>
        <a class="button button-secondary" href="<?= e(url('admin/staff.php')) ?>">Kitchen staff accounts</a>
    </div>
    <dl class="details">
        <div>
            <dt>Name</dt>
            <dd><?= e($user['full_name']) ?></dd>
        </div>
        <div>
            <dt>Email</dt>
            <dd><?= e($user['email']) ?></dd>
        </div>
        <div>
            <dt>Role</dt>
            <dd><?= e(role_label($user['role'])) ?></dd>
        </div>
    </dl>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
