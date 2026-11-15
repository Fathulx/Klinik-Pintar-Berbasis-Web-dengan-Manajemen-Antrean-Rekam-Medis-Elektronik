<?php
require_once __DIR__ . '/ikon.php';
$halamanIni = basename($_SERVER['SCRIPT_NAME'] ?? '');
$diMasuk    = in_array($halamanIni, ['login.php', 'lupa_password.php', 'reset_password.php'], true);
$diDaftar   = $halamanIni === 'register.php';
?>
<header class="border-b border-garis bg-white">
  <div class="mx-auto flex max-w-5xl items-center justify-between gap-2 px-4 py-4">
    <a href="<?= e(url('')) ?>" class="merek"><span class="merek__tanda" aria-hidden="true"></span>Klinik Pintar</a>
    <nav class="public-nav" aria-label="Akun">
      <?= tombol_tema_html() ?>
      <a class="public-nav__masuk" href="<?= e(url('auth/login.php')) ?>"<?= $diMasuk ? ' aria-current="page"' : '' ?>>Masuk</a>
      <a class="public-nav__daftar" href="<?= e(url('auth/register.php')) ?>"<?= $diDaftar ? ' aria-current="page"' : '' ?>>Daftar</a>
    </nav>
  </div>
</header>
