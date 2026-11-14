// Antrean admin diperbarui otomatis: halaman yang sama diambil ulang di latar
// belakang, lalu #daftar-antrean diganti bila isinya berubah. Baris yang baru
// atau berubah status disorot sebentar.

// id reservasi yang baru muncul atau statusnya berubah (peta id -> status)
function barisBerubah(lama, baru) {
  var hasil = [];
  for (var id in baru) {
    if (Object.prototype.hasOwnProperty.call(baru, id) && lama[id] !== baru[id]) hasil.push(id);
  }
  return hasil;
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { barisBerubah: barisBerubah };
}

(function () {
  if (typeof document === 'undefined') return;
  var wadah = document.getElementById('daftar-antrean');
  if (!wadah || wadah.getAttribute('data-langsung') !== '1') return;

  var JEDA = 10000;
  var keterangan = document.getElementById('antrean-langsung-ket');
  var berhenti = false;
  var sedangAmbil = false;

  function petaStatus(akar) {
    var peta = {};
    var baris = akar.querySelectorAll('[data-reservasi]');
    for (var i = 0; i < baris.length; i++) {
      peta[baris[i].getAttribute('data-reservasi')] = baris[i].getAttribute('data-status');
    }
    return peta;
  }

  function jamSekarang() {
    var d = new Date();
    return ('0' + d.getHours()).slice(-2) + '.' + ('0' + d.getMinutes()).slice(-2);
  }

  // jangan mengganti isi saat admin sedang menekan tombol di daftar
  function sedangDipakai() {
    return !!wadah.querySelector('form[data-sedang-kirim="1"]') ||
      (document.activeElement && wadah.contains(document.activeElement) && document.activeElement.tagName === 'BUTTON');
  }

  function ambil() {
    if (berhenti || sedangAmbil || document.hidden || sedangDipakai()) return;
    sedangAmbil = true;
    fetch(window.location.href, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-Antrean-Langsung': '1' }
    }).then(function (res) {
      // sesi habis: server mengarahkan ke halaman login
      if (!res.ok || res.redirected) {
        berhenti = true;
        if (keterangan) keterangan.textContent = 'Pembaruan otomatis berhenti. Muat ulang halaman.';
        return null;
      }
      return res.text();
    }).then(function (html) {
      if (html === null || html === undefined) return;
      var dok = new DOMParser().parseFromString(html, 'text/html');
      var baru = dok.getElementById('daftar-antrean');
      if (!baru) return;
      if (keterangan) keterangan.textContent = 'Diperbarui otomatis, terakhir dicek pukul ' + jamSekarang() + '.';
      if (baru.innerHTML === wadah.innerHTML || sedangDipakai()) return;

      var berubah = barisBerubah(petaStatus(wadah), petaStatus(baru));
      wadah.innerHTML = baru.innerHTML;
      for (var i = 0; i < berubah.length; i++) {
        var el = wadah.querySelector('[data-reservasi="' + berubah[i] + '"]');
        if (el) el.classList.add('baris-baru');
      }
    }).catch(function () {
      // jaringan putus sesaat: coba lagi pada putaran berikutnya
    }).then(function () {
      sedangAmbil = false;
    });
  }

  setInterval(ambil, JEDA);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) ambil();
  });
})();
