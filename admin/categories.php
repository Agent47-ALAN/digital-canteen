<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$pageTitle = 'Manage categories';
require __DIR__ . '/../includes/header.php';
?>
<div id="app"
     data-csrf="<?= e(csrf_token()) ?>"
     data-api="<?= e(url('api/admin/categories.php')) ?>"
     data-login="<?= e(url('login.php')) ?>">
    <section class="panel">
        <p class="eyebrow">Administrator</p>
        <h1>Categories</h1>
        <p>Group the menu into sections such as breakfast or drinks. A deactivated category and all its items are hidden from customers.</p>
        <p><a href="<?= e(url('admin/dashboard.php')) ?>">&larr; Back to dashboard</a></p>
    </section>

    <div id="status" class="alert" role="status" aria-live="polite" hidden></div>

    <section class="panel section-gap">
        <h2 id="form-title">Add a category</h2>
        <form id="category-form" class="admin-form" novalidate>
            <input type="hidden" id="category-id" value="">
            <div class="field">
                <label for="name">Name</label>
                <input id="name" type="text" maxlength="100" required autocomplete="off">
            </div>
            <div class="field">
                <label for="description">Description (optional)</label>
                <input id="description" type="text" maxlength="255" autocomplete="off">
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="sort_order">Display order</label>
                    <input id="sort_order" type="number" min="0" max="9999" step="1" value="0">
                    <p class="hint">Smaller numbers appear first.</p>
                </div>
                <div class="field field-check">
                    <input id="is_active" type="checkbox" checked>
                    <label for="is_active">Active (visible to customers)</label>
                </div>
            </div>
            <div class="actions">
                <button id="save-button" class="button" type="submit">Add category</button>
                <button id="cancel-button" class="button button-secondary" type="button" hidden>Cancel editing</button>
            </div>
        </form>
    </section>

    <section class="panel section-gap">
        <h2>All categories</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Category</th><th>Order</th><th>Items</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody id="category-rows">
                    <tr><td colspan="5" class="empty-cell">Loading categories&hellip;</td></tr>
                </tbody>
            </table>
        </div>
        <noscript><p class="alert alert-error">JavaScript is required to manage categories.</p></noscript>
    </section>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/admin-categories.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
