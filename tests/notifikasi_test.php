<?php
require_once __DIR__ . '/../includes/notifikasi.php';

function test_buat_dan_hitung()
{
    $pdo = test_pdo();
    $a = buat_user_uji($pdo, 'user');
    $id = buat_notifikasi($pdo, $a, 'Judul', 'Pesan', 'user/antrean.php');
    buat_notifikasi($pdo, $a, 'Judul 2', 'Pesan 2');
    cek($id > 0, 'id notifikasi baru');
    sama(2, jumlah_notif_belum_dibaca($pdo, $a), 'jumlah belum dibaca');
}

function test_tandai_satu_dan_semua()
{
    $pdo = test_pdo();
    $a = buat_user_uji($pdo, 'user');
    $id1 = buat_notifikasi($pdo, $a, 'J1', 'P1');
    buat_notifikasi($pdo, $a, 'J2', 'P2');
    buat_notifikasi($pdo, $a, 'J3', 'P3');
    sama(1, tandai_notif_dibaca($pdo, $a, $id1), 'tandai satu');
    sama(2, jumlah_notif_belum_dibaca($pdo, $a), 'sisa setelah tandai satu');
    sama(2, tandai_notif_dibaca($pdo, $a), 'tandai semua');
    sama(0, jumlah_notif_belum_dibaca($pdo, $a), 'sisa setelah tandai semua');
}

function test_tidak_bisa_tandai_milik_orang_lain()
{
    $pdo = test_pdo();
    $a = buat_user_uji($pdo, 'user');
    $b = buat_user_uji($pdo, 'user');
    $id = buat_notifikasi($pdo, $a, 'J', 'P');
    sama(0, tandai_notif_dibaca($pdo, $b, $id), 'B menandai milik A');
    sama(0, tandai_notif_dibaca($pdo, $b), 'B menandai semua');
    sama(1, jumlah_notif_belum_dibaca($pdo, $a), 'milik A tetap belum dibaca');
}

function test_daftar_setelah_id()
{
    $pdo = test_pdo();
    $a = buat_user_uji($pdo, 'user');
    $id1 = buat_notifikasi($pdo, $a, 'J1', 'P1');
    $id2 = buat_notifikasi($pdo, $a, 'J2', 'P2');
    $id3 = buat_notifikasi($pdo, $a, 'J3', 'P3');
    $semua = daftar_notifikasi($pdo, $a);
    sama([$id3, $id2, $id1], array_map('intval', array_column($semua, 'id')), 'urutan terbaru dulu');
    $baru = daftar_notifikasi($pdo, $a, $id1);
    sama([$id3, $id2], array_map('intval', array_column($baru, 'id')), 'hanya setelah id1');
    sama('P3', $baru[0]['pesan'], 'kolom pesan ada');
}

function test_data_lonceng()
{
    $pdo = test_pdo();
    $a = buat_user_uji($pdo, 'user');
    buat_notifikasi($pdo, $a, 'J1', 'P1');
    $id2 = buat_notifikasi($pdo, $a, 'J2', 'P2');
    sama(['belum' => 2, 'setelah' => $id2], data_lonceng($pdo, $a), 'jumlah dan id terbaru');
}

function test_data_lonceng_tanpa_tabel_tidak_error()
{
    // database yang belum dimigrasi: lonceng disembunyikan, halaman tetap jalan
    $pdo = test_pdo();
    $a = buat_user_uji($pdo, 'user');
    $pdo->exec('DROP TABLE notifikasi');
    $logLama = ini_set('error_log', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'klinik_uji_lonceng.log');
    $hasil = data_lonceng($pdo, $a);
    ini_set('error_log', $logLama);
    sama(null, $hasil, 'null bila tabel belum ada');
}
