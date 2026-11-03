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

// praktik hari ini
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

$langkah = [
    ['Daftar akun', 'Buat akun pasien dengan email Anda. Prosesnya kurang dari satu menit.'],
    ['Pilih jadwal', 'Pilih poli, dokter, lalu tanggal. Nomor antrean langsung terbit beserta perkiraan jam dilayani.'],
    ['Datang dan konfirmasi', 'Tunjukkan nomor antrean ke petugas. Kehadiran Anda dikonfirmasi di sistem.'],
    ['Diperiksa', 'Setelah diperiksa, hasil dan resep tersimpan di riwayat kunjungan Anda.'],
];
$peran = [
    'Super Admin' => 'Mengelola akun admin, data poli, dan memantau log aktivitas.',
    'Admin'       => 'Mengelola dokter, jadwal praktik, antrean harian, dan laporan.',
    'Dokter'      => 'Memanggil pasien, mengisi rekam medis dan resep.',
    'Pasien'      => 'Reservasi antrean dan melihat riwayat kunjungan sendiri.',
];

$judul = 'Klinik Pintar';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/public_nav.php';
?>
<style>
  body.home-fade {
    animation: home-fade-in 420ms ease-out both;
  }

  body.home-fade.is-leaving {
    animation: home-fade-out 220ms ease-in forwards;
  }

  body.home-section-animations .home-section-reveal {
    transition: opacity 600ms ease, transform 600ms ease;
  }

  body.home-section-animations .home-section-reveal:not(.is-visible) {
    opacity: 0;
    transform: translateY(24px);
  }

  @keyframes home-fade-in {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  @keyframes home-fade-out {
    from { opacity: 1; }
    to { opacity: 0; }
  }

  @media (prefers-reduced-motion: reduce) {
    body.home-fade {
      animation: none;
    }

    body.home-section-animations .home-section-reveal {
      opacity: 1;
      transform: none;
      transition: none;
    }
  }
</style>
<script>
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.body.classList.add('home-section-animations');
  }
