<?php
require_once __DIR__ . '/../includes/auth.php';

if (user_login()) {
    redirect(dashboard_path($_SESSION['user']['role']));
}

$err = [];
$old = ['nama' => '', 'email' => '', 'no_hp' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $old['nama']  = post('nama');
    $old['email'] = strtolower(post('email'));
    $old['no_hp'] = post('no_hp');
    $pw           = post('password', false);
    $konf         = post('konfirmasi', false);

    $len = mb_strlen($old['nama']);
    if ($len < 3 || $len > 100) {
        $err['nama'] = 'Nama harus 3 sampai 100 karakter.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 120) {
        $err['email'] = 'Format email tidak valid.';
    }
    if ($old['no_hp'] !== '' && !preg_match('/^[0-9+]{8,15}$/', $old['no_hp'])) {
        $err['no_hp'] = 'Nomor HP harus 8 sampai 15 digit.';
    }
    $errPw = validasi_password($pw);
    if ($errPw) {
        $err['password'] = $errPw;
    } elseif ($pw !== $konf) {
        $err['konfirmasi'] = 'Konfirmasi password tidak sama.';
    }

    $pdo = db();
    if (!$err) {
        $cek = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $cek->execute([$old['email']]);
        if ($cek->fetchColumn()) {
            $err['email'] = 'Email sudah terdaftar.';
        }
    }

    if (!$err) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO users (nama, email, password, role, status)
                 VALUES (?, ?, ?, 'user', 'aktif')"
            )->execute([$old['nama'], $old['email'], password_hash($pw, PASSWORD_DEFAULT)]);
            $idBaru = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO pasien (user_id, no_hp) VALUES (?, ?)')
                ->execute([$idBaru, $old['no_hp'] === '' ? null : $old['no_hp']]);
            $pdo->commit();

            catat_log('registrasi', null, $idBaru);
            set_flash('Registrasi berhasil. Silakan masuk.');
            redirect('auth/login.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $err['umum'] = $ex->getCode() === '23000'
                ? 'Email sudah terdaftar.'
                : 'Terjadi kesalahan. Silakan coba lagi.';
        }
    }
}

$kelasInput = 'mt-1 w-full border border-garis bg-white px-3 py-2 text-sm focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik';
$judul = 'Daftar Pasien';
require __DIR__ . '/../includes/header.php';
?>
<main class="mx-auto w-full max-w-md flex-1 px-4 py-12">
  <p class="font-mono text-xs uppercase tracking-widest text-ink/60">Pendaftaran pasien</p>
  <h1 class="mt-2 font-serif text-3xl font-bold text-klinik">Buat akun pasien</h1>
  <div class="mt-6 border border-garis bg-white p-6">
    <?php if (isset($err['umum'])): ?>
      <div class="mb-4 border border-bata bg-bata/10 px-3 py-2 text-sm text-bata"><?= e($err['umum']) ?></div>
    <?php endif; ?>
    <form method="post" class="space-y-4" novalidate>
      <?= csrf_field() ?>
      <div>
        <label class="block text-sm font-medium" for="nama">Nama lengkap</label>
        <input id="nama" name="nama" value="<?= e($old['nama']) ?>" class="<?= $kelasInput ?>">
        <?= pesan_field($err, 'nama') ?>
      </div>
      <div>
        <label class="block text-sm font-medium" for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e($old['email']) ?>" autocomplete="username" class="<?= $kelasInput ?>">
        <?= pesan_field($err, 'email') ?>
      </div>
      <div>
        <label class="block text-sm font-medium" for="no_hp">Nomor HP <span class="text-ink/50">(opsional)</span></label>
        <input id="no_hp" name="no_hp" value="<?= e($old['no_hp']) ?>" class="<?= $kelasInput ?>">
        <?= pesan_field($err, 'no_hp') ?>
      </div>
      <div>
        <label class="block text-sm font-medium" for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" class="<?= $kelasInput ?>">
        <p class="mt-1 text-xs text-ink/60">Minimal 8 karakter, berisi huruf dan angka.</p>
        <?= pesan_field($err, 'password') ?>
      </div>
      <div>
        <label class="block text-sm font-medium" for="konfirmasi">Ulangi password</label>
        <input id="konfirmasi" name="konfirmasi" type="password" autocomplete="new-password" class="<?= $kelasInput ?>">
        <?= pesan_field($err, 'konfirmasi') ?>
      </div>
      <button class="w-full bg-klinik px-4 py-2 text-sm font-medium text-white hover:bg-ink">Daftar</button>
    </form>
    <p class="mt-4 text-sm">Sudah punya akun?
      <a class="text-klinik underline" href="<?= e(url('auth/login.php')) ?>">Masuk</a>
    </p>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>