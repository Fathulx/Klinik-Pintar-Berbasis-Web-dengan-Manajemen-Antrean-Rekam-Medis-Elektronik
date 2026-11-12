<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifikasi.php';

$user = require_role('user');
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (post('aksi') === 'baca_semua') {
        tandai_notif_dibaca($pdo, $user['id']);
        redirect('user/notifikasi.php');
    }
    if (post('aksi') === 'baca') {
        $id = (int) post('id');
        tandai_notif_dibaca($pdo, $user['id'], $id);
        $st = $pdo->prepare('SELECT tautan FROM notifikasi WHERE id = ? AND user_id = ?');
        $st->execute([$id, $user['id']]);
        $tautan = $st->fetchColumn();
        redirect($tautan ? $tautan : 'user/notifikasi.php');
    }
    redirect('user/notifikasi.php');
}

$daftar = daftar_notifikasi($pdo, $user['id'], 0, 50);
$belum  = jumlah_notif_belum_dibaca($pdo, $user['id']);

$judul = 'Notifikasi';
$aktif = 'user/notifikasi.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="font-serif text-3xl font-bold text-klinik">Notifikasi</h1>
    <p class="mt-1 text-sm text-ink/70"><?= (int) $belum ?> belum dibaca</p>
  </div>
  <?php if ($belum > 0): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="baca_semua">
      <button class="action-button">Tandai semua dibaca</button>
    </form>
  <?php endif; ?>
</div>

<div class="mt-6 divide-y divide-garis border border-garis bg-white">
  <?php foreach ($daftar as $n): $baruIni = $n['dibaca_at'] === null; ?>
    <form method="post" class="<?= $baruIni ? 'bg-klinik/5' : '' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="baca">
      <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
      <button class="block w-full px-4 py-3 text-left hover:bg-paper">
        <span class="flex items-baseline justify-between gap-3">
          <span class="text-sm <?= $baruIni ? 'font-semibold text-klinik' : 'font-medium' ?>"><?= e($n['judul']) ?></span>
          <span class="whitespace-nowrap font-mono text-[11px] text-ink/60"><?= e(substr($n['created_at'], 0, 16)) ?></span>
        </span>
        <span class="mt-1 block text-sm text-ink/80"><?= e($n['pesan']) ?></span>
      </button>
    </form>
  <?php endforeach; ?>
  <?php if (!$daftar): ?>
    <p class="px-4 py-4 text-sm text-ink/60">Belum ada notifikasi.</p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>
