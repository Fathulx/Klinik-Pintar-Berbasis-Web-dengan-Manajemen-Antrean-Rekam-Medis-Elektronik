<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';

$user = require_role('admin');
$pdo  = db();
tandai_tidak_hadir_otomatis($pdo);

// mencegah sel CSV dianggap rumus oleh Excel
function csv_aman($nilai)
{
    $nilai = (string) $nilai;
    return preg_match('/^[=+\-@\t\r]/', $nilai) ? "'" . $nilai : $nilai;
}

$hariIni = date('Y-m-d');
$dari    = isset($_GET['dari']) && tanggal_valid($_GET['dari']) ? $_GET['dari'] : date('Y-m-d', strtotime('-29 days'));
$sampai  = isset($_GET['sampai']) && tanggal_valid($_GET['sampai']) ? $_GET['sampai'] : $hariIni;
$catatan = '';

if ($dari > $sampai) {
    list($dari, $sampai) = [$sampai, $dari];
    $catatan = 'Tanggal awal dan akhir tertukar, sudah dirapikan.';
}
if (strtotime($sampai) - strtotime($dari) > 366 * 86400) {
    $dari    = date('Y-m-d', strtotime($sampai . ' -366 days'));
    $catatan = 'Rentang laporan maksimal 366 hari. Tanggal awal disesuaikan.';
}

$poliList   = $pdo->query('SELECT id, nama_poli FROM poli ORDER BY nama_poli')->fetchAll();
$dokterList = $pdo->query(
    'SELECT d.id, u.nama FROM dokter d JOIN users u ON u.id = d.user_id ORDER BY u.nama'
)->fetchAll();

$poliId   = isset($_GET['poli']) && is_string($_GET['poli']) ? (int) $_GET['poli'] : 0;
$dokterId = isset($_GET['dokter']) && is_string($_GET['dokter']) ? (int) $_GET['dokter'] : 0;
if (!in_array($poliId, array_map('intval', array_column($poliList, 'id')), true)) {
    $poliId = 0;
}
if (!in_array($dokterId, array_map('intval', array_column($dokterList, 'id')), true)) {
    $dokterId = 0;
}

$where = 'r.tanggal BETWEEN ? AND ?';
$param = [$dari, $sampai];
if ($poliId) {
    $where  .= ' AND d.poli_id = ?';
    $param[] = $poliId;
}
if ($dokterId) {
    $where  .= ' AND j.dokter_id = ?';
    $param[] = $dokterId;
}
$from = "FROM reservasi r
         JOIN jadwal_dokter j ON j.id = r.jadwal_id
         JOIN dokter d ON d.id = j.dokter_id
         JOIN users ud ON ud.id = d.user_id
         JOIN poli p ON p.id = d.poli_id
         WHERE $where";

// Ekspor CSV: tanpa nama pasien dan tanpa isi medis.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $st = $pdo->prepare(
        "SELECT r.tanggal, j.hari, p.nama_poli, ud.nama AS dokter, r.no_antrean, r.status
         $from ORDER BY r.tanggal, ud.nama, r.no_antrean"
    );
    $st->execute($param);
    catat_log('ekspor_laporan', $dari . ' sampai ' . $sampai);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="laporan-kunjungan-' . $dari . '-' . $sampai . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Tanggal', 'Hari', 'Poli', 'Dokter', 'Nomor antrean', 'Status']);
    while ($b = $st->fetch()) {
        fputcsv($out, [
            $b['tanggal'], $b['hari'], csv_aman($b['nama_poli']), csv_aman($b['dokter']),
            kode_antrean($b['nama_poli'], $b['no_antrean']), $b['status'],
        ]);
    }
    fclose($out);
    exit;
}

$st = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(r.status = 'selesai'), 0) AS selesai,
            COALESCE(SUM(r.status = 'batal'), 0) AS batal,
            COALESCE(SUM(r.status = 'tidak_hadir'), 0) AS tidak_hadir,
            COALESCE(SUM(r.status IN ('menunggu', 'hadir', 'diperiksa')), 0) AS proses
     $from"
);
$st->execute($param);
$ring = $st->fetch();

$st = $pdo->prepare(
    "SELECT ud.nama AS dokter, p.nama_poli, COUNT(*) AS total,
            SUM(r.status = 'selesai') AS selesai, SUM(r.status = 'batal') AS batal,
            SUM(r.status = 'tidak_hadir') AS tidak_hadir
     $from GROUP BY d.id, ud.nama, p.nama_poli ORDER BY total DESC, ud.nama"
);
$st->execute($param);
$perDokter = $st->fetchAll();

$st = $pdo->prepare(
    "SELECT r.tanggal, COUNT(*) AS total, SUM(r.status = 'selesai') AS selesai,
            SUM(r.status = 'batal') AS batal, SUM(r.status = 'tidak_hadir') AS tidak_hadir
     $from GROUP BY r.tanggal ORDER BY r.tanggal DESC"
);
$st->execute($param);
$perTanggal = $st->fetchAll();

$qs = http_build_query(array_filter(
    ['dari' => $dari, 'sampai' => $sampai, 'poli' => $poliId ?: null, 'dokter' => $dokterId ?: null],
    function ($v) { return $v !== null; }
));

$kelasInput = 'mt-1 border border-garis bg-white px-3 py-1.5 text-sm focus:border-klinik focus:outline-none';
$judul = 'Laporan Kunjungan';
$aktif = 'admin/laporan.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Laporan kunjungan</h1>
<p class="mt-1 text-sm text-ink/70">
  <?= e(tanggal_indonesia($dari)) ?> sampai <?= e(tanggal_indonesia($sampai)) ?>
