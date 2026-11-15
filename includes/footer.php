  <footer class="mt-auto border-t border-garis">
    <p class="<?= empty($footerPanel) ? 'mx-auto max-w-5xl' : 'md:ml-60 md:px-8' ?> px-4 py-4 text-xs text-ink/70 print:hidden">
      Aplikasi demo untuk tugas kuliah, bukan layanan kesehatan resmi.
      Jangan memasukkan data pribadi atau data kesehatan yang asli.
    </p>
  </footer>
  <script src="<?= e(url('assets/tombol.js?v=' . filemtime(__DIR__ . '/../assets/tombol.js'))) ?>" defer></script>
  <script src="<?= e(url('assets/lihat_password.js?v=' . filemtime(__DIR__ . '/../assets/lihat_password.js'))) ?>" defer></script>
</body>
</html>