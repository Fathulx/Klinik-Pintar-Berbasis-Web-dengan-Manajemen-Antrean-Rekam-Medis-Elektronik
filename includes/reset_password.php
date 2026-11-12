<?php
// Reset password pasien lewat tautan email. Hanya hash SHA-256 token yang
// disimpan; token mentah hanya ada di email. Token berlaku 60 menit, sekali pakai.
require_once __DIR__ . '/helper.php';

define('RESET_MENIT_BERLAKU', 60);
define('RESET_BATAS_AKUN', 3);   // permintaan per akun per 15 menit
define('RESET_BATAS_IP', 5);     // permintaan per IP per 15 menit

function minta_reset_password(PDO $pdo, $email, $ip)
{
    $tidak = ['kirim' => false, 'token' => null, 'nama' => null, 'email' => null];

    $st = $pdo->prepare("SELECT id, nama, email FROM users WHERE email = ? AND role = 'user' AND status = 'aktif'");
    $st->execute([$email]);
    $user = $st->fetch();
    if (!$user) {
        return $tidak;
    }

    $hit = $pdo->prepare(
        'SELECT SUM(user_id = ?) AS per_akun, SUM(ip_address = ?) AS per_ip
         FROM password_resets WHERE created_at > NOW() - INTERVAL 15 MINUTE'
    );
    $hit->execute([$user['id'], $ip]);
    $h = $hit->fetch();
    if ((int) $h['per_akun'] >= RESET_BATAS_AKUN || (int) $h['per_ip'] >= RESET_BATAS_IP) {
        return $tidak;
    }

    $token = bin2hex(random_bytes(32));
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
        ->execute([$user['id']]);
    $pdo->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at, ip_address)
         VALUES (?, ?, NOW() + INTERVAL ' . RESET_MENIT_BERLAKU . ' MINUTE, ?)'
    )->execute([$user['id'], hash('sha256', $token), $ip]);
    $pdo->commit();

    return ['kirim' => true, 'token' => $token, 'nama' => $user['nama'], 'email' => $user['email']];
}

// $kunci true dipakai di dalam transaksi untuk mengunci baris token.
function cek_token_reset(PDO $pdo, $token, $kunci = false)
{
    if (!is_string($token) || !preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    $st = $pdo->prepare(
        "SELECT pr.id, pr.user_id FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
           AND u.role = 'user' AND u.status = 'aktif'" . ($kunci ? ' FOR UPDATE' : '')
    );
    $st->execute([hash('sha256', $token)]);
    $r = $st->fetch();
    return $r ? ['reset_id' => (int) $r['id'], 'user_id' => (int) $r['user_id']] : null;
}

function pakai_token_reset(PDO $pdo, $token, $passwordBaru)
{
    $salahPw = validasi_password($passwordBaru);
    if ($salahPw !== null) {
        return ['ok' => false, 'pesan' => $salahPw, 'user_id' => null];
    }

    $pdo->beginTransaction();
    try {
        $t = cek_token_reset($pdo, $token, true);
        if (!$t) {
            $pdo->rollBack();
            return ['ok' => false, 'pesan' => 'Tautan reset tidak valid atau sudah kedaluwarsa.', 'user_id' => null];
        }
        $pdo->prepare(
            'UPDATE users SET password = ?, wajib_ganti_password = 0, login_gagal = 0, terkunci_hingga = NULL
             WHERE id = ?'
        )->execute([password_hash($passwordBaru, PASSWORD_DEFAULT), $t['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
            ->execute([$t['user_id']]);
        $pdo->commit();
        return ['ok' => true, 'pesan' => 'Password berhasil diubah.', 'user_id' => $t['user_id']];
    } catch (PDOException $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}
