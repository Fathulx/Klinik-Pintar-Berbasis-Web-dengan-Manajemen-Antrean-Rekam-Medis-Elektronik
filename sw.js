// Service worker Klinik Pintar: menampilkan notifikasi Web Push walau
// halaman atau browser sedang ditutup, lalu membuka halaman terkait saat
// notifikasi disentuh. Lingkupnya folder aplikasi (lokasi file ini).

self.addEventListener('install', function () {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

function alamat(relatif) {
  return new URL(relatif, self.registration.scope).href;
}

self.addEventListener('push', function (event) {
  var data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = { pesan: event.data ? event.data.text() : '' };
  }

  event.waitUntil(
    self.registration.showNotification(data.judul || 'Klinik Pintar', {
      body: data.pesan || '',
      icon: alamat('assets/ikon-192.png'),
      badge: alamat('assets/ikon-badge.png'),
      tag: 'antrean',          // notifikasi baru menggantikan yang lama
      renotify: true,          // tetap berbunyi/bergetar saat menggantikan
      vibrate: [200, 100, 200],
      data: { url: alamat(data.url || 'user/notifikasi.php') }
    })
  );
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var tujuan = event.notification.data && event.notification.data.url
    ? event.notification.data.url
    : alamat('user/notifikasi.php');

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (jendela) {
      for (var i = 0; i < jendela.length; i++) {
        if (jendela[i].url === tujuan && 'focus' in jendela[i]) {
          return jendela[i].focus();
        }
      }
      return self.clients.openWindow(tujuan);
    })
  );
});
