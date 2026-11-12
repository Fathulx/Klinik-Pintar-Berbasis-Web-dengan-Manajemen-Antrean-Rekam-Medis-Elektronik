<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reset_password.php';
require_once __DIR__ . '/../includes/mailer.php';

if (user_login()) {
    redirect(dashboard_path($_SESSION['user']['role']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(post('email'));
    $ip    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

    if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 120) {
        $h = minta_reset_password(db(), $email, $ip);
        catat_log('lupa_password_minta', substr($email, 0, 100));
        if ($h['kirim']) {
            $tautan = url_absolut('auth/reset_password.php?token=' . $h['token']);
            $html = '<p>Halo ' . e($h['nama']) . ',</p>'
                . '<p>Kami menerima permintaan untuk mengatur ulang password akun Klinik Pintar Anda.</p>'
                . '<p><a href="' . e($tautan) . '">Atur ulang password</a></p>'
                . '<p>Tautan ini berlaku ' . RESET_MENIT_BERLAKU . ' menit dan hanya dapat dipakai sekali.</p>'
                . '<p>Jika Anda tidak memintanya, abaikan email ini. Password Anda tidak berubah.</p>';
            $teks = "Halo {$h['nama']},\n\n"
                . "Kami menerima permintaan untuk mengatur ulang password akun Klinik Pintar Anda.\n"
                . "Buka tautan berikut:\n$tautan\n\n"
                . 'Tautan ini berlaku ' . RESET_MENIT_BERLAKU . " menit dan hanya dapat dipakai sekali.\n"
                . "Jika Anda tidak memintanya, abaikan email ini. Password Anda tidak berubah.\n";
            if (!kirim_email($h['email'], $h['nama'], 'Reset password Klinik Pintar', $html, $teks)) {
                catat_log('lupa_password_email_gagal', null, null);
            }
        }
    }
    // pesan selalu sama agar tidak bisa dipakai menebak email yang terdaftar
    set_flash('Jika email terdaftar sebagai pasien, tautan reset telah dikirim. '
        . 'Untuk akun dokter atau admin, hubungi pengelola klinik.');
    redirect('auth/lupa_password.php');
}

$judul = 'Lupa Password';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/public_nav.php';
?>
<main class="mx-auto w-full max-w-md flex-1 px-6 py-16">
  <div class="rounded-2xl border border-garis bg-white p-8 shadow-sm">
    <h1 class="font-serif text-2xl font-bold">Lupa password</h1>
    <p class="mt-2 text-sm text-ink/70">
      Masukkan email akun pasien Anda. Kami akan mengirim tautan untuk membuat password baru.
    </p>
    <div class="mt-5"><?= tampil_flash() ?></div>
    <form method="post" class="space-y-5" novalidate>
      <?= csrf_field() ?>
      <div>
        <label class="block text-sm font-medium" for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" required
               class="mt-1.5 w-full rounded-xl border border-garis bg-white px-3 py-3 text-sm focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik">
      </div>
      <button class="w-full rounded-xl bg-klinik px-4 py-3 text-sm font-medium text-white hover:bg-ink">Kirim tautan reset</button>
    </form>
    <p class="mt-6 text-sm">
      <a class="text-klinik underline" href="<?= e(url('auth/login.php')) ?>">Kembali ke halaman masuk</a>
    </p>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
