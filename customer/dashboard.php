<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CUSTOMER');

$user = current_user();
if ($user === null) {
    redirect('login.php');
}

$pageTitle = 'Customer dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <p class="eyebrow">Customer</p>
    <h1>Hello, <?= e($user['full_name']) ?></h1>
    <p>You are signed in. You can browse the menu now. The cart and order tracking will be added in later phases.</p>
    <div class="actions">
        <a class="button" href="<?= e(url('customer/menu.php')) ?>">Browse the menu</a>
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
