<?php
require_once __DIR__ . '/../includes/reset_password.php';

function _email_user(PDO $pdo, $id)
{
    $st = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $st->execute([$id]);
    return $st->fetchColumn();
}

function _jumlah_token(PDO $pdo)
{
    return (int) $pdo->query('SELECT COUNT(*) FROM password_resets')->fetchColumn();
}

function test_pasien_aktif_mendapat_token()
{
    $pdo = test_pdo();
    $id = buat_user_uji($pdo, 'user');
    $h = minta_reset_password($pdo, _email_user($pdo, $id), '10.0.0.1');
    sama(true, $h['kirim'], 'kirim');
    cek(preg_match('/^[0-9a-f]{64}$/', $h['token']) === 1, 'token 64 hex');
    sama(_email_user($pdo, $id), $h['email'], 'email tujuan');
    cek($h['nama'] !== '', 'nama terisi');
    $simpan = $pdo->query('SELECT token_hash FROM password_resets')->fetchColumn();
    sama(hash('sha256', $h['token']), $simpan, 'yang disimpan hash');
    cek($simpan !== $h['token'], 'token mentah tidak disimpan');
}

function test_email_tidak_dikenal_staf_nonaktif_tidak_dikirim()
{
    $pdo = test_pdo();
    $email = ['tidakada@contoh.test'];
    foreach (['dokter', 'admin', 'super_admin'] as $role) {
        $email[] = _email_user($pdo, buat_user_uji($pdo, $role));
    }
    $email[] = _email_user($pdo, buat_user_uji($pdo, 'user', 'nonaktif'));
    foreach ($email as $e) {
        $h = minta_reset_password($pdo, $e, '10.0.0.1');
        sama(false, $h['kirim'], "tidak dikirim untuk $e");
        sama(null, $h['token'], "tanpa token untuk $e");
    }
    sama(0, _jumlah_token($pdo), 'tidak ada token tersimpan');
}

function test_batas_per_akun()
{
    $pdo = test_pdo();
    $email = _email_user($pdo, buat_user_uji($pdo, 'user'));
    for ($i = 1; $i <= 3; $i++) {
        sama(true, minta_reset_password($pdo, $email, '10.0.0.' . $i)['kirim'], "permintaan $i");
    }
    sama(false, minta_reset_password($pdo, $email, '10.0.0.9')['kirim'], 'permintaan ke-4');
}

function test_batas_per_ip()
{
    $pdo = test_pdo();
    for ($i = 1; $i <= 5; $i++) {
        $email = _email_user($pdo, buat_user_uji($pdo, 'user'));
        sama(true, minta_reset_password($pdo, $email, '10.9.9.9')['kirim'], "pasien $i");
    }
    $email = _email_user($pdo, buat_user_uji($pdo, 'user'));
    sama(false, minta_reset_password($pdo, $email, '10.9.9.9')['kirim'], 'pasien ke-6 dari IP sama');
}

function test_token_lama_batal_saat_minta_baru()
{
    $pdo = test_pdo();
    $id = buat_user_uji($pdo, 'user');
    $t1 = minta_reset_password($pdo, _email_user($pdo, $id), '10.0.0.1')['token'];
    cek(cek_token_reset($pdo, $t1) !== null, 'token pertama valid');
    $t2 = minta_reset_password($pdo, _email_user($pdo, $id), '10.0.0.1')['token'];
    sama(null, cek_token_reset($pdo, $t1), 'token pertama batal');
    $c = cek_token_reset($pdo, $t2);
    sama($id, $c['user_id'], 'token kedua valid');
}

function test_pakai_token_mengganti_password()
{
    $pdo = test_pdo();
    $id = buat_user_uji($pdo, 'user');
    $pdo->prepare('UPDATE users SET login_gagal = 4, terkunci_hingga = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s', time() + 600), $id]);
    $t = minta_reset_password($pdo, _email_user($pdo, $id), '10.0.0.1')['token'];

    $h = pakai_token_reset($pdo, $t, 'Baru12345');
    sama(true, $h['ok'], 'berhasil: ' . $h['pesan']);
    sama($id, $h['user_id'], 'user_id');

    $st = $pdo->prepare('SELECT password, login_gagal, terkunci_hingga, wajib_ganti_password FROM users WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch();
    cek(password_verify('Baru12345', $u['password']), 'password baru cocok');
    cek(!password_verify('Lama12345', $u['password']), 'password lama tidak cocok');
    sama('0', (string) $u['login_gagal'], 'login_gagal direset');
    sama(null, $u['terkunci_hingga'], 'kunci dihapus');
    sama('0', (string) $u['wajib_ganti_password'], 'tidak wajib ganti');

    sama(false, pakai_token_reset($pdo, $t, 'Lain12345')['ok'], 'token dipakai ulang');
}

function test_token_kedaluwarsa_ditolak()
{
    $pdo = test_pdo();
    $id = buat_user_uji($pdo, 'user');
    $t = minta_reset_password($pdo, _email_user($pdo, $id), '10.0.0.1')['token'];
    $pdo->exec('UPDATE password_resets SET expires_at = NOW() - INTERVAL 1 MINUTE');
    sama(null, cek_token_reset($pdo, $t), 'kedaluwarsa');
    sama(false, pakai_token_reset($pdo, $t, 'Baru12345')['ok'], 'tidak bisa dipakai');
}

function test_masa_berlaku_60_menit()
{
    $pdo = test_pdo();
    $id = buat_user_uji($pdo, 'user');
    minta_reset_password($pdo, _email_user($pdo, $id), '10.0.0.1');
    $menit = (int) $pdo->query('SELECT TIMESTAMPDIFF(MINUTE, NOW(), expires_at) FROM password_resets')->fetchColumn();
    cek($menit >= 59 && $menit <= 60, "berlaku sekitar 60 menit ($menit)");
}

function test_password_lemah_ditolak()
{
    $pdo = test_pdo();
    $id = buat_user_uji($pdo, 'user');
    $t = minta_reset_password($pdo, _email_user($pdo, $id), '10.0.0.1')['token'];
    sama(false, pakai_token_reset($pdo, $t, 'abc')['ok'], 'password lemah');
    cek(cek_token_reset($pdo, $t) !== null, 'token masih berlaku');
}

function test_token_akun_yang_dinonaktifkan_ditolak()
{
    $pdo = test_pdo();
    $id = buat_user_uji($pdo, 'user');
    $t = minta_reset_password($pdo, _email_user($pdo, $id), '10.0.0.1')['token'];
    $pdo->prepare("UPDATE users SET status = 'nonaktif' WHERE id = ?")->execute([$id]);
    sama(null, cek_token_reset($pdo, $t), 'akun nonaktif');
}

function test_token_format_salah()
{
    $pdo = test_pdo();
    sama(null, cek_token_reset($pdo, 'xyz'), 'pendek');
    sama(null, cek_token_reset($pdo, str_repeat('g', 64)), 'bukan hex');
    sama(null, cek_token_reset($pdo, str_repeat('a', 64)), 'tidak terdaftar');
}
