<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php'; // untuk tanggal_valid()

$user = require_role('super_admin');
$pdo  = db();

$aksiF  = isset($_GET['aksi']) && is_string($_GET['aksi']) ? $_GET['aksi'] : '';
$q      = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$dari   = isset($_GET['dari']) && tanggal_valid($_GET['dari']) ? $_GET['dari'] : '';
$sampai = isset($_GET['sampai']) && tanggal_valid($_GET['sampai']) ? $_GET['sampai'] : '';
$hal    = isset($_GET['hal']) && is_string($_GET['hal']) ? max(1, (int) $_GET['hal']) : 1;
$per    = 25;

$where = [];
$param = [];
if ($aksiF !== '') {
    $where[] = 'l.aksi = ?';
    $param[] = $aksiF;
}
if ($q !== '') {
    $where[] = '(u.nama LIKE ? OR u.email LIKE ? OR l.detail LIKE ?)';
    $like = '%' . $q . '%';
    array_push($param, $like, $like, $like);
}
if ($dari !== '') {
    $where[] = 'DATE(l.created_at) >= ?';
    $param[] = $dari;
}
if ($sampai !== '') {
    $where[] = 'DATE(l.created_at) <= ?';
    $param[] = $sampai;
}
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$hit = $pdo->prepare("SELECT COUNT(*) FROM log_aktivitas l LEFT JOIN users u ON u.id = l.user_id $w");
$hit->execute($param);
$total  = (int) $hit->fetchColumn();
$jmlHal = max(1, (int) ceil($total / $per));
$hal    = min($hal, $jmlHal);
$offset = ($hal - 1) * $per;

$st = $pdo->prepare(
    "SELECT l.aksi, l.detail, l.ip_address, l.created_at, u.nama, u.role
     FROM log_aktivitas l LEFT JOIN users u ON u.id = l.user_id
     $w ORDER BY l.id DESC LIMIT $per OFFSET $offset"
);
$st->execute($param);
$daftar = $st->fetchAll();

$aksiList = $pdo->query('SELECT DISTINCT aksi FROM log_aktivitas ORDER BY aksi')->fetchAll(PDO::FETCH_COLUMN);

$rawan = ['login_gagal', 'akun_terkunci', 'akses_ditolak'];
$ring  = array_fill_keys($rawan, 0);
$sr = $pdo->query(
    "SELECT aksi, COUNT(*) AS n FROM log_aktivitas
     WHERE aksi IN ('login_gagal', 'akun_terkunci', 'akses_ditolak')
       AND created_at >= NOW() - INTERVAL 1 DAY GROUP BY aksi"
)->fetchAll();
foreach ($sr as $b) {
    $ring[$b['aksi']] = (int) $b['n'];
}

function url_log($halaman, $f)
{
    $f['hal'] = $halaman;
    return url('superadmin/log.php?' . http_build_query(array_filter($f, function ($v) {
        return $v !== '' && $v !== null;
    })));
}
$filter = ['aksi' => $aksiF, 'q' => $q, 'dari' => $dari, 'sampai' => $sampai];

$kelasInput = 'mt-1 border border-garis bg-white px-3 py-1.5 text-sm focus:border-klinik focus:outline-none';
$judul = 'Log Aktivitas';
$aktif = 'superadmin/log.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-3xl font-bold text-klinik">Log aktivitas</h1>
<p class="mt-1 text-sm text-ink/70">Catatan siapa melakukan apa. Isi data medis tidak pernah dicatat di sini.</p>

<dl class="stat-grid mt-6 grid grid-cols-3 gap-3">
  <?php foreach ($rawan as $k): ?>
    <div class="bg-white p-4">
      <dt class="font-mono text-[11px] uppercase tracking-widest text-ink/60"><?= e(str_replace('_', ' ', $k)) ?> (24 jam)</dt>
      <dd class="mt-1 font-mono text-3xl font-semibold <?= $ring[$k] > 0 ? 'text-bata' : 'text-klinik' ?>"><?= $ring[$k] ?></dd>
    </div>
  <?php endforeach; ?>
</dl>

<form method="get" class="activity-log-filters mt-6 flex flex-wrap items-end gap-3">
  <div class="log-filter-action">
    <label class="block text-xs font-medium" for="aksi">Aksi</label>
    <select id="aksi" name="aksi" class="<?= $kelasInput ?>">
      <option value="">Semua</option>
      <?php foreach ($aksiList as $a): ?>
        <option value="<?= e($a) ?>" <?= $aksiF === $a ? 'selected' : '' ?>><?= e($a) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="log-filter-date">
    <label class="block text-xs font-medium" for="dari">Dari</label>
    <input id="dari" name="dari" type="date" value="<?= e($dari) ?>" class="<?= $kelasInput ?> font-mono">
  </div>
  <div class="log-filter-date">
    <label class="block text-xs font-medium" for="sampai">Sampai</label>
    <input id="sampai" name="sampai" type="date" value="<?= e($sampai) ?>" class="<?= $kelasInput ?> font-mono">
  </div>
  <div class="log-filter-search">
    <label class="block text-xs font-medium" for="q">Cari pengguna atau detail</label>
    <input id="q" name="q" value="<?= e($q) ?>" class="<?= $kelasInput ?>">
  </div>
  <div class="log-filter-buttons">
    <button class="px-3 py-1.5 text-sm">Terapkan</button>
    <a class="action-button" href="<?= e(url('superadmin/log.php')) ?>">Atur ulang</a>
  </div>
</form>

<p class="mt-4 font-mono text-xs text-ink/60"><?= $total ?> catatan, halaman <?= $hal ?> dari <?= $jmlHal ?></p>

<div class="mt-2 overflow-x-auto">
  <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
    <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
      <tr><th class="px-3 py-2">Waktu</th><th class="px-3 py-2">Pengguna</th><th class="px-3 py-2">Aksi</th><th class="px-3 py-2">Detail</th><th class="px-3 py-2">IP</th></tr>
    </thead>
    <tbody>
      <?php foreach ($daftar as $b): ?>
        <tr class="border-t border-garis align-top">
          <td data-label="Waktu" class="whitespace-nowrap px-3 py-2 font-mono text-xs"><?= e($b['created_at']) ?></td>
          <td data-label="Pengguna" class="px-3 py-2">
            <?= e($b['nama'] !== null ? $b['nama'] : '-') ?>
            <?php if ($b['role']): ?><span class="block text-xs text-ink/60"><?= e(str_replace('_', ' ', $b['role'])) ?></span><?php endif; ?>
          </td>
          <td data-label="Aksi" class="px-3 py-2 font-mono text-xs <?= in_array($b['aksi'], $rawan, true) ? 'font-semibold text-bata' : '' ?>"><?= e($b['aksi']) ?></td>
          <td data-label="Detail" class="px-3 py-2 text-ink/70"><?= e($b['detail']) ?></td>
          <td data-label="IP" class="whitespace-nowrap px-3 py-2 font-mono text-xs"><?= e($b['ip_address']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$daftar): ?>
        <tr><td colspan="5" class="px-3 py-4 text-ink/60">Tidak ada catatan yang cocok.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="mt-4 flex items-center gap-4 text-sm">
  <?php if ($hal > 1): ?><a class="action-button" href="<?= e(url_log($hal - 1, $filter)) ?>">Sebelumnya</a><?php endif; ?>
  <?php if ($hal < $jmlHal): ?><a class="action-button" href="<?= e(url_log($hal + 1, $filter)) ?>">Berikutnya</a><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>