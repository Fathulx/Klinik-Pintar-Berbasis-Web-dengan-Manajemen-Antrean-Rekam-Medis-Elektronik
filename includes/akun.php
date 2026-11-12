<?php
require_once __DIR__ . '/helper.php';

// Mengganti password akun dengan password sementara yang wajib diganti saat
// login berikutnya, sekaligus membuka kunci login. Mengembalikan password
// sementara untuk ditampilkan sekali kepada yang mereset.
function atur_password_sementara(PDO $pdo, $userId)
{
    $sementara = buat_password_sementara();
    $pdo->prepare(
        'UPDATE users SET password = ?, wajib_ganti_password = 1,
         login_gagal = 0, terkunci_hingga = NULL WHERE id = ?'
    )->execute([password_hash($sementara, PASSWORD_DEFAULT), (int) $userId]);
    return $sementara;
}
