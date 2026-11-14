<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/antrean.php';

$sesi = user_login();
if ($sesi) {
    redirect(dashboard_path($sesi['role']));
}

$pdo     = db();
$hariIni = date('Y-m-d');
$urutan  = daftar_hari();

// hari praktik per dokter
$hariDokter = [];
foreach ($pdo->query('SELECT DISTINCT dokter_id, hari FROM jadwal_dokter WHERE aktif = 1')->fetchAll() as $b) {
    $hariDokter[$b['dokter_id']][] = $b['hari'];
}
foreach ($hariDokter as $k => $h) {
    usort($h, function ($a, $b) use ($urutan) {
        return array_search($a, $urutan) - array_search($b, $urutan);
    });
    $hariDokter[$k] = $h;
}

// poli dan dokter yang punya jadwal aktif
$poli = [];
$st = $pdo->query(
    "SELECT p.id AS poli_id, p.nama_poli, p.deskripsi, d.id AS dokter_id, u.nama, d.bio
     FROM poli p
     JOIN dokter d ON d.poli_id = p.id
     JOIN users u ON u.id = d.user_id AND u.status = 'aktif'
     WHERE EXISTS (SELECT 1 FROM jadwal_dokter j WHERE j.dokter_id = d.id AND j.aktif = 1)
     ORDER BY p.nama_poli, u.nama"
);
foreach ($st->fetchAll() as $b) {
    if (!isset($poli[$b['poli_id']])) {
        $poli[$b['poli_id']] = ['nama' => $b['nama_poli'], 'deskripsi' => $b['deskripsi'], 'dokter' => []];
    }
    $poli[$b['poli_id']]['dokter'][] = [
        'nama' => $b['nama'],
        'bio'  => $b['bio'],
        'hari' => isset($hariDokter[$b['dokter_id']]) ? $hariDokter[$b['dokter_id']] : [],
    ];
}

// Layar antrean hari ini: per jadwal, nomor yang sedang diperiksa atau
// (bila belum ada) nomor berikutnya yang menunggu giliran.
$st = $pdo->prepare(
    "SELECT ud.nama, p.nama_poli, SUBSTR(j.jam_mulai, 1, 5) AS jam_mulai, SUBSTR(j.jam_selesai, 1, 5) AS jam_selesai,
            j.kuota,
            (SELECT COUNT(*) FROM reservasi r WHERE r.jadwal_id = j.id AND r.tanggal = ? AND r.status <> 'batal') AS terisi,
            (SELECT MIN(r.no_antrean) FROM reservasi r WHERE r.jadwal_id = j.id AND r.tanggal = ? AND r.status = 'diperiksa') AS dilayani,
            (SELECT MIN(r.no_antrean) FROM reservasi r WHERE r.jadwal_id = j.id AND r.tanggal = ? AND r.status IN ('hadir', 'menunggu')) AS berikutnya
     FROM jadwal_dokter j
     JOIN dokter d ON d.id = j.dokter_id
     JOIN users ud ON ud.id = d.user_id AND ud.status = 'aktif'
     JOIN poli p ON p.id = d.poli_id
     WHERE j.aktif = 1 AND j.hari = ?
     ORDER BY j.jam_mulai, ud.nama"
);
$st->execute([$hariIni, $hariIni, $hariIni, hari_dari_tanggal($hariIni)]);
$praktik = $st->fetchAll();

// hari praktik terdekat berikutnya, untuk layar kosong
$hariBerikut = null;
if (!$praktik) {
    $adaHari = array_unique(array_merge([], ...array_values($hariDokter ?: [[]])));
    for ($i = 1; $i <= 7 && $hariBerikut === null; $i++) {
        $tgl = date('Y-m-d', strtotime("+$i day"));
        if (in_array(hari_dari_tanggal($tgl), $adaHari, true)) {
            $hariBerikut = tanggal_indonesia($tgl);
        }
    }
}

$langkah = [
    ['Daftar akun', 'Buat akun pasien dengan email Anda.'],
    ['Pilih jadwal', 'Pilih poli, dokter, dan tanggal. Nomor antrean langsung terbit.'],
    ['Tunggu di rumah', 'Anda diberi tahu lewat HP saat tinggal 3 pasien lagi.'],
    ['Datang dan diperiksa', 'Tunjukkan nomor ke petugas. Hasil dan resep tersimpan di riwayat.'],
];
$singkat = ['Senin' => 'Sen', 'Selasa' => 'Sel', 'Rabu' => 'Rab', 'Kamis' => 'Kam', 'Jumat' => 'Jum', 'Sabtu' => 'Sab', 'Minggu' => 'Min'];

