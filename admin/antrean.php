<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';

$user = require_role('admin');
$pdo  = db();
tandai_tidak_hadir_otomatis($pdo);

$hariIni = date('Y-m-d');
$tanggal = isset($_GET['tanggal']) && tanggal_valid($_GET['tanggal']) ? $_GET['tanggal'] : $hariIni;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id   = (int) post('id');
    $aksi = post('aksi');

    // perubahan status hanya boleh pada hari kunjungan
    if ($aksi === 'hadir') {
        $up = $pdo->prepare(
            "UPDATE reservasi SET status = 'hadir' WHERE id = ? AND status = 'menunggu' AND tanggal = ?"
        );
        $up->execute([$id, $hariIni]);
        if ($up->rowCount() > 0) {
            catat_log('konfirmasi_hadir', 'reservasi ' . $id);
            set_flash('Kehadiran pasien dikonfirmasi.');
        } else {
            set_flash('Status tidak dapat diubah. Muat ulang halaman lalu coba lagi.', 'error');
        }
    } elseif ($aksi === 'tidak_hadir') {
        $up = $pdo->prepare(
            "UPDATE reservasi SET status = 'tidak_hadir'
             WHERE id = ? AND status IN ('menunggu', 'hadir') AND tanggal = ?"
        );
        $up->execute([$id, $hariIni]);
        if ($up->rowCount() > 0) {
            catat_log('tandai_tidak_hadir', 'reservasi ' . $id);
            set_flash('Pasien ditandai tidak hadir.');
        } else {
            set_flash('Status tidak dapat diubah. Muat ulang halaman lalu coba lagi.', 'error');
        }
    }
    redirect('admin/antrean.php?tanggal=' . (tanggal_valid(post('tanggal')) ? post('tanggal') : $hariIni));
}

$st = $pdo->prepare(
    "SELECT r.id, r.no_antrean, r.status, j.id AS jadwal_id,
            SUBSTR(j.jam_mulai, 1, 5) AS jam_mulai, SUBSTR(j.jam_selesai, 1, 5) AS jam_selesai,
            ud.nama AS dokter, p.nama_poli, up.nama AS pasien
     FROM reservasi r
     JOIN jadwal_dokter j ON j.id = r.jadwal_id
     JOIN dokter d ON d.id = j.dokter_id
     JOIN users ud ON ud.id = d.user_id
     JOIN poli p ON p.id = d.poli_id
     JOIN pasien ps ON ps.id = r.pasien_id
     JOIN users up ON up.id = ps.user_id
     WHERE r.tanggal = ?
     ORDER BY ud.nama, j.jam_mulai, r.no_antrean"
);
$st->execute([$tanggal]);

$grup = [];
foreach ($st->fetchAll() as $r) {
    $g = $r['jadwal_id'];
    if (!isset($grup[$g])) {
        $grup[$g] = [
            'dokter' => $r['dokter'], 'poli' => $r['nama_poli'],
            'jam' => $r['jam_mulai'] . ' - ' . $r['jam_selesai'], 'baris' => [],
        ];
    }
    $grup[$g]['baris'][] = $r;
}

$judul = 'Antrean';
$aktif = 'admin/antrean.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Antrean</h1>
<p class="mt-1 text-sm text-ink/70"><?= e(tanggal_indonesia($tanggal)) ?></p>

<form method="get" class="mt-4 flex flex-wrap items-end gap-3">
  <div>
    <label class="block text-xs font-medium" for="tanggal">Tanggal</label>
    <input id="tanggal" name="tanggal" type="date" value="<?= e($tanggal) ?>"
           class="mt-1 border border-garis bg-white px-3 py-1.5 font-mono text-sm focus:border-klinik focus:outline-none">
  </div>
  <button class="px-3 py-1.5 text-sm">Tampilkan</button>
  <a class="action-button" href="<?= e(url('admin/antrean.php')) ?>">Hari ini</a>
</form>

<?php if ($tanggal !== $hariIni): ?>
  <p class="mt-3 text-xs text-ink/60">Status kehadiran hanya dapat diubah pada hari kunjungan.</p>
<?php endif; ?>

<?php foreach ($grup as $g): ?>
  <?php $hitung = array_count_values(array_column($g['baris'], 'status')); ?>
  <section class="mt-8">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
      <h2 class="font-serif text-xl font-bold"><?= e($g['dokter']) ?>
        <span class="font-sans text-sm font-normal text-ink/60">Poli <?= e($g['poli']) ?></span>
      </h2>
      <p class="font-mono text-xs text-ink/60">
        <?= e($g['jam']) ?> |
        total <?= count($g['baris']) ?> |
        hadir <?= isset($hitung['hadir']) ? $hitung['hadir'] : 0 ?> |
        selesai <?= isset($hitung['selesai']) ? $hitung['selesai'] : 0 ?>
      </p>
    </div>
    <div class="mt-2 overflow-x-auto">
      <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
        <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
          <tr><th class="px-3 py-2">Nomor</th><th class="px-3 py-2">Pasien</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Aksi</th></tr>
        </thead>
        <tbody>
          <?php foreach ($g['baris'] as $r): ?>
            <tr class="border-t border-garis">
              <td data-label="Nomor" class="px-3 py-2 font-mono font-semibold text-bata"><?= e(kode_antrean($r['nama_poli'], $r['no_antrean'])) ?></td>
              <td data-label="Pasien" class="px-3 py-2"><?= e($r['pasien']) ?></td>
              <td data-label="Status" class="px-3 py-2"><?= badge_reservasi($r['status']) ?></td>
              <td data-label="Aksi" class="whitespace-nowrap px-3 py-2">
                <?php if ($tanggal === $hariIni && $r['status'] === 'menunggu'): ?>
                  <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="hadir">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="tanggal" value="<?= e($tanggal) ?>">
                    <button class="bg-klinik px-3 py-1 text-xs font-medium text-white hover:bg-ink">Konfirmasi hadir</button>
                  </form>
                <?php endif; ?>
                <?php if ($tanggal === $hariIni && in_array($r['status'], ['menunggu', 'hadir'], true)): ?>
                  <form method="post" class="ml-2 inline" onsubmit="return confirm('Tandai pasien ini tidak hadir?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="tidak_hadir">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="tanggal" value="<?= e($tanggal) ?>">
                    <button class="action-button action-danger text-xs text-bata">Tidak hadir</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endforeach; ?>

<?php if (!$grup): ?>
  <p class="mt-8 text-sm text-ink/60">Tidak ada reservasi pada tanggal ini.</p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>