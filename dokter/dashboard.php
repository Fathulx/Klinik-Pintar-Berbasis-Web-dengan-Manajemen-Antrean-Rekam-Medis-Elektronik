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
<h1 class="font-serif text-4xl font-bold">Selamat bertugas, <?= e($user['nama']) ?></h1>
<p class="mt-1 text-ink/70"><?= e(tanggal_indonesia($hariIni)) ?></p>

<section class="ringkasan mt-6" aria-label="Ruang periksa">
  <?php $pasienUtama = $sedang ?: $berikut; ?>
  <div class="ringkasan__utama layar-periksa">
    <?php if ($pasienUtama): ?>
      <p class="ringkasan__label"><?= $sedang ? 'Sedang diperiksa' : 'Pasien berikutnya, sudah hadir' ?></p>
      <p class="ringkasan__angka"><?= e(kode_antrean($pasienUtama['nama_poli'], $pasienUtama['no_antrean'])) ?></p>
      <p class="text-lg font-semibold"><?= e($pasienUtama['pasien']) ?></p>
      <?php if ($sedang): ?>
        <a class="mt-4 inline-flex bg-panggil px-4 py-2.5 font-semibold text-layar"
           href="<?= e(url('dokter/periksa.php?id=' . (int) $sedang['id'])) ?>">Lanjutkan pemeriksaan</a>
      <?php else: ?>
        <form method="post" action="<?= e(url('dokter/antrean.php')) ?>" class="mt-4">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="panggil">
          <input type="hidden" name="id" value="<?= (int) $berikut['id'] ?>">
          <button class="bg-panggil px-4 py-2.5 font-semibold text-layar">Panggil sekarang</button>
        </form>
      <?php endif; ?>
    <?php else: ?>
      <p class="ringkasan__label">Belum ada pasien yang hadir</p>
      <p class="mt-2 max-w-xs text-white/80">Pasien muncul di sini setelah petugas mengonfirmasi kedatangannya.</p>
    <?php endif; ?>
  </div>
  <dl class="ringkasan__lain">
    <div><dt>Belum hadir</dt><dd><?= isset($hitung['menunggu']) ? $hitung['menunggu'] : 0 ?></dd></div>
    <div><dt>Hadir, menunggu dipanggil</dt><dd><?= isset($hitung['hadir']) ? $hitung['hadir'] : 0 ?></dd></div>
    <div><dt>Selesai hari ini</dt><dd><?= isset($hitung['selesai']) ? $hitung['selesai'] : 0 ?></dd></div>
    <div><dt>Selesai bulan ini</dt><dd><?= (int) $selesai['bulan_ini'] ?></dd></div>
    <div><dt>Total diperiksa</dt><dd><?= (int) $selesai['total'] ?></dd></div>
    <div><dt>Antrean lengkap</dt><dd class="!font-sans !text-base"><a class="font-semibold text-klinik underline" href="<?= e(url('dokter/antrean.php')) ?>">Buka antrean hari ini</a></dd></div>
  </dl>
</section>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>