<?php
require_once __DIR__ . '/../includes/data_demo.php';

// Jadwal aktif Senin 08:00-12:00. 2026-11-16 adalah Senin.
function _jadwal_senin(PDO $pdo)
{
    $d = buat_reservasi_uji($pdo, '2026-11-16', '08:00', []);
    $pdo->prepare("UPDATE jadwal_dokter SET jam_selesai = '12:00' WHERE id = ?")->execute([$d['jadwal_id']]);
    return $d['jadwal_id'];
}

function _hitung(PDO $pdo, $sql, array $p = [])
{
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return (int) $st->fetchColumn();
}

function test_isi_membuat_riwayat_hari_ini_dan_mendatang()
{
    $pdo = test_pdo();
    $jadwal = _jadwal_senin($pdo);
    $h = isi_data_demo($pdo, '2026-11-16');

    cek($h['pasien'] >= 10, 'pasien contoh dibuat: ' . $h['pasien']);
    sama($h['pasien'], _hitung($pdo, "SELECT COUNT(*) FROM users WHERE email LIKE '%@contoh.klinik' AND role = 'user'"), 'akun pasien');

    // lampau: hanya status akhir, tidak ada yang masih menunggu
    cek(_hitung($pdo, "SELECT COUNT(*) FROM reservasi WHERE tanggal < '2026-11-16'") > 0, 'ada riwayat');
    sama(0, _hitung($pdo, "SELECT COUNT(*) FROM reservasi WHERE tanggal < '2026-11-16'
                             AND status IN ('menunggu', 'hadir', 'diperiksa')"), 'riwayat sudah final');
    // setiap kunjungan selesai punya rekam medis terkunci dan minimal satu resep
    sama(0, _hitung($pdo, "SELECT COUNT(*) FROM reservasi r LEFT JOIN rekam_medis rm ON rm.reservasi_id = r.id
                             WHERE r.status = 'selesai' AND (rm.id IS NULL OR rm.terkunci = 0)"), 'rekam medis lengkap');
    sama(0, _hitung($pdo, "SELECT COUNT(*) FROM rekam_medis rm LEFT JOIN resep rs ON rs.rekam_medis_id = rm.id
                             WHERE rs.id IS NULL"), 'resep ada');

    // hari ini: campuran status untuk demo antrean
    cek($h['hari_ini'] > 0, 'antrean hari ini');
    cek(_hitung($pdo, "SELECT COUNT(*) FROM reservasi WHERE tanggal = '2026-11-16' AND status = 'menunggu'") > 0, 'ada yang menunggu');
    cek(_hitung($pdo, "SELECT COUNT(*) FROM reservasi WHERE tanggal = '2026-11-16' AND status = 'selesai'") > 0, 'ada yang selesai');

    // mendatang: hanya menunggu, sesuai hari jadwal (Senin)
    cek(_hitung($pdo, "SELECT COUNT(*) FROM reservasi WHERE tanggal > '2026-11-16'") > 0, 'ada reservasi mendatang');
    sama(0, _hitung($pdo, "SELECT COUNT(*) FROM reservasi WHERE tanggal > '2026-11-16' AND status <> 'menunggu'"), 'mendatang menunggu');
    sama(0, _hitung($pdo, "SELECT COUNT(*) FROM reservasi WHERE DAYOFWEEK(tanggal) <> 2"), 'semua pada hari Senin');
    sama(0, _hitung($pdo, 'SELECT COUNT(*) FROM reservasi WHERE jadwal_id <> ?', [$jadwal]), 'hanya jadwal yang ada');
}

// Poli Anak hanya untuk anak, Poli Penyakit Dalam hanya untuk dewasa.
function test_pasien_sesuai_usia_poli()
{
    $pdo = test_pdo();
    foreach (['Anak', 'Penyakit Dalam'] as $nama) {
        $d = buat_reservasi_uji($pdo, '2026-11-16', '08:00', []);
        $pdo->prepare('UPDATE poli p JOIN dokter d ON d.poli_id = p.id JOIN jadwal_dokter j ON j.dokter_id = d.id
                       SET p.nama_poli = ? WHERE j.id = ?')->execute([$nama, $d['jadwal_id']]);
    }
    isi_data_demo($pdo, '2026-11-16');

    $usia = "TIMESTAMPDIFF(YEAR, ps.tanggal_lahir, r.tanggal)";
    $dasar = "SELECT COUNT(*) FROM reservasi r JOIN pasien ps ON ps.id = r.pasien_id
              JOIN jadwal_dokter j ON j.id = r.jadwal_id JOIN dokter d ON d.id = j.dokter_id
              JOIN poli p ON p.id = d.poli_id WHERE p.nama_poli = ?";
    cek(_hitung($pdo, $dasar, ['Anak']) > 0, 'poli anak terisi');
    cek(_hitung($pdo, $dasar, ['Penyakit Dalam']) > 0, 'poli penyakit dalam terisi');
    sama(0, _hitung($pdo, "$dasar AND $usia >= 15", ['Anak']), 'dewasa di poli anak');
    sama(0, _hitung($pdo, "$dasar AND $usia < 18", ['Penyakit Dalam']), 'anak di poli penyakit dalam');
}

function test_tidak_melebihi_kuota_dan_tidak_bentrok_nomor()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-16', '08:00', ['menunggu', 'menunggu']);
    $pdo->prepare('UPDATE jadwal_dokter SET kuota = 5 WHERE id = ?')->execute([$d['jadwal_id']]);
    isi_data_demo($pdo, '2026-11-16');

    sama(0, _hitung($pdo, 'SELECT COUNT(*) FROM (SELECT tanggal FROM reservasi GROUP BY jadwal_id, tanggal
                            HAVING COUNT(*) > 5) x'), 'kuota dipatuhi');
    // reservasi asli tetap nomor 1 dan 2
    sama(2, _hitung($pdo, "SELECT COUNT(*) FROM reservasi WHERE tanggal = '2026-11-16' AND no_antrean IN (1, 2)
                            AND pasien_id IN (?, ?)", [$d['reservasi'][1]['pasien_id'], $d['reservasi'][2]['pasien_id']]), 'data asli utuh');
}

function test_hapus_hanya_data_demo_dan_isi_ulang_tidak_dobel()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-16', '08:00', ['selesai']);
    $asliRes = _hitung($pdo, 'SELECT COUNT(*) FROM reservasi');
    $asliUser = _hitung($pdo, 'SELECT COUNT(*) FROM users');

    $a = isi_data_demo($pdo, '2026-11-16');
    $b = isi_data_demo($pdo, '2026-11-16'); // isi ulang mengganti, bukan menambah
    sama($a['reservasi'], $b['reservasi'], 'isi ulang tidak dobel');
    sama($asliRes + $b['reservasi'], _hitung($pdo, 'SELECT COUNT(*) FROM reservasi'), 'total reservasi');

    cek(hapus_data_demo($pdo) > 0, 'akun demo terhapus');
    sama($asliRes, _hitung($pdo, 'SELECT COUNT(*) FROM reservasi'), 'reservasi kembali');
    sama($asliUser, _hitung($pdo, 'SELECT COUNT(*) FROM users'), 'akun kembali');
    sama(0, _hitung($pdo, 'SELECT COUNT(*) FROM rekam_medis rm LEFT JOIN reservasi r ON r.id = rm.reservasi_id WHERE r.id IS NULL'), 'tidak ada rekam medis yatim');
    sama(1, _hitung($pdo, 'SELECT COUNT(*) FROM reservasi WHERE id = ?', [$d['reservasi'][1]['id']]), 'reservasi asli utuh');
}
