<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_role('super_admin');
$pdo  = db();
$err  = [];
$old  = ['nama' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = post('aksi');

    if ($aksi === 'tambah') {
        $old['nama']  = post('nama');
        $old['email'] = strtolower(post('email'));

        $len = mb_strlen($old['nama']);
        if ($len < 3 || $len > 100) {
            $err['nama'] = 'Nama harus 3 sampai 100 karakter.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 120) {
            $err['email'] = 'Format email tidak valid.';
        }
        if (!$err) {
            $cek = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
            $cek->execute([$old['email']]);
            if ($cek->fetchColumn()) {
                $err['email'] = 'Email sudah terdaftar.';
            }
        }
        if (!$err) {
            $sementara = buat_password_sementara();
            try {
                $pdo->prepare(
                    "INSERT INTO users (nama, email, password, role, status, wajib_ganti_password)
                     VALUES (?, ?, ?, 'admin', 'aktif', 1)"
                )->execute([$old['nama'], $old['email'], password_hash($sementara, PASSWORD_DEFAULT)]);
                catat_log('tambah_admin', $old['email']);
                set_flash('Admin ditambahkan. Password sementara: ' . $sementara
                    . ' (hanya tampil sekali, catat sekarang).');
                redirect('superadmin/kelola_admin.php');
            } catch (PDOException $ex) {
                $err['umum'] = $ex->getCode() === '23000'
                    ? 'Email sudah terdaftar.'
                    : 'Terjadi kesalahan. Silakan coba lagi.';
            }
        }
    } elseif ($aksi === 'status' || $aksi === 'reset') {
        $st = $pdo->prepare("SELECT id, email FROM users WHERE id = ? AND role = 'admin'");
        $st->execute([(int) post('id')]);
        $target = $st->fetch();

        if (!$target) {
            set_flash('Akun admin tidak ditemukan.', 'error');
        } elseif ($aksi === 'status') {
            $baru = post('status_baru');
            if (in_array($baru, ['aktif', 'nonaktif'], true)) {
                $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')
                    ->execute([$baru, $target['id']]);
                catat_log('ubah_status_admin', $target['email'] . ' menjadi ' . $baru);
                set_flash('Status akun diubah menjadi ' . $baru . '.');
            }
        } else {
            $sementara = buat_password_sementara();
            $pdo->prepare(
                'UPDATE users SET password = ?, wajib_ganti_password = 1,
                 login_gagal = 0, terkunci_hingga = NULL WHERE id = ?'
            )->execute([password_hash($sementara, PASSWORD_DEFAULT), $target['id']]);
            catat_log('reset_password_admin', $target['email']);
            set_flash('Password direset. Password sementara: ' . $sementara
                . ' (hanya tampil sekali, catat sekarang).');
        }
        redirect('superadmin/kelola_admin.php');
    }
}

$daftar = $pdo->query(
    "SELECT id, nama, email, status FROM users WHERE role = 'admin' ORDER BY nama"
)->fetchAll();

$kelasInput = 'mt-1 w-full border border-garis bg-white px-3 py-2 text-sm focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik';
$judul = 'Kelola Admin';
$aktif = 'superadmin/kelola_admin.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Kelola admin</h1>
<p class="mt-1 text-sm text-ink/70">Akun admin hanya dibuat di halaman ini. Admin wajib mengganti password sementara saat login pertama.</p>

<div class="mt-6 grid gap-8 lg:grid-cols-3">
  <section class="overflow-x-auto lg:col-span-2">
    <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
      <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
        <tr><th class="px-3 py-2">Nama</th><th class="px-3 py-2">Email</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($daftar as $a): ?>
          <tr class="border-t border-garis">
            <td data-label="Nama" class="px-3 py-2"><?= e($a['nama']) ?></td>
            <td data-label="Email" class="px-3 py-2 font-mono text-xs"><?= e($a['email']) ?></td>
            <td data-label="Status" class="px-3 py-2"><?= badge_status($a['status']) ?></td>
            <td data-label="Aksi" class="whitespace-nowrap px-3 py-2">
              <form method="post" class="inline" onsubmit="return confirm('Ubah status akun ini?')">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="status">
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <input type="hidden" name="status_baru" value="<?= $a['status'] === 'aktif' ? 'nonaktif' : 'aktif' ?>">
                <button class="action-button"><?= $a['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?></button>
              </form>
              <span class="mx-1 text-ink/30">|</span>
              <form method="post" class="inline" onsubmit="return confirm('Reset password akun ini?')">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="reset">
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button class="action-button">Reset password</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$daftar): ?>
          <tr><td colspan="4" class="px-3 py-4 text-ink/60">Belum ada akun admin.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section>
    <div class="border border-garis bg-white p-5">
      <h2 class="font-serif text-xl font-bold">Tambah admin</h2>
      <?php if (isset($err['umum'])): ?>
        <div class="mt-3 border border-bata bg-bata/10 px-3 py-2 text-sm text-bata"><?= e($err['umum']) ?></div>
      <?php endif; ?>
      <form method="post" class="mt-4 space-y-4" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah">
        <div>
          <label class="block text-sm font-medium" for="nama">Nama lengkap</label>
          <input id="nama" name="nama" value="<?= e($old['nama']) ?>" class="<?= $kelasInput ?>">
          <?= pesan_field($err, 'nama') ?>
        </div>
        <div>
          <label class="block text-sm font-medium" for="email">Email</label>
          <input id="email" name="email" type="email" value="<?= e($old['email']) ?>" class="<?= $kelasInput ?>">
          <?= pesan_field($err, 'email') ?>
        </div>
        <button class="w-full bg-klinik px-4 py-2 text-sm font-medium text-white hover:bg-ink">Buat akun admin</button>
      </form>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>