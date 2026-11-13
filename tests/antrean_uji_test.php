<?php
require_once __DIR__ . '/../includes/antrean_uji.php';
require_once __DIR__ . '/../includes/notifikasi.php';

function uji_email_pasien(PDO $pdo, $userId)
{
    $st = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $st->execute([$userId]);
    return $st->fetchColumn();
}

function uji_siapkan_poli(PDO $pdo)
{
    $pdo->exec("INSERT INTO poli (nama_poli) VALUES ('Umum')");
}

function test_isi_antrean_uji_menaruh_pasien_di_belakang()
{
    $pdo = test_pdo();
    uji_siapkan_poli($pdo);
    $u = buat_user_uji($pdo, 'user');
    $hasil = isi_antrean_uji($pdo, uji_email_pasien($pdo, $u), 5);

    sama(5, $hasil['di_depan'], 'jumlah di depan');
    $r = reservasi_pasien($pdo, pasien_id_dari_user($pdo, $u), true);
    sama(1, count($r), 'pasien punya satu reservasi aktif');
    sama(5, (int) $r[0]['di_depan'], 'di depan menurut karcis');
    sama(6, (int) $r[0]['no_antrean'], 'nomor antrean pasien');
}

function test_notif_muncul_setelah_antrean_maju()
{
    $pdo = test_pdo();
    uji_siapkan_poli($pdo);
    $u = buat_user_uji($pdo, 'user');
    $hasil = isi_antrean_uji($pdo, uji_email_pasien($pdo, $u), 5);
    $jam = date('H:i');

    sama(0, proses_notif_antrean_dekat($pdo, $hasil['tanggal'], $jam), 'belum dekat: 5 di depan');
    $pdo->exec("UPDATE reservasi SET status = 'tidak_hadir' WHERE no_antrean IN (1, 2)");
    sama(1, proses_notif_antrean_dekat($pdo, $hasil['tanggal'], $jam), 'dekat: 3 di depan');
    sama(1, jumlah_notif_belum_dibaca($pdo, $u), 'pasien menerima notifikasi');

    $pdo->exec("UPDATE reservasi SET status = 'tidak_hadir' WHERE no_antrean = 3");
    sama(1, proses_notif_antrean_dekat($pdo, $hasil['tanggal'], $jam), 'turun ke 2: hanya pasien sasaran');
    sama(2, jumlah_notif_belum_dibaca($pdo, $u), 'pasien menerima notifikasi kedua');
}

function test_hapus_antrean_uji_membersihkan_semua()
{
    $pdo = test_pdo();
    uji_siapkan_poli($pdo);
    $u = buat_user_uji($pdo, 'user');
    isi_antrean_uji($pdo, uji_email_pasien($pdo, $u), 3);
    isi_antrean_uji($pdo, uji_email_pasien($pdo, $u), 3); // diulang: tetap bersih

    sama(4, hapus_antrean_uji($pdo), 'dokter uji + 3 pasien uji');
    sama(0, (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE '%@uji.klinik'")->fetchColumn(), 'akun uji habis');
    sama(0, (int) $pdo->query('SELECT COUNT(*) FROM reservasi')->fetchColumn(), 'reservasi uji habis');
    sama(0, (int) $pdo->query('SELECT COUNT(*) FROM jadwal_dokter')->fetchColumn(), 'jadwal uji habis');
    sama(1, (int) $pdo->query("SELECT COUNT(*) FROM users WHERE id = $u")->fetchColumn(), 'pasien asli tetap ada');
}

function test_isi_antrean_uji_menolak_akun_bukan_pasien()
{
    $pdo = test_pdo();
    uji_siapkan_poli($pdo);
    $admin = buat_user_uji($pdo, 'admin');
    try {
        isi_antrean_uji($pdo, uji_email_pasien($pdo, $admin), 5);
        cek(false, 'seharusnya ditolak');
    } catch (InvalidArgumentException $ex) {
        cek(true, 'ditolak');
    }
}
