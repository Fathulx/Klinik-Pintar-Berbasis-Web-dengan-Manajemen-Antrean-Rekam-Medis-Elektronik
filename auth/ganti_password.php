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
        // dari halaman akun kembali ke sana; setelah password sementara ke dashboard
        redirect($user['wajib_ganti'] ? dashboard_path($user['role']) : 'auth/akun.php');
    }
}

$kelasInput = 'mt-1 w-full border border-garis bg-white px-3 py-2 text-sm focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik';
$judul = 'Ganti Password';
// Password sementara: halaman polos, menu lain belum boleh dibuka.
// Selain itu tampil di dalam panel, di bawah menu "Akun saya".
$polos = $user['wajib_ganti'];
if ($polos) {
    require __DIR__ . '/../includes/header.php';
} else {
    $aktif = 'auth/akun.php';
    require __DIR__ . '/../includes/panel_start.php';
}
?>
<?php if ($polos): ?><main class="mx-auto w-full max-w-md flex-1 px-4 py-12"><?php else: ?><div class="max-w-md"><a class="text-sm font-semibold text-klinik underline" href="<?= e(url('auth/akun.php')) ?>">Kembali ke akun saya</a><?php endif; ?>
  <h1 class="<?= $polos ? '' : 'mt-3 ' ?>font-serif text-4xl font-bold">Ganti password</h1>
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
      <button class="w-full bg-klinik px-4 py-2.5 font-semibold text-white">Simpan password</button>
    </form>
  </div>
<?php if ($polos): ?></main><?php require __DIR__ . '/../includes/footer.php'; ?>
<?php else: ?></div><?php require __DIR__ . '/../includes/panel_end.php'; ?>
<?php endif; ?>