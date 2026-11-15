// Panel "Notifikasi di perangkat ini": mengaktifkan/mematikan Web Push untuk
// browser ini dan mendaftarkannya ke server (user/push_langganan.php).

// Menentukan keadaan panel dari kemampuan browser.
//   info: { aman, didukung, ios, standalone, izin, berlangganan }
function keadaanPush(info) {
  if (!info.aman) return 'butuh_https';
  if (!info.didukung) {
    return info.ios && !info.standalone ? 'ios_layar_utama' : 'tidak_didukung';
  }
  if (info.izin === 'denied') return 'diblokir';
  if (info.izin === 'granted' && info.berlangganan) return 'aktif';
  return 'belum';
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { keadaanPush: keadaanPush };
}

(function () {
  if (typeof document === 'undefined') return;
  var panel = document.getElementById('push-panel');
  if (!panel) return;

  var keterangan = panel.querySelector('[data-push-keterangan]');
  var tombol = panel.querySelector('[data-push-tombol]');
  var kunciServer = panel.getAttribute('data-kunci');
  var registrasi = null;

  var TEKS = {
    belum: ['Dapatkan pemberitahuan antrean walau browser atau layar HP sedang ditutup.', 'Aktifkan notifikasi'],
    aktif: ['Aktif. Pemberitahuan antrean akan muncul di perangkat ini.', 'Matikan'],
    diblokir: ['Notifikasi diblokir untuk situs ini. Izinkan lewat pengaturan situs di browser (ikon di samping alamat), lalu muat ulang halaman.', ''],
    butuh_https: ['Notifikasi di perangkat butuh alamat yang diawali https://. Pemberitahuan di dalam halaman tetap berjalan.', ''],
    ios_layar_utama: ['Di iPhone/iPad: ketuk tombol Bagikan lalu "Tambahkan ke Layar Utama", buka Klinik Pintar dari ikon itu, kemudian aktifkan notifikasi di sini.', ''],
    tidak_didukung: ['Browser ini belum mendukung notifikasi di perangkat. Pemberitahuan di dalam halaman tetap berjalan.', ''],
    gagal: ['Gagal mengaktifkan notifikasi. Periksa koneksi lalu coba lagi.', 'Coba lagi']
  };

  function tampilkan(keadaan) {
    var t = TEKS[keadaan];
    keterangan.textContent = t[0];
    tombol.hidden = !t[1];
    tombol.textContent = t[1];
    tombol.removeAttribute('aria-busy');
    tombol.setAttribute('data-keadaan', keadaan);
    // tombol utama untuk mengaktifkan, tombol garis untuk mematikan
    tombol.className = keadaan === 'aktif'
      ? 'action-button'
      : 'bg-klinik px-4 py-2 text-sm font-semibold text-white';
    panel.hidden = false;
  }

  function kunciKeBytes(b64url) {
    var b64 = (b64url + '==='.slice((b64url.length + 3) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    var mentah = atob(b64);
    var hasil = new Uint8Array(mentah.length);
    for (var i = 0; i < mentah.length; i++) hasil[i] = mentah.charCodeAt(i);
    return hasil;
  }

  function samaKunci(langganan) {
    var k = langganan.options && langganan.options.applicationServerKey;
    if (!k) return true; // browser lama tidak memberi tahu; anggap sama
    var a = new Uint8Array(k), b = kunciKeBytes(kunciServer);
    if (a.length !== b.length) return false;
    for (var i = 0; i < a.length; i++) if (a[i] !== b[i]) return false;
    return true;
  }

  function kirim(aksi, langganan) {
    var data = new URLSearchParams();
    data.append('csrf', panel.getAttribute('data-csrf'));
    data.append('aksi', aksi);
    data.append('endpoint', langganan.endpoint);
    if (aksi === 'simpan') {
      var json = langganan.toJSON();
      data.append('p256dh', json.keys.p256dh);
      data.append('auth', json.keys.auth);
    }
    return fetch(panel.getAttribute('data-simpan'), {
      method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' }
    }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
    });
  }

  function aktifkan() {
    tombol.setAttribute('aria-busy', 'true');
    Notification.requestPermission().then(function (izin) {
      if (izin !== 'granted') {
        tampilkan(izin === 'denied' ? 'diblokir' : 'belum');
        return null;
      }
      return registrasi.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: kunciKeBytes(kunciServer)
      }).then(function (langganan) {
        return kirim('simpan', langganan).then(function () { tampilkan('aktif'); });
      });
    }).catch(function () { tampilkan('gagal'); });
  }

  function matikan() {
    tombol.setAttribute('aria-busy', 'true');
    registrasi.pushManager.getSubscription().then(function (langganan) {
      if (!langganan) return null;
      return kirim('hapus', langganan).catch(function () {}).then(function () {
        return langganan.unsubscribe();
      });
    }).then(function () { tampilkan('belum'); })
      .catch(function () { tampilkan('gagal'); });
  }

  tombol.addEventListener('click', function () {
    if (tombol.getAttribute('data-keadaan') === 'aktif') matikan();
    else aktifkan();
  });

  var info = {
    aman: window.isSecureContext !== false,
    didukung: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
    ios: /iPad|iPhone|iPod/.test(navigator.userAgent) ||
      (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1),
    standalone: window.navigator.standalone === true ||
      (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches),
    izin: 'Notification' in window ? Notification.permission : 'default',
    berlangganan: false
  };

  var awal = keadaanPush(info);
  if (awal !== 'belum' && awal !== 'diblokir') {
    tampilkan(awal);
    return;
  }

  navigator.serviceWorker.register(panel.getAttribute('data-sw')).then(function (reg) {
    registrasi = reg;
    return reg.pushManager.getSubscription();
  }).then(function (langganan) {
    if (langganan && !samaKunci(langganan)) {
      // kunci server sudah diganti: langganan lama tidak bisa dipakai lagi
      return langganan.unsubscribe().then(function () { return null; });
    }
    return langganan;
  }).then(function (langganan) {
    info.berlangganan = !!langganan;
    var keadaan = keadaanPush(info);
    tampilkan(keadaan);
    // sinkronkan ulang: perangkat ini mungkin terakhir dipakai akun lain,
    // atau langganan dihapus server saat logout
    if (keadaan === 'aktif') kirim('simpan', langganan).catch(function () {});
  }).catch(function () {
    tampilkan('tidak_didukung');
  });
})();
