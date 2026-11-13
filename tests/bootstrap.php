<?php
// Pendukung test tanpa framework. Semua test memakai database terpisah
// (klinik_pintar_test) yang dibuat ulang sebelum setiap test, sehingga
// database aplikasi (DB_NAME) tidak pernah tersentuh.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../includes/antrean.php';

define('TEST_DB_NAME', 'klinik_pintar_test');

// warning/notice dianggap kegagalan test
set_error_handler(function ($no, $pesan, $file, $baris) {
    throw new ErrorException($pesan, 0, $no, $file, $baris);
});

if (DB_NAME === TEST_DB_NAME) {
    fwrite(STDERR, "DB_NAME sama dengan database uji. Test dihentikan.\n");
    exit(2);
}

class GagalUji extends Exception
{
}

function test_pdo()
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+07:00'");
    }
    return $pdo;
}

// Memecah file SQL menjadi pernyataan tunggal (baris komentar "--" dibuang).
function jalankan_file_sql(PDO $pdo, $path)
{
    $baris = [];
    foreach (preg_split('/\R/', file_get_contents($path)) as $b) {
        if (!preg_match('/^\s*--/', $b)) {
            $baris[] = $b;
        }
    }
    foreach (explode(';', implode("\n", $baris)) as $sql) {
        if (trim($sql) !== '') {
            $pdo->exec($sql);
        }
    }
}

function test_db_baru()
{
    $pdo = test_pdo();
    $pdo->exec('DROP DATABASE IF EXISTS ' . TEST_DB_NAME);
    $pdo->exec('CREATE DATABASE ' . TEST_DB_NAME . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE ' . TEST_DB_NAME);
    jalankan_file_sql($pdo, __DIR__ . '/../database/schema.sql');
    // migrasi dijalankan dua kali untuk membuktikan aman diulang
    // semua migrasi diterapkan dua kali: harus aman diimpor ulang
    $migrasi = glob(__DIR__ . '/../database/migrations/*.sql');
    sort($migrasi);
    foreach ([1, 2] as $_) {
        foreach ($migrasi as $f) {
            jalankan_file_sql($pdo, $f);
        }
    }
    return $pdo;
}

function cek($kondisi, $pesan)
{
    if (!$kondisi) {
        throw new GagalUji($pesan);
    }
}

function sama($harap, $nyata, $pesan)
{
    if ($harap !== $nyata) {
        throw new GagalUji($pesan . ' (harap ' . var_export($harap, true)
            . ', nyata ' . var_export($nyata, true) . ')');
    }
}

function buat_user_uji(PDO $pdo, $role, $status = 'aktif')
{
    static $n = 0;
    $n++;
    $pdo->prepare('INSERT INTO users (nama, email, password, role, status) VALUES (?, ?, ?, ?, ?)')
        ->execute(['Uji ' . $n, 'uji' . $n . '_' . $role . '@contoh.test',
                   password_hash('Lama12345', PASSWORD_DEFAULT), $role, $status]);
    $id = (int) $pdo->lastInsertId();
    if ($role === 'user') {
        $pdo->prepare('INSERT INTO pasien (user_id) VALUES (?)')->execute([$id]);
    }
    return $id;
}

// Membuat satu jadwal pada hari $tanggal dan satu reservasi untuk tiap status
// di $statusUrut, dengan nomor antrean 1..n sesuai urutan.
function buat_reservasi_uji(PDO $pdo, $tanggal, $jamMulai, array $statusUrut)
{
    static $n = 0;
    $n++;
    $pdo->prepare('INSERT INTO poli (nama_poli) VALUES (?)')->execute(['Umum' . $n]);
    $poliId = (int) $pdo->lastInsertId();
    $dokterUser = buat_user_uji($pdo, 'dokter');
    $pdo->prepare('INSERT INTO dokter (user_id, poli_id, no_str) VALUES (?, ?, ?)')
        ->execute([$dokterUser, $poliId, 'STR-UJI-' . $n]);
    $dokterId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO jadwal_dokter (dokter_id, hari, jam_mulai, jam_selesai, kuota) VALUES (?, ?, ?, ?, 50)')
        ->execute([$dokterId, hari_dari_tanggal($tanggal), $jamMulai, '23:00']);
    $jadwalId = (int) $pdo->lastInsertId();

    $hasil = ['jadwal_id' => $jadwalId, 'reservasi' => []];
    foreach (array_values($statusUrut) as $i => $status) {
        $userId = buat_user_uji($pdo, 'user');
        $pasienId = pasien_id_dari_user($pdo, $userId);
        $pdo->prepare('INSERT INTO reservasi (pasien_id, jadwal_id, tanggal, no_antrean, status) VALUES (?, ?, ?, ?, ?)')
            ->execute([$pasienId, $jadwalId, $tanggal, $i + 1, $status]);
        $hasil['reservasi'][$i + 1] = [
            'id'        => (int) $pdo->lastInsertId(),
            'user_id'   => $userId,
            'pasien_id' => $pasienId,
        ];
    }
    return $hasil;
}
