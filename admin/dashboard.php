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
<section class="hero-banner">
    <p class="eyebrow">CampusBite &bull; Administrator</p>
    <h1>Hello, <?= e($user['full_name']) ?></h1>
    <p class="lede">Manage CampusBite menu categories, food items, pricing, and kitchen staff accounts from one place.</p>
    <div class="actions">
        <a class="button" href="<?= e(url('admin/foods.php')) ?>">Manage food items</a>
        <a class="button button-secondary" href="<?= e(url('admin/categories.php')) ?>">Manage categories</a>
    </div>
</section>

<section class="quick-grid section-gap" aria-label="Administration tools">
    <a class="quick-card" href="<?= e(url('admin/categories.php')) ?>">
        <span class="quick-card-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"/>
                <rect x="14" y="3" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/>
            </svg>
        </span>
        <h2>Categories</h2>
        <p>Organize the menu into sections and control category visibility.</p>
    </a>
    <a class="quick-card" href="<?= e(url('admin/foods.php')) ?>">
        <span class="quick-card-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/>
                <path d="M7 2v20"/>
                <path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>
            </svg>
        </span>
        <h2>Food Items</h2>
        <p>Add dishes, update prices, and toggle live availability.</p>
    </a>
    <a class="quick-card" href="<?= e(url('admin/staff.php')) ?>">
        <span class="quick-card-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </span>
        <h2>Kitchen Staff</h2>
        <p>Create and review kitchen staff accounts for order handling.</p>
    </a>
</section>

<section class="panel section-gap">
    <h2>Account details</h2>
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
