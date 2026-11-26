/* Digital Canteen Service Worker (Phase 5.3)
   Scope: /digital_canteen/
   Handles Web Push delivery and notification clicks. */
'use strict';

self.addEventListener('install', function (event) {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    var payload = {
        title: 'CampusBite',
        body: 'You have a new order update.',
        url: '/digital_canteen/',
        tag: 'campusbite-notification'
    };

    if (event.data) {
        try {
            var parsed = event.data.json();
            if (parsed && typeof parsed === 'object') {
                if (typeof parsed.title === 'string' && parsed.title !== '') {
                    payload.title = parsed.title;
                }
                if (typeof parsed.body === 'string' && parsed.body !== '') {
                    payload.body = parsed.body;
                }
                if (typeof parsed.url === 'string' && parsed.url !== '') {
                    payload.url = parsed.url;
                }
                if (typeof parsed.tag === 'string' && parsed.tag !== '') {
                    payload.tag = parsed.tag;
                }
            }
        } catch (err) {
            var text = event.data.text();
            if (text) {
                payload.body = text;
            }
        }
    }

    var options = {
        body: payload.body,
        icon: '/digital_canteen/assets/icons/icon-192.png',
        badge: '/digital_canteen/assets/icons/icon-192.png',
        tag: payload.tag,
        renotify: true,
        data: { url: payload.url }
    };

    event.waitUntil(
        self.registration.showNotification(payload.title, options)
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var targetUrl = '/digital_canteen/';
    if (event.notification.data && typeof event.notification.data.url === 'string') {
        targetUrl = event.notification.data.url;
    }

    var resolvedTarget = new URL(targetUrl, self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url.indexOf(self.location.origin + '/digital_canteen/') === 0 && 'focus' in client) {
                    if ('navigate' in client && client.url !== resolvedTarget) {
                        return client.navigate(resolvedTarget).then(function (navigated) {
                            return navigated ? navigated.focus() : client.focus();
                        });
                    }
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(resolvedTarget);
            }
            return null;
        })
    );
});
