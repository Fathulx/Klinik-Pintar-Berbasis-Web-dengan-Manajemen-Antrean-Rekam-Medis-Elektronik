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

// Kasus yang sama diuji di tests/js/karcis_test.js agar PHP dan JS seragam.
function test_keadaan_karcis()
{
    require_once __DIR__ . '/../includes/antrean.php';
    sama(['teks' => '5 pasien lagi sebelum Anda', 'dekat' => false, 'lewat' => false], keadaan_karcis('menunggu', 5, 3, true), 'masih jauh');
    sama(['teks' => '3 pasien lagi sebelum Anda', 'dekat' => true, 'lewat' => false], keadaan_karcis('hadir', 3, 3, true), 'dekat');
    sama(['teks' => '2 pasien lagi sebelum Anda', 'dekat' => false, 'lewat' => false], keadaan_karcis('menunggu', 2, 3, false), 'bukan hari ini');
    sama(['teks' => 'Silakan masuk ke ruang periksa', 'dekat' => true, 'lewat' => false], keadaan_karcis('diperiksa', 0, 3, true), 'dipanggil');
    sama(['teks' => 'Anda ditandai tidak hadir', 'dekat' => false, 'lewat' => true], keadaan_karcis('tidak_hadir', 0, 3, true), 'tidak hadir');
    sama(['teks' => 'Pemeriksaan selesai', 'dekat' => false, 'lewat' => true], keadaan_karcis('selesai', 0, 3, true), 'selesai');
    sama(['teks' => 'Reservasi dibatalkan', 'dekat' => false, 'lewat' => true], keadaan_karcis('batal', 0, 3, true), 'batal');
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
