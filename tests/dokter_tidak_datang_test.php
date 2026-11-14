<?php
require_once __DIR__ . '/../includes/antrean.php';

function _dokter_dari_jadwal(PDO $pdo, $jadwalId)
{
    $st = $pdo->prepare('SELECT dokter_id FROM jadwal_dokter WHERE id = ?');
    $st->execute([$jadwalId]);
    return (int) $st->fetchColumn();
}

function _status(PDO $pdo, $id)
{
    $st = $pdo->prepare('SELECT status FROM reservasi WHERE id = ?');
    $st->execute([$id]);
    return $st->fetchColumn();
}

function test_dokter_menandai_pasien_dipanggil_tidak_datang()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-16', '08:00', ['diperiksa', 'hadir']);
    $dokter = _dokter_dari_jadwal($pdo, $d['jadwal_id']);

    sama(true, dokter_tandai_tidak_datang($pdo, $dokter, $d['reservasi'][1]['id']), 'berhasil');
    sama('tidak_hadir', _status($pdo, $d['reservasi'][1]['id']), 'status');
}

function test_tidak_datang_ditolak_untuk_status_lain_dan_dokter_lain()
{
    $pdo = test_pdo();
    $a = buat_reservasi_uji($pdo, '2026-11-16', '08:00', ['diperiksa', 'hadir']);
    $b = buat_reservasi_uji($pdo, '2026-11-16', '08:00', ['diperiksa']);
    $dokterA = _dokter_dari_jadwal($pdo, $a['jadwal_id']);

    sama(false, dokter_tandai_tidak_datang($pdo, $dokterA, $a['reservasi'][2]['id']), 'masih hadir, belum dipanggil');
    sama(false, dokter_tandai_tidak_datang($pdo, $dokterA, $b['reservasi'][1]['id']), 'pasien dokter lain');
    sama('diperiksa', _status($pdo, $b['reservasi'][1]['id']), 'pasien dokter lain utuh');
}

function test_tidak_datang_ditolak_bila_sudah_ada_catatan_pemeriksaan()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-16', '08:00', ['diperiksa']);
    $id = $d['reservasi'][1]['id'];
    $pdo->prepare("INSERT INTO rekam_medis (reservasi_id, keluhan) VALUES (?, 'Batuk')")->execute([$id]);

    sama(false, dokter_tandai_tidak_datang($pdo, _dokter_dari_jadwal($pdo, $d['jadwal_id']), $id), 'ada catatan');
    sama('diperiksa', _status($pdo, $id), 'status tetap');
}
