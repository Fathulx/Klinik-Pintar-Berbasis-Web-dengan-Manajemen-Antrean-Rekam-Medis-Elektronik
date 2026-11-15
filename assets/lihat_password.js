// Ikon mata di setiap kolom password untuk menampilkan/menyembunyikan isinya.
// Dipasang otomatis pada semua <input type="password"> di halaman.

var MATA = '<svg class="ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
  + '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></svg>';
var MATA_TUTUP = '<svg class="ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
  + '<path d="M10.6 5.6A10 10 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-2.6 3.4M6.6 6.6C3.9 8.3 2.5 12 2.5 12S6 18.5 12 18.5a9.6 9.6 0 0 0 5.4-1.6"/>'
  + '<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2M3 3l18 18"/></svg>';

// Mengganti jenis kolom dan keadaan tombol. Mengembalikan true bila kini terlihat.
function alihTampil(kolom, tombol) {
  var terlihat = kolom.type === 'password';
  kolom.type = terlihat ? 'text' : 'password';
  tombol.setAttribute('aria-pressed', terlihat ? 'true' : 'false');
  tombol.setAttribute('aria-label', terlihat ? 'Sembunyikan password' : 'Tampilkan password');
  return terlihat;
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { alihTampil: alihTampil };
}

(function () {
  if (typeof document === 'undefined') return;
  var kolomList = document.querySelectorAll('input[type="password"]');
  for (var i = 0; i < kolomList.length; i++) {
    (function (kolom) {
      var bungkus = document.createElement('span');
      bungkus.className = 'kolom-password';
      kolom.parentNode.insertBefore(bungkus, kolom);
      bungkus.appendChild(kolom);

      var tombol = document.createElement('button');
      tombol.type = 'button';
      tombol.className = 'kolom-password__mata';
      tombol.setAttribute('aria-pressed', 'false');
      tombol.setAttribute('aria-label', 'Tampilkan password');
      if (kolom.id) tombol.setAttribute('aria-controls', kolom.id);
      tombol.innerHTML = MATA;
      bungkus.appendChild(tombol);

      tombol.addEventListener('click', function () {
        tombol.innerHTML = alihTampil(kolom, tombol) ? MATA_TUTUP : MATA;
        kolom.focus();
      });

      // sebelum dikirim, sembunyikan lagi agar tidak tersimpan sebagai teks biasa
      if (kolom.form) {
        kolom.form.addEventListener('submit', function () {
          if (kolom.type === 'text') {
            alihTampil(kolom, tombol);
            tombol.innerHTML = MATA;
          }
        });
      }
    })(kolomList[i]);
  }
})();
