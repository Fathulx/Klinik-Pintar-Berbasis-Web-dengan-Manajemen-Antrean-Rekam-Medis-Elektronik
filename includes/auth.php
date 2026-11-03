<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/csrf.php';

function dashboard_path($role)
{
    $peta = [
        'super_admin' => 'superadmin/dashboard.php',
        'admin'       => 'admin/dashboard.php',
        'dokter'      => 'dokter/dashboard.php',
        'user'        => 'user/dashboard.php',
    ];
    return isset($peta[$role]) ? $peta[$role] : 'auth/login.php';
}

function catat_log($aksi, $detail = null, $userId = null)
{
    if ($userId === null && isset($_SESSION['user']['id'])) {
        $userId = $_SESSION['user']['id'];
    }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    $st = db()->prepare(
        'INSERT INTO log_aktivitas (user_id, aksi, detail, ip_address) VALUES (?, ?, ?, ?)'
    );
    $st->execute([$userId, $aksi, $detail === null ? null : substr($detail, 0, 255), $ip]);
}

function user_login()
{
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function paksa_keluar($pesan)
{
    $_SESSION = [];
    session_regenerate_id(true);
    set_flash($pesan, 'error');
    redirect('auth/login.php');
}

// Memeriksa ulang akun ke database pada setiap permintaan,
// sehingga akun yang dinonaktifkan atau diubah rolenya langsung berlaku.
function require_login()
{
    $sesi = user_login();
    if (!$sesi) {
        redirect('auth/login.php');
    }
    $st = db()->prepare(
        'SELECT id, nama, role, status, wajib_ganti_password FROM users WHERE id = ?'
    );
    $st->execute([$sesi['id']]);
    $baris = $st->fetch();

    if (!$baris || $baris['status'] !== 'aktif') {
        paksa_keluar('Akun Anda tidak aktif. Hubungi admin.');
    }

    $user = [
        'id'          => (int) $baris['id'],
        'nama'        => $baris['nama'],
        'role'        => $baris['role'],
        'wajib_ganti' => (bool) $baris['wajib_ganti_password'],
    ];

    $halaman = basename($_SERVER['SCRIPT_NAME']);
    if ($user['wajib_ganti'] && $halaman !== 'ganti_password.php' && $halaman !== 'logout.php') {
        redirect('auth/ganti_password.php');
    }
    return $user;
}

function require_role($roles)
{
    $user = require_login();
    if (!in_array($user['role'], (array) $roles, true)) {
        catat_log('akses_ditolak', isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
        http_response_code(403);
        exit('Anda tidak berhak mengakses halaman ini.');
    }
    return $user;
}
