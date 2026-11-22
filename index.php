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
<section class="hero">
    <p class="eyebrow">College canteen</p>
    <h1>Order food from the canteen and collect it with a token.</h1>
    <p class="lede">Students and staff use one customer account. The kitchen prepares the order, and you collect it from the counter when it is ready.</p>
    <p>Registration, login, and the menu are ready. Ordering is added in the next part of the project.</p>
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
            <p>Browse what the canteen is serving and place an order.</p>
        </li>
        <li>
            <h3>Keep the token</h3>
            <p>Every order receives one token number.</p>
        </li>
        <li>
            <h3>Collect at the counter</h3>
            <p>Pick up the food when the kitchen marks the token ready.</p>
        </li>
    </ol>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
