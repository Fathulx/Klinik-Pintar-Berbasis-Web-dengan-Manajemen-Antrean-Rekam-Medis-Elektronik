<?php
// Jalan terakhir untuk Super Admin yang lupa password, sekaligus cara membuat
// Super Admin pertama. Hanya dapat dijalankan dari command line di server:
//
//   php tools/reset_superadmin.php <email>                  reset password
//   php tools/reset_superadmin.php <email> --buat "<Nama>"  buat akun baru
//
// Password sementara dicetak sekali dan wajib diganti saat login.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya dapat dijalankan dari command line.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/akun.php';

function keluar_galat($pesan)
{
    fwrite(STDERR, $pesan . "\n");
    exit(1);
}

$email = isset($argv[1]) ? strtolower(trim($argv[1])) : '';
$buat  = isset($argv[2]) && $argv[2] === '--buat';
$nama  = isset($argv[3]) ? trim($argv[3]) : '';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    keluar_galat("Pemakaian:\n  php tools/reset_superadmin.php <email>\n"
        . "  php tools/reset_superadmin.php <email> --buat \"<Nama>\"");
}

$pdo = db();
$st = $pdo->prepare('SELECT id, role FROM users WHERE email = ?');
$st->execute([$email]);
$u = $st->fetch();

if ($u && $u['role'] !== 'super_admin') {
    keluar_galat("Akun $email bukan Super Admin. Skrip ini hanya untuk Super Admin.");
}

if ($u) {
    $userId = (int) $u['id'];
    $sementara = atur_password_sementara($pdo, $userId);
    $pdo->prepare("UPDATE users SET status = 'aktif' WHERE id = ?")->execute([$userId]);
    $aksi = 'reset';
} elseif ($buat) {
    $len = mb_strlen($nama);
    if ($len < 3 || $len > 100) {
        keluar_galat('Nama harus 3 sampai 100 karakter.');
    }
    $sementara = buat_password_sementara();
    $pdo->prepare(
        "INSERT INTO users (nama, email, password, role, status, wajib_ganti_password)
         VALUES (?, ?, ?, 'super_admin', 'aktif', 1)"
    )->execute([$nama, $email, password_hash($sementara, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $aksi = 'buat';
} else {
    keluar_galat("Email $email tidak terdaftar. Tambahkan --buat \"<Nama>\" untuk membuat Super Admin baru.");
}

$pdo->prepare('INSERT INTO log_aktivitas (user_id, aksi, detail, ip_address) VALUES (?, ?, ?, NULL)')
    ->execute([$userId, 'reset_superadmin_cli', $aksi . ' ' . $email]);

echo ($aksi === 'buat' ? 'Super Admin dibuat.' : 'Password Super Admin direset.') . "\n";
echo "Email             : $email\n";
echo "Password sementara: $sementara\n";
echo "Wajib diganti saat login pertama.\n";
