/* Phase 5.2 — Notification bell, dropdown and polling.
   Relies on window.DC (app.js) for DC.request, DC.el and DC.sessionEnded.
   All DOM mutations use createElement / textContent — no innerHTML anywhere.

   API contract (api/notifications/notifications.php):
   -------------------------------------------------------
   GET  ?count_only=1
        -> { success, data: { unread_count: int, latest_id: int } }

   GET  (no extra params, or before_id=N)
        -> { success, data: { notifications: [...], has_more: bool,
                              unread_count: int, latest_id: int } }
        Each notification object:
          id          : int
          order_id    : int|null
          event_type  : string
          title       : string
          message     : string
          is_read     : bool
          created_at  : string  (formatted, e.g. "26 Nov 2026, 01:30 PM")
          url         : string  (deep link for this user's role)

   POST action=mark_read&id=N&csrf_token=T
        -> { success, data: { unread_count: int } }

   POST action=mark_all_read&csrf_token=T
        -> { success, data: { unread_count: int } }
*/
(function () {
    'use strict';

    // ------------------------------------------------------------------ setup

    var bellWrap = document.getElementById('notif-bell-wrap');
    if (!bellWrap || !window.DC) {
        // Not logged in, or app.js not loaded — do nothing.
        return;
    }

    var DC       = window.DC;
    var api      = bellWrap.getAttribute('data-notif-api');
    var pushApi  = bellWrap.getAttribute('data-push-api');
    var swUrl    = bellWrap.getAttribute('data-sw-url');
    var swScope  = bellWrap.getAttribute('data-sw-scope');
    var csrf     = bellWrap.getAttribute('data-csrf');
    var loginUrl = bellWrap.getAttribute('data-login');

    var bellBtn    = document.getElementById('notif-bell-btn');
    var badge      = document.getElementById('notif-badge');
    var panel      = document.getElementById('notif-panel');
    var listEl     = document.getElementById('notif-list');
    var markAllBtn = document.getElementById('notif-mark-all');
    var loadMoreBtn= document.getElementById('notif-load-more');
    var pushBar    = document.getElementById('notif-push-bar');
    var pushStatus = document.getElementById('notif-push-status');
    var pushBtn    = document.getElementById('notif-push-btn');

    // ------------------------------------------------------------------ state

    var POLL_MS     = 15000;   // badge-only poll interval
    var PAGE_SIZE   = 20;      // notifications per page
    var panelOpen   = false;
    var pollTimer   = null;
    var pollBusy    = false;
    var listBusy    = false;
    var latestId    = 0;       // highest notification id seen; used to detect new arrivals
    var oldestId    = null;    // lowest id in the panel list; used for "load more"
    var rendered    = {};      // id -> DOM element already in the list

    // ------------------------------------------------------------------ badge

    function setBadge(count) {
        var n = parseInt(count, 10) || 0;
        badge.textContent = n > 99 ? '99+' : String(n);
        badge.hidden = n === 0;
        bellBtn.setAttribute('aria-label',
            n === 0 ? 'Notifications' : 'Notifications, ' + n + ' unread');
    }

    // ------------------------------------------------------------------ panel open / close

    function openPanel() {
        panelOpen = true;
        panel.hidden = false;
        bellBtn.setAttribute('aria-expanded', 'true');
        loadList();
    }

    function closePanel() {
        panelOpen = false;
        panel.hidden = true;
        bellBtn.setAttribute('aria-expanded', 'false');
    }

    function togglePanel() {
        if (panelOpen) {
            closePanel();
        } else {
            openPanel();
        }
    }

    // ------------------------------------------------------------------ rendering one item

    function buildItem(n) {
        var item = DC.el('div', {
            className: 'notif-item' + (n.is_read ? ' notif-read' : ' notif-unread'),
            role: 'article',
            'data-id': String(n.id)
        });

        // Unread dot
        var dot = DC.el('span', { className: 'notif-dot', 'aria-hidden': 'true' });

        // Body wrapper
        var body = DC.el('div', { className: 'notif-body' });

        var titleEl = DC.el('p', { className: 'notif-title', text: n.title });
        var msgEl   = DC.el('p', { className: 'notif-msg',   text: n.message });
        var timeEl  = DC.el('p', { className: 'notif-time',  text: n.created_at });

        body.appendChild(titleEl);
        body.appendChild(msgEl);
        body.appendChild(timeEl);

        // If the notification has a destination URL, make the title a link.
        if (n.url) {
            var link = DC.el('a', { className: 'notif-link', href: n.url });
            link.textContent = n.title;
            titleEl.textContent = '';
            titleEl.appendChild(link);
        }

        // "Mark as read" button — only shown for unread items.
        var actions = DC.el('div', { className: 'notif-actions' });
        if (!n.is_read) {
            var readBtn = DC.el('button', {
                className: 'button-link notif-read-btn',
                type: 'button',
                'data-id': String(n.id),
                text: 'Mark as read'
            });
            readBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                markOneRead(n.id, item);
            });
            actions.appendChild(readBtn);
        }

        item.appendChild(dot);
        item.appendChild(body);
        item.appendChild(actions);

        return item;
    }

    // Apply the "read" visual state to an already-rendered item element.
    function applyReadState(itemEl) {
        itemEl.className = itemEl.className
            .replace('notif-unread', 'notif-read');
        var btn = itemEl.querySelector('.notif-read-btn');
        if (btn) {
            btn.parentNode.removeChild(btn);
        }
        var dot = itemEl.querySelector('.notif-dot');
        if (dot) {
            dot.className = 'notif-dot notif-dot-read';
        }
    }

    // ------------------------------------------------------------------ list management

    function showListState(state, message) {
        // state: 'loading' | 'empty' | 'error'
        var p = DC.el('p', { className: 'notif-state', text: message });
        listEl.textContent = '';  // clear
        listEl.appendChild(p);
        rendered = {};
        oldestId = null;
        loadMoreBtn.hidden = true;
    }

    function prependItems(notifications) {
        // Prepend newest items that are not already in the list.
        // Notifications arrive newest-first from the API.
        var fragment = document.createDocumentFragment();
        var added = 0;
        for (var i = 0; i < notifications.length; i++) {
            var n = notifications[i];
            if (rendered[n.id]) {
                // Already displayed — update its read state if it changed.
                if (n.is_read) {
                    applyReadState(rendered[n.id]);
                }
                continue;
            }
            var el = buildItem(n);
            rendered[n.id] = el;
            fragment.appendChild(el);
            added++;
        }
        if (added > 0) {
            // Insert before the first existing child so new items appear at top.
            listEl.insertBefore(fragment, listEl.firstChild);
        }
    }

    function appendItems(notifications) {
        // Append older items (from "load more") at the bottom.
        for (var i = 0; i < notifications.length; i++) {
            var n = notifications[i];
            if (rendered[n.id]) { continue; }
            var el = buildItem(n);
            rendered[n.id] = el;
            listEl.appendChild(el);
        }
    }

    // ------------------------------------------------------------------ fetch the full list (panel open)

    function loadList() {
        if (listBusy) { return; }
        listBusy = true;
        showListState('loading', 'Loading notifications\u2026');

        DC.request('GET', api, { limit: PAGE_SIZE }, function (result) {
            listBusy = false;
            if (DC.sessionEnded(result, loginUrl)) { return; }
            if (!result.success || !result.data) {
                showListState('error', 'Could not load notifications. Try again.');
                return;
            }
            var d = result.data;
            var list = d.notifications || [];

            // Clear any state message; replace with real content.
            listEl.textContent = '';
            rendered = {};
            oldestId = null;

            if (list.length === 0) {
                showListState('empty', 'You have no notifications yet.');
                setBadge(d.unread_count);
                if (d.latest_id > latestId) { latestId = d.latest_id; }
                return;
            }

            prependItems(list);
            oldestId = list[list.length - 1].id;
            setBadge(d.unread_count);
            if (d.latest_id > latestId) { latestId = d.latest_id; }
            loadMoreBtn.hidden = !d.has_more;
        });
    }

    // ------------------------------------------------------------------ incremental update (new items only)

    function fetchNew() {
        if (listBusy) { return; }
        // sinceId must be > 0 for the API to apply the filter.
        if (latestId <= 0) { return; }
        listBusy = true;

        DC.request('GET', api, { since_id: latestId, limit: PAGE_SIZE }, function (result) {
            listBusy = false;
            if (DC.sessionEnded(result, loginUrl)) { return; }
            if (!result.success || !result.data) { return; }
            var d = result.data;
            var list = d.notifications || [];

            if (list.length > 0) {
                // Remove any "empty" or "loading" state message first.
                var state = listEl.querySelector('.notif-state');
                if (state) {
                    listEl.removeChild(state);
                }
                prependItems(list);
                if (d.latest_id > latestId) { latestId = d.latest_id; }
            }
            setBadge(d.unread_count);
        });
    }

    // ------------------------------------------------------------------ load more (older items)

    function loadMore() {
        if (listBusy || oldestId === null) { return; }
        listBusy = true;
        loadMoreBtn.disabled = true;

        DC.request('GET', api, { before_id: oldestId, limit: PAGE_SIZE }, function (result) {
            listBusy = false;
            loadMoreBtn.disabled = false;
            if (DC.sessionEnded(result, loginUrl)) { return; }
            if (!result.success || !result.data) { return; }
            var d = result.data;
            var list = d.notifications || [];

            appendItems(list);
            if (list.length > 0) {
                oldestId = list[list.length - 1].id;
            }
            setBadge(d.unread_count);
            loadMoreBtn.hidden = !d.has_more;
        });
    }

    // ------------------------------------------------------------------ mark one read

    function markOneRead(id, itemEl) {
        DC.request('POST', api,
            { action: 'mark_read', id: id, csrf_token: csrf },
            function (result) {
                if (DC.sessionEnded(result, loginUrl)) { return; }
                if (!result.success) { return; }
                applyReadState(itemEl);
                if (result.data) { setBadge(result.data.unread_count); }
                // Keep csrf fresh — the token is regenerated only on login, so
                // the same token stays valid across all calls in this session.
            }
        );
    }

    // ------------------------------------------------------------------ mark all read

    function markAllRead() {
        if (markAllBtn.disabled) { return; }
        markAllBtn.disabled = true;

        DC.request('POST', api,
            { action: 'mark_all_read', csrf_token: csrf },
            function (result) {
                markAllBtn.disabled = false;
                if (DC.sessionEnded(result, loginUrl)) { return; }
                if (!result.success) { return; }
                // Visually apply read state to every item in the list.
                var items = listEl.querySelectorAll('.notif-unread');
                Array.prototype.forEach.call(items, function (el) {
                    applyReadState(el);
                });
                setBadge(0);
            }
        );
    }

    // ------------------------------------------------------------------ badge-only poll

    function pollBadge() {
        if (pollBusy) { return; }
        pollBusy = true;

        DC.request('GET', api, { count_only: '1' }, function (result) {
            pollBusy = false;
            if (DC.sessionEnded(result, loginUrl)) {
                clearInterval(pollTimer);
                return;
            }
            if (!result.success || !result.data) { return; }
            var d = result.data;
            setBadge(d.unread_count);

            var newLatest = parseInt(d.latest_id, 10) || 0;
            if (newLatest > latestId) {
                latestId = newLatest;
                // If the panel is open, pull the new items in immediately.
                if (panelOpen) { fetchNew(); }
            }
        });
    }

    // ------------------------------------------------------------------ events

    bellBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        togglePanel();
    });

    markAllBtn.addEventListener('click', function () {
        markAllRead();
    });

    loadMoreBtn.addEventListener('click', function () {
        loadMore();
    });

    // Close panel when clicking anywhere outside the bell wrap.
    document.addEventListener('click', function (e) {
        if (panelOpen && !bellWrap.contains(e.target)) {
            closePanel();
        }
    });

    // Close on Escape key.
    document.addEventListener('keydown', function (e) {
        if (panelOpen && (e.key === 'Escape' || e.keyCode === 27)) {
            closePanel();
            bellBtn.focus();
        }
    });

    // On mobile: when the user switches back to the tab, poll immediately.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            pollBadge();
        }
    });

    // ------------------------------------------------------------------ start

    // Initial badge fetch on page load.
    pollBadge();

    // Recurring badge poll — only the cheap count_only=1 request every 15 s.
    pollTimer = setInterval(function () {
        if (!document.hidden) {
            pollBadge();
        }
    }, POLL_MS);

    // ------------------------------------------------------------------ Phase 5.3: PWA + Web Push

    var swReg = null;
    var pushBusy = false;

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) {
            out[i] = raw.charCodeAt(i);
        }
        return out;
    }

    function arrayBufferToBase64Url(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = '';
        for (var i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function setPushUi(subscribed, message, disabled) {
        if (!pushBar || !pushStatus || !pushBtn) { return; }
        pushStatus.textContent = message;
        pushBtn.textContent = subscribed ? 'Disable alerts' : 'Enable alerts';
        pushBtn.disabled = !!disabled;
    }

    function syncPushState() {
        if (!swReg || !pushBar) { return; }
        if (!('PushManager' in window) || !('Notification' in window)) {
            return;
        }
        pushBar.hidden = false;

        if (Notification.permission === 'denied') {
            setPushUi(false, 'Push alerts blocked in browser settings', true);
            return;
        }

        swReg.pushManager.getSubscription().then(function (sub) {
            if (!sub) {
                setPushUi(false, 'Push alerts off on this device', false);
                return;
            }
            DC.request('GET', pushApi, { endpoint: sub.endpoint }, function (result) {
                if (DC.sessionEnded(result, loginUrl)) { return; }
                if (result.success && result.data && result.data.subscribed) {
                    setPushUi(true, 'Push alerts active on this device', false);
                } else {
                    setPushUi(false, 'Push alerts off on this device', false);
                }
            });
        }).catch(function () {
            setPushUi(false, 'Push alerts unavailable', true);
        });
    }

    function subscribeBrowserPush() {
        if (!swReg || pushBusy) { return; }
        pushBusy = true;
        setPushUi(false, 'Enabling push alerts\u2026', true);

        Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                pushBusy = false;
                syncPushState();
                return;
            }

            DC.request('GET', pushApi, {}, function (keyRes) {
                if (DC.sessionEnded(keyRes, loginUrl)) {
                    pushBusy = false;
                    return;
                }
                if (!keyRes.success || !keyRes.data || !keyRes.data.public_key) {
                    pushBusy = false;
                    setPushUi(false, 'Could not load push key', false);
                    return;
                }

                var appKey = urlBase64ToUint8Array(keyRes.data.public_key);
                swReg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: appKey
                }).then(function (sub) {
                    var json = sub.toJSON ? sub.toJSON() : {};
                    var keys = json.keys || {};
                    var p256dh = keys.p256dh || (sub.getKey ? arrayBufferToBase64Url(sub.getKey('p256dh')) : '');
                    var auth   = keys.auth   || (sub.getKey ? arrayBufferToBase64Url(sub.getKey('auth'))   : '');

                    DC.request('POST', pushApi, {
                        action: 'subscribe',
                        endpoint: sub.endpoint,
                        public_key: p256dh,
                        auth_token: auth,
                        csrf_token: csrf
                    }, function (saveRes) {
                        pushBusy = false;
                        if (DC.sessionEnded(saveRes, loginUrl)) { return; }
                        if (saveRes.success) {
                            setPushUi(true, 'Push alerts active on this device', false);
                        } else {
                            setPushUi(false, saveRes.message || 'Could not save subscription', false);
                        }
                    });
                }).catch(function () {
                    pushBusy = false;
                    setPushUi(false, 'Push requires HTTPS or supported browser', false);
                });
            });
        }).catch(function () {
            pushBusy = false;
            setPushUi(false, 'Could not request permission', false);
        });
    }

    function unsubscribeBrowserPush() {
        if (!swReg || pushBusy) { return; }
        pushBusy = true;
        setPushUi(true, 'Disabling push alerts\u2026', true);

        swReg.pushManager.getSubscription().then(function (sub) {
            if (!sub) {
                pushBusy = false;
                setPushUi(false, 'Push alerts off on this device', false);
                return;
            }
            var endpoint = sub.endpoint;
            sub.unsubscribe().then(function () {
                DC.request('POST', pushApi, {
                    action: 'unsubscribe',
                    endpoint: endpoint,
                    csrf_token: csrf
                }, function (res) {
                    pushBusy = false;
                    if (DC.sessionEnded(res, loginUrl)) { return; }
                    setPushUi(false, 'Push alerts off on this device', false);
                });
            }).catch(function () {
                pushBusy = false;
                syncPushState();
            });
        }).catch(function () {
            pushBusy = false;
            syncPushState();
        });
    }

    if (swUrl && pushApi && 'serviceWorker' in navigator) {
        navigator.serviceWorker.register(swUrl, { scope: swScope || '/digital_canteen/' })
            .then(function (reg) {
                swReg = reg;
                syncPushState();
            })
            .catch(function () {
                // Service worker registration requires HTTPS (or localhost).
            });
    }

    if (pushBtn) {
        pushBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (!swReg || pushBusy) { return; }
            swReg.pushManager.getSubscription().then(function (sub) {
                if (sub && pushBtn.textContent === 'Disable alerts') {
                    unsubscribeBrowserPush();
                } else {
                    subscribeBrowserPush();
                }
            });
        });
    }

}());
