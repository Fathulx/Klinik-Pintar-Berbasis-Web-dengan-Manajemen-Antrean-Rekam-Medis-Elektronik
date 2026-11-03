<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';

$user = require_role('dokter');
$pdo  = db();
tandai_tidak_hadir_otomatis($pdo);

$dokterId = dokter_id_dari_user($pdo, $user['id']);
if (!$dokterId) {
    http_response_code(500);
    exit('Data dokter tidak ditemukan. Hubungi admin.');
}
$hariIni = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('aksi') === 'panggil') {
    csrf_verify();
    $id = (int) post('id');
    try {
        $pdo->beginTransaction();
        // kunci baris dokter agar dua pemanggilan tidak berjalan bersamaan
        $pdo->prepare('SELECT id FROM dokter WHERE id = ? FOR UPDATE')->execute([$dokterId]);

        $sedang = $pdo->prepare(
            "SELECT 1 FROM reservasi r JOIN jadwal_dokter j ON j.id = r.jadwal_id
             WHERE j.dokter_id = ? AND r.status = 'diperiksa' LIMIT 1"
        );
        $sedang->execute([$dokterId]);
        if ($sedang->fetchColumn()) {
            $pdo->rollBack();
            set_flash('Selesaikan pemeriksaan pasien sebelumnya terlebih dahulu.', 'error');
        } else {
            $up = $pdo->prepare(
                "UPDATE reservasi r JOIN jadwal_dokter j ON j.id = r.jadwal_id
                 SET r.status = 'diperiksa'
                 WHERE r.id = ? AND j.dokter_id = ? AND r.status = 'hadir' AND r.tanggal = ?"
            );
            $up->execute([$id, $dokterId, $hariIni]);
            if ($up->rowCount() > 0) {
                $pdo->commit();
                catat_log('panggil_pasien', 'reservasi ' . $id);
                redirect('dokter/periksa.php?id=' . $id);
            }
            $pdo->rollBack();
            set_flash('Pasien tidak dapat dipanggil. Pastikan statusnya hadir.', 'error');
        }
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('Terjadi kesalahan. Silakan coba lagi.', 'error');
    }
    redirect('dokter/antrean.php');
}

$st = $pdo->prepare(
    "SELECT r.id, r.tanggal, r.no_antrean, r.status,
            SUBSTR(j.jam_mulai, 1, 5) AS jam_mulai, SUBSTR(j.jam_selesai, 1, 5) AS jam_selesai,
            p.nama_poli, up.nama AS pasien
     FROM reservasi r
     JOIN jadwal_dokter j ON j.id = r.jadwal_id
     JOIN dokter d ON d.id = j.dokter_id
     JOIN poli p ON p.id = d.poli_id
     JOIN pasien ps ON ps.id = r.pasien_id
     JOIN users up ON up.id = ps.user_id
     WHERE j.dokter_id = ?
       AND r.status NOT IN ('batal', 'tidak_hadir')
       AND (r.tanggal = ? OR r.status = 'diperiksa')
     ORDER BY FIELD(r.status, 'diperiksa', 'hadir', 'menunggu', 'selesai'), r.tanggal, r.no_antrean"
);
$st->execute([$dokterId, $hariIni]);
$baris = $st->fetchAll();

$hitung = array_count_values(array_column($baris, 'status'));
$label  = ['menunggu' => 'Menunggu', 'hadir' => 'Hadir', 'diperiksa' => 'Diperiksa', 'selesai' => 'Selesai'];

$judul = 'Antrean Hari Ini';
$aktif = 'dokter/antrean.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Antrean hari ini</h1>
<p class="mt-1 text-sm text-ink/70"><?= e(tanggal_indonesia($hariIni)) ?></p>

<dl class="stat-grid mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
  <?php foreach ($label as $kunci => $nama): ?>
    <div class="bg-white p-4">
      <dt class="font-mono text-[11px] uppercase tracking-widest text-ink/60"><?= e($nama) ?></dt>
      <dd class="mt-1 font-mono text-3xl font-semibold text-klinik"><?= isset($hitung[$kunci]) ? (int) $hitung[$kunci] : 0 ?></dd>
    </div>
  <?php endforeach; ?>
</dl>

<div class="mt-8 overflow-x-auto">
  <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
    <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
      <tr><th class="px-3 py-2">Nomor</th><th class="px-3 py-2">Pasien</th><th class="px-3 py-2">Jam praktik</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($baris as $r): ?>
        <tr class="border-t border-garis">
          <td data-label="Nomor" class="px-3 py-2 font-mono font-semibold text-bata"><?= e(kode_antrean($r['nama_poli'], $r['no_antrean'])) ?></td>
          <td data-label="Pasien" class="px-3 py-2">
            <?= e($r['pasien']) ?>
            <?php if ($r['tanggal'] !== $hariIni): ?>
              <span class="block text-xs text-bata">Belum diselesaikan, <?= e(tanggal_indonesia($r['tanggal'])) ?></span>
            <?php endif; ?>
          </td>
          <td data-label="Jam praktik" class="whitespace-nowrap px-3 py-2 font-mono text-xs"><?= e($r['jam_mulai']) ?> - <?= e($r['jam_selesai']) ?></td>
          <td data-label="Status" class="px-3 py-2"><?= badge_reservasi($r['status']) ?></td>
          <td data-label="Aksi" class="whitespace-nowrap px-3 py-2">
            <?php if ($r['status'] === 'hadir'): ?>
              <form method="post" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="panggil">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="bg-klinik px-3 py-1 text-xs font-medium text-white hover:bg-ink">Panggil</button>
              </form>
            <?php elseif ($r['status'] === 'diperiksa'): ?>
              <a class="bg-bata px-3 py-1 text-xs font-medium text-white hover:bg-ink"
                 href="<?= e(url('dokter/periksa.php?id=' . (int) $r['id'])) ?>">Isi pemeriksaan</a>
            <?php elseif ($r['status'] === 'selesai'): ?>
              <a class="action-button" href="<?= e(url('dokter/periksa.php?id=' . (int) $r['id'])) ?>">Lihat</a>
            <?php else: ?>
              <span class="text-xs text-ink/50">Belum hadir</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$baris): ?>
        <tr><td colspan="5" class="px-3 py-4 text-ink/60">Tidak ada antrean hari ini.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>