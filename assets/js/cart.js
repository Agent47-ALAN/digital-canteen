(function () {
    'use strict';

    var app = document.getElementById('app');
    if (!app || !window.DC) {
        return;
    }

    var DC = window.DC;
    var api = app.getAttribute('data-api');
    var csrf = app.getAttribute('data-csrf');
    var loginUrl = app.getAttribute('data-login');
    var MAX_QTY = 20;

    var statusBox = document.getElementById('status');
    var filled = document.getElementById('cart-filled');
    var empty = document.getElementById('cart-empty');
    var loading = document.getElementById('cart-loading');
    var rows = document.getElementById('cart-rows');
    var totalCell = document.getElementById('cart-total');
    var checkoutLink = document.getElementById('checkout-link');
    var checkoutBlocked = document.getElementById('checkout-blocked');

    function render(cart) {
        loading.hidden = true;
        DC.setCartCount(cart.count);

        if (!cart.lines || cart.lines.length === 0) {
            filled.hidden = true;
            empty.hidden = false;
            return;
        }

        empty.hidden = true;
        filled.hidden = false;
        rows.textContent = '';

        cart.lines.forEach(function (line) {
            var broken = !!line.problem;

            var minus = DC.el('button', {
                type: 'button',
                className: 'button button-secondary button-small qty-button',
                'aria-label': 'Decrease quantity of ' + line.name,
                text: '\u2212'
            });
            minus.disabled = broken || line.quantity <= 1;
            minus.addEventListener('click', function () { change(line.food_item_id, line.quantity - 1, minus); });

            var plus = DC.el('button', {
                type: 'button',
                className: 'button button-secondary button-small qty-button',
                'aria-label': 'Increase quantity of ' + line.name,
                text: '+'
            });
            plus.disabled = broken || line.quantity >= MAX_QTY;
            plus.addEventListener('click', function () { change(line.food_item_id, line.quantity + 1, plus); });

            var removeButton = DC.el('button', {
                type: 'button',
                className: 'button button-secondary button-small',
                'aria-label': 'Remove ' + line.name + ' from the cart',
                text: 'Remove'
            });
            removeButton.addEventListener('click', function () { remove(line.food_item_id, removeButton); });

            var nameCell = DC.el('td', { 'data-label': 'Item' }, [
                DC.el('strong', { text: line.name }),
                broken ? DC.el('span', { className: 'cell-sub problem-text', text: line.problem + ' Remove it to continue.' }) : null
            ]);

            var tr = DC.el('tr', { className: broken ? 'is-problem' : '' }, [
                nameCell,
                DC.el('td', { 'data-label': 'Unit price', text: broken ? '-' : DC.money(line.unit_price) }),
                DC.el('td', { 'data-label': 'Quantity' }, [
                    DC.el('div', { className: 'qty-control' }, [
                        minus,
                        DC.el('span', { className: 'qty-value', text: String(line.quantity) }),
                        plus
                    ])
                ]),
                DC.el('td', { 'data-label': 'Subtotal', text: broken ? '-' : DC.money(line.subtotal) }),
                DC.el('td', { 'data-label': 'Remove' }, [removeButton])
            ]);
            rows.appendChild(tr);
        });

        totalCell.textContent = DC.money(cart.total);
        checkoutLink.hidden = !cart.can_checkout;
        checkoutBlocked.hidden = cart.can_checkout;

        if (cart.has_issues) {
            DC.showStatus(statusBox, 'error', 'Some items are no longer available. Remove them to continue to checkout.');
        }
    }

    function load() {
        DC.request('GET', api, {}, function (result) {
            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }
            if (!result.success) {
                loading.hidden = true;
                DC.showStatus(statusBox, 'error', result.message);
                return;
            }
            render(result.data.cart);
        });
    }

    function send(params, button) {
        if (button) {
            button.disabled = true;
        }
        DC.hideStatus(statusBox);
        params.csrf_token = csrf;

        DC.request('POST', api, params, function (result) {
            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }
            if (result.success) {
                render(result.data.cart);
            } else {
                DC.showStatus(statusBox, 'error', result.message);
                load();
            }
        });
    }

    function change(foodId, quantity, button) {
        send({ action: 'set', food_item_id: foodId, quantity: quantity }, button);
    }

    function remove(foodId, button) {
        send({ action: 'remove', food_item_id: foodId }, button);
    }

    load();
}());
