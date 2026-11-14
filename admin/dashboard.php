<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';

$user = require_role('admin');
$pdo  = db();
tandai_tidak_hadir_otomatis($pdo);

$hariIni = date('Y-m-d');

$st = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'menunggu'), 0) AS menunggu,
            COALESCE(SUM(status IN ('hadir', 'diperiksa')), 0) AS hadir,
            COALESCE(SUM(status = 'selesai'), 0) AS selesai
     FROM reservasi WHERE tanggal = ? AND status <> 'batal'"
);
$st->execute([$hariIni]);
$hari = $st->fetch();

$jml = $pdo->query(
    "SELECT (SELECT COUNT(*) FROM dokter d JOIN users u ON u.id = d.user_id AND u.status = 'aktif') AS dokter,
            (SELECT COUNT(*) FROM pasien) AS pasien,
            (SELECT COUNT(*) FROM poli) AS poli"
)->fetch();

// reservasi (tidak dibatalkan) 7 hari terakhir
$awal = date('Y-m-d', strtotime('-6 days'));
$st = $pdo->prepare(
    "SELECT tanggal, COUNT(*) AS n FROM reservasi
     WHERE tanggal BETWEEN ? AND ? AND status <> 'batal' GROUP BY tanggal"
);
$st->execute([$awal, $hariIni]);
$petaHari = [];
foreach ($st->fetchAll() as $b) {
    $petaHari[$b['tanggal']] = (int) $b['n'];
}
$deret = [];
for ($i = 6; $i >= 0; $i--) {
    $t = date('Y-m-d', strtotime("-$i days"));
    $deret[] = ['tgl' => $t, 'n' => isset($petaHari[$t]) ? $petaHari[$t] : 0];
}
$maks = max(1, max(array_column($deret, 'n')));

$st = $pdo->prepare(
    "SELECT ud.nama, p.nama_poli, SUBSTR(j.jam_mulai, 1, 5) AS jam_mulai, SUBSTR(j.jam_selesai, 1, 5) AS jam_selesai,
            j.kuota,
            (SELECT COUNT(*) FROM reservasi r WHERE r.jadwal_id = j.id AND r.tanggal = ? AND r.status <> 'batal') AS terisi
     FROM jadwal_dokter j
     JOIN dokter d ON d.id = j.dokter_id
     JOIN users ud ON ud.id = d.user_id AND ud.status = 'aktif'
     JOIN poli p ON p.id = d.poli_id
     WHERE j.aktif = 1 AND j.hari = ?
     ORDER BY j.jam_mulai, ud.nama"
);
$st->execute([$hariIni, hari_dari_tanggal($hariIni)]);
$praktik = $st->fetchAll();

$judul = 'Dashboard Admin';
$aktif = 'admin/dashboard.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-4xl font-bold">Dashboard</h1>
<p class="mt-1 text-ink/70"><?= e(tanggal_indonesia($hariIni)) ?></p>

<section class="ringkasan mt-6" aria-label="Ringkasan antrean hari ini">
  <div class="ringkasan__utama">
    <p class="ringkasan__label">Belum hadir hari ini</p>
    <p class="ringkasan__angka"><?= (int) $hari['menunggu'] ?></p>
    <p class="max-w-xs text-sm text-ink/70">Pasien yang sudah memesan tetapi kedatangannya belum dikonfirmasi.</p>
    <a class="mt-4 inline-flex bg-klinik px-4 py-2.5 font-semibold text-white" href="<?= e(url('admin/antrean.php')) ?>">Buka antrean hari ini</a>
  </div>
  <dl class="ringkasan__lain">
    <div><dt>Total antrean</dt><dd><?= (int) $hari['total'] ?></dd></div>
    <div><dt>Hadir atau diperiksa</dt><dd><?= (int) $hari['hadir'] ?></dd></div>
    <div><dt>Selesai</dt><dd><?= (int) $hari['selesai'] ?></dd></div>
    <div><dt>Dokter aktif</dt><dd><?= (int) $jml['dokter'] ?></dd></div>
    <div><dt>Poli</dt><dd><?= (int) $jml['poli'] ?></dd></div>
    <div><dt>Pasien terdaftar</dt><dd><?= (int) $jml['pasien'] ?></dd></div>
  </dl>
</section>

<div class="mt-10 grid gap-10 lg:grid-cols-2">
  <section>
    <h2 class="font-serif text-2xl font-bold">Reservasi 7 hari terakhir</h2>
    <?php $totalPekan = array_sum(array_column($deret, 'n')); ?>
    <div class="relative mt-4 flex h-44 items-end gap-2 border-b border-garis">
      <?php if ($totalPekan === 0): ?>
        <p class="absolute inset-x-0 top-1/3 text-center text-sm text-ink/60">
          Belum ada reservasi dalam 7 hari terakhir.
        </p>
      <?php endif; ?>
      <?php foreach ($deret as $d): $hariIniBar = ($d['tgl'] === $hariIni); ?>
        <div class="flex h-full flex-1 flex-col items-center justify-end"
             title="<?= e(tanggal_indonesia($d['tgl'])) ?>: <?= (int) $d['n'] ?> reservasi">
          <span class="mb-1 font-mono text-xs <?= $d['n'] > 0 ? '' : 'text-ink/30' ?>"><?= (int) $d['n'] ?></span>
          <div class="w-full rounded-t-md <?= $hariIniBar ? 'bg-panggil' : 'bg-klinik/85' ?>"
               style="height: <?= $d['n'] > 0 ? max(4, (int) round($d['n'] / $maks * 100)) : 2 ?>%"></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-1 flex gap-2">
      <?php foreach ($deret as $d): ?>
        <span class="flex-1 text-center text-[13px] font-semibold text-ink/60"><?= e(mb_substr(hari_dari_tanggal($d['tgl']), 0, 3)) ?></span>
      <?php endforeach; ?>
    </div>
  </section>

  <section>
    <h2 class="font-serif text-2xl font-bold">Praktik hari ini</h2>
    <div class="mt-3 overflow-x-auto">
      <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
        <thead class="bg-paper text-[13px] font-semibold text-ink/60">
          <tr><th class="px-3 py-2">Dokter</th><th class="px-3 py-2">Jam</th><th class="px-3 py-2">Terisi</th></tr>
        </thead>
        <tbody>
          <?php foreach ($praktik as $p): ?>
            <tr class="border-t border-garis">
              <td data-label="Dokter" class="px-3 py-2.5"><?= tanda_poli_html($p['nama_poli']) ?><span class="mt-0.5 block font-semibold"><?= e($p['nama']) ?></span></td>
              <td data-label="Jam" class="whitespace-nowrap px-3 py-2 font-mono text-xs"><?= e($p['jam_mulai']) ?> - <?= e($p['jam_selesai']) ?></td>
              <td data-label="Terisi" class="px-3 py-2.5">
                <span class="font-mono font-semibold"><?= (int) $p['terisi'] ?>/<?= (int) $p['kuota'] ?></span>
                <span class="meter-kuota" aria-hidden="true"><span style="width: <?= min(100, (int) round($p['terisi'] / max(1, $p['kuota']) * 100)) ?>%"></span></span>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$praktik): ?>
            <tr><td colspan="3" class="px-3 py-4 text-ink/60">Tidak ada jadwal praktik aktif hari ini. <a class="text-klinik underline" href="<?= e(url('admin/jadwal.php')) ?>">Atur jadwal</a></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>