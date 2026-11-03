<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_role('admin');
$pdo  = db();
$err  = [];
$form = ['id' => 0, 'nama' => '', 'email' => '', 'poli_id' => 0, 'no_str' => '', 'bio' => ''];

$poliList = $pdo->query('SELECT id, nama_poli FROM poli ORDER BY nama_poli')->fetchAll();
$poliIds  = array_map('intval', array_column($poliList, 'id'));

if (isset($_GET['edit']) && is_string($_GET['edit'])) {
    $st = $pdo->prepare(
        'SELECT d.id, u.nama, u.email, d.poli_id, d.no_str, d.bio
         FROM dokter d JOIN users u ON u.id = d.user_id WHERE d.id = ?'
    );
    $st->execute([(int) $_GET['edit']]);
    $baris = $st->fetch();
    if ($baris) {
        $form = $baris;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = post('aksi');

    if ($aksi === 'simpan') {
        $form = [
            'id'      => (int) post('id'),
            'nama'    => post('nama'),
            'email'   => strtolower(post('email')),
            'poli_id' => (int) post('poli_id'),
            'no_str'  => post('no_str'),
            'bio'     => post('bio'),
        ];

        $len = mb_strlen($form['nama']);
        if ($len < 3 || $len > 100) {
            $err['nama'] = 'Nama harus 3 sampai 100 karakter.';
        }
        if ($form['id'] === 0) {
            if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 120) {
                $err['email'] = 'Format email tidak valid.';
            } else {
                $cek = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
                $cek->execute([$form['email']]);
                if ($cek->fetchColumn()) {
                    $err['email'] = 'Email sudah terdaftar.';
                }
            }
        }
        if (!in_array($form['poli_id'], $poliIds, true)) {
            $err['poli_id'] = 'Pilih poli.';
        }
        if (!preg_match('/^[A-Za-z0-9\/.\-]{3,40}$/', $form['no_str'])) {
            $err['no_str'] = 'Nomor STR 3 sampai 40 karakter (huruf, angka, / . -).';
        } else {
            $cek = $pdo->prepare('SELECT 1 FROM dokter WHERE no_str = ? AND id <> ?');
            $cek->execute([$form['no_str'], $form['id']]);
            if ($cek->fetchColumn()) {
                $err['no_str'] = 'Nomor STR sudah dipakai dokter lain.';
            }
        }
        if (mb_strlen($form['bio']) > 255) {
            $err['bio'] = 'Bio maksimal 255 karakter.';
        }

        if (!$err) {
            $bio = $form['bio'] === '' ? null : $form['bio'];
            try {
                $pdo->beginTransaction();
                if ($form['id'] > 0) {
                    $st = $pdo->prepare('SELECT user_id FROM dokter WHERE id = ?');
                    $st->execute([$form['id']]);
                    $uid = $st->fetchColumn();
                    if (!$uid) {
                        $pdo->rollBack();
                        set_flash('Data dokter tidak ditemukan.', 'error');
                        redirect('admin/dokter.php');
                    }
                    $pdo->prepare('UPDATE users SET nama = ? WHERE id = ?')
                        ->execute([$form['nama'], $uid]);
                    $pdo->prepare('UPDATE dokter SET poli_id = ?, no_str = ?, bio = ? WHERE id = ?')
                        ->execute([$form['poli_id'], $form['no_str'], $bio, $form['id']]);
                    catat_log('ubah_dokter', $form['nama']);
                    $pesan = 'Data dokter diperbarui.';
                } else {
                    $sementara = buat_password_sementara();
                    $pdo->prepare(
                        "INSERT INTO users (nama, email, password, role, status, wajib_ganti_password)
                         VALUES (?, ?, ?, 'dokter', 'aktif', 1)"
                    )->execute([$form['nama'], $form['email'], password_hash($sementara, PASSWORD_DEFAULT)]);
                    $uid = (int) $pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO dokter (user_id, poli_id, no_str, bio) VALUES (?, ?, ?, ?)')
                        ->execute([$uid, $form['poli_id'], $form['no_str'], $bio]);
                    catat_log('tambah_dokter', $form['email']);
                    $pesan = 'Dokter ditambahkan. Password sementara: ' . $sementara
                        . ' (hanya tampil sekali, catat sekarang).';
                }
                $pdo->commit();
                set_flash($pesan);
                redirect('admin/dokter.php');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $err['umum'] = $ex->getCode() === '23000'
                    ? 'Email atau nomor STR sudah terdaftar.'
                    : 'Terjadi kesalahan. Silakan coba lagi.';
            }
        }
    } elseif ($aksi === 'status' || $aksi === 'reset') {
        $st = $pdo->prepare(
            'SELECT u.id, u.email FROM dokter d JOIN users u ON u.id = d.user_id WHERE d.id = ?'
        );
        $st->execute([(int) post('id')]);
        $target = $st->fetch();

        if (!$target) {
            set_flash('Data dokter tidak ditemukan.', 'error');
        } elseif ($aksi === 'status') {
            $baru = post('status_baru');
            if (in_array($baru, ['aktif', 'nonaktif'], true)) {
                $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')
                    ->execute([$baru, $target['id']]);
                catat_log('ubah_status_dokter', $target['email'] . ' menjadi ' . $baru);
                set_flash('Status dokter diubah menjadi ' . $baru . '.');
            }
        } else {
            $sementara = buat_password_sementara();
            $pdo->prepare(
                'UPDATE users SET password = ?, wajib_ganti_password = 1,
                 login_gagal = 0, terkunci_hingga = NULL WHERE id = ?'
            )->execute([password_hash($sementara, PASSWORD_DEFAULT), $target['id']]);
            catat_log('reset_password_dokter', $target['email']);
            set_flash('Password direset. Password sementara: ' . $sementara
                . ' (hanya tampil sekali, catat sekarang).');
        }
        redirect('admin/dokter.php');
    }
}

