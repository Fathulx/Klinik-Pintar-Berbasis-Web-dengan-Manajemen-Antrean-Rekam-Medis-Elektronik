<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_role('super_admin');
$pdo  = db();
$err  = [];
$edit = ['id' => 0, 'nama_poli' => '', 'deskripsi' => ''];

if (isset($_GET['edit']) && is_string($_GET['edit'])) {
    $st = $pdo->prepare('SELECT id, nama_poli, deskripsi FROM poli WHERE id = ?');
    $st->execute([(int) $_GET['edit']]);
    $baris = $st->fetch();
    if ($baris) {
        $edit = $baris;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = post('aksi');

    if ($aksi === 'simpan') {
        $edit = [
            'id'        => (int) post('id'),
            'nama_poli' => post('nama_poli'),
            'deskripsi' => post('deskripsi'),
        ];
        $len = mb_strlen($edit['nama_poli']);
        if ($len < 2 || $len > 80) {
            $err['nama_poli'] = 'Nama poli harus 2 sampai 80 karakter.';
        }
        if (mb_strlen($edit['deskripsi']) > 255) {
            $err['deskripsi'] = 'Deskripsi maksimal 255 karakter.';
        }
        if (!$err) {
            $cek = $pdo->prepare('SELECT 1 FROM poli WHERE nama_poli = ? AND id <> ?');
            $cek->execute([$edit['nama_poli'], $edit['id']]);
            if ($cek->fetchColumn()) {
                $err['nama_poli'] = 'Nama poli sudah ada.';
            }
        }
        if (!$err) {
            $desk = $edit['deskripsi'] === '' ? null : $edit['deskripsi'];
            if ($edit['id'] > 0) {
                $pdo->prepare('UPDATE poli SET nama_poli = ?, deskripsi = ? WHERE id = ?')
                    ->execute([$edit['nama_poli'], $desk, $edit['id']]);
                catat_log('ubah_poli', $edit['nama_poli']);
                set_flash('Poli diperbarui.');
            } else {
                $pdo->prepare('INSERT INTO poli (nama_poli, deskripsi) VALUES (?, ?)')
                    ->execute([$edit['nama_poli'], $desk]);
                catat_log('tambah_poli', $edit['nama_poli']);
                set_flash('Poli ditambahkan.');
            }
            redirect('superadmin/poli.php');
        }
    } elseif ($aksi === 'hapus') {
        $id  = (int) post('id');
        $cek = $pdo->prepare('SELECT COUNT(*) FROM dokter WHERE poli_id = ?');
        $cek->execute([$id]);
        if ($cek->fetchColumn() > 0) {
            set_flash('Poli tidak bisa dihapus karena masih memiliki dokter.', 'error');
        } else {
            $pdo->prepare('DELETE FROM poli WHERE id = ?')->execute([$id]);
            catat_log('hapus_poli', 'id ' . $id);
            set_flash('Poli dihapus.');
        }
        redirect('superadmin/poli.php');
    }
}

$daftar = $pdo->query(
    'SELECT p.id, p.nama_poli, p.deskripsi, COUNT(d.id) AS jml_dokter
     FROM poli p LEFT JOIN dokter d ON d.poli_id = p.id
     GROUP BY p.id, p.nama_poli, p.deskripsi ORDER BY p.nama_poli'
)->fetchAll();

$kelasInput = 'mt-1 w-full border border-garis bg-white px-3 py-2 text-sm focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik';
$judul = 'Data Poli';
$aktif = 'superadmin/poli.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Data poli</h1>
<p class="mt-1 text-sm text-ink/70">Poli yang masih memiliki dokter tidak dapat dihapus.</p>

<div class="mt-6 grid gap-8 lg:grid-cols-3">
  <section class="overflow-x-auto lg:col-span-2">
    <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
      <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
        <tr><th class="px-3 py-2">Poli</th><th class="px-3 py-2">Deskripsi</th><th class="px-3 py-2">Dokter</th><th class="px-3 py-2">Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($daftar as $p): ?>
          <tr class="border-t border-garis">
            <td data-label="Poli" class="px-3 py-2 font-medium"><?= e($p['nama_poli']) ?></td>
            <td data-label="Deskripsi" class="px-3 py-2 text-ink/70"><?= e($p['deskripsi']) ?></td>
            <td data-label="Dokter" class="px-3 py-2 font-mono"><?= (int) $p['jml_dokter'] ?></td>
            <td data-label="Aksi" class="whitespace-nowrap px-3 py-2">
              <a class="action-button underline" href="<?= e(url('superadmin/poli.php?edit=' . (int) $p['id'])) ?>">Ubah</a>
              <span class="mx-1 text-ink/30">|</span>
              <form method="post" class="inline" onsubmit="return confirm('Hapus poli ini?')">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="action-button action-danger">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$daftar): ?>
          <tr><td colspan="4" class="px-3 py-4 text-ink/60">Belum ada poli.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section>
    <div class="border border-garis bg-white p-5">
      <h2 class="font-serif text-xl font-bold"><?= $edit['id'] > 0 ? 'Ubah poli' : 'Tambah poli' ?></h2>
      <form method="post" class="mt-4 space-y-4" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
        <div>
          <label class="block text-sm font-medium" for="nama_poli">Nama poli</label>
          <input id="nama_poli" name="nama_poli" value="<?= e($edit['nama_poli']) ?>" class="<?= $kelasInput ?>">
          <?= pesan_field($err, 'nama_poli') ?>
        </div>
        <div>
          <label class="block text-sm font-medium" for="deskripsi">Deskripsi <span class="text-ink/50">(opsional)</span></label>
          <input id="deskripsi" name="deskripsi" value="<?= e($edit['deskripsi']) ?>" class="<?= $kelasInput ?>">
          <?= pesan_field($err, 'deskripsi') ?>
        </div>
        <div class="flex items-center gap-3">
          <button class="bg-klinik px-4 py-2 text-sm font-medium text-white hover:bg-ink">Simpan</button>
          <?php if ($edit['id'] > 0): ?>
            <a class="action-button" href="<?= e(url('superadmin/poli.php')) ?>">Batal</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>