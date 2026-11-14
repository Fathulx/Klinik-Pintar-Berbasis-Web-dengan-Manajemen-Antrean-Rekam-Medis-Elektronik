<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';

$user     = require_role('user');
$pdo      = db();
tandai_tidak_hadir_otomatis($pdo);
$pasienId = pasien_id_dari_user($pdo, $user['id']);
$berikut  = $pasienId ? reservasi_pasien($pdo, $pasienId, true) : [];

// tiga kunjungan selesai terakhir, untuk membuka rekam medis dengan cepat
$terakhir = [];
if ($pasienId) {
    $st = $pdo->prepare(
        "SELECT r.id, r.tanggal, u.nama AS dokter, p.nama_poli
         FROM reservasi r
         JOIN jadwal_dokter j ON j.id = r.jadwal_id
         JOIN dokter d ON d.id = j.dokter_id
         JOIN users u ON u.id = d.user_id
         JOIN poli p ON p.id = d.poli_id
         WHERE r.pasien_id = ? AND r.status = 'selesai'
         ORDER BY r.tanggal DESC, r.id DESC LIMIT 3"
    );
    $st->execute([$pasienId]);
    $terakhir = $st->fetchAll();
}

$judul = 'Dashboard Pasien';
$aktif = 'user/dashboard.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-4xl font-bold text-ink">Halo, <?= e($user['nama']) ?></h1>
<p class="mt-1 text-ink/70"><?= e(tanggal_indonesia(date('Y-m-d'))) ?></p>

<div class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,30rem)_minmax(0,1fr)]">
  <section aria-labelledby="judul-antrean">
    <h2 id="judul-antrean" class="font-serif text-2xl font-bold">
      <?= $berikut ? 'Kunjungan berikutnya' : 'Belum ada antrean' ?>
    </h2>
    <?php if ($berikut): ?>
      <div class="mt-4"><?= karcis_html($berikut[0]) ?></div>
      <div class="mt-5 flex flex-wrap gap-3">
        <a class="bg-klinik px-4 py-2.5 font-bold text-white" href="<?= e(url('user/antrean.php')) ?>">Lihat antrean saya</a>
        <a class="action-button" href="<?= e(url('user/reservasi.php')) ?>">Pesan kunjungan lain</a>
      </div>
      <?php if (count($berikut) > 1): ?>
        <p class="mt-3 text-sm text-ink/70">Anda punya <?= count($berikut) - 1 ?> reservasi lain yang akan datang.</p>
      <?php endif; ?>
    <?php else: ?>
      <p class="mt-3 max-w-md text-ink/75">
        Pilih poli, dokter, dan tanggal. Nomor antrean langsung terbit dan Anda diberi tahu
        saat giliran sudah dekat.
      </p>
      <a class="mt-5 inline-flex bg-klinik px-5 py-3 font-bold text-white" href="<?= e(url('user/reservasi.php')) ?>">Buat reservasi</a>
    <?php endif; ?>
  </section>

  <section aria-labelledby="judul-riwayat">
    <h2 id="judul-riwayat" class="font-serif text-2xl font-bold">Kunjungan terakhir</h2>
    <?php if ($terakhir): ?>
      <ul class="mt-4 divide-y divide-garis border-y border-garis">
        <?php foreach ($terakhir as $k): ?>
          <li class="flex flex-wrap items-center justify-between gap-3 py-3.5">
            <div>
              <span class="poli-tanda <?= kelas_poli($k['nama_poli']) ?>">Poli <?= e($k['nama_poli']) ?></span>
              <p class="mt-0.5 font-bold"><?= e($k['dokter']) ?></p>
              <p class="text-sm text-ink/70"><?= e(tanggal_indonesia($k['tanggal'])) ?></p>
            </div>
            <a class="action-button" href="<?= e(url('user/rekam_medis.php?id=' . (int) $k['id'])) ?>">Rekam medis</a>
          </li>
        <?php endforeach; ?>
      </ul>
      <a class="mt-3 inline-block font-bold text-klinik underline" href="<?= e(url('user/riwayat.php')) ?>">Semua riwayat kunjungan</a>
    <?php else: ?>
      <p class="mt-3 text-ink/70">Hasil pemeriksaan dan resep akan muncul di sini setelah kunjungan pertama Anda.</p>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>
