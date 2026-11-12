<?php
// Notifikasi dalam aplikasi. Semua fungsi menerima $pdo dan selalu
// menyaring berdasarkan user_id agar pengguna hanya menyentuh miliknya sendiri.

function buat_notifikasi(PDO $pdo, $userId, $judul, $pesan, $tautan = null)
{
    $pdo->prepare('INSERT INTO notifikasi (user_id, judul, pesan, tautan) VALUES (?, ?, ?, ?)')
        ->execute([(int) $userId, mb_substr($judul, 0, 120), mb_substr($pesan, 0, 255), $tautan]);
    return (int) $pdo->lastInsertId();
}

function jumlah_notif_belum_dibaca(PDO $pdo, $userId)
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND dibaca_at IS NULL');
    $st->execute([(int) $userId]);
    return (int) $st->fetchColumn();
}

function daftar_notifikasi(PDO $pdo, $userId, $setelahId = 0, $batas = 20)
{
    $st = $pdo->prepare(
        'SELECT id, judul, pesan, tautan, dibaca_at, created_at
         FROM notifikasi WHERE user_id = ? AND id > ?
         ORDER BY id DESC LIMIT ' . max(1, (int) $batas)
    );
    $st->execute([(int) $userId, (int) $setelahId]);
    return $st->fetchAll();
}

// $id null berarti menandai semua notifikasi milik user.
function tandai_notif_dibaca(PDO $pdo, $userId, $id = null)
{
    $sql = 'UPDATE notifikasi SET dibaca_at = NOW() WHERE user_id = ? AND dibaca_at IS NULL';
    $param = [(int) $userId];
    if ($id !== null) {
        $sql .= ' AND id = ?';
        $param[] = (int) $id;
    }
    $st = $pdo->prepare($sql);
    $st->execute($param);
    return $st->rowCount();
}
