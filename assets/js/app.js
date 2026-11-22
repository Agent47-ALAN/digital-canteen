/* Shared helpers for the Digital Canteen pages (XMLHttpRequest + JSON). */
(function () {
    'use strict';

    function encode(params) {
        return Object.keys(params).filter(function (key) {
            return params[key] !== undefined && params[key] !== null;
        }).map(function (key) {
            return encodeURIComponent(key) + '=' + encodeURIComponent(String(params[key]));
        }).join('&');
    }

    /**
     * Send one request and call done(result) once.
     * result is always {success, message, data, status}.
     * Returns the XMLHttpRequest so the caller can abort it.
     */
    function request(method, url, params, done) {
        var xhr = new XMLHttpRequest();
        var query = encode(params || {});
        var body = null;
        var finished = false;

        if (method === 'GET') {
            if (query) {
                url += (url.indexOf('?') === -1 ? '?' : '&') + query;
            }
        } else {
            body = query;
        }

        function finish(result) {
            if (finished) {
                return;
            }
            finished = true;
            done(result);
        }

        function failure(message) {
            finish({ success: false, message: message, data: null, status: 0 });
        }

        xhr.open(method, url, true);
        xhr.timeout = 15000;
        xhr.setRequestHeader('Accept', 'application/json');
        if (method !== 'GET') {
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        }

        xhr.onload = function () {
            var payload = null;
            try {
                payload = JSON.parse(xhr.responseText);
            } catch (error) {
                payload = null;
            }
            if (!payload || typeof payload.success !== 'boolean') {
                payload = { success: false, message: 'The server sent an unexpected response.', data: null };
            }
            payload.status = xhr.status;
            finish(payload);
        };
        xhr.onerror = function () {
            failure('Could not reach the server. Check your connection and try again.');
        };
        xhr.ontimeout = function () {
            failure('The server took too long to answer. Please try again.');
        };
        xhr.onabort = function () {
            finished = true;
        };

        xhr.send(body);
        return xhr;
    }

    /** Create an element. Text is always set with textContent, never as HTML. */
    function el(tag, options, children) {
        var node = document.createElement(tag);
        var opts = options || {};

        Object.keys(opts).forEach(function (key) {
            if (key === 'className') {
                node.className = opts[key];
            } else if (key === 'text') {
                node.textContent = opts[key];
            } else {
                node.setAttribute(key, opts[key]);
            }
        });
        (children || []).forEach(function (child) {
            if (child) {
                node.appendChild(child);
            }
        });

        return node;
    }

    function money(value) {
        var number = Number(value);
        var text = isNaN(number) ? '0.00' : number.toFixed(2);

        try {
            text = number.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } catch (error) {
            /* keep the plain toFixed text */
        }

        return '\u20B9' + text;
    }

    function showStatus(box, type, message) {
        box.hidden = false;
        box.className = 'alert alert-' + (type === 'success' ? 'success' : 'error');
        box.textContent = message;
    }

    function hideStatus(box) {
        box.hidden = true;
        box.textContent = '';
    }

    /** Send the browser to the login page when the server says the session ended. */
    function sessionEnded(result, loginUrl) {
        if (result.status === 401 && loginUrl) {
            window.location.href = loginUrl;
            return true;
        }
        return false;
    }

    window.DC = {
        request: request,
        el: el,
        money: money,
        showStatus: showStatus,
        hideStatus: hideStatus,
        sessionEnded: sessionEnded
    };
}());
