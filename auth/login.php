<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';

if (user_login()) {
    redirect(dashboard_path($_SESSION['user']['role']));
}

define('MAKS_GAGAL', 5);
define('DURASI_KUNCI', 900); // 15 menit

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email    = strtolower(post('email'));
    $password = post('password', false);
    $pdo      = db();

    $st = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $user = $st->fetch();
    $pesanGagal = 'Email atau password salah.';

    if (!$user) {
        // samakan waktu respons agar email yang terdaftar tidak bisa ditebak
        password_verify($password, password_hash('dummy', PASSWORD_DEFAULT));
        catat_log('login_gagal', 'email tidak dikenal: ' . substr($email, 0, 100));
        $error = $pesanGagal;
    } elseif ($user['terkunci_hingga'] && strtotime($user['terkunci_hingga']) > time()) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi beberapa menit lagi.';
    } elseif (!password_verify($password, $user['password'])) {
        $gagal = (int) $user['login_gagal'] + 1;
        if ($gagal >= MAKS_GAGAL) {
            $pdo->prepare('UPDATE users SET login_gagal = 0, terkunci_hingga = ? WHERE id = ?')
                ->execute([date('Y-m-d H:i:s', time() + DURASI_KUNCI), $user['id']]);
            catat_log('akun_terkunci', null, $user['id']);
        } else {
            $pdo->prepare('UPDATE users SET login_gagal = ? WHERE id = ?')
                ->execute([$gagal, $user['id']]);
        }
        catat_log('login_gagal', null, $user['id']);
        $error = $pesanGagal;
    } elseif ($user['status'] !== 'aktif') {
        $error = 'Akun belum aktif atau dinonaktifkan. Hubungi admin.';
    } else {
        $pdo->prepare('UPDATE users SET login_gagal = 0, terkunci_hingga = NULL WHERE id = ?')
            ->execute([$user['id']]);
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'   => (int) $user['id'],
            'nama' => $user['nama'],
            'role' => $user['role'],
        ];
        catat_log('login', null, $user['id']);
        redirect(dashboard_path($user['role']));
    }
}

// isi kolom kiri: praktik hari ini dan poli yang tersedia (data nyata)
$pdo     = db();
$hariIni = date('Y-m-d');

$st = $pdo->prepare(
    "SELECT ud.nama, p.nama_poli, SUBSTR(j.jam_mulai, 1, 5) AS jam_mulai,
            SUBSTR(j.jam_selesai, 1, 5) AS jam_selesai, j.kuota,
            (SELECT COUNT(*) FROM reservasi r
              WHERE r.jadwal_id = j.id AND r.tanggal = ? AND r.status <> 'batal') AS terisi
     FROM jadwal_dokter j
     JOIN dokter d ON d.id = j.dokter_id
     JOIN users ud ON ud.id = d.user_id AND ud.status = 'aktif'
     JOIN poli p ON p.id = d.poli_id
     WHERE j.aktif = 1 AND j.hari = ?
     ORDER BY j.jam_mulai, ud.nama LIMIT 4"
);
$st->execute([$hariIni, hari_dari_tanggal($hariIni)]);
$praktik = $st->fetchAll();

$poliList = $pdo->query(
    "SELECT DISTINCT p.nama_poli
     FROM poli p
     JOIN dokter d ON d.poli_id = p.id
     JOIN users u ON u.id = d.user_id AND u.status = 'aktif'
     WHERE EXISTS (SELECT 1 FROM jadwal_dokter j WHERE j.dokter_id = d.id AND j.aktif = 1)
     ORDER BY p.nama_poli"
)->fetchAll(PDO::FETCH_COLUMN);

$judul = 'Masuk';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/public_nav.php';
?>
<main class="mx-auto grid w-full max-w-5xl flex-1 gap-12 px-4 py-12 md:grid-cols-[minmax(0,25rem)_minmax(0,1fr)] md:items-start md:gap-16 md:py-16">
  <section>
    <h1 class="font-serif text-4xl font-bold text-ink">Masuk</h1>
    <p class="mt-2 text-ink/75">Untuk pasien, dokter, dan petugas klinik.</p>

    <div class="mt-6"><?= tampil_flash() ?></div>
    <?php if ($error): ?>
      <div class="mb-5 rounded-lg border border-bata bg-bata/10 px-3 py-2.5 text-bata" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-5" novalidate>
      <?= csrf_field() ?>
      <div>
        <label class="block font-bold" for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="username" required
               class="mt-1.5 w-full border border-garis bg-white px-3 py-3 text-base focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik">
      </div>
      <div>
        <div class="flex items-baseline justify-between gap-3">
          <label class="block font-bold" for="password">Password</label>
          <a class="text-sm font-bold text-klinik underline" href="<?= e(url('auth/lupa_password.php')) ?>">Lupa password?</a>
        </div>
        <input id="password" name="password" type="password" autocomplete="current-password" required
               class="mt-1.5 w-full border border-garis bg-white px-3 py-3 text-base focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik">
      </div>
      <button class="w-full bg-klinik px-4 py-3 text-base font-bold text-white">Masuk</button>
    </form>

    <p class="mt-6 border-t border-garis pt-5">Belum punya akun?
      <a class="font-bold text-klinik underline" href="<?= e(url('auth/register.php')) ?>">Daftar sebagai pasien</a>
    </p>
    <p class="mt-2 text-sm text-ink/65">Akun dokter dan admin dibuat oleh pengelola klinik.</p>
  </section>

  <aside class="layar-antrean" aria-label="Praktik hari ini">
    <div class="layar-antrean__kepala">
      <strong>Praktik hari ini</strong>
      <span class="layar-antrean__jam"><?= e(tanggal_indonesia($hariIni)) ?></span>
    </div>
    <?php foreach ($praktik as $p): ?>
      <div class="layar-antrean__baris">
        <div>
          <span class="poli-tanda <?= kelas_poli($p['nama_poli']) ?>">Poli <?= e($p['nama_poli']) ?></span>
          <span class="layar-antrean__dokter"><?= e($p['nama']) ?>, <?= e($p['jam_mulai']) ?>-<?= e($p['jam_selesai']) ?></span>
        </div>
        <span class="layar-antrean__nomor layar-antrean__nomor--kosong"><?= (int) $p['terisi'] ?>/<?= (int) $p['kuota'] ?> terisi</span>
      </div>
    <?php endforeach; ?>
    <?php if (!$praktik): ?>
      <p class="layar-antrean__kosong">Tidak ada jadwal praktik hari ini.<?= $poliList ? ' Poli tersedia: ' . e(implode(', ', $poliList)) . '.' : '' ?></p>
    <?php endif; ?>
  </aside>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
