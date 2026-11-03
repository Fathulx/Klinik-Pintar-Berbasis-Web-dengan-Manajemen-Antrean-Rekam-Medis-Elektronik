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

$id = isset($_GET['id']) && is_string($_GET['id']) ? (int) $_GET['id'] : 0;

// Pasien hanya dapat membuka rekam medis miliknya sendiri.
$st = $pdo->prepare(
    "SELECT r.id, r.tanggal, r.no_antrean, p.nama_poli, u.nama AS dokter,
            rm.id AS rm_id, rm.keluhan, rm.pemeriksaan, rm.catatan_dokter
     FROM reservasi r
     JOIN jadwal_dokter j ON j.id = r.jadwal_id
     JOIN dokter d ON d.id = j.dokter_id
     JOIN users u ON u.id = d.user_id
     JOIN poli p ON p.id = d.poli_id
     JOIN rekam_medis rm ON rm.reservasi_id = r.id AND rm.terkunci = 1
     WHERE r.id = ? AND r.pasien_id = ? AND r.status = 'selesai'"
);
$st->execute([$id, $pasienId]);
$r = $st->fetch();

if (!$r) {
    set_flash('Rekam medis tidak ditemukan.', 'error');
    redirect('user/riwayat.php');
}

$q = $pdo->prepare('SELECT nama_obat, dosis, aturan_pakai FROM resep WHERE rekam_medis_id = ? ORDER BY id');
$q->execute([$r['rm_id']]);
$resep = $q->fetchAll();

catat_log('lihat_rekam_medis', 'reservasi ' . $r['id']);

$bagian = [
    'Keluhan'           => $r['keluhan'],
    'Hasil pemeriksaan' => $r['pemeriksaan'],
    'Catatan dokter'    => $r['catatan_dokter'],
];

$judul = 'Rekam Medis';
$aktif = 'user/riwayat.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<a class="action-button" href="<?= e(url('user/riwayat.php')) ?>">Kembali ke riwayat</a>

<div class="mt-4 border border-garis bg-white p-5">
  <p class="font-mono text-[11px] uppercase tracking-widest text-ink/60">Kunjungan</p>
  <p class="font-serif text-2xl font-bold"><?= e(tanggal_indonesia($r['tanggal'])) ?></p>
  <p class="text-sm text-ink/70"><?= e($r['dokter']) ?> &middot; Poli <?= e($r['nama_poli']) ?> &middot;
    <span class="font-mono"><?= e(kode_antrean($r['nama_poli'], $r['no_antrean'])) ?></span></p>
</div>

<div class="mt-6 space-y-6">
  <?php foreach ($bagian as $judulBagian => $isi): ?>
    <div>
      <p class="font-mono text-[11px] uppercase tracking-widest text-ink/60"><?= e($judulBagian) ?></p>
      <p class="mt-1 whitespace-pre-line border border-garis bg-white px-3 py-2 text-sm"><?= e($isi !== null && $isi !== '' ? $isi : '-') ?></p>
    </div>
  <?php endforeach; ?>

  <div>
    <p class="font-mono text-[11px] uppercase tracking-widest text-ink/60">Resep</p>
    <table class="responsive-table mt-1 w-full border border-garis bg-white text-left text-sm">
      <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
        <tr><th class="px-3 py-2">Obat</th><th class="px-3 py-2">Dosis</th><th class="px-3 py-2">Aturan pakai</th></tr>
      </thead>
      <tbody>
        <?php foreach ($resep as $o): ?>
          <tr class="border-t border-garis">
            <td data-label="Obat" class="px-3 py-2"><?= e($o['nama_obat']) ?></td>
            <td data-label="Dosis" class="px-3 py-2"><?= e($o['dosis']) ?></td>
            <td data-label="Aturan pakai" class="px-3 py-2"><?= e($o['aturan_pakai']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$resep): ?>
          <tr><td colspan="3" class="px-3 py-3 text-ink/60">Tidak ada resep.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>