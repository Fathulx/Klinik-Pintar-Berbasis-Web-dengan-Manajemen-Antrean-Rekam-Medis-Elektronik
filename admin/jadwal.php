<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';
require_once __DIR__ . '/../includes/jadwal.php';

$user = require_role('admin');
$pdo  = db();
$HARI = daftar_hari();
$err  = [];
$form = ['id' => 0, 'dokter_id' => 0, 'hari' => 'Senin',
         'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'kuota' => 20];

$dokterList = $pdo->query(
    'SELECT d.id, u.nama, u.status, p.nama_poli
     FROM dokter d JOIN users u ON u.id = d.user_id JOIN poli p ON p.id = d.poli_id
     ORDER BY u.nama'
)->fetchAll();

if (isset($_GET['edit']) && is_string($_GET['edit'])) {
    $j = jadwal_ambil($pdo, (int) $_GET['edit']);
    if ($j) {
        $form = [
            'id'          => (int) $j['id'],
            'dokter_id'   => (int) $j['dokter_id'],
            'hari'        => $j['hari'],
            'jam_mulai'   => $j['jam_mulai'],
            'jam_selesai' => $j['jam_selesai'],
            'kuota'       => $j['kuota'],
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = post('aksi');

    if ($aksi === 'simpan') {
        $form = [
            'id'          => (int) post('id'),
            'dokter_id'   => (int) post('dokter_id'),
            'hari'        => post('hari'),
            'jam_mulai'   => post('jam_mulai'),
            'jam_selesai' => post('jam_selesai'),
            'kuota'       => post('kuota'),
        ];
        $hasil = simpan_jadwal($pdo, $form);
        if ($hasil['ok']) {
            set_flash($hasil['pesan']);
            redirect('admin/jadwal.php');
        }
        $err = $hasil['err'];
    } elseif ($aksi === 'aktif') {
        list($ok, $pesan) = ubah_status_jadwal($pdo, (int) post('id'), post('aktif_baru') === '1');
        set_flash($pesan, $ok ? 'ok' : 'error');
        redirect('admin/jadwal.php');
    } elseif ($aksi === 'hapus') {
        list($ok, $pesan) = hapus_jadwal($pdo, (int) post('id'));
        set_flash($pesan, $ok ? 'ok' : 'error');
        redirect('admin/jadwal.php');
    }
}

$urutHari = "'" . implode("','", $HARI) . "'";
$daftar = $pdo->query(
    "SELECT j.id, u.nama AS dokter, p.nama_poli, j.hari,
            SUBSTR(j.jam_mulai, 1, 5) AS jam_mulai, SUBSTR(j.jam_selesai, 1, 5) AS jam_selesai,
            j.kuota, j.aktif
     FROM jadwal_dokter j
     JOIN dokter d ON d.id = j.dokter_id
     JOIN users u ON u.id = d.user_id
     JOIN poli p ON p.id = d.poli_id
     ORDER BY u.nama, FIELD(j.hari, $urutHari), j.jam_mulai"
)->fetchAll();

$ringEdit = $form['id'] > 0 ? jadwal_ringkasan_mendatang($pdo, $form['id']) : null;

$kelasInput = 'mt-1 w-full border border-garis bg-white px-3 py-2 text-sm focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik';
$judul = 'Jadwal Praktik';
$aktif = 'admin/jadwal.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Jadwal praktik</h1>
<p class="mt-1 max-w-2xl text-sm text-ink/70">
  Hanya jadwal yang aktif yang dapat dipilih pasien. Dokter juga dapat mengubah jadwalnya sendiri.
  Jadwal yang sudah memiliki reservasi tidak dihapus, cukup dinonaktifkan, dan jam praktiknya
  tidak dapat diubah selama masih ada reservasi mendatang.
</p>

<div class="mt-6 grid gap-8 lg:grid-cols-3">
  <section class="overflow-x-auto lg:col-span-2">
    <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
      <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
        <tr>
          <th class="px-3 py-2">Dokter</th><th class="px-3 py-2">Hari</th><th class="px-3 py-2">Jam</th>
          <th class="px-3 py-2">Kuota</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar as $j): ?>
          <tr class="border-t border-garis align-top">
            <td data-label="Dokter" class="px-3 py-2">
              <p class="font-medium"><?= e($j['dokter']) ?></p>
              <p class="text-xs text-ink/60">Poli <?= e($j['nama_poli']) ?></p>
            </td>
            <td data-label="Hari" class="px-3 py-2"><?= e($j['hari']) ?></td>
            <td data-label="Jam" class="whitespace-nowrap px-3 py-2 font-mono text-xs"><?= e($j['jam_mulai']) ?> - <?= e($j['jam_selesai']) ?></td>
            <td data-label="Kuota" class="px-3 py-2 font-mono"><?= (int) $j['kuota'] ?></td>
            <td data-label="Status" class="px-3 py-2"><?= badge_status($j['aktif'] ? 'aktif' : 'nonaktif') ?></td>
            <td data-label="Aksi" class="whitespace-nowrap px-3 py-2">
              <a class="action-button underline" href="<?= e(url('admin/jadwal.php?edit=' . (int) $j['id'])) ?>">Ubah</a>
              <span class="mx-1 text-ink/30">|</span>
              <form method="post" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="aktif">
                <input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
                <input type="hidden" name="aktif_baru" value="<?= $j['aktif'] ? '0' : '1' ?>">
                <button class="action-button"><?= $j['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
              </form>
              <span class="mx-1 text-ink/30">|</span>
              <form method="post" class="inline" onsubmit="return confirm('Hapus jadwal ini?')">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
                <button class="action-button action-danger">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$daftar): ?>
          <tr><td colspan="6" class="px-3 py-4 text-ink/60">Belum ada jadwal praktik.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section>
    <div class="border border-garis bg-white p-5">
      <h2 class="font-serif text-xl font-bold"><?= $form['id'] > 0 ? 'Ubah jadwal' : 'Tambah jadwal' ?></h2>
      <?php if (isset($err['umum'])): ?>
        <div class="mt-3 border border-bata bg-bata/10 px-3 py-2 text-sm text-bata"><?= e($err['umum']) ?></div>
      <?php endif; ?>
      <?php if ($ringEdit && ($ringEdit['aktif'] > 0 || $ringEdit['maks_per_tanggal'] > 0)): ?>
        <p class="mt-3 border border-garis bg-paper px-3 py-2 text-xs text-ink/70">
          Jadwal ini punya <?= (int) $ringEdit['aktif'] ?> reservasi mendatang aktif
          (terbanyak <?= (int) $ringEdit['maks_per_tanggal'] ?> pada satu tanggal).
          Jam tidak dapat diubah dan kuota minimal <?= (int) $ringEdit['maks_per_tanggal'] ?>.
        </p>
      <?php endif; ?>
      <form method="post" class="mt-4 space-y-4" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div>
          <label class="block text-sm font-medium" for="dokter_id">Dokter</label>
          <select id="dokter_id" name="dokter_id" class="<?= $kelasInput ?>">
            <option value="0">Pilih dokter</option>
            <?php foreach ($dokterList as $d): ?>
              <option value="<?= (int) $d['id'] ?>" <?= (int) $form['dokter_id'] === (int) $d['id'] ? 'selected' : '' ?>>
                <?= e($d['nama']) ?> (<?= e($d['nama_poli']) ?>)<?= $d['status'] !== 'aktif' ? ' - nonaktif' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?= pesan_field($err, 'dokter_id') ?>
        </div>
        <div>
          <label class="block text-sm font-medium" for="hari">Hari</label>
          <select id="hari" name="hari" class="<?= $kelasInput ?>">
            <?php foreach ($HARI as $h): ?>
              <option value="<?= e($h) ?>" <?= $form['hari'] === $h ? 'selected' : '' ?>><?= e($h) ?></option>
            <?php endforeach; ?>
          </select>
          <?= pesan_field($err, 'hari') ?>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium" for="jam_mulai">Jam mulai</label>
            <input id="jam_mulai" name="jam_mulai" type="time" value="<?= e($form['jam_mulai']) ?>" class="<?= $kelasInput ?> font-mono">
            <?= pesan_field($err, 'jam_mulai') ?>
          </div>
          <div>
            <label class="block text-sm font-medium" for="jam_selesai">Jam selesai</label>
            <input id="jam_selesai" name="jam_selesai" type="time" value="<?= e($form['jam_selesai']) ?>" class="<?= $kelasInput ?> font-mono">
            <?= pesan_field($err, 'jam_selesai') ?>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium" for="kuota">Kuota pasien per hari</label>
          <input id="kuota" name="kuota" type="number" min="1" max="100" value="<?= e($form['kuota']) ?>" class="<?= $kelasInput ?> font-mono">
          <?= pesan_field($err, 'kuota') ?>
        </div>
        <div class="flex items-center gap-3">
          <button class="bg-klinik px-4 py-2 text-sm font-medium text-white hover:bg-ink">Simpan</button>
          <?php if ($form['id'] > 0): ?>
            <a class="action-button" href="<?= e(url('admin/jadwal.php')) ?>">Batal</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>