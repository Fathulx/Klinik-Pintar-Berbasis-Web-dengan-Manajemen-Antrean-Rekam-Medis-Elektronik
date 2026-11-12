<?php
// Notifikasi dalam aplikasi. Semua fungsi menerima $pdo dan selalu
// menyaring berdasarkan user_id agar pengguna hanya menyentuh miliknya sendiri.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/antrean.php';

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

// Membuat notifikasi "giliran sudah dekat" untuk reservasi pada $tanggal
// yang jumlah pasien aktif di depannya <= NOTIF_ANTREAN_SISA. Hanya berlaku
// mulai NOTIF_MENIT_SEBELUM_PRAKTIK menit sebelum jam mulai praktik, dan
// paling banyak sekali per reservasi. $jamSekarang berformat 'H:i'.
// Mengembalikan jumlah notifikasi yang dibuat.
function proses_notif_antrean_dekat(PDO $pdo, $tanggal, $jamSekarang, $pasienId = null)
{
    list($jam, $menit) = array_map('intval', explode(':', $jamSekarang));
    $batasDetik = ($jam * 60 + $menit + NOTIF_MENIT_SEBELUM_PRAKTIK) * 60;

    $sql = "SELECT r.id, r.no_antrean, ps.user_id, p.nama_poli,
                   (SELECT COUNT(*) FROM reservasi r2
                     WHERE r2.jadwal_id = r.jadwal_id AND r2.tanggal = r.tanggal
                       AND r2.no_antrean < r.no_antrean
                       AND r2.status IN ('menunggu', 'hadir', 'diperiksa')) AS di_depan
            FROM reservasi r
            JOIN jadwal_dokter j ON j.id = r.jadwal_id
            JOIN dokter d ON d.id = j.dokter_id
            JOIN poli p ON p.id = d.poli_id
            JOIN pasien ps ON ps.id = r.pasien_id
            WHERE r.tanggal = ? AND r.status IN ('menunggu', 'hadir')
              AND r.notif_dekat_at IS NULL
              AND TIME_TO_SEC(j.jam_mulai) <= ?";
    $param = [$tanggal, $batasDetik];
    if ($pasienId !== null) {
        $sql .= ' AND r.pasien_id = ?';
        $param[] = (int) $pasienId;
    }
    $sql .= ' HAVING di_depan <= ?';
    $param[] = (int) NOTIF_ANTREAN_SISA;

    $st = $pdo->prepare($sql);
    $st->execute($param);
    $calon = $st->fetchAll();

    $klaim = $pdo->prepare('UPDATE reservasi SET notif_dekat_at = NOW() WHERE id = ? AND notif_dekat_at IS NULL');
    $dibuat = 0;
    foreach ($calon as $r) {
        $pdo->beginTransaction();
        try {
            $klaim->execute([$r['id']]);
            if ($klaim->rowCount() !== 1) {
                $pdo->rollBack(); // sudah diklaim permintaan lain
                continue;
            }
            $kode = kode_antrean($r['nama_poli'], $r['no_antrean']);
            $n = (int) $r['di_depan'];
            $pesan = $n === 0
                ? "Antrean $kode (Poli {$r['nama_poli']}): Anda pasien berikutnya. Silakan menuju klinik sekarang."
                : "Antrean $kode (Poli {$r['nama_poli']}): tinggal $n pasien lagi sebelum Anda. Silakan bersiap menuju klinik.";
            buat_notifikasi($pdo, $r['user_id'], 'Giliran Anda sudah dekat', $pesan, 'user/antrean.php');
            $pdo->commit();
            $dibuat++;
        } catch (PDOException $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }
    return $dibuat;
}

// Dipanggil halaman setelah status antrean berubah. Kegagalan notifikasi
// dicatat ke log dan tidak menggagalkan aksi utama.
function picu_notif_antrean(PDO $pdo, $pasienId = null)
{
    try {
        proses_notif_antrean_dekat($pdo, date('Y-m-d'), date('H:i'), $pasienId);
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log((string) $ex);
    }
}
