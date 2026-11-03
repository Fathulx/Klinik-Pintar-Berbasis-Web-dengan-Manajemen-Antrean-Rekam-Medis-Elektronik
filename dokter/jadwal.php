<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';
require_once __DIR__ . '/../includes/jadwal.php';

$user = require_role('dokter');
$pdo  = db();

$dokterId = dokter_id_dari_user($pdo, $user['id']);
if (!$dokterId) {
    http_response_code(500);
    exit('Data dokter tidak ditemukan. Hubungi admin.');
}

$HARI = daftar_hari();
$err  = [];
$form = ['id' => 0, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'kuota' => 20];

// mode ubah: hanya jadwal milik dokter ini yang bisa dimuat
if (isset($_GET['edit']) && is_string($_GET['edit'])) {
    $j = jadwal_ambil($pdo, (int) $_GET['edit'], $dokterId);
    if ($j) {
        $form = [
            'id' => (int) $j['id'], 'hari' => $j['hari'], 'jam_mulai' => $j['jam_mulai'],
            'jam_selesai' => $j['jam_selesai'], 'kuota' => $j['kuota'],
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = post('aksi');

    if ($aksi === 'simpan') {
        $form = [
            'id'          => (int) post('id'),
            'hari'        => post('hari'),
            'jam_mulai'   => post('jam_mulai'),
            'jam_selesai' => post('jam_selesai'),
            'kuota'       => post('kuota'),
        ];
        // dokter_id selalu dari sesi, bukan dari formulir
        $hasil = simpan_jadwal($pdo, $form + ['dokter_id' => $dokterId], $dokterId);
        if ($hasil['ok']) {
            set_flash($hasil['pesan']);
            redirect('dokter/jadwal.php');
        }
        $err = $hasil['err'];
    } elseif ($aksi === 'aktif') {
        list($ok, $pesan) = ubah_status_jadwal($pdo, (int) post('id'), post('aktif_baru') === '1', $dokterId);
        set_flash($pesan, $ok ? 'ok' : 'error');
        redirect('dokter/jadwal.php');
    } elseif ($aksi === 'hapus') {
        list($ok, $pesan) = hapus_jadwal($pdo, (int) post('id'), $dokterId);
        set_flash($pesan, $ok ? 'ok' : 'error');
        redirect('dokter/jadwal.php');
    }
}

$urutHari = "'" . implode("','", $HARI) . "'";
$st = $pdo->prepare(
    "SELECT j.id, j.hari, SUBSTR(j.jam_mulai, 1, 5) AS jam_mulai, SUBSTR(j.jam_selesai, 1, 5) AS jam_selesai,
            j.kuota, j.aktif,
            (SELECT COUNT(*) FROM reservasi r
              WHERE r.jadwal_id = j.id AND r.tanggal >= ?
                AND r.status IN ('menunggu', 'hadir', 'diperiksa')) AS mendatang
     FROM jadwal_dokter j
     WHERE j.dokter_id = ?
     ORDER BY FIELD(j.hari, $urutHari), j.jam_mulai"
);
$st->execute([date('Y-m-d'), $dokterId]);
$daftar = $st->fetchAll();

$ringEdit = $form['id'] > 0 ? jadwal_ringkasan_mendatang($pdo, $form['id']) : null;

$kelasInput = 'mt-1 w-full border border-garis bg-white px-3 py-2 text-sm focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik';
$judul = 'Jadwal Saya';
$aktif = 'dokter/jadwal.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Jadwal saya</h1>
<p class="mt-1 max-w-2xl text-sm text-ink/70">
  Anda dapat menambah dan mengubah jadwal praktik sendiri. Jadwal yang sudah dipesan pasien dilindungi:
  jam praktik tidak dapat diubah selama ada reservasi mendatang, dan kuota tidak boleh lebih kecil dari
  reservasi yang sudah masuk. Untuk menggeser jam, nonaktifkan jadwal lama lalu buat jadwal baru.
</p>

<div class="mt-6 grid gap-8 lg:grid-cols-3">
  <section class="overflow-x-auto lg:col-span-2">
    <table class="w-full border border-garis bg-white text-left text-sm">
      <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
        <tr>
          <th class="px-3 py-2">Hari</th><th class="px-3 py-2">Jam</th><th class="px-3 py-2">Kuota</th>
          <th class="px-3 py-2">Reservasi mendatang</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar as $j): ?>
          <tr class="border-t border-garis">
            <td class="px-3 py-2"><?= e($j['hari']) ?></td>
            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs"><?= e($j['jam_mulai']) ?> - <?= e($j['jam_selesai']) ?></td>
            <td class="px-3 py-2 font-mono"><?= (int) $j['kuota'] ?></td>
            <td class="px-3 py-2 font-mono"><?= (int) $j['mendatang'] ?></td>
            <td class="px-3 py-2"><?= badge_status($j['aktif'] ? 'aktif' : 'nonaktif') ?></td>
            <td class="whitespace-nowrap px-3 py-2">
              <a class="underline" href="<?= e(url('dokter/jadwal.php?edit=' . (int) $j['id'])) ?>">Ubah</a>
              <span class="mx-1 text-ink/30">|</span>
              <form method="post" class="inline"
                    <?= $j['aktif'] ? 'onsubmit="return confirm(\'Tutup pendaftaran baru untuk jadwal ini? Reservasi yang sudah masuk tetap dilayani.\')"' : '' ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="aktif">
                <input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
                <input type="hidden" name="aktif_baru" value="<?= $j['aktif'] ? '0' : '1' ?>">
                <button class="underline"><?= $j['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
              </form>
              <span class="mx-1 text-ink/30">|</span>
              <form method="post" class="inline" onsubmit="return confirm('Hapus jadwal ini?')">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
                <button class="underline">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$daftar): ?>
          <tr><td colspan="6" class="px-3 py-4 text-ink/60">Anda belum punya jadwal praktik.</td></tr>
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
            <a class="text-sm underline" href="<?= e(url('dokter/jadwal.php')) ?>">Batal</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>