<?php
require_once __DIR__ . '/../includes/helper.php';

function test_label_peran_berbahasa_indonesia()
{
    sama('Pasien', label_peran('user'), 'user');
    sama('Super Admin', label_peran('super_admin'), 'super admin');
    sama('Dokter', label_peran('dokter'), 'dokter');
    sama('lainnya', label_peran('lainnya'), 'tidak dikenal apa adanya');
}

function test_usia_dihitung_pada_tanggal_kunjungan()
{
    sama(32, usia_tahun('1994-03-12', '2026-11-14'), 'sudah lewat ulang tahun');
    sama(31, usia_tahun('1994-12-01', '2026-11-14'), 'belum ulang tahun');
    sama(0, usia_tahun('2026-05-01', '2026-11-14'), 'bayi');
}
