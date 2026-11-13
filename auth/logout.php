<?php
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && user_login()) {
    csrf_verify();
    catat_log('logout');
    // perangkat bersama: setelah keluar, notifikasi akun ini tidak dikirim lagi ke sini
    if (!empty($_SESSION['push_endpoint'])) {
        try {
            require_once __DIR__ . '/../includes/webpush.php';
            hapus_langganan_push(db(), $_SESSION['user']['id'], $_SESSION['push_endpoint']);
        } catch (PDOException $ex) {
            error_log('logout push: ' . $ex->getMessage());
        }
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
redirect('auth/login.php');