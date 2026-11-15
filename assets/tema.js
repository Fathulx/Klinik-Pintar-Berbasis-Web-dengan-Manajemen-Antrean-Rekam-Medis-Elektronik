// Tema terang/gelap. Bawaannya mengikuti pengaturan perangkat; pilihan
// pengguna lewat tombol tema disimpan di browser ini (localStorage).
// Penerapan awal dilakukan oleh skrip kecil di <head> (includes/header.php)
// agar halaman tidak sempat berkedip terang.

function temaAwal(simpanan, sukaGelap) {
  if (simpanan === 'terang' || simpanan === 'gelap') return simpanan;
  return sukaGelap ? 'gelap' : 'terang';
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { temaAwal: temaAwal };
}

(function () {
  if (typeof document === 'undefined') return;
  var akar = document.documentElement;

  function terapkan(tema) {
    akar.setAttribute('data-tema', tema);
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', tema === 'gelap' ? '#0D1720' : '#1B4F82');
    var tombol = document.querySelectorAll('[data-tema-tombol]');
    for (var i = 0; i < tombol.length; i++) {
      var gelap = tema === 'gelap';
      tombol[i].setAttribute('aria-pressed', gelap ? 'true' : 'false');
      tombol[i].setAttribute('aria-label', gelap ? 'Gunakan tema terang' : 'Gunakan tema gelap');
      tombol[i].setAttribute('title', gelap ? 'Tema terang' : 'Tema gelap');
    }
  }

  terapkan(akar.getAttribute('data-tema') || 'terang');

  document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-tema-tombol]') : null;
    if (!t) return;
    var baru = akar.getAttribute('data-tema') === 'gelap' ? 'terang' : 'gelap';
    try { localStorage.setItem('tema', baru); } catch (e) { /* mode privat: tetap berganti */ }
    terapkan(baru);
  });

  // tanpa pilihan pengguna, ikuti perubahan pengaturan perangkat
  if (window.matchMedia) {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var ikuti = function () {
      var simpanan = null;
      try { simpanan = localStorage.getItem('tema'); } catch (e) {}
      if (!simpanan) terapkan(mq.matches ? 'gelap' : 'terang');
    };
    if (mq.addEventListener) mq.addEventListener('change', ikuti);
  }
})();
