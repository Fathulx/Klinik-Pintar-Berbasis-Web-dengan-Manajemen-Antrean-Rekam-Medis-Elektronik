<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_login();
$err  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $lama = post('password_lama', false);
    $baru = post('password_baru', false);
    $konf = post('konfirmasi', false);

    $st = db()->prepare('SELECT password FROM users WHERE id = ?');
    $st->execute([$user['id']]);
    $hash = $st->fetchColumn();

    if (!password_verify($lama, $hash)) {
        $err['lama'] = 'Password saat ini salah.';
    }
    $errPw = validasi_password($baru);
    if ($errPw) {
        $err['baru'] = $errPw;
    } elseif ($baru === $lama) {
        $err['baru'] = 'Password baru tidak boleh sama dengan yang lama.';
    } elseif ($baru !== $konf) {
        $err['konfirmasi'] = 'Konfirmasi password tidak sama.';
    }

    if (!$err) {
        db()->prepare('UPDATE users SET password = ?, wajib_ganti_password = 0 WHERE id = ?')
            ->execute([password_hash($baru, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);
        catat_log('ganti_password');
        set_flash('Password berhasil diubah.');
        redirect(dashboard_path($user['role']));
    }
}

$kelasInput = 'mt-1 w-full border border-garis bg-white px-3 py-2 text-sm focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik';
$judul = 'Ganti Password';
require __DIR__ . '/../includes/header.php';
?>
<main class="mx-auto w-full max-w-md flex-1 px-4 py-12">
  <h1 class="font-serif text-3xl font-bold text-klinik">Ganti password</h1>
  <?php if ($user['wajib_ganti']): ?>
    <p class="mt-2 text-sm text-bata">Akun Anda memakai password sementara. Ganti password sebelum melanjutkan.</p>
  <?php endif; ?>
  <div class="mt-6 border border-garis bg-white p-6">
    <form method="post" class="space-y-4" novalidate>
      <?= csrf_field() ?>
      <div>
        <label class="block text-sm font-medium" for="password_lama">Password saat ini</label>
        <input id="password_lama" name="password_lama" type="password" autocomplete="current-password" class="<?= $kelasInput ?>">
        <?= pesan_field($err, 'lama') ?>
      </div>
      <div>
        <label class="block text-sm font-medium" for="password_baru">Password baru</label>
        <input id="password_baru" name="password_baru" type="password" autocomplete="new-password" class="<?= $kelasInput ?>">
        <?= pesan_field($err, 'baru') ?>
      </div>
      <div>
        <label class="block text-sm font-medium" for="konfirmasi">Ulangi password baru</label>
        <input id="konfirmasi" name="konfirmasi" type="password" autocomplete="new-password" class="<?= $kelasInput ?>">
        <?= pesan_field($err, 'konfirmasi') ?>
      </div>
      <button class="w-full bg-klinik px-4 py-2 text-sm font-medium text-white hover:bg-ink">Simpan password</button>
    </form>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>