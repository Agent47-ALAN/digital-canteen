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

    var list = document.getElementById('order-list');
    var statusBox = document.getElementById('status');
    var loadMore = document.getElementById('load-more');
    var page = 1;
    var loadedAny = false;

    function badge(text, kind) {
        return DC.el('span', { className: 'badge badge-' + kind, text: text });
    }

    function paymentKind(order) {
        return order.payment_status === 'PAID' ? 'on' : 'warn';
    }

    function card(order) {
        var link = DC.el('a', {
            className: 'button button-secondary button-small',
            href: detailUrl + '?id=' + encodeURIComponent(order.id),
            text: 'View order'
        });

        return DC.el('article', { className: 'order-card' }, [
            DC.el('div', { className: 'order-card-head' }, [
                DC.el('p', { className: 'order-token', text: order.token_number }),
                badge(order.status_label, 'on')
            ]),
            DC.el('p', { className: 'order-meta', text: order.placed_at }),
            DC.el('div', { className: 'order-lines' }, [
                DC.el('span', { text: order.item_count + (order.item_count === 1 ? ' item' : ' items') }),
                DC.el('strong', { text: DC.money(order.total_amount) })
            ]),
            DC.el('div', { className: 'order-pay' }, [
                DC.el('span', { text: order.payment_method_label }),
                badge(order.payment_status_label, paymentKind(order))
            ]),
            link
        ]);
    }

    function load() {
        loadMore.disabled = true;
        list.setAttribute('aria-busy', 'true');

        DC.request('GET', api, { page: page }, function (result) {
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

    loadMore.addEventListener('click', function () {
        page += 1;
        load();
    });

    load();
}());
