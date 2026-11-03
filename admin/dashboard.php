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
<h1 class="font-serif text-3xl font-bold text-klinik">Dashboard</h1>
<p class="mt-1 text-sm text-ink/70"><?= e(tanggal_indonesia($hariIni)) ?></p>

<dl class="stat-grid mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
  <?php foreach (['Antrean hari ini' => 'total', 'Belum hadir' => 'menunggu', 'Hadir atau diperiksa' => 'hadir', 'Selesai' => 'selesai'] as $nama => $k): ?>
    <div class="bg-white p-4">
      <dt class="font-mono text-[11px] uppercase tracking-widest text-ink/60"><?= e($nama) ?></dt>
      <dd class="mt-1 font-mono text-3xl font-semibold text-klinik"><?= (int) $hari[$k] ?></dd>
    </div>
  <?php endforeach; ?>
</dl>
<p class="mt-2 text-xs text-ink/60">
  <?= (int) $jml['dokter'] ?> dokter aktif &middot; <?= (int) $jml['poli'] ?> poli &middot; <?= (int) $jml['pasien'] ?> pasien terdaftar
  &middot; <a class="underline" href="<?= e(url('admin/antrean.php')) ?>">Kelola antrean hari ini</a>
</p>

<div class="mt-10 grid gap-10 lg:grid-cols-2">
  <section>
    <h2 class="font-serif text-xl font-bold">Reservasi 7 hari terakhir</h2>
    <div class="mt-4 flex h-44 items-end gap-2 border-b border-garis">
      <?php foreach ($deret as $d): $hariIniBar = ($d['tgl'] === $hariIni); ?>
        <div class="flex h-full flex-1 flex-col items-center justify-end">
          <span class="mb-1 font-mono text-xs"><?= (int) $d['n'] ?></span>
          <div class="w-full <?= $hariIniBar ? 'bg-bata' : 'bg-klinik' ?>"
               style="height: <?= $d['n'] > 0 ? max(4, (int) round($d['n'] / $maks * 100)) : 2 ?>%"></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-1 flex gap-2">
      <?php foreach ($deret as $d): ?>
        <span class="flex-1 text-center font-mono text-[11px] uppercase text-ink/60"><?= e(mb_substr(hari_dari_tanggal($d['tgl']), 0, 3)) ?></span>
      <?php endforeach; ?>
    </div>
  </section>

  <section>
    <h2 class="font-serif text-xl font-bold">Praktik hari ini</h2>
    <div class="mt-3 overflow-x-auto">
      <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
        <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
          <tr><th class="px-3 py-2">Dokter</th><th class="px-3 py-2">Jam</th><th class="px-3 py-2">Terisi</th></tr>
        </thead>
        <tbody>
          <?php foreach ($praktik as $p): ?>
            <tr class="border-t border-garis">
              <td data-label="Dokter" class="px-3 py-2"><?= e($p['nama']) ?><span class="block text-xs text-ink/60">Poli <?= e($p['nama_poli']) ?></span></td>
              <td data-label="Jam" class="whitespace-nowrap px-3 py-2 font-mono text-xs"><?= e($p['jam_mulai']) ?> - <?= e($p['jam_selesai']) ?></td>
              <td data-label="Terisi" class="px-3 py-2 font-mono"><?= (int) $p['terisi'] ?>/<?= (int) $p['kuota'] ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$praktik): ?>
            <tr><td colspan="3" class="px-3 py-4 text-ink/60">Tidak ada jadwal praktik aktif hari ini.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>