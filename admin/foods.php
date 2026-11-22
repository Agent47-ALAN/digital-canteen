<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$pageTitle = 'Manage food items';
require __DIR__ . '/../includes/header.php';
?>
<div id="app"
     data-csrf="<?= e(csrf_token()) ?>"
     data-api="<?= e(url('api/admin/foods.php')) ?>"
     data-login="<?= e(url('login.php')) ?>">
    <section class="panel">
        <p class="eyebrow">Administrator</p>
        <h1>Food items</h1>
        <p>Add dishes, set prices, and control what customers can see. &ldquo;Unavailable&rdquo; items stay on the menu but are marked as not available right now.</p>
        <p><a href="<?= e(url('admin/dashboard.php')) ?>">&larr; Back to dashboard</a> &middot; <a href="<?= e(url('admin/categories.php')) ?>">Manage categories</a></p>
    </section>

    <div id="status" class="alert" role="status" aria-live="polite" hidden></div>

    <section class="panel section-gap">
        <h2 id="form-title">Add a food item</h2>
        <form id="food-form" class="admin-form" novalidate>
            <input type="hidden" id="food-id" value="">
            <div class="field-row">
                <div class="field">
                    <label for="name">Name</label>
                    <input id="name" type="text" maxlength="150" required autocomplete="off">
                </div>
                <div class="field">
                    <label for="category_id">Category</label>
                    <select id="category_id" required>
                        <option value="">Loading&hellip;</option>
                    </select>
                </div>
            </div>
            <div class="field">
                <label for="description">Description (optional)</label>
                <input id="description" type="text" maxlength="500" autocomplete="off">
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="price">Price (&#8377;)</label>
                    <input id="price" type="text" inputmode="decimal" maxlength="8" placeholder="e.g. 45.50" autocomplete="off">
                </div>
                <div class="field">
                    <div class="field-check">
                        <input id="is_available" type="checkbox" checked>
                        <label for="is_available">Available now</label>
                    </div>
                    <div class="field-check">
                        <input id="is_active" type="checkbox" checked>
                        <label for="is_active">Active (shown on the menu)</label>
                    </div>
                </div>
            </div>
            <div class="actions">
                <button id="save-button" class="button" type="submit">Add item</button>
                <button id="cancel-button" class="button button-secondary" type="button" hidden>Cancel editing</button>
            </div>
        </form>
    </section>

    <section class="panel section-gap">
        <h2>All food items</h2>
        <form id="filter-form" class="toolbar" role="search">
            <div class="field">
                <label for="filter-q">Search</label>
                <input id="filter-q" type="search" maxlength="100" placeholder="Search name or description" autocomplete="off">
            </div>
            <div class="field">
                <label for="filter-category">Category</label>
                <select id="filter-category"><option value="">All categories</option></select>
            </div>
        </form>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Item</th><th>Category</th><th>Price</th><th>Availability</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody id="food-rows">
                    <tr><td colspan="6" class="empty-cell">Loading food items&hellip;</td></tr>
                </tbody>
            </table>
        </div>
        <noscript><p class="alert alert-error">JavaScript is required to manage food items.</p></noscript>
    </section>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/admin-foods.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
