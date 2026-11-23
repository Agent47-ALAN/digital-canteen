(function () {
    'use strict';

    var app = document.getElementById('app');
    if (!app || !window.DC) {
        return;
    }

    var DC = window.DC;
    var api = app.getAttribute('data-api');
    var csrf = app.getAttribute('data-csrf');
    var checkoutToken = app.getAttribute('data-token');
    var loginUrl = app.getAttribute('data-login');

    var form = document.getElementById('checkout-form');
    var statusBox = document.getElementById('status');
    var demoPanel = document.getElementById('demo-panel');
    var failedBox = document.getElementById('payment-failed');
    var failedText = document.getElementById('payment-failed-text');
    var placeButton = document.getElementById('place-button');
    var useCash = document.getElementById('use-cash');
    var methodRadios = form.querySelectorAll('input[name="payment_method"]');
    var busy = false;

    function checkedValue(name) {
        var radio = form.querySelector('input[name="' + name + '"]:checked');
        return radio ? radio.value : '';
    }

    function updateMode() {
        var demo = checkedValue('payment_method') === 'DEMO_ONLINE';
        demoPanel.hidden = !demo;
        placeButton.textContent = demo ? 'Pay now (demo)' : 'Confirm order';
        failedBox.hidden = true;
        DC.hideStatus(statusBox);
    }

    Array.prototype.forEach.call(methodRadios, function (radio) {
        radio.addEventListener('change', updateMode);
    });

    useCash.addEventListener('click', function () {
        form.querySelector('input[name="payment_method"][value="CASH"]').checked = true;
        updateMode();
        placeButton.focus();
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        // Ignore a second click while the first request is still running.
        // The server also refuses to create a second order for the same checkout.
        if (busy) {
            return;
        }
        busy = true;
        placeButton.disabled = true;

        var method = checkedValue('payment_method');
        var demo = method === 'DEMO_ONLINE';
        placeButton.textContent = demo ? 'Processing demo payment\u2026' : 'Placing your order\u2026';
        failedBox.hidden = true;
        DC.hideStatus(statusBox);

        var params = {
            csrf_token: csrf,
            action: 'place',
            payment_method: method,
            checkout_token: checkoutToken
        };
        if (demo) {
            // The simulator scenario only. The browser never sends a payment status.
            params.demo_outcome = checkedValue('demo_outcome');
        }

        DC.request('POST', api, params, function (result) {
            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }

            if (result.success) {
                DC.showStatus(statusBox, 'success', result.message);
                window.location.href = result.data.redirect;
                return;
            }

            busy = false;
            placeButton.disabled = false;
            updateMode();

            if (result.status === 402) {
                failedText.textContent = result.message;
                failedBox.hidden = false;
                failedBox.focus();
            } else {
                DC.showStatus(statusBox, 'error', result.message);
            }
        });
    });

    updateMode();
}());
