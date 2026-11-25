(function () {
    'use strict';

    var app = document.getElementById('app');
    if (!app || !window.DC) {
        return;
    }

    var DC = window.DC;
    var api = app.getAttribute('data-api');
    var detailUrl = app.getAttribute('data-detail');
    var loginUrl = app.getAttribute('data-login');

    var POLL_MS = 10000;

    var list = document.getElementById('order-list');
    var statusBox = document.getElementById('status');
    var loadMore = document.getElementById('load-more');
    var page = 1;
    var loadedAny = false;
    var loading = false;
    var refreshing = false;

    function badge(text, kind) {
        return DC.el('span', { className: 'badge badge-' + kind, text: text });
    }

    function card(order) {
        var link = DC.el('a', {
            className: 'button button-secondary button-small',
            href: detailUrl + '?id=' + encodeURIComponent(order.id),
            text: 'View order'
        });

        var statusBadge = badge(order.status_label, DC.statusKind(order.status));
        statusBadge.setAttribute('data-status-badge', '');
        var paymentBadge = badge(order.payment_status_label, DC.paymentKind(order.payment_status));
        paymentBadge.setAttribute('data-payment-badge', '');

        return DC.el('article', { className: 'order-card', 'data-order-id': String(order.id) }, [
            DC.el('div', { className: 'order-card-head' }, [
                DC.el('p', { className: 'order-token', text: order.token_number }),
                statusBadge
            ]),
            DC.el('p', { className: 'order-meta', text: order.placed_at }),
            DC.el('div', { className: 'order-lines' }, [
                DC.el('span', { text: order.item_count + (order.item_count === 1 ? ' item' : ' items') }),
                DC.el('strong', { text: DC.money(order.total_amount) })
            ]),
            DC.el('div', { className: 'order-pay' }, [
                DC.el('span', { text: order.payment_method_label }),
                paymentBadge
            ]),
            link
        ]);
    }

    function load() {
        loading = true;
        loadMore.disabled = true;
        list.setAttribute('aria-busy', 'true');

        DC.request('GET', api, { page: page }, function (result) {
            loading = false;
            list.setAttribute('aria-busy', 'false');
            loadMore.disabled = false;

            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }
            if (!result.success) {
                DC.showStatus(statusBox, 'error', result.message);
                if (!loadedAny) {
                    list.textContent = '';
                }
                return;
            }

            if (!loadedAny) {
                list.textContent = '';
            }
            loadedAny = true;

            result.data.orders.forEach(function (order) {
                list.appendChild(card(order));
            });

            if (list.children.length === 0) {
                list.appendChild(DC.el('p', { className: 'empty-state', text: 'You have not placed any orders yet.' }));
            }
            loadMore.hidden = !result.data.has_more;
        });
    }

    /* Keep the statuses of the orders already on screen up to date. Each page that is
       loaded is asked for again in turn, and only the two badges of each card change. */
    function updateCard(order) {
        var node = list.querySelector('[data-order-id="' + order.id + '"]');
        if (!node) {
            return;
        }
        var statusBadge = node.querySelector('[data-status-badge]');
        statusBadge.textContent = order.status_label;
        statusBadge.className = 'badge badge-' + DC.statusKind(order.status);

        var paymentBadge = node.querySelector('[data-payment-badge]');
        paymentBadge.textContent = order.payment_status_label;
        paymentBadge.className = 'badge badge-' + DC.paymentKind(order.payment_status);
    }

    function refreshPage(number, last) {
        DC.request('GET', api, { page: number }, function (result) {
            if (DC.sessionEnded(result, loginUrl)) {
                refreshing = false;
                return;
            }
            if (result.success) {
                result.data.orders.forEach(updateCard);
            }
            if (number < last && result.success) {
                refreshPage(number + 1, last);
            } else {
                refreshing = false;
            }
        });
    }

    window.setInterval(function () {
        if (document.hidden || loading || refreshing || !loadedAny) {
            return;
        }
        refreshing = true;
        refreshPage(1, page);
    }, POLL_MS);

    loadMore.addEventListener('click', function () {
        page += 1;
        load();
    });

    load();
}());
