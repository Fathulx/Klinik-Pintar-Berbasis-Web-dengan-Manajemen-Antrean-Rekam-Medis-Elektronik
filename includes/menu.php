<?php
function menu_panel($role)
{
    $semua = [
        'super_admin' => [
    ['Dashboard', 'superadmin/dashboard.php'],
    ['Kelola Admin', 'superadmin/kelola_admin.php'],
    ['Data Poli', 'superadmin/poli.php'],
    ['Log Aktivitas', 'superadmin/log.php'],
],
       'admin' => [
    ['Dashboard', 'admin/dashboard.php'],
    ['Antrean', 'admin/antrean.php'],
    ['Laporan', 'admin/laporan.php'],
    ['Kelola Dokter', 'admin/dokter.php'],
    ['Jadwal Praktik', 'admin/jadwal.php'],
],
       'dokter' => [
    ['Dashboard', 'dokter/dashboard.php'],
    ['Antrean Hari Ini', 'dokter/antrean.php'],
    ['Jadwal Saya', 'dokter/jadwal.php'],
    ['Riwayat Pasien', 'dokter/riwayat.php'],
],
        'user' => [
    ['Dashboard', 'user/dashboard.php'],
    ['Buat Reservasi', 'user/reservasi.php'],
    ['Antrean Saya', 'user/antrean.php'],
    ['Riwayat Kunjungan', 'user/riwayat.php'],
],
    ];
    return isset($semua[$role]) ? $semua[$role] : [];
}