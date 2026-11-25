
/*
|--------------------------------------------------------------------------
| Neighbour Marketplace - Web Push Service Worker
|--------------------------------------------------------------------------
| Local development testing
*/

self.addEventListener('push', event => {
    event.waitUntil((async () => {
        let payload = {};

        try {
            payload = event.data
                ? event.data.json()
                : {};
        } catch (_) {
            payload = {
                body: event.data
                    ? event.data.text()
                    : 'New notification'
            };
        }

        const title = typeof payload.title === 'string'
            ? payload.title
            : 'Neighbour Marketplace';

        const body = typeof payload.body === 'string'
            ? payload.body
            : 'You have a new notification.';

        const tag = typeof payload.tag === 'string'
            ? payload.tag
            : 'marketplace-notification';

        const url = typeof payload.url === 'string'
            ? payload.url
            : '/';

        await self.registration.showNotification(title, {
            body,
            tag,
            data: { url }
        });
    })());
});

/*
|--------------------------------------------------------------------------
| Notification Click Handler
|--------------------------------------------------------------------------
*/

self.addEventListener('notificationclick', event => {
    event.notification.close();

    event.waitUntil((async () => {
        const proposed = event.notification.data?.url || '/';

        const destination = new URL(
            proposed,
            self.location.origin
        );

        if (destination.origin !== self.location.origin) {
            return;
        }

        const allClients = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        });

        for (const client of allClients) {
            if (
                client.url === destination.href &&
                'focus' in client
            ) {
                return client.focus();
            }
        }

        if (self.clients.openWindow) {
            return self.clients.openWindow(destination.href);
        }
    })());
});
