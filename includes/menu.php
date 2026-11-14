<?php
function menu_panel($role)
{
    $semua = [
        'super_admin' => [
    ['Dashboard', 'superadmin/dashboard.php', 'dasbor'],
    ['Kelola Admin', 'superadmin/kelola_admin.php', 'pengguna'],
    ['Data Poli', 'superadmin/poli.php', 'gedung'],
    ['Log Aktivitas', 'superadmin/log.php', 'daftar'],
],
       'admin' => [
    ['Dashboard', 'admin/dashboard.php', 'dasbor'],
    ['Antrean', 'admin/antrean.php', 'karcis'],
    ['Laporan', 'admin/laporan.php', 'grafik'],
    ['Kelola Dokter', 'admin/dokter.php', 'stetoskop'],
    ['Jadwal Praktik', 'admin/jadwal.php', 'kalender'],
],
       'dokter' => [
    ['Dashboard', 'dokter/dashboard.php', 'dasbor'],
    ['Antrean Hari Ini', 'dokter/antrean.php', 'karcis'],
    ['Jadwal Saya', 'dokter/jadwal.php', 'kalender'],
    ['Riwayat Pasien', 'dokter/riwayat.php', 'periksa'],
],
        'user' => [
    ['Dashboard', 'user/dashboard.php', 'dasbor'],
    ['Buat Reservasi', 'user/reservasi.php', 'pesan'],
    ['Antrean Saya', 'user/antrean.php', 'karcis'],
    ['Notifikasi', 'user/notifikasi.php', 'lonceng'],
    ['Riwayat Kunjungan', 'user/riwayat.php', 'dokumen'],
],
    ];
    return isset($semua[$role]) ? $semua[$role] : [];
}