<?php
require_once __DIR__ . '/../includes/helper.php';

function test_label_peran_berbahasa_indonesia()
{
    sama('Pasien', label_peran('user'), 'user');
    sama('Super Admin', label_peran('super_admin'), 'super admin');
    sama('Dokter', label_peran('dokter'), 'dokter');
    sama('lainnya', label_peran('lainnya'), 'tidak dikenal apa adanya');
}

function test_teks_sisa_antrean()
{
    require_once __DIR__ . '/../includes/antrean.php';
    sama('Anda pasien berikutnya', teks_sisa_antrean(0), 'nol');
    sama('1 pasien lagi sebelum Anda', teks_sisa_antrean(1), 'satu');
    sama('12 pasien lagi sebelum Anda', teks_sisa_antrean(12), 'banyak');
}

function test_ikon_poli_dan_menu()
{
    require_once __DIR__ . '/../includes/ikon.php';
    require_once __DIR__ . '/../includes/menu.php';
    cek(strpos(ikon_poli('Gigi'), ikon('gigi')) === 0, 'poli gigi');
    cek(ikon_poli('Penyakit Dalam') === ikon('jantung'), 'poli dalam');
    cek(ikon_poli('Umum') === ikon('stetoskop'), 'poli lain');
    cek(strpos(ikon('tidak-ada'), 'aria-hidden="true"') !== false, 'ikon dekoratif');
    foreach (['super_admin', 'admin', 'dokter', 'user'] as $peran) {
        foreach (menu_panel($peran) as $m) {
            cek(isset($m[2]) && $m[2] !== '', "menu {$m[1]} punya ikon");
        }
    }
}

function test_usia_dihitung_pada_tanggal_kunjungan()
{
    sama(32, usia_tahun('1994-03-12', '2026-11-14'), 'sudah lewat ulang tahun');
    sama(31, usia_tahun('1994-12-01', '2026-11-14'), 'belum ulang tahun');
    sama(0, usia_tahun('2026-05-01', '2026-11-14'), 'bayi');
}
