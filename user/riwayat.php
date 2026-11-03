<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';

$user = require_role('user');
$pdo  = db();

$pasienId = pasien_id_dari_user($pdo, $user['id']);
if (!$pasienId) {
    http_response_code(500);
    exit('Data pasien tidak ditemukan. Hubungi admin.');
}

// hanya kunjungan yang rekam medisnya sudah selesai dan dikunci
$st = $pdo->prepare(
    "SELECT r.id, r.tanggal, r.no_antrean, p.nama_poli, u.nama AS dokter, rm.keluhan
     FROM reservasi r
     JOIN jadwal_dokter j ON j.id = r.jadwal_id
     JOIN dokter d ON d.id = j.dokter_id
     JOIN users u ON u.id = d.user_id
     JOIN poli p ON p.id = d.poli_id
     JOIN rekam_medis rm ON rm.reservasi_id = r.id AND rm.terkunci = 1
     WHERE r.pasien_id = ? AND r.status = 'selesai'
     ORDER BY r.tanggal DESC, r.id DESC LIMIT 100"
);
$st->execute([$pasienId]);
$daftar = $st->fetchAll();

$judul = 'Riwayat Kunjungan';
$aktif = 'user/riwayat.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Riwayat kunjungan</h1>
<p class="mt-1 text-sm text-ink/70">Kunjungan yang sudah selesai diperiksa beserta rekam medisnya.</p>

<div class="mt-6 overflow-x-auto">
  <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
    <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
      <tr><th class="px-3 py-2">Tanggal</th><th class="px-3 py-2">Dokter</th><th class="px-3 py-2">Keluhan</th><th class="px-3 py-2">Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($daftar as $r): ?>
        <tr class="border-t border-garis align-top">
          <td data-label="Tanggal" class="whitespace-nowrap px-3 py-2"><?= e(tanggal_indonesia($r['tanggal'])) ?></td>
          <td data-label="Dokter" class="px-3 py-2">
            <?= e($r['dokter']) ?>
            <span class="block text-xs text-ink/60">Poli <?= e($r['nama_poli']) ?></span>
          </td>
          <td data-label="Keluhan" class="px-3 py-2 text-ink/80"><?= e(mb_strimwidth($r['keluhan'], 0, 80, '...')) ?></td>
          <td data-label="Aksi" class="whitespace-nowrap px-3 py-2 text-right">
            <a class="action-button" href="<?= e(url('user/rekam_medis.php?id=' . (int) $r['id'])) ?>">Lihat rekam medis</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$daftar): ?>
        <tr><td colspan="4" class="px-3 py-4 text-ink/60">Belum ada riwayat kunjungan.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>