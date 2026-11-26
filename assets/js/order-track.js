/* Live order tracking for the customer's order and confirmation pages.
   Polls the existing history API for this one order (the server checks ownership)
   and updates the status on the page without reloading it. */
(function () {
    'use strict';

    var app = document.getElementById('app');
    if (!app || !window.DC || !app.getAttribute('data-order-id')) {
        return;
    }

    var DC = window.DC;
    var api = app.getAttribute('data-api');
    var orderId = app.getAttribute('data-order-id');
    var loginUrl = app.getAttribute('data-login');

    var POLL_MS = 8000;
    var FLOW = ['PLACED', 'PREPARING', 'READY', 'COLLECTED'];

    var badge = app.querySelector('[data-track="status-badge"]');
    var hint = app.querySelector('[data-track="hint"]');
    var note = app.querySelector('[data-track="updated"]');
    var paymentCell = app.querySelector('[data-track="payment-status"]');
    var steps = app.querySelectorAll('[data-step]');

    var timer = null;
    var inFlight = false;

    function isFinished(status) {
        return status === 'COLLECTED' || status === 'CANCELLED';
    }

    function showNote(text) {
        note.hidden = false;
        note.textContent = text;
    }

    function apply(order) {
        badge.textContent = order.status_label;
        badge.className = 'badge badge-' + DC.statusKind(order.status);
        hint.textContent = order.status_hint;
        if (paymentCell) {
            paymentCell.textContent = order.payment_status_label;
        }

        var index = FLOW.indexOf(order.status);
        Array.prototype.forEach.call(steps, function (item) {
            var position = FLOW.indexOf(item.getAttribute('data-step'));
            var state = 'todo';
            if (index !== -1) {
                if (position < index || (position === index && order.status === 'COLLECTED')) {
                    state = 'done';
                } else if (position === index) {
                    state = 'current';
                }
            }
            item.className = 'is-' + state;
            if (state === 'current') {
                item.setAttribute('aria-current', 'step');
            } else {
                item.removeAttribute('aria-current');
            }
        });

        app.setAttribute('data-status', order.status);
    }

    function stop() {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
    }

    function poll() {
        if (inFlight) {
            return;
        }
        inFlight = true;

        DC.request('GET', api, { id: orderId }, function (result) {
            // Always release the lock — even if apply() somehow throws — so
            // the next tick is never permanently blocked.
            try {
                if (DC.sessionEnded(result, loginUrl)) {
                    return;
                }
                if (result.status === 404) {
                    stop();
                    showNote('This order could not be found any more.');
                    return;
                }
                // Guard against null data (500 errors, unexpected server
                // responses) so a bad response is treated as a retryable
                // network hiccup rather than crashing the callback.
                if (!result.success || !result.data || !result.data.order) {
                    showNote('Connection problem. Trying again\u2026');
                    return;
                }

                apply(result.data.order);
                if (isFinished(result.data.order.status)) {
                    stop();
                    showNote('This order is complete, so live updates have stopped.');
                } else {
                    showNote('Status updates automatically. Last checked ' + new Date().toLocaleTimeString() + '.');
                }
            } finally {
                inFlight = false;
            }
        });
    }

    // A finished order never changes again, so there is nothing to poll.
    if (isFinished(app.getAttribute('data-status') || '')) {
        return;
    }

    showNote('Status updates automatically.');

    // Poll immediately on load so the displayed status is never stale from
    // the moment the page was served, then continue on a fixed interval.
    // This is the key fix: without an initial call the first update is delayed
    // by a full POLL_MS (8 s) even if the status changed while navigating.
    poll();

    timer = window.setInterval(function () {
        if (!document.hidden) {
            poll();
        }
    }, POLL_MS);

    // When the customer returns from the background (e.g. switching apps on
    // mobile) fire immediately instead of waiting for the next tick.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && timer !== null) {
            poll();
        }
    });
}());
