<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/akun_saya.php';

$user  = require_login();
$akun  = detail_akun(db(), $user['id']);
$judul = 'Akun Saya';
$aktif = 'auth/akun.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-4xl font-bold">Akun saya</h1>

<section class="akun-profil mt-6" aria-label="Profil">
  <span class="akun-avatar akun-avatar--besar" aria-hidden="true"><?= e($akun['inisial']) ?></span>
  <div class="min-w-0">
    <p class="text-2xl font-bold"><?= e($akun['nama']) ?></p>
    <p class="mt-0.5 text-ink/70"><?= e($akun['peran']) ?></p>
  </div>
</section>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
  <section class="akun-panel" aria-labelledby="judul-data">
    <h2 id="judul-data" class="font-serif text-xl font-bold">Data akun</h2>
    <dl class="akun-rincian mt-3">
      <div><dt>Nama</dt><dd><?= e($akun['nama']) ?></dd></div>
      <div><dt>Email</dt><dd><?= e($akun['email']) ?></dd></div>
      <div><dt>Peran</dt><dd><?= e($akun['peran']) ?></dd></div>
      <?php foreach ($akun['rincian'] as $label => $nilai): ?>
        <div><dt><?= e($label) ?></dt><dd><?= e($nilai) ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <p class="mt-3 text-sm text-ink/65">
      <?= $user['role'] === 'user'
        ? 'Untuk mengubah nama atau email, hubungi petugas klinik.'
        : 'Data akun dikelola oleh ' . ($user['role'] === 'admin' ? 'Super Admin.' : ($user['role'] === 'dokter' ? 'Admin klinik.' : 'Super Admin lain.')) ?>
    </p>
  </section>

  <section class="akun-panel" aria-labelledby="judul-keamanan">
    <h2 id="judul-keamanan" class="font-serif text-xl font-bold">Keamanan</h2>
    <p class="mt-2 text-ink/75">
      Gunakan password minimal 8 karakter berisi huruf dan angka, dan jangan
      memberikannya kepada siapa pun, termasuk petugas klinik.
    </p>
    <div class="mt-4 flex flex-wrap gap-3">
      <a class="bg-klinik px-4 py-2.5 font-semibold text-white" href="<?= e(url('auth/ganti_password.php')) ?>">Ganti password</a>
      <form method="post" action="<?= e(url('auth/logout.php')) ?>">
        <?= csrf_field() ?>
        <button class="action-button">Keluar</button>
      </form>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>
