<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';

$user = require_role('user');
$pdo  = db();
tandai_tidak_hadir_otomatis($pdo);

$pasienId = pasien_id_dari_user($pdo, $user['id']);
if (!$pasienId) {
    http_response_code(500);
    exit('Data pasien tidak ditemukan. Hubungi admin.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('aksi') === 'batal') {
    csrf_verify();
    list($berhasil, $pesan) = batalkan_reservasi($pdo, $pasienId, (int) post('id'));
    if ($berhasil) {
        catat_log('batal_reservasi', 'id ' . (int) post('id'));
    }
    set_flash($pesan, $berhasil ? 'ok' : 'error');
    redirect('user/antrean.php');
}

$aktifList = reservasi_pasien($pdo, $pasienId, true);
$riwayat   = reservasi_pasien($pdo, $pasienId, false);

$judul = 'Antrean Saya';
$aktif = 'user/antrean.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Antrean saya</h1>

<?php if ($aktifList): ?>
  <div class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($aktifList as $r): ?>
      <div>
        <?= karcis_html($r) ?>
        <?php if ($r['status'] === 'menunggu' && $r['tanggal'] >= date('Y-m-d')): ?>
          <form method="post" class="mt-2" onsubmit="return confirm('Batalkan reservasi ini?')">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="batal">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="action-button action-danger text-sm text-bata">Batalkan reservasi</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <p class="mt-4 text-sm text-ink/70">
    Belum ada antrean aktif.
    <a class="text-klinik underline" href="<?= e(url('user/reservasi.php')) ?>">Buat reservasi</a>
  </p>
<?php endif; ?>

<h2 class="mt-10 font-serif text-xl font-bold">Riwayat</h2>
<div class="mt-3 overflow-x-auto">
  <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
    <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
      <tr><th class="px-3 py-2">Tanggal</th><th class="px-3 py-2">Dokter</th><th class="px-3 py-2">Nomor</th><th class="px-3 py-2">Status</th></tr>
    </thead>
    <tbody>
      <?php foreach ($riwayat as $r): ?>
        <tr class="border-t border-garis">
          <td data-label="Tanggal" class="whitespace-nowrap px-3 py-2"><?= e(tanggal_indonesia($r['tanggal'])) ?></td>
          <td data-label="Dokter" class="px-3 py-2"><?= e($r['dokter']) ?> <span class="text-xs text-ink/60">(<?= e($r['nama_poli']) ?>)</span></td>
          <td data-label="Nomor" class="px-3 py-2 font-mono"><?= e(kode_antrean($r['nama_poli'], $r['no_antrean'])) ?></td>
          <td data-label="Status" class="px-3 py-2"><?= badge_reservasi($r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$riwayat): ?>
        <tr><td colspan="4" class="px-3 py-4 text-ink/60">Belum ada riwayat.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>