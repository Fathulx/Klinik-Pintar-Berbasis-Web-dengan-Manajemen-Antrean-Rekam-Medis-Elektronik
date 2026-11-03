<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';

$user = require_role('dokter');
$pdo  = db();

$dokterId = dokter_id_dari_user($pdo, $user['id']);
if (!$dokterId) {
    http_response_code(500);
    exit('Data dokter tidak ditemukan. Hubungi admin.');
}

$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$q = mb_substr($q, 0, 100);

$sql = "SELECT r.id, r.tanggal, r.no_antrean, p.nama_poli, up.nama AS pasien
        FROM reservasi r
        JOIN jadwal_dokter j ON j.id = r.jadwal_id
        JOIN dokter d ON d.id = j.dokter_id
        JOIN poli p ON p.id = d.poli_id
        JOIN pasien ps ON ps.id = r.pasien_id
        JOIN users up ON up.id = ps.user_id
        WHERE j.dokter_id = ? AND r.status = 'selesai'";
$param = [$dokterId];
if ($q !== '') {
    $sql .= ' AND up.nama LIKE ?';
    $param[] = '%' . $q . '%';
}
$sql .= ' ORDER BY r.tanggal DESC, r.id DESC LIMIT 100';
$st = $pdo->prepare($sql);
$st->execute($param);
$daftar = $st->fetchAll();

$judul = 'Riwayat Pasien';
$aktif = 'dokter/riwayat.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Riwayat pasien</h1>
<p class="mt-1 text-sm text-ink/70">Pasien yang pernah selesai Anda periksa (maksimal 100 terbaru).</p>

<form method="get" class="mt-4 flex items-end gap-3">
  <div>
    <label class="block text-xs font-medium" for="q">Cari nama pasien</label>
    <input id="q" name="q" value="<?= e($q) ?>"
           class="mt-1 border border-garis bg-white px-3 py-1.5 text-sm focus:border-klinik focus:outline-none">
  </div>
  <button class="px-3 py-1.5 text-sm">Cari</button>
</form>

<div class="mt-6 overflow-x-auto">
  <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
    <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
      <tr><th class="px-3 py-2">Tanggal</th><th class="px-3 py-2">Pasien</th><th class="px-3 py-2">Nomor</th><th class="px-3 py-2">Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($daftar as $r): ?>
        <tr class="border-t border-garis">
          <td data-label="Tanggal" class="whitespace-nowrap px-3 py-2"><?= e(tanggal_indonesia($r['tanggal'])) ?></td>
          <td data-label="Pasien" class="px-3 py-2"><?= e($r['pasien']) ?></td>
          <td data-label="Nomor" class="px-3 py-2 font-mono"><?= e(kode_antrean($r['nama_poli'], $r['no_antrean'])) ?></td>
          <td data-label="Aksi" class="px-3 py-2 text-right"><a class="action-button" href="<?= e(url('dokter/periksa.php?id=' . (int) $r['id'])) ?>">Lihat</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$daftar): ?>
        <tr><td colspan="4" class="px-3 py-4 text-ink/60">Belum ada riwayat pemeriksaan.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>