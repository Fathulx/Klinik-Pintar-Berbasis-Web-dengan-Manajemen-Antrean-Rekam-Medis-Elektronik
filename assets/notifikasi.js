// Polling notifikasi pasien setiap 15 detik: memperbarui badge lonceng,
// menampilkan toast + bunyi + getar untuk notifikasi baru, dan memperbarui
// jumlah pasien di depan pada karcis antrean.

// Menentukan tindakan dari respons polling:
//  'data'       : JSON normal, proses isinya
//  'sesi_habis' : 401/403, atau dialihkan ke halaman HTML (login/ganti password)
//  'coba_lagi'  : gangguan server sesaat (5xx dan lainnya), lanjutkan polling
function nilaiRespons(status, contentType, dialihkan) {
  var json = (contentType || '').indexOf('application/json') !== -1;
  if (status === 401 || status === 403) return 'sesi_habis';
  if (status >= 200 && status < 300) return json && !dialihkan ? 'data' : 'sesi_habis';
  return 'coba_lagi';
}

// sama dengan teks_sisa_antrean() di includes/antrean.php
function teksSisa(n) {
  return n === 0 ? 'Anda pasien berikutnya' : n + ' pasien lagi sebelum Anda';
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { nilaiRespons: nilaiRespons, teksSisa: teksSisa };
}

(function () {
  if (typeof document === 'undefined') return;
  var lonceng = document.getElementById('notif-lonceng');
  if (!lonceng || !window.fetch) return;

  var cekUrl = lonceng.getAttribute('data-cek-url');
  var setelah = parseInt(lonceng.getAttribute('data-setelah'), 10) || 0;
  var badge = document.getElementById('notif-jumlah');
  var timer = null;
  var audio = null;

  function aturBadge(n) {
    badge.textContent = n > 99 ? '99+' : String(n);
    badge.hidden = n <= 0;
    lonceng.setAttribute('aria-label', 'Notifikasi, ' + n + ' belum dibaca');
  }

  // Browser hanya mengizinkan suara setelah pengguna berinteraksi dengan halaman.
  function siapkanAudio() {
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!audio && AC) {
      try { audio = new AC(); } catch (e) { audio = null; }
    }
  }
  document.addEventListener('click', siapkanAudio, { once: true });
  document.addEventListener('keydown', siapkanAudio, { once: true });

  function bunyi() {
    if (!audio) return;
    try {
      var osc = audio.createOscillator();
      var gain = audio.createGain();
      osc.frequency.value = 880;
      gain.gain.value = 0.15;
      osc.connect(gain);
      gain.connect(audio.destination);
      osc.start();
      osc.stop(audio.currentTime + 0.3);
    } catch (e) { /* abaikan */ }
  }

  function wadahToast() {
    var w = document.getElementById('notif-toast');
    if (!w) {
      w = document.createElement('div');
      w.id = 'notif-toast';
      w.setAttribute('role', 'status');
      w.setAttribute('aria-live', 'polite');
      w.className = 'fixed bottom-4 right-4 z-50 flex max-w-sm flex-col gap-2';
      document.body.appendChild(w);
    }
    return w;
  }

  function toast(n) {
    var el = document.createElement(n.tautan_url ? 'a' : 'div');
    if (n.tautan_url) el.href = n.tautan_url;
    el.className = 'block rounded-xl border border-klinik bg-white px-4 py-3 text-sm shadow-lg';
    var j = document.createElement('p');
    j.className = 'font-semibold text-klinik';
    j.textContent = n.judul;
    var p = document.createElement('p');
    p.className = 'mt-1 text-ink/80';
    p.textContent = n.pesan;
    el.appendChild(j);
    el.appendChild(p);
    wadahToast().appendChild(el);
    setTimeout(function () { el.remove(); }, 8000);
  }

  // Sesi habis: hentikan polling dan beri tahu pasien dengan banner tetap,
  // supaya mereka tidak mengira notifikasi masih berjalan.
  function sesiHabis() {
    if (timer) clearInterval(timer);
    timer = null;
    if (document.getElementById('notif-sesi-habis')) return;
    var b = document.createElement('div');
    b.id = 'notif-sesi-habis';
    b.setAttribute('role', 'alert');
    b.className = 'fixed inset-x-4 top-4 z-50 mx-auto max-w-md rounded-xl border border-bata bg-white px-4 py-3 text-sm shadow-lg';
    var p = document.createElement('p');
    p.className = 'font-semibold text-bata';
    p.textContent = 'Sesi berakhir. Notifikasi antrean berhenti.';
    var a = document.createElement('a');
    a.href = lonceng.getAttribute('data-login-url');
    a.className = 'mt-1 inline-block text-klinik underline';
    a.textContent = 'Masuk lagi agar tetap menerima notifikasi';
    b.appendChild(p);
    b.appendChild(a);
    document.body.appendChild(b);
    bunyi();
    if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
  }

  function cek() {
    fetch(cekUrl + '?setelah=' + setelah, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (res) {
        var t = nilaiRespons(res.status, res.headers.get('Content-Type'), res.redirected);
        if (t === 'sesi_habis') { sesiHabis(); return null; }
        if (t === 'coba_lagi') return null; // gangguan sesaat: coba lagi di putaran berikut
        return res.json();
      })
      .then(function (data) {
        if (!data) return;
        aturBadge(data.belum_dibaca);
        if (data.baru && data.baru.length) {
          data.baru.slice().reverse().forEach(toast);
          setelah = Math.max.apply(null, [setelah].concat(data.baru.map(function (n) { return n.id; })));
          bunyi();
          if (navigator.vibrate) navigator.vibrate(200);
        }
        (data.antrean || []).forEach(function (r) {
          var el = document.querySelector('[data-di-depan="' + r.id + '"]');
          if (el) el.textContent = String(r.di_depan);
          // karcis layar antrean: teks sisa dan nyala kuning saat sudah dekat
          var sisa = document.querySelector('[data-sisa="' + r.id + '"]');
          if (sisa) sisa.textContent = teksSisa(r.di_depan);
          var karcis = document.querySelector('[data-karcis="' + r.id + '"]');
          if (karcis && karcis.getAttribute('data-hari-ini') === '1') {
            karcis.classList.toggle('karcis--dekat', r.di_depan <= Number(karcis.getAttribute('data-batas-dekat') || 3));
          }
        });
      })
      .catch(function () { /* jaringan putus: coba lagi di putaran berikut */ });
  }

  timer = setInterval(cek, 15000);
  cek();
})();
