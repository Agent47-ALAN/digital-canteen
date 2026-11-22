<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('KITCHEN_STAFF');

$user = current_user();
if ($user === null) {
    redirect('login.php');
}

$pageTitle = 'Kitchen dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <p class="eyebrow">Kitchen</p>
    <h1>Hello, <?= e($user['full_name']) ?></h1>
    <p>You are signed in as kitchen staff. The order queue will be added in a later phase.</p>
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
