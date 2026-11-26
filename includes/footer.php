<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

$footerUser = $currentUser ?? null;
$footerIsCustomer = $footerUser && ($footerUser['role'] ?? '') === 'CUSTOMER';
$footerCartCount = (int) ($cartItemCount ?? 0);
$footerScript = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
?>
    </main>
    <footer class="site-footer">
        <div class="shell">
            <p><strong>CampusBite</strong> &mdash; Eat &bull; Connect &bull; Belong.</p>
        </div>
    </footer>
    <?php if ($footerIsCustomer): ?>
    <nav class="bottom-nav" aria-label="Mobile navigation">
        <a class="bottom-nav-item <?= str_ends_with($footerScript, '/customer/dashboard.php') ? 'is-active' : '' ?>" href="<?= e(url('customer/dashboard.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span>Home</span>
        </a>
        <a class="bottom-nav-item <?= str_ends_with($footerScript, '/customer/menu.php') ? 'is-active' : '' ?>" href="<?= e(url('customer/menu.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/>
                <path d="M7 2v20"/>
                <path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>
            </svg>
            <span>Menu</span>
        </a>
        <a class="bottom-nav-item <?= (str_ends_with($footerScript, '/customer/cart.php') || str_ends_with($footerScript, '/customer/checkout.php')) ? 'is-active' : '' ?>" href="<?= e(url('customer/cart.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <span class="cart-count" data-cart-count<?= $footerCartCount > 0 ? '' : ' hidden' ?>><?= e((string) $footerCartCount) ?></span>
            <span>Cart</span>
        </a>
        <a class="bottom-nav-item <?= (str_ends_with($footerScript, '/customer/orders.php') || str_ends_with($footerScript, '/customer/order.php') || str_ends_with($footerScript, '/customer/order-success.php')) ? 'is-active' : '' ?>" href="<?= e(url('customer/orders.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            <span>Orders</span>
        </a>
        <a class="bottom-nav-item" href="<?= e(url('customer/dashboard.php#account-details')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            <span>Profile</span>
        </a>
    </nav>
    <?php endif; ?>
</body>
</html>
