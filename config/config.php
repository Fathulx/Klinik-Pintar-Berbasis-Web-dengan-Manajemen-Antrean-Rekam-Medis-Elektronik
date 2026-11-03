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