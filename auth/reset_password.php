<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reset_password.php';

// token ada di URL: jangan bocorkan lewat header Referer
header('Referrer-Policy: no-referrer');

$token = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = post('token');
} elseif (isset($_GET['token']) && is_string($_GET['token'])) {
    $token = $_GET['token'];
}
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $baru = post('password_baru', false);
    if ($baru !== post('konfirmasi', false)) {
        $err['konfirmasi'] = 'Konfirmasi password tidak sama.';
    } else {
        $h = pakai_token_reset(db(), $token, $baru);
        if ($h['ok']) {
            catat_log('reset_password_email', null, $h['user_id']);
            set_flash('Password berhasil diubah. Silakan masuk.');
            redirect('auth/login.php');
        }
        $err['baru'] = $h['pesan'];
    }
}

$valid = cek_token_reset(db(), $token) !== null;

$kelasInput = 'mt-1.5 w-full rounded-xl border border-garis bg-white px-3 py-3 text-sm focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik';
$judul = 'Reset Password';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/public_nav.php';
?>
<main class="mx-auto w-full max-w-md flex-1 px-6 py-16">
  <div class="rounded-2xl border border-garis bg-white p-8 shadow-sm">
    <h1 class="font-serif text-2xl font-bold">Buat password baru</h1>
    <?php if (!$valid): ?>
      <div class="mt-5 border border-bata bg-bata/10 px-3 py-2 text-sm text-bata">
        Tautan reset tidak valid atau sudah kedaluwarsa.
      </div>
      <p class="mt-6 text-sm">
        <a class="text-klinik underline" href="<?= e(url('auth/lupa_password.php')) ?>">Minta tautan baru</a>
      </p>
    <?php else: ?>
      <p class="mt-2 text-sm text-ink/70">Minimal 8 karakter, berisi huruf dan angka.</p>
      <form method="post" class="mt-5 space-y-5" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
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
        <button class="w-full rounded-xl bg-klinik px-4 py-3 text-sm font-medium text-white hover:bg-ink">Simpan password</button>
      </form>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
