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
<main class="mx-auto w-full max-w-6xl flex-1 px-6 py-16 md:py-20">
  <div class="grid items-center gap-12 md:grid-cols-2 md:gap-20">

    <section class="order-2 md:order-1">
      <p class="font-mono text-xs uppercase tracking-widest text-ink/60">Akses pengguna</p>
      <h1 class="mt-4 font-serif text-4xl font-bold leading-tight text-klinik">
        Masuk untuk melihat antrean dan riwayat kunjungan Anda.
      </h1>
      <p class="mt-5 max-w-md text-sm leading-relaxed text-ink/80">
        Pasien mendaftar sendiri. Akun dokter dan admin dibuat oleh pengelola klinik.
      </p>

      <div class="mt-10 max-w-md rounded-2xl border border-garis bg-white p-6">
        <div class="flex items-baseline justify-between gap-3">
          <p class="font-mono text-[11px] uppercase tracking-widest text-ink/60">Praktik hari ini</p>
          <p class="text-xs text-ink/60"><?= e(tanggal_indonesia($hariIni)) ?></p>
        </div>

        <?php if ($praktik): ?>
          <div class="mt-4 divide-y divide-garis">
            <?php foreach ($praktik as $p): ?>
              <div class="flex items-baseline justify-between gap-4 py-3 text-sm">
                <span>
                  <?= e($p['nama']) ?>
                  <span class="mt-0.5 block text-xs text-ink/60">
                    Poli <?= e($p['nama_poli']) ?> &middot;
                    <span class="font-mono"><?= e($p['jam_mulai']) ?>-<?= e($p['jam_selesai']) ?></span>
                  </span>
                </span>
                <span class="whitespace-nowrap font-mono text-xs"><?= (int) $p['terisi'] ?>/<?= (int) $p['kuota'] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="mt-1 text-xs text-ink/50">Angka di kanan: antrean terisi dari kuota.</p>
        <?php else: ?>
          <p class="mt-4 text-sm text-ink/70">Tidak ada jadwal praktik hari ini.</p>
        <?php endif; ?>

        <?php if ($poliList): ?>
          <div class="mt-5 border-t border-garis pt-4">
            <p class="font-mono text-[11px] uppercase tracking-widest text-ink/60">Poli tersedia</p>
            <p class="mt-2 font-mono text-xs leading-relaxed text-klinik"><?= e(implode(' / ', $poliList)) ?></p>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="order-1 w-full max-w-md md:order-2 md:justify-self-end">
      <div class="rounded-2xl border border-garis bg-white p-8 shadow-sm">
        <h2 class="font-serif text-2xl font-bold">Masuk</h2>
        <div class="mt-5"><?= tampil_flash() ?></div>
        <?php if ($error): ?>
          <div class="mb-5 border border-bata bg-bata/10 px-3 py-2 text-sm text-bata"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" class="space-y-5" novalidate>
          <?= csrf_field() ?>
          <div>
            <label class="block text-sm font-medium" for="email">Email</label>
            <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="username" required
                   class="mt-1.5 w-full rounded-xl border border-garis bg-white px-3 py-3 text-sm focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik">
          </div>
          <div>
            <label class="block text-sm font-medium" for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   class="mt-1.5 w-full rounded-xl border border-garis bg-white px-3 py-3 text-sm focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik">
          </div>
          <button class="w-full rounded-xl bg-klinik px-4 py-3 text-sm font-medium text-white hover:bg-ink">Masuk</button>
        </form>
        <p class="mt-6 text-sm">Belum punya akun?
          <a class="text-klinik underline" href="<?= e(url('auth/register.php')) ?>">Daftar sebagai pasien</a>
        </p>
      </div>
    </section>

  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>