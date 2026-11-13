<?php
require_once __DIR__ . '/../includes/notifikasi.php';

function _jumlah_notif_user(PDO $pdo, $userId)
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM notifikasi WHERE user_id = ?');
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

function test_hanya_yang_posisinya_dekat()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-12', '09:00', array_fill(0, 6, 'menunggu'));
    sama(4, proses_notif_antrean_dekat($pdo, '2026-11-12', '09:00'), 'jumlah notifikasi dibuat');
    foreach ([1, 2, 3, 4] as $no) {
        sama(1, _jumlah_notif_user($pdo, $d['reservasi'][$no]['user_id']), "nomor $no dapat notifikasi");
    }
    sama(0, _jumlah_notif_user($pdo, $d['reservasi'][5]['user_id']), 'nomor 5 belum');
    sama(0, _jumlah_notif_user($pdo, $d['reservasi'][6]['user_id']), 'nomor 6 belum');

    $st = $pdo->prepare('SELECT judul, pesan, tautan FROM notifikasi WHERE user_id = ?');
    $st->execute([$d['reservasi'][4]['user_id']]);
    $n = $st->fetch();
    sama('Giliran Anda sudah dekat', $n['judul'], 'judul');
    cek(strpos($n['pesan'], 'tinggal 3 pasien lagi') !== false, 'pesan memuat sisa: ' . $n['pesan']);
    sama('user/antrean.php', $n['tautan'], 'tautan');
}

function test_tidak_dobel()
{
    $pdo = test_pdo();
    buat_reservasi_uji($pdo, '2026-11-12', '09:00', array_fill(0, 6, 'menunggu'));
    proses_notif_antrean_dekat($pdo, '2026-11-12', '09:00');
    sama(0, proses_notif_antrean_dekat($pdo, '2026-11-12', '09:00'), 'panggilan kedua');
    sama(4, (int) $pdo->query('SELECT COUNT(*) FROM notifikasi')->fetchColumn(), 'total notifikasi');
}

function test_menjadi_dekat_setelah_selesai()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-12', '09:00', array_fill(0, 6, 'menunggu'));
    proses_notif_antrean_dekat($pdo, '2026-11-12', '09:00');
    $pdo->prepare("UPDATE reservasi SET status = 'selesai' WHERE id = ?")->execute([$d['reservasi'][1]['id']]);
    // nomor 2-4 turun satu angka (dapat lagi), nomor 5 baru masuk batas
    sama(4, proses_notif_antrean_dekat($pdo, '2026-11-12', '09:30'), 'notifikasi baru');
    sama(1, _jumlah_notif_user($pdo, $d['reservasi'][5]['user_id']), 'nomor 5 sekarang dapat');
    sama(0, _jumlah_notif_user($pdo, $d['reservasi'][6]['user_id']), 'nomor 6 masih belum');
}

function test_notif_lagi_setiap_angka_turun()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-12', '09:00', array_fill(0, 6, 'menunggu'));
    proses_notif_antrean_dekat($pdo, '2026-11-12', '09:00');
    $no4 = $d['reservasi'][4]['user_id'];
    sama(1, _jumlah_notif_user($pdo, $no4), 'nomor 4: notifikasi pertama (3 di depan)');

    $pdo->prepare("UPDATE reservasi SET status = 'selesai' WHERE id = ?")->execute([$d['reservasi'][1]['id']]);
    proses_notif_antrean_dekat($pdo, '2026-11-12', '09:10');
    sama(2, _jumlah_notif_user($pdo, $no4), 'nomor 4: notifikasi kedua (2 di depan)');

    $st = $pdo->prepare('SELECT pesan FROM notifikasi WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$no4]);
    cek(strpos($st->fetchColumn(), 'tinggal 2 pasien lagi') !== false, 'pesan terbaru memuat angka baru');
}

function test_angka_melompat_hanya_kirim_angka_terbaru()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-12', '09:00', array_fill(0, 6, 'menunggu'));
    proses_notif_antrean_dekat($pdo, '2026-11-12', '09:00');
    $pdo->exec("UPDATE reservasi SET status = 'tidak_hadir' WHERE no_antrean IN (1, 2)");
    proses_notif_antrean_dekat($pdo, '2026-11-12', '09:10');

    $no4 = $d['reservasi'][4]['user_id'];
    sama(2, _jumlah_notif_user($pdo, $no4), 'nomor 4: 3 lalu langsung 1');
    $st = $pdo->prepare('SELECT pesan FROM notifikasi WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$no4]);
    cek(strpos($st->fetchColumn(), 'tinggal 1 pasien lagi') !== false, 'pesan memuat 1');
}

function test_batal_tidak_dihitung()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-12', '09:00', ['batal', 'tidak_hadir', 'menunggu', 'hadir']);
    sama(2, proses_notif_antrean_dekat($pdo, '2026-11-12', '09:00'), 'hanya status aktif yang dinotifikasi');
    $st = $pdo->prepare('SELECT pesan FROM notifikasi WHERE user_id = ?');
    $st->execute([$d['reservasi'][3]['user_id']]);
    cek(strpos($st->fetchColumn(), 'Anda pasien berikutnya') !== false, 'nomor 3 dianggap berikutnya');
}

function test_belum_waktunya()
{
    $pdo = test_pdo();
    buat_reservasi_uji($pdo, '2026-11-12', '09:00', array_fill(0, 6, 'menunggu'));
    sama(0, proses_notif_antrean_dekat($pdo, '2026-11-12', '07:59'), 'lebih dari 60 menit sebelum praktik');
    sama(4, proses_notif_antrean_dekat($pdo, '2026-11-12', '08:00'), 'tepat 60 menit sebelum praktik');
}

function test_tanggal_lain_diabaikan()
{
    $pdo = test_pdo();
    buat_reservasi_uji($pdo, '2026-11-13', '09:00', array_fill(0, 3, 'menunggu'));
    sama(0, proses_notif_antrean_dekat($pdo, '2026-11-12', '10:00'), 'reservasi besok');
}

function test_filter_pasien()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-12', '09:00', array_fill(0, 6, 'menunggu'));
    sama(1, proses_notif_antrean_dekat($pdo, '2026-11-12', '09:00', $d['reservasi'][2]['pasien_id']), 'hanya pasien itu');
    sama(1, _jumlah_notif_user($pdo, $d['reservasi'][2]['user_id']), 'nomor 2 dapat');
    sama(0, _jumlah_notif_user($pdo, $d['reservasi'][1]['user_id']), 'nomor 1 tidak tersentuh');
}

function test_diperiksa_dihitung_di_depan_tapi_tidak_dinotifikasi()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-12', '09:00', ['diperiksa', 'hadir', 'menunggu', 'menunggu', 'menunggu']);
    sama(3, proses_notif_antrean_dekat($pdo, '2026-11-12', '09:00'), 'nomor 2-4');
    sama(0, _jumlah_notif_user($pdo, $d['reservasi'][1]['user_id']), 'yang diperiksa tidak dinotifikasi');
    sama(0, _jumlah_notif_user($pdo, $d['reservasi'][5]['user_id']), 'nomor 5 di depan 4');
}