$daftar = $pdo->query(
    'SELECT d.id, u.nama, u.email, u.status, p.nama_poli, d.no_str,
            (SELECT COUNT(*) FROM jadwal_dokter j WHERE j.dokter_id = d.id AND j.aktif = 1) AS jml_jadwal
     FROM dokter d
     JOIN users u ON u.id = d.user_id
     JOIN poli p ON p.id = d.poli_id
     ORDER BY u.nama'
)->fetchAll();

$kelasInput = 'mt-1 w-full border border-garis bg-white px-3 py-2 text-sm focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik';
$judul = 'Kelola Dokter';
$aktif = 'admin/dokter.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Kelola dokter</h1>
<p class="mt-1 text-sm text-ink/70">
  Dokter tidak dapat mendaftar sendiri. Akun dibuat di sini dengan password sementara.
  Dokter yang pernah melayani pasien tidak dihapus, cukup dinonaktifkan.
</p>

<div class="mt-6 grid gap-8 lg:grid-cols-3">
  <section class="overflow-x-auto lg:col-span-2">
    <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
      <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
        <tr>
          <th class="px-3 py-2">Dokter</th><th class="px-3 py-2">Poli</th>
          <th class="px-3 py-2">Jadwal</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar as $d): ?>
          <tr class="border-t border-garis align-top">
            <td data-label="Dokter" class="px-3 py-2">
              <p class="font-medium"><?= e($d['nama']) ?></p>
              <p class="font-mono text-xs text-ink/60"><?= e($d['email']) ?></p>
              <p class="font-mono text-xs text-ink/60">STR <?= e($d['no_str']) ?></p>
            </td>
            <td data-label="Poli" class="px-3 py-2"><?= e($d['nama_poli']) ?></td>
            <td data-label="Jadwal" class="px-3 py-2 font-mono"><?= (int) $d['jml_jadwal'] ?></td>
            <td data-label="Status" class="px-3 py-2"><?= badge_status($d['status']) ?></td>
            <td data-label="Aksi" class="whitespace-nowrap px-3 py-2">
              <a class="action-button underline" href="<?= e(url('admin/dokter.php?edit=' . (int) $d['id'])) ?>">Ubah</a>
              <span class="mx-1 text-ink/30">|</span>
              <form method="post" class="inline" onsubmit="return confirm('Ubah status dokter ini?')">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="status">
                <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                <input type="hidden" name="status_baru" value="<?= $d['status'] === 'aktif' ? 'nonaktif' : 'aktif' ?>">
                <button class="action-button"><?= $d['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?></button>
              </form>
              <span class="mx-1 text-ink/30">|</span>
              <form method="post" class="inline" onsubmit="return confirm('Reset password dokter ini?')">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="reset">
                <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                <button class="action-button">Reset password</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$daftar): ?>
          <tr><td colspan="5" class="px-3 py-4 text-ink/60">Belum ada dokter.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section>
    <div class="border border-garis bg-white p-5">
      <h2 class="font-serif text-xl font-bold"><?= $form['id'] > 0 ? 'Ubah dokter' : 'Tambah dokter' ?></h2>
      <?php if (isset($err['umum'])): ?>
        <div class="mt-3 border border-bata bg-bata/10 px-3 py-2 text-sm text-bata"><?= e($err['umum']) ?></div>
      <?php endif; ?>
      <form method="post" class="mt-4 space-y-4" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div>
          <label class="block text-sm font-medium" for="nama">Nama dan gelar</label>
          <input id="nama" name="nama" value="<?= e($form['nama']) ?>" class="<?= $kelasInput ?>">
          <?= pesan_field($err, 'nama') ?>
        </div>
        <div>
          <label class="block text-sm font-medium" for="email">Email</label>
          <input id="email" name="email" type="email" value="<?= e($form['email']) ?>"
                 <?= $form['id'] > 0 ? 'readonly' : '' ?>
                 class="<?= $kelasInput ?> <?= $form['id'] > 0 ? 'bg-paper text-ink/60' : '' ?>">
          <?php if ($form['id'] > 0): ?>
            <p class="mt-1 text-xs text-ink/60">Email tidak dapat diubah.</p>
          <?php endif; ?>
          <?= pesan_field($err, 'email') ?>
        </div>
        <div>
          <label class="block text-sm font-medium" for="poli_id">Poli</label>
          <select id="poli_id" name="poli_id" class="<?= $kelasInput ?>">
            <option value="0">Pilih poli</option>
            <?php foreach ($poliList as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= (int) $form['poli_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                <?= e($p['nama_poli']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?= pesan_field($err, 'poli_id') ?>
        </div>
        <div>
          <label class="block text-sm font-medium" for="no_str">Nomor STR</label>
          <input id="no_str" name="no_str" value="<?= e($form['no_str']) ?>" class="<?= $kelasInput ?> font-mono">
          <?= pesan_field($err, 'no_str') ?>
        </div>
        <div>
          <label class="block text-sm font-medium" for="bio">Bio singkat <span class="text-ink/50">(opsional)</span></label>
          <input id="bio" name="bio" value="<?= e($form['bio']) ?>" class="<?= $kelasInput ?>">
          <?= pesan_field($err, 'bio') ?>
        </div>
        <div class="flex items-center gap-3">
          <button class="bg-klinik px-4 py-2 text-sm font-medium text-white hover:bg-ink">
            <?= $form['id'] > 0 ? 'Simpan perubahan' : 'Buat akun dokter' ?>
          </button>
          <?php if ($form['id'] > 0): ?>
            <a class="action-button" href="<?= e(url('admin/dokter.php')) ?>">Batal</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>