<?php
require_once __DIR__ . '/../includes/akun_saya.php';

function test_inisial_nama()
{
    sama('F', inisial_nama('Fathul'), 'satu kata');
    sama('RK', inisial_nama('Rina Kartika'), 'dua kata');
    sama('SR', inisial_nama('dr. Siti Rahma'), 'gelar dr. diabaikan');
    sama('BS', inisial_nama('drg. Budi Santoso'), 'gelar drg. diabaikan');
    sama('AP', inisial_nama('dr. Andi Pratama, Sp.PD'), 'gelar belakang diabaikan');
    sama('SA', inisial_nama('Super Admin'), 'admin');
    sama('?', inisial_nama('   '), 'kosong');
}

function test_detail_akun_pasien()
{
    $pdo = test_pdo();
    $id = buat_user_uji($pdo, 'user');
    $pdo->prepare("UPDATE pasien SET no_hp = '081234', jenis_kelamin = 'P', tanggal_lahir = '1994-03-12' WHERE user_id = ?")
        ->execute([$id]);
    $a = detail_akun($pdo, $id, '2026-11-15');

    sama('Pasien', $a['peran'], 'peran');
    cek(strpos($a['email'], '@') !== false, 'email');
    sama(['No. HP' => '081234', 'Jenis kelamin' => 'Perempuan', 'Tanggal lahir' => '12/03/1994 (32 tahun)'], $a['rincian'], 'rincian pasien');
}

function test_detail_akun_dokter()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-16', '08:00', []);
    $st = $pdo->prepare('SELECT d.user_id FROM jadwal_dokter j JOIN dokter d ON d.id = j.dokter_id WHERE j.id = ?');
    $st->execute([$d['jadwal_id']]);
    $a = detail_akun($pdo, (int) $st->fetchColumn(), '2026-11-15');

    sama('Dokter', $a['peran'], 'peran');
    cek(strpos($a['rincian']['Poli'], 'Umum') === 0, 'poli: ' . $a['rincian']['Poli']);
    cek($a['rincian']['No. STR'] !== '', 'no STR');
}
