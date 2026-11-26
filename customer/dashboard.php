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
<section class="hero-banner">
    <p class="eyebrow">CampusBite &bull; Customer</p>
    <h1>Hello, <?= e($user['full_name']) ?></h1>
    <p class="lede">Order fresh meals on CampusBite, track your token and estimated ready time live, and collect your food when it is ready.</p>
    <div class="actions">
        <a class="button" href="<?= e(url('customer/menu.php')) ?>">Browse the menu</a>
        <a class="button button-secondary" href="<?= e(url('customer/orders.php')) ?>">My orders</a>
    </div>
</section>

<section class="quick-grid section-gap" aria-label="Quick actions">
    <a class="quick-card" href="<?= e(url('customer/menu.php')) ?>">
        <span class="quick-card-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/>
                <path d="M7 2v20"/>
                <path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>
            </svg>
        </span>
        <h2>Today&rsquo;s Menu</h2>
        <p>Explore dishes, filter by category, and add items to your cart.</p>
    </a>
    <a class="quick-card" href="<?= e(url('customer/cart.php')) ?>">
        <span class="quick-card-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
        </span>
        <h2>Your Cart</h2>
        <p>Review quantities, check prices, and proceed to checkout.</p>
    </a>
    <a class="quick-card" href="<?= e(url('customer/orders.php')) ?>">
        <span class="quick-card-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
        </span>
        <h2>Orders &amp; Tokens</h2>
        <p>Track live preparation status and view your token numbers.</p>
    </a>
</section>

<section id="account-details" class="panel section-gap">
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
