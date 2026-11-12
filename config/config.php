<?php
// Kredensial dan pengaturan server publik ditaruh di config.local.php,
// yang dimuat lebih dulu dan menimpa nilai bawaan di bawah.
if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

if (!defined('DB_HOST'))             { define('DB_HOST', 'localhost'); }
if (!defined('DB_NAME'))             { define('DB_NAME', 'klinik_pintar'); }
if (!defined('DB_USER'))             { define('DB_USER', 'root'); }
if (!defined('DB_PASS'))             { define('DB_PASS', ''); }
if (!defined('APP_ENV'))             { define('APP_ENV', 'development'); }
if (!defined('BASE_URL'))            { define('BASE_URL', '/klinik'); }
if (!defined('TAMPILKAN_AKUN_DEMO')) { define('TAMPILKAN_AKUN_DEMO', true); }

// Notifikasi "giliran sudah dekat": dikirim saat pasien aktif di depan
// tinggal sebanyak ini, mulai sekian menit sebelum jam praktik.
if (!defined('NOTIF_ANTREAN_SISA'))          { define('NOTIF_ANTREAN_SISA', 3); }
if (!defined('NOTIF_MENIT_SEBELUM_PRAKTIK')) { define('NOTIF_MENIT_SEBELUM_PRAKTIK', 60); }

// Alamat lengkap aplikasi untuk tautan di email, mis. https://klinik.contoh.id
if (!defined('APP_URL')) { define('APP_URL', 'http://localhost' . BASE_URL); }

// SMTP untuk email reset password. Isi SMTP_USER dan SMTP_PASS (App Password
// Gmail) di config.local.php; jangan pernah menaruh password di file ini.
if (!defined('SMTP_HOST'))      { define('SMTP_HOST', 'smtp.gmail.com'); }
if (!defined('SMTP_PORT'))      { define('SMTP_PORT', 587); }
if (!defined('SMTP_SECURE'))    { define('SMTP_SECURE', 'tls'); }
if (!defined('SMTP_USER'))      { define('SMTP_USER', ''); }
if (!defined('SMTP_PASS'))      { define('SMTP_PASS', ''); }
if (!defined('SMTP_FROM'))      { define('SMTP_FROM', ''); }
if (!defined('SMTP_FROM_NAME')) { define('SMTP_FROM_NAME', 'Klinik Pintar'); }

date_default_timezone_set('Asia/Jakarta');
error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');
ini_set('log_errors', '1');

if (APP_ENV === 'production') {
    // pesan error rinci (termasuk kredensial) tidak pernah tampil ke pengunjung
    set_exception_handler(function ($ex) {
        error_log((string) $ex);
        http_response_code(500);
        exit('Terjadi kesalahan pada server. Silakan coba lagi nanti.');
    });
}