</p>
<p class="mt-1 hidden text-xs text-ink/60 print:block">Dicetak oleh <?= e($user['nama']) ?> pada <?= e(date('d-m-Y H:i')) ?></p>

<?php if ($catatan): ?>
  <p class="mt-3 border border-bata bg-bata/10 px-3 py-2 text-sm text-bata"><?= e($catatan) ?></p>
<?php endif; ?>

<form method="get" class="mt-6 flex flex-wrap items-end gap-3 print:hidden">
  <div>
    <label class="block text-xs font-medium" for="dari">Dari</label>
    <input id="dari" name="dari" type="date" value="<?= e($dari) ?>" class="<?= $kelasInput ?> font-mono">
  </div>
  <div>
    <label class="block text-xs font-medium" for="sampai">Sampai</label>
    <input id="sampai" name="sampai" type="date" value="<?= e($sampai) ?>" class="<?= $kelasInput ?> font-mono">
  </div>
  <div>
    <label class="block text-xs font-medium" for="poli">Poli</label>
    <select id="poli" name="poli" class="<?= $kelasInput ?>">
      <option value="0">Semua poli</option>
      <?php foreach ($poliList as $p): ?>
        <option value="<?= (int) $p['id'] ?>" <?= $poliId === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nama_poli']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="block text-xs font-medium" for="dokter">Dokter</label>
    <select id="dokter" name="dokter" class="<?= $kelasInput ?>">
      <option value="0">Semua dokter</option>
      <?php foreach ($dokterList as $d): ?>
        <option value="<?= (int) $d['id'] ?>" <?= $dokterId === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['nama']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="px-3 py-1.5 text-sm">Terapkan</button>
  <a class="action-button" href="<?= e(url('admin/laporan.php')) ?>">Atur ulang</a>
</form>

<div class="mt-4 flex gap-3 print:hidden">
  <a class="bg-klinik px-4 py-2 text-sm font-medium text-white hover:bg-ink"
     href="<?= e(url('admin/laporan.php?' . $qs . '&export=csv')) ?>">Unduh CSV</a>
  <button type="button" onclick="window.print()" class="px-4 py-2 text-sm">Cetak</button>
</div>

<dl class="stat-grid mt-6 grid grid-cols-2 gap-3 md:grid-cols-5">
  <?php
  $kartu = ['Total reservasi' => 'total', 'Selesai' => 'selesai', 'Batal' => 'batal',
            'Tidak hadir' => 'tidak_hadir', 'Dalam proses' => 'proses'];
  foreach ($kartu as $nama => $kunci): ?>
    <div class="bg-white p-4">
      <dt class="font-mono text-[11px] uppercase tracking-widest text-ink/60"><?= e($nama) ?></dt>
      <dd class="mt-1 font-mono text-3xl font-semibold text-klinik"><?= (int) $ring[$kunci] ?></dd>
    </div>
  <?php endforeach; ?>
</dl>

<h2 class="mt-10 font-serif text-xl font-bold">Per dokter</h2>
<div class="mt-3 overflow-x-auto">
  <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
    <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
      <tr><th class="px-3 py-2">Dokter</th><th class="px-3 py-2">Poli</th><th class="px-3 py-2">Total</th><th class="px-3 py-2">Selesai</th><th class="px-3 py-2">Batal</th><th class="px-3 py-2">Tidak hadir</th></tr>
    </thead>
    <tbody>
      <?php foreach ($perDokter as $b): ?>
        <tr class="border-t border-garis">
          <td data-label="Dokter" class="px-3 py-2"><?= e($b['dokter']) ?></td>
          <td data-label="Poli" class="px-3 py-2"><?= e($b['nama_poli']) ?></td>
          <td data-label="Total" class="px-3 py-2 font-mono"><?= (int) $b['total'] ?></td>
          <td data-label="Selesai" class="px-3 py-2 font-mono"><?= (int) $b['selesai'] ?></td>
          <td data-label="Batal" class="px-3 py-2 font-mono"><?= (int) $b['batal'] ?></td>
          <td data-label="Tidak hadir" class="px-3 py-2 font-mono"><?= (int) $b['tidak_hadir'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$perDokter): ?>
        <tr><td colspan="6" class="px-3 py-4 text-ink/60">Tidak ada data pada rentang ini.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<h2 class="mt-10 font-serif text-xl font-bold">Per tanggal</h2>
<div class="mt-3 overflow-x-auto">
  <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
    <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
      <tr><th class="px-3 py-2">Tanggal</th><th class="px-3 py-2">Total</th><th class="px-3 py-2">Selesai</th><th class="px-3 py-2">Batal</th><th class="px-3 py-2">Tidak hadir</th></tr>
    </thead>
    <tbody>
      <?php foreach ($perTanggal as $b): ?>
        <tr class="border-t border-garis">
          <td data-label="Tanggal" class="whitespace-nowrap px-3 py-2"><?= e(tanggal_indonesia($b['tanggal'])) ?></td>
          <td data-label="Total" class="px-3 py-2 font-mono"><?= (int) $b['total'] ?></td>
          <td data-label="Selesai" class="px-3 py-2 font-mono"><?= (int) $b['selesai'] ?></td>
          <td data-label="Batal" class="px-3 py-2 font-mono"><?= (int) $b['batal'] ?></td>
          <td data-label="Tidak hadir" class="px-3 py-2 font-mono"><?= (int) $b['tidak_hadir'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$perTanggal): ?>
        <tr><td colspan="5" class="px-3 py-4 text-ink/60">Tidak ada data pada rentang ini.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>