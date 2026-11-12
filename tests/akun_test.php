<?php
require_once __DIR__ . '/../includes/akun.php';

function test_atur_password_sementara()
{
    $pdo = test_pdo();
    $id = buat_user_uji($pdo, 'admin');
    $pdo->prepare('UPDATE users SET login_gagal = 3, terkunci_hingga = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s', time() + 600), $id]);

    $sementara = atur_password_sementara($pdo, $id);
    cek(validasi_password($sementara) === null, 'password sementara memenuhi aturan');

    $st = $pdo->prepare('SELECT password, wajib_ganti_password, login_gagal, terkunci_hingga FROM users WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch();
    cek(password_verify($sementara, $u['password']), 'password sementara cocok');
    cek(!password_verify('Lama12345', $u['password']), 'password lama tidak berlaku');
    sama('1', (string) $u['wajib_ganti_password'], 'wajib ganti');
    sama('0', (string) $u['login_gagal'], 'login_gagal direset');
    sama(null, $u['terkunci_hingga'], 'kunci dihapus');
}

function test_cli_hanya_dari_command_line()
{
    // cek statis: baris pertama yang dieksekusi skrip menolak selain CLI
    $isi = file_get_contents(__DIR__ . '/../tools/reset_superadmin.php');
    cek(preg_match("/PHP_SAPI\s*!==\s*'cli'/", $isi) === 1, 'skrip memeriksa PHP_SAPI');
}
