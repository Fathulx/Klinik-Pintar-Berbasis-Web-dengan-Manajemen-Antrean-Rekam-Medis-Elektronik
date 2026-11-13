// Umpan balik saat formulir dikirim: tombol yang diklik menampilkan pemutar
// dan klik berikutnya diabaikan, supaya data tidak terkirim dua kali.
// Formulir yang dibatalkan lewat confirm() tidak terpengaruh.
(function () {
  var BATAS_MS = 10000; // jaga-jaga bila server tidak berpindah halaman (mis. unduhan)

  function pulihkan(form) {
    form.removeAttribute('data-sedang-kirim');
    var sibuk = form.querySelectorAll('[aria-busy="true"]');
    for (var i = 0; i < sibuk.length; i++) sibuk[i].removeAttribute('aria-busy');
  }

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (ev.defaultPrevented || !(form instanceof HTMLFormElement)) return;

    if (form.getAttribute('data-sedang-kirim') === '1') {
      ev.preventDefault();
      return;
    }
    form.setAttribute('data-sedang-kirim', '1');

    var tombol = ev.submitter || form.querySelector('button:not([type="button"]), input[type="submit"]');
    if (tombol && !tombol.classList.contains('notif-item')) {
      tombol.setAttribute('aria-busy', 'true');
    }
    setTimeout(function () { pulihkan(form); }, BATAS_MS);
  });

  // tombol Kembali di browser memulihkan halaman dari cache: kembalikan tombol
  window.addEventListener('pageshow', function (ev) {
    if (!ev.persisted) return;
    var forms = document.querySelectorAll('form[data-sedang-kirim]');
    for (var i = 0; i < forms.length; i++) pulihkan(forms[i]);
  });
})();
