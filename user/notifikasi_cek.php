<?php
// Endpoint JSON untuk polling notifikasi pasien. Tidak memperpanjang sesi,
// supaya tab yang dibiarkan terbuka tetap keluar otomatis setelah 30 menit.
define('SESI_TANPA_PERPANJANG', true);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';
require_once __DIR__ . '/../includes/notifikasi.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!user_login()) {
    http_response_code(401);
    echo json_encode(['login' => false]);
    exit;
}

$user = require_role('user');
session_write_close(); // jangan menahan kunci sesi selama query
$pdo = db();

$pasienId = pasien_id_dari_user($pdo, $user['id']);
if ($pasienId) {
    picu_notif_antrean($pdo, $pasienId);
}

$setelah = isset($_GET['setelah']) && is_string($_GET['setelah']) ? (int) $_GET['setelah'] : 0;
$baru = [];
foreach (daftar_notifikasi($pdo, $user['id'], $setelah, 10) as $n) {
    $baru[] = [
        'id'         => (int) $n['id'],
        'judul'      => $n['judul'],
        'pesan'      => $n['pesan'],
        'tautan_url' => $n['tautan'] === null ? null : url($n['tautan']),
    ];
}

$antrean = [];
if ($pasienId) {
    foreach (reservasi_pasien($pdo, $pasienId, true) as $r) {
        $antrean[] = ['id' => (int) $r['id'], 'di_depan' => (int) $r['di_depan']];
    }
}

echo json_encode([
    'belum_dibaca' => jumlah_notif_belum_dibaca($pdo, $user['id']),
    'baru'         => $baru,
    'antrean'      => $antrean,
]);
