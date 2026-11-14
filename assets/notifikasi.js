// Polling notifikasi pasien setiap 8 detik: memperbarui badge lonceng,
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

// sama dengan keadaan_karcis() di includes/antrean.php
function keadaanKarcis(status, diDepan, batas, hariIni) {
  var akhir = {
    tidak_hadir: 'Anda ditandai tidak hadir',
    selesai: 'Pemeriksaan selesai',
    batal: 'Reservasi dibatalkan'
  };
  if (akhir[status]) return { teks: akhir[status], dekat: false, lewat: true };
  if (status === 'diperiksa') return { teks: 'Silakan masuk ke ruang periksa', dekat: true, lewat: false };
  return { teks: teksSisa(diDepan), dekat: !!hariIni && diDepan <= batas, lewat: false };
}

// label dan warna lencana status, sama dengan badge_reservasi() di PHP
var LENCANA = {
  menunggu: ['Menunggu', 'status-badge--warning'],
  hadir: ['Hadir', 'status-badge--success'],
  diperiksa: ['Diperiksa', 'status-badge--info'],
  selesai: ['Selesai', 'status-badge--neutral'],
  batal: ['Dibatalkan', 'status-badge--danger'],
  tidak_hadir: ['Tidak hadir', 'status-badge--danger']
};

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { nilaiRespons: nilaiRespons, teksSisa: teksSisa, keadaanKarcis: keadaanKarcis };
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

  // Karcis berubah langsung mengikuti status dan jumlah pasien di depan:
  // teks berganti dengan animasi, layar menyala kuning saat dekat/dipanggil,
  // dan redup saat selesai, dibatalkan, atau ditandai tidak hadir.
  function perbaruiKarcis(r) {
    var karcis = document.querySelector('[data-karcis="' + r.id + '"]');
    if (!karcis) return;
    var k = keadaanKarcis(r.status, r.di_depan,
      Number(karcis.getAttribute('data-batas-dekat') || 3),
      karcis.getAttribute('data-hari-ini') === '1');

    var sisa = karcis.querySelector('[data-sisa]');
    if (sisa && sisa.textContent !== k.teks) {
      sisa.textContent = k.teks;
      sisa.classList.remove('berganti');
      void sisa.offsetWidth; // mulai ulang animasi
      sisa.classList.add('berganti');
    }

    var sebelumnyaDekat = karcis.classList.contains('karcis--dekat');
    karcis.classList.toggle('karcis--dekat', k.dekat);
    karcis.classList.toggle('karcis--lewat', k.lewat);
    if (k.dekat && !sebelumnyaDekat) {
      // kedip panggilan sekali saat baru menjadi dekat
      var layar = karcis.querySelector('.karcis__layar');
      if (layar) { layar.style.animation = 'none'; void layar.offsetWidth; layar.style.animation = ''; }
    }

    if (karcis.getAttribute('data-status') !== r.status) {
      karcis.setAttribute('data-status', r.status);
      var label = karcis.querySelector('[data-status-label]');
      var l = LENCANA[r.status];
      if (label && l) {
        label.innerHTML = '';
        var span = document.createElement('span');
        span.className = 'status-badge ' + l[1];
        span.textContent = l[0];
        label.appendChild(span);
      }
      var perkiraan = karcis.querySelector('[data-perkiraan]');
      if (perkiraan) perkiraan.hidden = !(r.status === 'menunggu' || r.status === 'hadir');
    }
  }

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
          perbaruiKarcis(r);
        });
      })
      .catch(function () { /* jaringan putus: coba lagi di putaran berikut */ });
  }

  timer = setInterval(cek, 8000);
  cek();
})();
