// Polling notifikasi pasien setiap 15 detik: memperbarui badge lonceng,
// menampilkan toast + bunyi + getar untuk notifikasi baru, dan memperbarui
// jumlah pasien di depan pada karcis antrean.
(function () {
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

  function berhenti() {
    if (timer) clearInterval(timer);
    timer = null;
  }

  function cek() {
    fetch(cekUrl + '?setelah=' + setelah, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (res) {
        var json = (res.headers.get('Content-Type') || '').indexOf('application/json') !== -1;
        if (!res.ok || !json) { berhenti(); return null; } // sesi habis atau dialihkan
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
        });
      })
      .catch(function () { /* jaringan putus: coba lagi di putaran berikut */ });
  }

  timer = setInterval(cek, 15000);
  cek();
})();
