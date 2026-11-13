<?php
// Mendaftarkan atau mencabut perangkat untuk Web Push. Dipanggil oleh
// assets/push.js dengan POST (formulir + token CSRF). Menjawab JSON.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/webpush.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jawab_json($status, array $isi)
{
    http_response_code($status);
    echo json_encode($isi);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jawab_json(405, ['ok' => false]);
}
if (!user_login()) {
    jawab_json(401, ['ok' => false, 'login' => false]);
}
$user = require_role('user');
csrf_verify();

$endpoint = post('endpoint');
$pdo = db();

try {
    if (post('aksi') === 'simpan') {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        simpan_langganan_push($pdo, $user['id'], $endpoint, post('p256dh'), post('auth'), $ua);
        $_SESSION['push_endpoint'] = $endpoint; // dicabut saat logout
        jawab_json(200, ['ok' => true]);
    }
    if (post('aksi') === 'hapus') {
        hapus_langganan_push($pdo, $user['id'], $endpoint);
        unset($_SESSION['push_endpoint']);
        jawab_json(200, ['ok' => true]);
    }
} catch (InvalidArgumentException $ex) {
    jawab_json(422, ['ok' => false, 'pesan' => $ex->getMessage()]);
} catch (PDOException $ex) {
    error_log('push_langganan: ' . $ex->getMessage());
    jawab_json(500, ['ok' => false, 'pesan' => 'Gagal menyimpan. Coba lagi nanti.']);
}
jawab_json(400, ['ok' => false]);
