<?php
// Mengisi data demo untuk presentasi. Hanya dari command line:
//
//   php tools/data_demo.php isi     pasien contoh, riwayat 4 minggu, antrean hari ini, reservasi 7 hari ke depan
//   php tools/data_demo.php hapus   hapus semua data demo (akun @contoh.klinik)
//
// Jalankan "isi" pada hari presentasi agar antrean hari ini ikut terisi.
// Data lain (akun demo, dokter, jadwal, pasien sungguhan) tidak diubah.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya dapat dijalankan dari command line.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/data_demo.php';

$perintah = isset($argv[1]) ? $argv[1] : '';
$pdo = db();

function catat_demo(PDO $pdo, $aksi, $detail)
{
    $pdo->prepare('INSERT INTO log_aktivitas (user_id, aksi, detail, ip_address) VALUES (NULL, ?, ?, ?)')
        ->execute([$aksi, $detail, 'cli']);
}

if ($perintah === 'isi') {
    try {
        $h = isi_data_demo($pdo);
    } catch (InvalidArgumentException $ex) {
        fwrite(STDERR, $ex->getMessage() . "\n");
        exit(1);
    }
    catat_demo($pdo, 'data_demo_isi', "{$h['pasien']} pasien, {$h['reservasi']} reservasi");
    echo "Data demo siap.\n";
    echo "- {$h['pasien']} pasien contoh (email @contoh.klinik, password " . DEMO_PASSWORD . ")\n";
    echo "- {$h['reservasi']} reservasi, {$h['rekam_medis']} di antaranya selesai dengan rekam medis dan resep\n";
    echo $h['hari_ini'] > 0
        ? "- {$h['hari_ini']} antrean hari ini\n"
        : "- Hari ini tidak ada jadwal praktik aktif, jadi antrean hari ini kosong.\n"
          . "  Jalankan lagi pada hari presentasi, atau tambahkan jadwal untuk hari ini.\n";
    echo "Hapus lagi dengan: php tools/data_demo.php hapus\n";
    exit(0);
}

if ($perintah === 'hapus') {
    $n = hapus_data_demo($pdo);
    catat_demo($pdo, 'data_demo_hapus', "$n akun demo dihapus");
    echo "Data demo dihapus ($n akun pasien contoh).\n";
    exit(0);
}

fwrite(STDERR, "Pemakaian:\n  php tools/data_demo.php isi\n  php tools/data_demo.php hapus\n");
exit(1);
