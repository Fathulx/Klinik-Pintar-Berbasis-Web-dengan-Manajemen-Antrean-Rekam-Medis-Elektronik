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

$log = $pdo->query(
    'SELECT l.aksi, l.detail, l.ip_address, l.created_at, u.nama
     FROM log_aktivitas l LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.id DESC LIMIT 8'
)->fetchAll();

$label = ['admin' => 'Admin', 'dokter' => 'Dokter', 'pasien' => 'Pasien', 'poli' => 'Poli'];
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Dashboard</h1>
<p class="mt-1 text-sm text-ink/70">Ringkasan sistem dan aktivitas terbaru.</p>

<dl class="stat-grid mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
  <?php foreach ($label as $kunci => $nama): ?>
    <div class="bg-white p-4">
      <dt class="font-mono text-[11px] uppercase tracking-widest text-ink/60"><?= e($nama) ?></dt>
      <dd class="mt-1 font-mono text-3xl font-semibold text-klinik"><?= (int) $stat[$kunci] ?></dd>
    </div>
  <?php endforeach; ?>
</dl>

<h2 class="mt-10 font-serif text-xl font-bold">Aktivitas terbaru</h2>
<div class="mt-3 overflow-x-auto">
  <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
    <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
      <tr><th class="px-3 py-2">Waktu</th><th class="px-3 py-2">Pengguna</th><th class="px-3 py-2">Aksi</th><th class="px-3 py-2">Detail</th></tr>
    </thead>
    <tbody>
      <?php foreach ($log as $b): ?>
        <tr class="border-t border-garis">
          <td data-label="Waktu" class="whitespace-nowrap px-3 py-2 font-mono text-xs"><?= e($b['created_at']) ?></td>
          <td data-label="Pengguna" class="px-3 py-2"><?= e($b['nama'] !== null ? $b['nama'] : '-') ?></td>
          <td data-label="Aksi" class="px-3 py-2 font-mono text-xs"><?= e($b['aksi']) ?></td>
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