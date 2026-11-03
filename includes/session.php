<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/security.php';          // baris baru

define('SESSION_TIMEOUT', 1800);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');     // baris baru
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('KLINIKSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (isset($_SESSION['last_activity'])
    && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['flash'] = [
        'pesan' => 'Sesi berakhir karena tidak ada aktivitas. Silakan masuk lagi.',
        'tipe'  => 'error',
    ];
}
$_SESSION['last_activity'] = time();