<?php
// Membuat sepasang kunci VAPID untuk Web Push. Hanya dari command line:
//
//   php tools/buat_kunci_webpush.php            tampilkan baris konfigurasi
//   php tools/buat_kunci_webpush.php --simpan   tambahkan langsung ke config/config.local.php
//
// Jalankan SEKALI per server. Mengganti kunci membuat semua perangkat yang
// sudah berlangganan harus menekan "Aktifkan notifikasi" lagi.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya dapat dijalankan dari command line.');
}

require_once __DIR__ . '/../includes/webpush.php';

$fileLokal = __DIR__ . '/../config/config.local.php';
$simpan = in_array('--simpan', $argv, true);

if (WEBPUSH_PUBLIC_KEY !== '' && $simpan) {
    fwrite(STDERR, "Kunci Web Push sudah ada di konfigurasi. Hapus baris WEBPUSH_* di config.local.php dulu bila memang ingin mengganti.\n");
    exit(1);
}

$k = webpush_buat_kunci();
$pengirim = defined('SMTP_FROM') && SMTP_FROM !== '' ? SMTP_FROM : (defined('SMTP_USER') ? SMTP_USER : '');
$subjek = $pengirim !== '' ? 'mailto:' . $pengirim : 'mailto:admin@klinik.example';

$baris = "\n// Web Push (dibuat " . date('Y-m-d') . " oleh tools/buat_kunci_webpush.php)\n"
    . "define('WEBPUSH_PUBLIC_KEY', '{$k['publik']}');\n"
    . "define('WEBPUSH_PRIVATE_KEY', '{$k['privat']}');\n"
    . "define('WEBPUSH_SUBJECT', '$subjek');\n";

if (!$simpan) {
    echo "Salin baris berikut ke config/config.local.php:\n$baris";
    exit(0);
}

if (!is_file($fileLokal)) {
    file_put_contents($fileLokal, "<?php\n");
}
$isi = file_get_contents($fileLokal);
if (preg_match('/\?>\s*$/', $isi)) {
    $isi = preg_replace('/\?>\s*$/', '', $isi); // baris baru harus di dalam blok PHP
}
file_put_contents($fileLokal, rtrim($isi) . "\n" . $baris);
echo "Kunci Web Push disimpan ke config/config.local.php.\n";
echo "Kunci publik: {$k['publik']}\n";
echo "Subjek: $subjek\n";