</script>
<main class="flex-1">
  <section class="home-section-reveal mx-auto grid max-w-5xl gap-10 px-4 py-14 md:grid-cols-5">
    <div class="md:col-span-3">
      <p class="font-mono text-xs uppercase tracking-widest text-ink/60">Sistem informasi klinik</p>
      <h1 class="mt-3 font-serif text-4xl font-bold leading-tight text-klinik md:text-5xl">
        Pesan antrean dari rumah, datang saat giliran Anda dekat.
      </h1>
      <p class="mt-5 max-w-lg text-ink/80">
        Pilih poli, dokter, dan tanggal kunjungan. Nomor antrean terbit saat itu juga,
        lengkap dengan perkiraan jam Anda dilayani, dan hasil pemeriksaan tersimpan di riwayat Anda.
      </p>
      <div class="mt-7 flex flex-wrap items-center gap-4">
        <a class="bg-klinik px-5 py-2.5 text-sm font-medium text-white hover:bg-ink" href="<?= e(url('auth/register.php')) ?>">Buat akun pasien</a>
        <a class="text-sm underline" href="<?= e(url('auth/login.php')) ?>">Sudah punya akun? Masuk</a>
      </div>
    </div>

    <aside class="md:col-span-2">
      <div class="rounded-2xl border border-klinik/15 bg-white p-5 shadow-sm">
        <p class="font-mono text-[11px] uppercase tracking-widest text-ink/60">Praktik hari ini</p>
        <p class="font-serif text-lg font-bold"><?= e(tanggal_indonesia($hariIni)) ?></p>
        <hr class="my-3 border-garis">
        <?php foreach ($praktik as $p): ?>
          <div class="flex items-baseline justify-between gap-3 border-b border-garis/70 py-2 text-sm last:border-b-0">
            <span>
              <?= e($p['nama']) ?>
              <span class="block text-xs text-ink/60">Poli <?= e($p['nama_poli']) ?> &middot; <span class="font-mono"><?= e($p['jam_mulai']) ?>-<?= e($p['jam_selesai']) ?></span></span>
            </span>
            <span class="whitespace-nowrap font-mono text-xs"><?= (int) $p['terisi'] ?>/<?= (int) $p['kuota'] ?></span>
          </div>
        <?php endforeach; ?>
        <?php if (!$praktik): ?>
          <p class="text-sm text-ink/70">Tidak ada jadwal praktik hari ini.</p>
        <?php else: ?>
          <p class="mt-2 border-t border-garis pt-2 text-xs text-ink/60">Angka di kanan: antrean terisi dari kuota.</p>
        <?php endif; ?>
      </div>
    </aside>
  </section>

  <section class="home-section-reveal border-t border-garis">
    <div class="mx-auto max-w-5xl px-4 py-12">
      <h2 class="font-serif text-2xl font-bold">Poli dan dokter</h2>
      <div class="mt-6 border-t border-garis">
        <?php foreach ($poli as $p): ?>
          <div class="grid gap-4 border-b border-garis py-5 md:grid-cols-3">
            <div class="rounded-xl border border-garis bg-white px-4 py-3">
              <h3 class="font-serif text-xl font-bold text-klinik">Poli <?= e($p['nama']) ?></h3>
              <p class="mt-1 text-sm text-ink/70"><?= e($p['deskripsi']) ?></p>
            </div>
            <div class="space-y-3 md:col-span-2">
              <?php foreach ($p['dokter'] as $d): ?>
                <div>
                  <p class="font-medium"><?= e($d['nama']) ?></p>
                  <p class="text-sm text-ink/70"><?= e($d['bio']) ?></p>
                  <p class="mt-0.5 font-mono text-xs text-klinik"><?= e(implode(', ', $d['hari'])) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$poli): ?>
          <p class="py-5 text-sm text-ink/60">Jadwal praktik belum tersedia.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="home-section-reveal border-t border-garis bg-white">
    <div class="mx-auto max-w-5xl px-4 py-12">
      <h2 class="font-serif text-2xl font-bold">Cara kerja</h2>
      <ol class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ($langkah as $i => $l): ?>
          <li class="rounded-xl border border-garis bg-paper p-4">
            <p class="font-mono text-lg font-semibold text-bata"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></p>
            <p class="mt-1 font-medium"><?= e($l[0]) ?></p>
            <p class="mt-1 text-sm text-ink/70"><?= e($l[1]) ?></p>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <section class="home-section-reveal border-t border-garis">
    <div class="mx-auto max-w-5xl px-4 py-12">
      <h2 class="font-serif text-2xl font-bold">Empat peran, akses terpisah</h2>
      <p class="mt-1 text-sm text-ink/70">Setiap peran hanya melihat data yang menjadi tugasnya.</p>
      <dl class="mt-6 grid gap-3 sm:grid-cols-2 md:grid-cols-4">
        <?php foreach ($peran as $nama => $uraian): ?>
          <div class="rounded-xl border border-garis bg-white p-4">
            <dt class="font-mono text-[11px] uppercase tracking-widest text-klinik"><?= e($nama) ?></dt>
            <dd class="mt-1 text-sm"><?= e($uraian) ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
    </div>
  </section>
</main>
<script>
  (() => {
    const sections = document.querySelectorAll('.home-section-reveal');
    const showSection = (section) => section.classList.add('is-visible');

    if (!('IntersectionObserver' in window)) {
      sections.forEach(showSection);
      return;
    }

    const observer = new IntersectionObserver((entries, currentObserver) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          showSection(entry.target);
          currentObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.01, rootMargin: '0px 0px -8% 0px' });

    sections.forEach((section) => observer.observe(section));
  })();

  (() => {
    const body = document.body;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let sedangMeninggalkan = false;

    if (reduceMotion) return;

    body.classList.add('home-fade');
    document.addEventListener('click', (event) => {
      const tautan = event.target.closest('a');
      if (!tautan || event.defaultPrevented || event.button !== 0
        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
        || tautan.hasAttribute('download')
        || (tautan.target && tautan.target !== '_self')) {
        return;
      }

      const tujuan = new URL(tautan.href, window.location.href);
      if (tujuan.origin !== window.location.origin
        || (tujuan.pathname === window.location.pathname
          && tujuan.search === window.location.search
          && tujuan.hash)) {
        return;
      }

      event.preventDefault();
      if (sedangMeninggalkan) return;

      sedangMeninggalkan = true;
      body.classList.add('is-leaving');
      window.setTimeout(() => window.location.assign(tujuan.href), 220);
    });
  })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>