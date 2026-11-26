<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$currentUser = current_user();
if ($currentUser) {
    redirect(home_path_for_role($currentUser['role']) ?? 'login.php');
}

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero hero-banner">
    <div class="hero-brand-wrap">
        <img class="hero-brand-logo" src="<?= e(url('assets/branding/campusbite-logo-white.svg')) ?>" alt="CampusBite — Eat, Connect, Belong" width="250" height="122">
    </div>
    <p class="eyebrow">CampusBite &bull; Eat &bull; Connect &bull; Belong</p>
    <h1>Order food from the canteen and collect it with a token.</h1>
    <p class="lede">Students and staff use one CampusBite account. The kitchen prepares your order live, and you collect it from the counter when your token is ready.</p>
    <div class="actions">
        <a class="button" href="<?= e(url('register.php')) ?>">Create an account</a>
        <a class="button button-secondary" href="<?= e(url('login.php')) ?>">Log in</a>
    </div>
</section>

<section class="steps">
    <h2>How a canteen order works</h2>
    <ol>
        <li>
            <h3>Choose the food</h3>
            <p>Browse what the canteen is serving and add dishes to your cart.</p>
        </li>
        <li>
            <h3>Keep the token</h3>
            <p>Every confirmed order receives a unique token number and live status tracker.</p>
        </li>
        <li>
            <h3>Collect at the counter</h3>
            <p>Pick up your meal when the kitchen marks your token ready.</p>
        </li>
    </ol>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
