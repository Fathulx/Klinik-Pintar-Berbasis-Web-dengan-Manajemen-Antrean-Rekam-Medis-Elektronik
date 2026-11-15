<?php
require_once __DIR__ . '/../includes/auth.php';

$user  = require_role('super_admin');
$judul = 'Dashboard Super Admin';
$aktif = 'superadmin/dashboard.php';
$pdo   = db();

$stat = $pdo->query(
    "SELECT
       (SELECT COUNT(*) FROM users WHERE role = 'admin') AS admin,
       (SELECT COUNT(*) FROM dokter) AS dokter,
       (SELECT COUNT(*) FROM pasien) AS pasien,
       (SELECT COUNT(*) FROM poli) AS poli"
)->fetch();

// kejadian rawan 24 jam terakhir, sama dengan ringkasan di Log Aktivitas
$rawan = ['login_gagal' => 'Login gagal', 'akun_terkunci' => 'Akun terkunci', 'akses_ditolak' => 'Akses ditolak'];
$ring  = array_fill_keys(array_keys($rawan), 0);
foreach ($pdo->query(
    "SELECT aksi, COUNT(*) AS n FROM log_aktivitas
     WHERE aksi IN ('login_gagal', 'akun_terkunci', 'akses_ditolak')
       AND created_at >= NOW() - INTERVAL 1 DAY GROUP BY aksi"
)->fetchAll() as $b) {
    $ring[$b['aksi']] = (int) $b['n'];
}
$totalRawan = array_sum($ring);

$log = $pdo->query(
    'SELECT l.aksi, l.detail, l.ip_address, l.created_at, u.nama
     FROM log_aktivitas l LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.id DESC LIMIT 8'
)->fetchAll();

require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-4xl font-bold">Dashboard</h1>
<p class="mt-1 text-ink/70">Keamanan akun dan ringkasan data klinik.</p>

<section class="ringkasan mt-6" aria-label="Ringkasan keamanan dan data">
  <div class="ringkasan__utama <?= $totalRawan > 0 ? 'ringkasan__utama--waspada' : '' ?>">
    <p class="ringkasan__label">Kejadian rawan, 24 jam terakhir</p>
    <p class="ringkasan__angka"><?= $totalRawan ?></p>
    <p class="max-w-xs text-sm text-ink/70">
      <?php
      $rincian = [];
      foreach ($rawan as $k => $nama) {
          if ($ring[$k] > 0) {
              $rincian[] = $nama . ' ' . $ring[$k];
          }
      }
      echo $rincian ? e(implode(', ', $rincian)) . '.' : 'Tidak ada login gagal, akun terkunci, atau akses ditolak.';
      ?>
    </p>
    <a class="mt-4 inline-flex bg-klinik px-4 py-2.5 font-semibold text-white" href="<?= e(url('superadmin/log.php')) ?>">Buka log aktivitas</a>
  </div>
  <dl class="ringkasan__lain">
    <div><dt>Admin</dt><dd><?= (int) $stat['admin'] ?></dd></div>
    <div><dt>Dokter</dt><dd><?= (int) $stat['dokter'] ?></dd></div>
    <div><dt>Pasien</dt><dd><?= (int) $stat['pasien'] ?></dd></div>
    <div><dt>Poli</dt><dd><?= (int) $stat['poli'] ?></dd></div>
    <div class="ringkasan__tautan"><a class="font-semibold text-klinik underline" href="<?= e(url('superadmin/kelola_admin.php')) ?>">Kelola admin</a></div>
    <div class="ringkasan__tautan"><a class="font-semibold text-klinik underline" href="<?= e(url('superadmin/poli.php')) ?>">Data poli</a></div>
  </dl>
</section>

<h2 class="mt-10 font-serif text-2xl font-bold">Aktivitas terbaru</h2>
<div class="mt-3 overflow-x-auto">
  <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
    <thead class="bg-paper text-[13px] font-semibold text-ink/60">
      <tr><th class="px-3 py-2">Waktu</th><th class="px-3 py-2">Pengguna</th><th class="px-3 py-2">Aksi</th><th class="px-3 py-2">Detail</th></tr>
    </thead>
    <tbody>
      <?php foreach ($log as $b): ?>
        <tr class="border-t border-garis">
          <td data-label="Waktu" class="whitespace-nowrap px-3 py-2 font-mono text-xs"><?= e($b['created_at']) ?></td>
          <td data-label="Pengguna" class="px-3 py-2"><?= e($b['nama'] !== null ? $b['nama'] : '-') ?></td>
          <td data-label="Aksi" class="px-3 py-2 font-mono text-xs <?= isset($rawan[$b['aksi']]) ? 'font-semibold text-bata' : '' ?>"><?= e($b['aksi']) ?></td>
          <td data-label="Detail" class="px-3 py-2 text-ink/70"><?= e($b['detail']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$log): ?>
        <tr><td colspan="4" class="px-3 py-4 text-ink/60">Belum ada aktivitas.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>