$judul = 'Klinik Pintar';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/public_nav.php';
?>
<main class="flex-1">
  <section class="mx-auto grid max-w-5xl gap-10 px-4 py-12 md:grid-cols-[1.05fr_1fr] md:items-center md:py-16">
    <div>
      <h1 class="font-serif text-[2.6rem] font-bold leading-[1.05] text-ink md:text-[3.4rem]">
        Ambil nomor antrean dari rumah. Datang saat giliran Anda dekat.
      </h1>
      <p class="mt-5 max-w-md text-lg text-ink/80">
        Pilih poli dan dokter, nomor antrean langsung terbit. Anda diberi tahu
        lewat HP saat tinggal 3 pasien lagi, jadi tidak perlu menunggu lama di klinik.
      </p>
      <div class="mt-7 flex flex-wrap items-center gap-4">
        <a class="bg-klinik px-5 py-3 text-base font-bold text-white" href="<?= e(url('auth/register.php')) ?>">Buat akun pasien</a>
        <a class="text-base font-bold text-klinik underline" href="<?= e(url('auth/login.php')) ?>">Sudah punya akun? Masuk</a>
      </div>
    </div>

    <aside class="layar-antrean" aria-label="Antrean hari ini">
      <div class="layar-antrean__kepala">
        <strong>Sedang dilayani</strong>
        <span class="layar-antrean__jam"><?= e(tanggal_indonesia($hariIni)) ?>, <span id="jam-layar"><?= date('H.i') ?></span></span>
      </div>
      <?php foreach ($praktik as $p): ?>
        <div class="layar-antrean__baris">
          <div>
            <?= tanda_poli_html($p['nama_poli']) ?>
            <span class="layar-antrean__dokter"><?= e($p['nama']) ?>, <?= e($p['jam_mulai']) ?>-<?= e($p['jam_selesai']) ?></span>
          </div>
          <?php if ($p['dilayani']): ?>
            <span class="layar-antrean__nomor"><?= e(kode_antrean($p['nama_poli'], $p['dilayani'])) ?></span>
          <?php elseif ($p['berikutnya']): ?>
            <span class="layar-antrean__nomor layar-antrean__nomor--kosong">Berikutnya <?= e(kode_antrean($p['nama_poli'], $p['berikutnya'])) ?></span>
          <?php else: ?>
            <span class="layar-antrean__nomor layar-antrean__nomor--kosong"><?= (int) $p['terisi'] ?>/<?= (int) $p['kuota'] ?> terisi</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$praktik): ?>
        <p class="layar-antrean__kosong">
          Hari ini tidak ada praktik.<?= $hariBerikut ? ' Praktik berikutnya ' . e($hariBerikut) . '.' : '' ?>
        </p>
      <?php endif; ?>
    </aside>
  </section>

  <section class="border-t border-garis bg-white">
    <div class="mx-auto max-w-5xl px-4 py-12">
      <h2 class="font-serif text-3xl font-bold">Jadwal praktik</h2>
      <p class="mt-1 text-ink/70">Hari yang diberi warna adalah hari dokter praktik.</p>
      <div class="papan-jadwal mt-6">
        <?php foreach ($poli as $p): foreach ($p['dokter'] as $d): ?>
          <div class="papan-jadwal__baris">
            <div>
              <?= tanda_poli_html($p['nama']) ?>
              <p class="mt-0.5 text-lg font-bold"><?= e($d['nama']) ?></p>
              <p class="text-sm text-ink/70"><?= e($d['bio']) ?></p>
            </div>
            <ol class="papan-jadwal__hari <?= kelas_poli($p['nama']) ?>" aria-label="Hari praktik">
              <?php foreach ($urutan as $h): $ada = in_array($h, $d['hari'], true); ?>
                <li class="<?= $ada ? 'is-praktik' : '' ?>" title="<?= e($h) ?><?= $ada ? ': praktik' : ': libur' ?>">
                  <?= e($singkat[$h]) ?><span class="sr-only"><?= $ada ? ' praktik' : ' libur' ?></span>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>
        <?php endforeach; endforeach; ?>
        <?php if (!$poli): ?>
          <p class="py-5 text-ink/60">Jadwal praktik belum tersedia.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="border-t border-garis">
    <div class="mx-auto max-w-5xl px-4 py-12">
      <h2 class="font-serif text-3xl font-bold">Cara berobat</h2>
      <ol class="alur-berobat mt-6">
        <?php foreach ($langkah as $i => $l): ?>
          <li>
            <span class="alur-berobat__no"><?= $i + 1 ?></span>
            <p class="font-bold"><?= e($l[0]) ?></p>
            <p class="mt-1 text-ink/75"><?= e($l[1]) ?></p>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>
</main>
<script>
  // jam di layar antrean ikut berjalan
  (function () {
    var el = document.getElementById('jam-layar');
    if (!el) return;
    setInterval(function () {
      var d = new Date();
      el.textContent = ('0' + d.getHours()).slice(-2) + '.' + ('0' + d.getMinutes()).slice(-2);
    }, 15000);
  })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
