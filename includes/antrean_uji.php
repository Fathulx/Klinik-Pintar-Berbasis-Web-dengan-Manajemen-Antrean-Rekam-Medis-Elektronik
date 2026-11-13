<?php
// Antrean uji untuk mencoba notifikasi "giliran sudah dekat" tanpa membuat
// akun pasien satu per satu. Semua data uji memakai email @uji.klinik dan
// dapat dihapus kembali dengan hapus_antrean_uji().
//
// Yang dibuat:
//   - satu dokter uji ("dr. Uji Antrean") di poli pertama
//   - satu jadwal hari ini 00:00-23:59 yang TIDAK aktif, sehingga pasien
//     sungguhan tidak bisa memesannya
//   - $jumlahDiDepan pasien uji dengan nomor antrean 1..N (status menunggu)
//   - reservasi pasien sasaran di nomor N+1
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/antrean.php';

const UJI_DOMAIN_EMAIL = '@uji.klinik';
const UJI_EMAIL_DOKTER = 'dokter' . UJI_DOMAIN_EMAIL;
const UJI_PASSWORD     = 'Uji@12345';

function hapus_antrean_uji(PDO $pdo)
{
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'SELECT j.id FROM jadwal_dokter j
             JOIN dokter d ON d.id = j.dokter_id
             JOIN users u ON u.id = d.user_id
             WHERE u.email = ?'
        );
        $st->execute([UJI_EMAIL_DOKTER]);
        $jadwal = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

        if ($jadwal) {
            $in = implode(',', $jadwal); // hanya bilangan bulat dari database
            $pdo->exec("DELETE rs FROM resep rs
                        JOIN rekam_medis rm ON rm.id = rs.rekam_medis_id
                        JOIN reservasi r ON r.id = rm.reservasi_id
                        WHERE r.jadwal_id IN ($in)");
            $pdo->exec("DELETE rm FROM rekam_medis rm
                        JOIN reservasi r ON r.id = rm.reservasi_id
                        WHERE r.jadwal_id IN ($in)");
            $pdo->exec("DELETE FROM reservasi WHERE jadwal_id IN ($in)");
            $pdo->exec("DELETE FROM jadwal_dokter WHERE id IN ($in)");
        }

        $suka = '%' . UJI_DOMAIN_EMAIL;
        $pdo->prepare('DELETE d FROM dokter d JOIN users u ON u.id = d.user_id WHERE u.email LIKE ?')
            ->execute([$suka]);
        $pdo->prepare('DELETE ps FROM pasien ps JOIN users u ON u.id = ps.user_id WHERE u.email LIKE ?')
            ->execute([$suka]);
        $st = $pdo->prepare('DELETE FROM users WHERE email LIKE ?');
        $st->execute([$suka]);
        $akunTerhapus = $st->rowCount();

        $pdo->commit();
        return $akunTerhapus;
    } catch (Exception $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

// Mengembalikan ['reservasi_id', 'di_depan', 'tanggal'] untuk pasien sasaran.
// Melempar InvalidArgumentException bila email bukan akun pasien aktif.
function isi_antrean_uji(PDO $pdo, $emailPasien, $jumlahDiDepan = 5)
{
    $jumlahDiDepan = max(1, min(20, (int) $jumlahDiDepan));

    $st = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'user' AND status = 'aktif'");
    $st->execute([strtolower(trim($emailPasien))]);
    $userSasaran = $st->fetchColumn();
    if (!$userSasaran || substr(strtolower($emailPasien), -strlen(UJI_DOMAIN_EMAIL)) === UJI_DOMAIN_EMAIL) {
        throw new InvalidArgumentException("Akun pasien aktif dengan email $emailPasien tidak ditemukan.");
    }
    $pasienSasaran = pasien_id_dari_user($pdo, $userSasaran);
    if (!$pasienSasaran) {
        throw new InvalidArgumentException("Akun $emailPasien belum punya data pasien.");
    }

    $poliId = $pdo->query('SELECT id FROM poli ORDER BY id LIMIT 1')->fetchColumn();
    if (!$poliId) {
        throw new InvalidArgumentException('Belum ada poli. Tambahkan poli lebih dulu.');
    }

    hapus_antrean_uji($pdo); // mulai dari keadaan bersih

    $tanggal = date('Y-m-d');
    $pw = password_hash(UJI_PASSWORD, PASSWORD_DEFAULT);
    $insUser = $pdo->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)");
    $insRes  = $pdo->prepare(
        "INSERT INTO reservasi (pasien_id, jadwal_id, tanggal, no_antrean, status) VALUES (?, ?, ?, ?, 'menunggu')"
    );

    $pdo->beginTransaction();
    try {
        $insUser->execute(['dr. Uji Antrean', UJI_EMAIL_DOKTER, $pw, 'dokter']);
        $pdo->prepare('INSERT INTO dokter (user_id, poli_id, no_str, bio) VALUES (?, ?, ?, ?)')
            ->execute([$pdo->lastInsertId(), $poliId, 'UJI-ANTREAN', 'Dokter fiktif untuk uji notifikasi antrean.']);
        $dokterId = $pdo->lastInsertId();

        $pdo->prepare(
            "INSERT INTO jadwal_dokter (dokter_id, hari, jam_mulai, jam_selesai, kuota, aktif)
             VALUES (?, ?, '00:00', '23:59', 50, 0)"
        )->execute([$dokterId, hari_dari_tanggal($tanggal)]);
        $jadwalId = $pdo->lastInsertId();

        for ($i = 1; $i <= $jumlahDiDepan; $i++) {
            $insUser->execute(["Pasien Uji $i", "pasien$i" . UJI_DOMAIN_EMAIL, $pw, 'user']);
            $pdo->prepare('INSERT INTO pasien (user_id) VALUES (?)')->execute([$pdo->lastInsertId()]);
            $insRes->execute([$pdo->lastInsertId(), $jadwalId, $tanggal, $i]);
        }
        // pasien uji tidak perlu notifikasi; hanya pasien sasaran yang diuji
        // (notif_di_depan = 0: tidak ada angka lebih kecil yang bisa dikirim)
        $pdo->prepare('UPDATE reservasi SET notif_dekat_at = NOW(), notif_di_depan = 0 WHERE jadwal_id = ?')
            ->execute([$jadwalId]);
        $insRes->execute([$pasienSasaran, $jadwalId, $tanggal, $jumlahDiDepan + 1]);
        $reservasiId = (int) $pdo->lastInsertId();

        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    return ['reservasi_id' => $reservasiId, 'di_depan' => $jumlahDiDepan, 'tanggal' => $tanggal];
}
