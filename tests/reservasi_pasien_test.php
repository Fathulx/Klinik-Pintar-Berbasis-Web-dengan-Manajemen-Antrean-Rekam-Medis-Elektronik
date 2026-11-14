<?php
require_once __DIR__ . '/../includes/antrean.php';

// Reservasi yang tanggalnya sudah lewat tidak boleh muncul sebagai
// "kunjungan berikutnya", walau statusnya masih hadir/diperiksa.
function test_reservasi_lewat_tanggal_tidak_dianggap_aktif()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-03', '06:00', ['hadir']);
    $pasien = $d['reservasi'][1]['pasien_id'];

    sama([], reservasi_pasien($pdo, $pasien, true, '2026-11-14'), 'aktif');
    $riwayat = reservasi_pasien($pdo, $pasien, false, '2026-11-14');
    sama(1, count($riwayat), 'masuk riwayat');
    sama('hadir', $riwayat[0]['status'], 'status apa adanya');
}

function test_reservasi_hari_ini_dan_mendatang_tetap_aktif()
{
    $pdo = test_pdo();
    $a = buat_reservasi_uji($pdo, '2026-11-14', '08:00', ['diperiksa']);
    $b = buat_reservasi_uji($pdo, '2026-11-20', '08:00', ['menunggu']);

    sama(1, count(reservasi_pasien($pdo, $a['reservasi'][1]['pasien_id'], true, '2026-11-14')), 'hari ini');
    sama(1, count(reservasi_pasien($pdo, $b['reservasi'][1]['pasien_id'], true, '2026-11-14')), 'mendatang');
    sama([], reservasi_pasien($pdo, $b['reservasi'][1]['pasien_id'], false, '2026-11-14'), 'belum riwayat');
}
