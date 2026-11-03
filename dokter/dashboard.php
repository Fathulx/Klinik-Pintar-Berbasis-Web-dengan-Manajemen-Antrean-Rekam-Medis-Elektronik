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

$st = $pdo->prepare(
    "SELECT r.status, COUNT(*) AS n FROM reservasi r JOIN jadwal_dokter j ON j.id = r.jadwal_id
     WHERE j.dokter_id = ? AND r.tanggal = ? GROUP BY r.status"
);
$st->execute([$dokterId, $hariIni]);
$hitung = [];
foreach ($st->fetchAll() as $b) {
    $hitung[$b['status']] = (int) $b['n'];
}

$ambilSatu = function ($statusSql, $urut) use ($pdo, $dokterId, $hariIni) {
    $tanggalSql = $statusSql === "'diperiksa'" ? '' : ' AND r.tanggal = ?';
    $st = $pdo->prepare(
        "SELECT r.id, r.no_antrean, p.nama_poli, up.nama AS pasien
         FROM reservasi r
         JOIN jadwal_dokter j ON j.id = r.jadwal_id
         JOIN dokter d ON d.id = j.dokter_id
         JOIN poli p ON p.id = d.poli_id
         JOIN pasien ps ON ps.id = r.pasien_id
         JOIN users up ON up.id = ps.user_id
         WHERE j.dokter_id = ? AND r.status = $statusSql $tanggalSql
         ORDER BY $urut LIMIT 1"
    );
    $st->execute($tanggalSql === '' ? [$dokterId] : [$dokterId, $hariIni]);
    return $st->fetch();
};
$sedang  = $ambilSatu("'diperiksa'", 'r.tanggal, r.no_antrean');
$berikut = $sedang ? null : $ambilSatu("'hadir'", 'r.no_antrean');

$st = $pdo->prepare(
    "SELECT COUNT(*) AS total, COALESCE(SUM(r.tanggal >= ?), 0) AS bulan_ini
     FROM reservasi r JOIN jadwal_dokter j ON j.id = r.jadwal_id
     WHERE j.dokter_id = ? AND r.status = 'selesai'"
);
$st->execute([date('Y-m-01'), $dokterId]);
$selesai = $st->fetch();

$judul = 'Dashboard Dokter';
$aktif = 'dokter/dashboard.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Selamat bertugas, <?= e($user['nama']) ?></h1>
<p class="mt-1 text-sm text-ink/70"><?= e(tanggal_indonesia($hariIni)) ?></p>

<dl class="stat-grid mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
  <?php foreach (['menunggu' => 'Belum hadir', 'hadir' => 'Hadir', 'diperiksa' => 'Diperiksa', 'selesai' => 'Selesai'] as $k => $nama): ?>
    <div class="bg-white p-4">
      <dt class="font-mono text-[11px] uppercase tracking-widest text-ink/60"><?= e($nama) ?> hari ini</dt>
      <dd class="mt-1 font-mono text-3xl font-semibold text-klinik"><?= isset($hitung[$k]) ? $hitung[$k] : 0 ?></dd>
    </div>
  <?php endforeach; ?>
</dl>

<section class="mt-8 border border-garis bg-white p-5">
  <?php if ($sedang): ?>
    <p class="font-mono text-[11px] uppercase tracking-widest text-ink/60">Sedang diperiksa</p>
    <p class="mt-1 font-serif text-2xl font-bold">
      <span class="font-mono text-bata"><?= e(kode_antrean($sedang['nama_poli'], $sedang['no_antrean'])) ?></span>
      <?= e($sedang['pasien']) ?>
    </p>
    <a class="mt-3 inline-block bg-bata px-4 py-2 text-sm font-medium text-white hover:bg-ink"
       href="<?= e(url('dokter/periksa.php?id=' . (int) $sedang['id'])) ?>">Lanjutkan pemeriksaan</a>
  <?php elseif ($berikut): ?>
    <p class="font-mono text-[11px] uppercase tracking-widest text-ink/60">Pasien berikutnya (sudah hadir)</p>
    <p class="mt-1 font-serif text-2xl font-bold">
      <span class="font-mono text-bata"><?= e(kode_antrean($berikut['nama_poli'], $berikut['no_antrean'])) ?></span>
      <?= e($berikut['pasien']) ?>
    </p>
    <a class="mt-3 inline-block bg-klinik px-4 py-2 text-sm font-medium text-white hover:bg-ink"
       href="<?= e(url('dokter/antrean.php')) ?>">Buka antrean untuk memanggil</a>
  <?php else: ?>
    <p class="text-sm text-ink/70">Belum ada pasien yang hadir. Antrean akan muncul setelah Admin mengonfirmasi kedatangan.</p>
  <?php endif; ?>
</section>

<p class="mt-6 text-sm text-ink/70">
  Pasien selesai diperiksa: <span class="font-mono"><?= (int) $selesai['bulan_ini'] ?></span> bulan ini,
  <span class="font-mono"><?= (int) $selesai['total'] ?></span> keseluruhan.
</p>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>