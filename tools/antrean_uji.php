<?php
// Mengisi antrean uji agar notifikasi "giliran sudah dekat" bisa dicoba
// tanpa membuat akun pasien manual. Hanya dari command line:
//
//   php tools/antrean_uji.php isi <email-pasien> [jumlah-di-depan]
//   php tools/antrean_uji.php hapus
//
// Semua data uji memakai email @uji.klinik dan dihapus oleh perintah "hapus".
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya dapat dijalankan dari command line.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/antrean_uji.php';

$perintah = isset($argv[1]) ? $argv[1] : '';
$pdo = db();

function catat_uji(PDO $pdo, $aksi, $detail)
{
    $pdo->prepare('INSERT INTO log_aktivitas (user_id, aksi, detail, ip_address) VALUES (NULL, ?, ?, ?)')
        ->execute([$aksi, $detail, 'cli']);
}

if ($perintah === 'isi' && isset($argv[2])) {
    $jumlah = isset($argv[3]) ? (int) $argv[3] : 5;
    try {
        $hasil = isi_antrean_uji($pdo, $argv[2], $jumlah);
    } catch (InvalidArgumentException $ex) {
        fwrite(STDERR, $ex->getMessage() . "\n");
        exit(1);
    }
    catat_uji($pdo, 'antrean_uji_isi', "{$hasil['di_depan']} pasien uji di depan {$argv[2]}");

    $batas = NOTIF_ANTREAN_SISA;
    echo "Antrean uji siap untuk tanggal {$hasil['tanggal']}.\n";
    echo "- {$hasil['di_depan']} pasien uji di depan {$argv[2]} (nomor antrean " . ($hasil['di_depan'] + 1) . ").\n";
    echo "- Notifikasi muncul ketika pasien di depan tinggal $batas atau kurang.\n\n";
    echo "Cara mencoba:\n";
    echo "1. Login sebagai pasien {$argv[2]}, buka menu Antrean, biarkan terbuka.\n";
    echo "2. Di browser lain login sebagai admin, buka Antrean, pilih tanggal hari ini.\n";
    echo "3. Tandai \"Pasien Uji 1\" tidak hadir (atau hadir lalu selesaikan lewat dokter).\n";
    echo "   Ulangi sampai pasien di depan tinggal $batas: notifikasi muncul dalam ~15 detik.\n\n";
    echo "Dokter uji: " . UJI_EMAIL_DOKTER . " / " . UJI_PASSWORD . "\n";
    echo "Hapus data uji: php tools/antrean_uji.php hapus\n";
    exit(0);
}

if ($perintah === 'hapus') {
    $n = hapus_antrean_uji($pdo);
    catat_uji($pdo, 'antrean_uji_hapus', "$n akun uji dihapus");
    echo "Data antrean uji dihapus ($n akun uji).\n";
    exit(0);
}

fwrite(STDERR, "Pemakaian:\n"
    . "  php tools/antrean_uji.php isi <email-pasien> [jumlah-di-depan]\n"
    . "  php tools/antrean_uji.php hapus\n");
exit(1);
