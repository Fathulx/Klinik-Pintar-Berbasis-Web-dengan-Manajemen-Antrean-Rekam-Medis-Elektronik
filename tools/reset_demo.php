<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/antrean.php';

function jalankan_reset($pdo)
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['resep', 'rekam_medis', 'reservasi', 'jadwal_dokter', 'dokter', 'pasien', 'poli', 'log_aktivitas', 'users'] as $tabel) {
        $pdo->exec("TRUNCATE TABLE `$tabel`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pw = password_hash('Demo@12345', PASSWORD_DEFAULT);
    $pdo->beginTransaction();

    $insUser   = $pdo->prepare('INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)');
    $insPasien = $pdo->prepare('INSERT INTO pasien (user_id) VALUES (?)');

    $insUser->execute(['Super Admin', 'superadmin@demo.klinik', $pw, 'super_admin']);
    $insUser->execute(['Admin Klinik', 'admin@demo.klinik', $pw, 'admin']);
    foreach ([['Pasien Contoh', 'pasien@demo.klinik'], ['Pasien Kedua', 'pasien2@demo.klinik']] as $p) {
        $insUser->execute([$p[0], $p[1], $pw, 'user']);
        $insPasien->execute([$pdo->lastInsertId()]);
    }

    $poli = [
        'Umum'           => 'Pemeriksaan umum dan keluhan sehari-hari',
        'Gigi'           => 'Pemeriksaan dan perawatan gigi serta mulut',
        'Anak'           => 'Pemeriksaan kesehatan bayi dan anak',
        'Penyakit Dalam' => 'Konsultasi penyakit dalam untuk dewasa',
    ];
    $insPoli = $pdo->prepare('INSERT INTO poli (nama_poli, deskripsi) VALUES (?, ?)');
    $idPoli  = [];
    foreach ($poli as $nama => $desk) {
        $insPoli->execute([$nama, $desk]);
        $idPoli[$nama] = $pdo->lastInsertId();
    }

    // semua dokter di bawah ini fiktif
    $dokter = [
        ['dr. Siti Rahma',            'siti.rahma@demo.klinik',   'Umum',           'DEMO-0001', 'Dokter umum, melayani pemeriksaan keluhan sehari-hari.'],
        ['drg. Budi Santoso',         'budi.santoso@demo.klinik', 'Gigi',           'DEMO-0002', 'Dokter gigi, pemeriksaan dan perawatan rutin.'],
        ['dr. Laras Wulandari, Sp.A', 'laras.w@demo.klinik',      'Anak',           'DEMO-0003', 'Dokter spesialis anak.'],
        ['dr. Andi Pratama, Sp.PD',   'andi.pratama@demo.klinik', 'Penyakit Dalam', 'DEMO-0004', 'Dokter spesialis penyakit dalam.'],
    ];
    $insDokter = $pdo->prepare('INSERT INTO dokter (user_id, poli_id, no_str, bio) VALUES (?, ?, ?, ?)');
    $insJadwal = $pdo->prepare(
        'INSERT INTO jadwal_dokter (dokter_id, hari, jam_mulai, jam_selesai, kuota) VALUES (?, ?, ?, ?, 20)'
    );
    $idSiti = 0;
    foreach ($dokter as $i => $d) {
        $insUser->execute([$d[0], $d[1], $pw, 'dokter']);
        $insDokter->execute([$pdo->lastInsertId(), $idPoli[$d[2]], $d[3], $d[4]]);
        $idDokter = $pdo->lastInsertId();
        if ($i === 0) {
            $idSiti = $idDokter;
        }
        foreach (['Senin', 'Rabu', 'Jumat'] as $hari) {
            $insJadwal->execute([$idDokter, $hari, '08:00', '12:00']);
        }
    }

    // jadwal khusus hari ini untuk dr. Siti agar demo bisa langsung dicoba
    $hariIni = hari_dari_tanggal(date('Y-m-d'));
    $mulai   = in_array($hariIni, ['Senin', 'Rabu', 'Jumat'], true) ? '12:00' : '06:00';
    $insJadwal->execute([$idSiti, $hariIni, $mulai, '23:59']);

    $pdo->commit();

    $pdo->prepare('INSERT INTO log_aktivitas (user_id, aksi, detail, ip_address) VALUES (NULL, ?, ?, ?)')
        ->execute(['reset_demo', 'data demo diisi ulang', isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli']);
}

// Jalur baris perintah: php tools/reset_demo.php
if (php_sapi_name() === 'cli') {
    jalankan_reset(db());
    echo "Data demo direset.\n";
    exit;
}

// Jalur web: wajib token rahasia dan konfirmasi.
require_once __DIR__ . '/../includes/auth.php';

if (!defined('DEMO_RESET_TOKEN') || DEMO_RESET_TOKEN === '') {
    http_response_code(403);
    exit('Reset data demo tidak diaktifkan.');
}
$token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
if (!hash_equals((string) DEMO_RESET_TOKEN, $token)) {
    http_response_code(403);
    exit('Token tidak valid.');
}

$selesai = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    jalankan_reset(db());
    $selesai = true;
}

$judul = 'Reset Data Demo';
require __DIR__ . '/../includes/header.php';
?>
<main class="mx-auto w-full max-w-md flex-1 px-4 py-12">
  <h1 class="font-serif text-3xl font-bold text-klinik">Reset data demo</h1>
  <?php if ($selesai): ?>
    <p class="mt-4 border border-klinik bg-klinik/10 px-3 py-2 text-sm text-klinik">
      Data demo berhasil diisi ulang. Semua akun demo memakai password <span class="font-mono">Demo@12345</span>.
    </p>
    <a class="mt-4 inline-block text-sm underline" href="<?= e(url('auth/login.php')) ?>">Ke halaman login</a>
  <?php else: ?>
    <p class="mt-4 text-sm text-ink/80">
      Tindakan ini menghapus <strong>semua</strong> data (akun, reservasi, rekam medis, log) lalu mengisi ulang data demo.
      Tidak dapat dibatalkan.
    </p>
    <form method="post" action="<?= e(url('tools/reset_demo.php?token=' . rawurlencode($token))) ?>" class="mt-6"
          onsubmit="return confirm('Hapus semua data dan isi ulang data demo?')">
      <?= csrf_field() ?>
      <button class="bg-bata px-4 py-2 text-sm font-medium text-white hover:bg-ink">Reset data demo</button>
    </form>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>