
/* Kitchen order queue: loads orders with XMLHttpRequest (through DC.request), draws the cards
   with safe DOM calls (textContent only), moves orders one step at a time and polls for changes. */
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

    var POLL_MS = 10000;
    var ACTIVE_LIMIT = 100;
    var COLLECTED_LIMIT = 20;
    var COLUMNS = ['PLACED', 'PREPARING', 'READY'];
    var EMPTY_TEXT = {
        PLACED: 'No new orders right now.',
        PREPARING: 'Nothing is being prepared.',
        READY: 'No orders waiting for collection.'
    };

    var statusBox = document.getElementById('status');
    var lastUpdated = document.getElementById('last-updated');
    var refreshButton = document.getElementById('refresh-button');
    var moreNote = document.getElementById('more-note');
    var toggleCollected = document.getElementById('toggle-collected');
    var collectedBox = document.getElementById('collected-box');
    var collectedList = document.getElementById('collected-list');
    var collectedMore = document.getElementById('collected-more');

    var busy = {};              // order id -> true while a status update is running
    var paymentBusy = {};       // order id -> true while cash confirmation is running
    var knownIds = null;        // ids seen so far (null until the first load finishes)
    var lastSignature = '';
    var inFlight = false;
    var queued = false;
    var connectionProblem = false;
    var collectedOpen = false;
    var collectedPage = 1;
    var collectedBusy = false;
    var collectedRefreshQueued = false;
    var collectedCountSeen = null;

    /* ------------------------------------------------------------ drawing */

    function badge(text, kind) {
        return DC.el('span', { className: 'badge badge-' + kind, text: text });
    }

    function waitingText(minutes) {
        if (minutes < 1) {
            return 'just now';
        }
        if (minutes < 60) {
            return minutes + ' min';
        }
        return Math.floor(minutes / 60) + ' h ' + (minutes % 60) + ' min';
    }

    function card(order, showAction) {
        var items = DC.el('ul', { className: 'kitchen-items' }, order.items.map(function (item) {
            return DC.el('li', {}, [
                DC.el('span', { className: 'kq', text: item.quantity + '\u00D7' }),
                DC.el('span', { className: 'kn', text: item.name }),
                DC.el('span', { className: 'ku', text: DC.money(item.unit_price) + ' each' }),
                DC.el('strong', { className: 'kl', text: DC.money(item.line_total) })
            ]);
        }));

        var timeLine = 'Placed ' + order.placed_at;
        if (showAction) {
            timeLine += ' \u00B7 waiting ' + waitingText(order.age_minutes);
        } else {
            timeLine += ' \u00B7 collected ' + order.updated_at;
        }

        var parts = [
            DC.el('div', { className: 'kitchen-card-head' }, [
                DC.el('p', { className: 'kitchen-token', text: order.token_number || 'No token' }),
                badge(order.status_label, DC.statusKind(order.status))
            ]),
            DC.el('p', { className: 'kitchen-meta', text: 'Order #' + order.id + ' \u00B7 ' + order.customer_name }),
            DC.el('p', { className: 'kitchen-meta', text: timeLine }),
            items,
            DC.el('div', { className: 'kitchen-total' }, [
                DC.el('span', { text: 'Total' }),
                DC.el('strong', { text: DC.money(order.total_amount) })
            ]),
            DC.el('div', { className: 'kitchen-pay' }, [
                DC.el('span', { text: order.payment_method_label }),
                badge(order.payment_status_label, DC.paymentKind(order.payment_status))
            ])
        ];

        if (order.payment_method === 'CASH' && order.payment_status === 'PENDING') {
            parts.push(DC.el('p', { className: 'kitchen-note', text: 'Cash payment is still pending.' }));
        }

        if (showAction && order.next_status) {
            var button = DC.el('button', {
                type: 'button',
                className: 'button kitchen-action',
                text: order.next_label
            });
            if (busy[order.id]) {
                button.disabled = true;
                button.textContent = 'Updating\u2026';
            }
            button.addEventListener('click', function () { moveOrder(order, button); });
            parts.push(button);
        }

        if (!showAction && order.payment_method === 'CASH' && order.payment_status === 'PENDING') {
            var paymentButton = DC.el('button', {
                type: 'button',
                className: 'button kitchen-action',
                text: 'Confirm Cash Received'
            });

            if (paymentBusy[order.id]) {
                paymentButton.disabled = true;
                paymentButton.textContent = 'Confirming\u2026';
            }

            paymentButton.addEventListener('click', function () {
                confirmCashPayment(order, paymentButton);
            });

            parts.push(paymentButton);
        }

        return DC.el('article', { className: 'kitchen-card', 'data-status': order.status, 'data-order-id': String(order.id) }, parts);
    }

    function renderBoard(orders) {
        COLUMNS.forEach(function (status) {
            var list = document.querySelector('[data-list="' + status + '"]');
            var inColumn = orders.filter(function (order) { return order.status === status; });

            list.textContent = '';
            if (inColumn.length === 0) {
                list.appendChild(DC.el('p', { className: 'empty-state', text: EMPTY_TEXT[status] }));
                return;
            }
            inColumn.forEach(function (order) { list.appendChild(card(order, true)); });
        });
    }

    function setCounts(counts) {
        Object.keys(counts).forEach(function (status) {
            var pill = document.querySelector('[data-count="' + status + '"]');
            if (pill) {
                pill.textContent = String(counts[status]);
            }
        });
    }

    /* ------------------------------------------------------------ loading */

    function load() {
        if (inFlight) {
            queued = true;
            return;
        }
        inFlight = true;
        refreshButton.disabled = true;
        refreshButton.textContent = 'Refreshing\u2026';

        DC.request('GET', api, { limit: ACTIVE_LIMIT }, function (result) {
            inFlight = false;
            refreshButton.disabled = false;
            refreshButton.textContent = 'Refresh now';

            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }

            // A newer load was requested while this one ran (for example after a status
            // change), so this answer may already be out of date. Ask again instead.
            if (queued) {
                queued = false;
                load();
                return;
            }

            if (!result.success) {
                connectionProblem = true;
                lastUpdated.textContent = 'Could not refresh the queue. Trying again\u2026';
                DC.showStatus(statusBox, 'error', result.message);
                return;
            }

            if (connectionProblem) {
                connectionProblem = false;
                DC.hideStatus(statusBox);
            }

            var orders = result.data.orders || [];
            announceNewOrders(orders);
            setCounts(result.data.counts || {});
            moreNote.hidden = !result.data.has_more;

            var signature = JSON.stringify(orders);
            if (signature !== lastSignature) {
                lastSignature = signature;
                renderBoard(orders);
            }
            lastUpdated.textContent = 'Last updated ' + new Date().toLocaleTimeString();

            refreshCollectedIfChanged(result.data.counts || {});
        });
    }

    function announceNewOrders(orders) {
        var fresh = [];

        if (knownIds === null) {
            knownIds = {};
        } else {
            orders.forEach(function (order) {
                if (order.status === 'PLACED' && !knownIds[order.id]) {
                    fresh.push(order.token_number || ('#' + order.id));
                }
            });
        }
        orders.forEach(function (order) { knownIds[order.id] = true; });

        if (fresh.length > 0) {
            DC.showStatus(statusBox, 'success', (fresh.length === 1 ? 'New order ' : 'New orders ') + fresh.join(', ') + ' received.');
        }
    }

    /* ------------------------------------------------------------ status changes */

    function moveOrder(order, button) {
        // One update per order at a time: a second click is ignored.
        if (busy[order.id]) {
            return;
        }
        busy[order.id] = true;
        button.disabled = true;
        button.textContent = 'Updating\u2026';
        DC.hideStatus(statusBox);

        DC.request('POST', api, {
            csrf_token: csrf,
            action: 'update_status',
            order_id: order.id,
            status: order.next_status
        }, function (result) {
            delete busy[order.id];

            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }

            var label = (order.token_number || ('#' + order.id));
            DC.showStatus(statusBox, result.success ? 'success' : 'error',
                label + ': ' + result.message);

            // Success or refusal, show the real state from the server.
            lastSignature = '';
            load();
            if (collectedOpen) {
                loadCollected(true);
            }
        });
    }

    function confirmCashPayment(order, button) {
        // One payment confirmation per order at a time.
        if (paymentBusy[order.id]) {
            return;
        }

        var label = order.token_number || ('#' + order.id);

        if (!window.confirm('Confirm that cash has been received for order ' + label + '?')) {
            return;
        }

        paymentBusy[order.id] = true;
        button.disabled = true;
        button.textContent = 'Confirming\u2026';
        DC.hideStatus(statusBox);

        DC.request('POST', api, {
            csrf_token: csrf,
            action: 'confirm_cash_payment',
            order_id: order.id
        }, function (result) {
            delete paymentBusy[order.id];

            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }

            DC.showStatus(statusBox, result.success ? 'success' : 'error',
                label + ': ' + result.message);

            // Reload collected orders so the latest payment status is displayed.
            loadCollected(true);
        });
    }

    /* ------------------------------------------------------------ collected orders */

    function loadCollected(reset) {
        if (collectedBusy) {
            if (reset) {
                collectedRefreshQueued = true;
            }
            return;
        }
        collectedBusy = true;
        if (reset) {
            collectedPage = 1;
        }

        DC.request('GET', api, { status: 'COLLECTED', limit: COLLECTED_LIMIT, page: collectedPage }, function (result) {
            collectedBusy = false;

            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }

            // A refresh was requested while this request was running.
            // Load page one again to show the latest payment/order details.
            if (collectedRefreshQueued) {
                collectedRefreshQueued = false;
                loadCollected(true);
                return;
            }

            if (!result.success) {
                DC.showStatus(statusBox, 'error', result.message);
                return;
            }

            if (collectedPage === 1) {
                collectedList.textContent = '';
            }
            result.data.orders.forEach(function (order) { collectedList.appendChild(card(order, false)); });
            if (collectedList.children.length === 0) {
                collectedList.appendChild(DC.el('p', { className: 'empty-state', text: 'No collected orders yet.' }));
            }
            collectedMore.hidden = !result.data.has_more;
            collectedCountSeen = result.data.counts ? result.data.counts.COLLECTED : collectedCountSeen;
        });
    }

    function refreshCollectedIfChanged(counts) {
        if (!collectedOpen || typeof counts.COLLECTED !== 'number') {
            return;
        }
        if (collectedCountSeen !== null && counts.COLLECTED !== collectedCountSeen) {
            loadCollected(true);
        }
    }

    toggleCollected.addEventListener('click', function () {
        collectedOpen = !collectedOpen;
        collectedBox.hidden = !collectedOpen;
        toggleCollected.setAttribute('aria-expanded', collectedOpen ? 'true' : 'false');
        toggleCollected.textContent = collectedOpen ? 'Hide collected orders' : 'Show collected orders';
        if (collectedOpen) {
            loadCollected(true);
        }
    });

    collectedMore.addEventListener('click', function () {
        collectedPage += 1;
        loadCollected(false);
    });

    /* ------------------------------------------------------------ polling */

    refreshButton.addEventListener('click', load);

    // Poll in the background. Nothing is requested while the tab is hidden,
    // and the queue is refreshed as soon as the tab is shown again.
    window.setInterval(function () {
        if (!document.hidden) {
            load();
        }
    }, POLL_MS);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            load();
        }
    });

    load();
}());
