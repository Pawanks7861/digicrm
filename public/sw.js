/* DigiCRM Web Push service worker — push + notificationclick only */
self.addEventListener('push', (event) => {
  let data = { title: 'DigiCRM', body: 'You have a new notification', data: { url: '/notifications' } };
  try {
    if (event.data) {
      data = { ...data, ...event.data.json() };
    }
  } catch (e) {
    // ignore parse errors
  }
  event.waitUntil(
    self.registration.showNotification(data.title || 'DigiCRM', {
      body: data.body || '',
      tag: data.tag || 'digicrm',
      data: data.data || { url: '/notifications' },
      icon: '/favicon.ico',
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || '/notifications';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if ('focus' in client) {
          client.navigate(url);
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});
