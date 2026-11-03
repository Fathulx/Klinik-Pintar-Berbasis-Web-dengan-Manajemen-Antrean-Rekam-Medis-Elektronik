<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';

$user     = require_role('user');
$pdo      = db();
tandai_tidak_hadir_otomatis($pdo);
$pasienId = pasien_id_dari_user($pdo, $user['id']);
$berikut  = $pasienId ? reservasi_pasien($pdo, $pasienId, true) : [];

$judul = 'Dashboard Pasien';
$aktif = 'user/dashboard.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<div class="space-y-6">
  <section class="overflow-hidden rounded-2xl border border-klinik/20 bg-gradient-to-br from-klinik/10 via-white to-bata/10 p-6 shadow-sm">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-ink/60">Dashboard pasien</p>
        <h1 class="mt-2 font-serif text-4xl font-bold text-klinik">Selamat datang, <?= e($user['nama']) ?></h1>
        <p class="mt-2 max-w-xl text-sm text-ink/70">
          Semoga hari Anda tetap sehat dan lancar. Berikut status antrean serta langkah cepat untuk booking kunjungan.
        </p>
      </div>
      <div class="inline-flex items-center rounded-full border border-klinik/20 bg-white px-3 py-2 text-xs font-medium text-klinik shadow-sm">
        <?= $berikut ? 'Antrean aktif' : 'Siap reservasi' ?>
      </div>
    </div>
  </section>

  <div class="grid gap-4 md:grid-cols-[1.3fr_0.7fr]">
    <section class="rounded-2xl border border-garis bg-white p-5 shadow-sm">
      <?php if ($berikut): ?>
        <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-ink/60">Kunjungan berikutnya</p>
        <div class="mt-4"><?= karcis_html($berikut[0]) ?></div>
        <div class="mt-4 flex flex-wrap gap-3">
          <a class="inline-flex items-center bg-klinik px-4 py-2 text-sm font-medium text-white transition hover:bg-ink"
             href="<?= e(url('user/antrean.php')) ?>">Lihat semua antrean</a>
          <a class="inline-flex items-center gap-2 rounded-xl border border-klinik/20 bg-klinik/5 px-4 py-2.5 text-sm font-semibold text-klinik shadow-sm hover:border-klinik/40 hover:bg-klinik/10"
             href="<?= e(url('user/reservasi.php')) ?>">
            Buat reservasi baru
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
              <path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </a>
        </div>
      <?php else: ?>
        <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-ink/60">Status antrean</p>
        <p class="mt-4 text-base font-medium text-ink">Anda belum punya antrean aktif.</p>
        <p class="mt-2 text-sm text-ink/70">Reservasi sekarang untuk mendapatkan jadwal konsultasi yang sesuai dengan kebutuhan Anda.</p>
        <a class="mt-5 inline-flex items-center bg-klinik px-4 py-2 text-sm font-medium text-white transition hover:bg-ink"
           href="<?= e(url('user/reservasi.php')) ?>">Buat reservasi</a>
      <?php endif; ?>
    </section>

    <aside class="rounded-2xl border border-garis bg-paper p-5 shadow-sm">
      <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-ink/60">Aksi cepat</p>
      <div class="mt-4 space-y-3">
        <a href="<?= e(url('user/reservasi.php')) ?>"
           class="block rounded-xl border border-garis bg-white px-3 py-3 text-sm font-medium text-ink transition hover:border-klinik hover:text-klinik">
          Reservasi kunjungan
        </a>
        <a href="<?= e(url('user/antrean.php')) ?>"
           class="block rounded-xl border border-garis bg-white px-3 py-3 text-sm font-medium text-ink transition hover:border-klinik hover:text-klinik">
          Riwayat antrean
        </a>
        <a href="<?= e(url('auth/ganti_password.php')) ?>"
           class="block rounded-xl border border-garis bg-white px-3 py-3 text-sm font-medium text-ink transition hover:border-klinik hover:text-klinik">
          Ubah password
        </a>
      </div>
    </aside>
  </div>
</div>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>