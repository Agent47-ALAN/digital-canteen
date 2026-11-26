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
<section class="hero-banner">
    <p class="eyebrow">CampusBite &bull; Kitchen</p>
    <h1>Hello, <?= e($user['full_name']) ?></h1>
    <p class="lede">Live CampusBite order queue. New orders appear automatically and refresh every 10 seconds.</p>
</section>
<section class="panel section-gap">
    <dl class="details" style="margin-top:0">
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
<div id="app"
     data-csrf="<?= e(csrf_token()) ?>"
     data-api="<?= e(url('api/kitchen/orders.php')) ?>"
     data-login="<?= e(url('login.php')) ?>">
    <div class="kitchen-toolbar section-gap">
        <p id="last-updated" class="live-note" aria-live="off">Loading orders&hellip;</p>
        <button id="refresh-button" class="button button-secondary button-small" type="button">Refresh now</button>
    </div>

    <div id="status" class="alert" role="status" aria-live="polite" hidden></div>

    <div class="kitchen-board" aria-label="Order queue">
        <section class="kitchen-col" aria-labelledby="col-placed">
            <h2 id="col-placed">New <span class="count-pill" data-count="PLACED">0</span></h2>
            <div class="kitchen-list" data-list="PLACED"><p class="empty-state">Loading&hellip;</p></div>
        </section>
        <section class="kitchen-col" aria-labelledby="col-preparing">
            <h2 id="col-preparing">Preparing <span class="count-pill" data-count="PREPARING">0</span></h2>
            <div class="kitchen-list" data-list="PREPARING"><p class="empty-state">Loading&hellip;</p></div>
        </section>
        <section class="kitchen-col" aria-labelledby="col-ready">
            <h2 id="col-ready">Ready <span class="count-pill" data-count="READY">0</span></h2>
            <div class="kitchen-list" data-list="READY"><p class="empty-state">Loading&hellip;</p></div>
        </section>
    </div>
    <p id="more-note" class="hint" hidden>Showing the newest 100 active orders.</p>

    <section class="panel section-gap">
        <h2>Collected orders <span class="count-pill" data-count="COLLECTED">0</span></h2>
        <p class="hint">Orders that have been handed over. Newest first.</p>
        <div class="actions">
            <button id="toggle-collected" class="button button-secondary" type="button" aria-expanded="false">Show collected orders</button>
        </div>
        <div id="collected-box" hidden>
            <div id="collected-list" class="kitchen-list kitchen-list-wide"></div>
            <div class="actions">
                <button id="collected-more" class="button button-secondary" type="button" hidden>Load more</button>
            </div>
        </div>
    </section>
    <noscript><p class="alert alert-error">JavaScript is required to use the kitchen queue.</p></noscript>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/kitchen-orders.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
