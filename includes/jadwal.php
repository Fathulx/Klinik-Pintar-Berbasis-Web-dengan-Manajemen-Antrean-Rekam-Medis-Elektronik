<?php
// Aturan jadwal praktik yang dipakai bersama oleh halaman Admin dan Dokter.
// Semua pemeriksaan dilakukan di server.

function jadwal_ringkasan_mendatang($pdo, $jadwalId)
{
    $st = $pdo->prepare(
        "SELECT COUNT(*) AS n, SUM(status IN ('menunggu', 'hadir', 'diperiksa')) AS aktif
         FROM reservasi
         WHERE jadwal_id = ? AND tanggal >= ? AND status <> 'batal'
         GROUP BY tanggal"
    );
    $st->execute([$jadwalId, date('Y-m-d')]);
    $maks  = 0;
    $aktif = 0;
    foreach ($st->fetchAll() as $b) {
        $maks   = max($maks, (int) $b['n']);
        $aktif += (int) $b['aktif'];
    }
    return ['maks_per_tanggal' => $maks, 'aktif' => $aktif];
}

function jadwal_jumlah_reservasi($pdo, $jadwalId)
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM reservasi WHERE jadwal_id = ?');
    $st->execute([$jadwalId]);
    return (int) $st->fetchColumn();
}

// Mengambil satu jadwal. Jika $dokterIdPaksa diisi, jadwal milik dokter lain dianggap tidak ada.
function jadwal_ambil($pdo, $id, $dokterIdPaksa = null)
{
    $st = $pdo->prepare(
        'SELECT id, dokter_id, hari, SUBSTR(jam_mulai, 1, 5) AS jam_mulai,
                SUBSTR(jam_selesai, 1, 5) AS jam_selesai, kuota, aktif
         FROM jadwal_dokter WHERE id = ?'
    );
    $st->execute([$id]);
    $j = $st->fetch();
    if (!$j) {
        return null;
    }
    if ($dokterIdPaksa !== null && (int) $j['dokter_id'] !== (int) $dokterIdPaksa) {
        return null;
    }
    return $j;
}

// Bentrok hanya dihitung terhadap jadwal aktif milik dokter yang sama pada hari yang sama.
function jadwal_bentrok($pdo, $dokterId, $hari, $mulai, $selesai, $kecualiId)
{
    $st = $pdo->prepare(
        'SELECT 1 FROM jadwal_dokter
         WHERE dokter_id = ? AND hari = ? AND id <> ? AND aktif = 1
           AND jam_mulai < ? AND jam_selesai > ? LIMIT 1'
    );
    $st->execute([$dokterId, $hari, $kecualiId, $selesai . ':00', $mulai . ':00']);
    return (bool) $st->fetchColumn();
}

// Menambah atau mengubah jadwal. $in: id, dokter_id, hari, jam_mulai, jam_selesai, kuota.
// Untuk dokter, $dokterIdPaksa diisi dari sesi sehingga dokter_id kiriman formulir diabaikan.
function simpan_jadwal($pdo, $in, $dokterIdPaksa = null)
{
    $id       = (int) $in['id'];
    $dokterId = $dokterIdPaksa !== null ? (int) $dokterIdPaksa : (int) $in['dokter_id'];
    $hari     = (string) $in['hari'];
    $mulai    = (string) $in['jam_mulai'];
    $selesai  = (string) $in['jam_selesai'];
    $kuota    = (string) $in['kuota'];
    $err      = [];
    $polaJam  = '/^([01]\d|2[0-3]):[0-5]\d$/';

    $cek = $pdo->prepare('SELECT 1 FROM dokter WHERE id = ?');
    $cek->execute([$dokterId]);
    if (!$cek->fetchColumn()) {
        $err['dokter_id'] = 'Pilih dokter.';
    }
    if (!in_array($hari, daftar_hari(), true)) {
        $err['hari'] = 'Pilih hari.';
    }
    if (!preg_match($polaJam, $mulai)) {
        $err['jam_mulai'] = 'Jam mulai tidak valid.';
    }
    if (!preg_match($polaJam, $selesai)) {
        $err['jam_selesai'] = 'Jam selesai tidak valid.';
    } elseif (!isset($err['jam_mulai']) && $selesai <= $mulai) {
        $err['jam_selesai'] = 'Jam selesai harus setelah jam mulai.';
    }
    if (!ctype_digit($kuota) || (int) $kuota < 1 || (int) $kuota > 100) {
        $err['kuota'] = 'Kuota harus angka 1 sampai 100.';
    }

    $asli = null;
    if ($id > 0) {
        $asli = jadwal_ambil($pdo, $id, $dokterIdPaksa);
        if (!$asli) {
            return ['ok' => false, 'err' => ['umum' => 'Jadwal tidak ditemukan.']];
        }
    }

    if (!$err && $asli) {
        if (jadwal_jumlah_reservasi($pdo, $id) > 0
            && ((int) $asli['dokter_id'] !== $dokterId || $asli['hari'] !== $hari)) {
            $err['hari'] = 'Jadwal ini sudah punya reservasi, dokter dan hari tidak dapat diubah.';
        }
        $ring = jadwal_ringkasan_mendatang($pdo, $id);
        if ($ring['aktif'] > 0 && ($asli['jam_mulai'] !== $mulai || $asli['jam_selesai'] !== $selesai)) {
            $err['jam_mulai'] = 'Ada reservasi mendatang pada jadwal ini, jam praktik tidak dapat diubah. '
                . 'Nonaktifkan jadwal ini lalu buat jadwal baru; reservasi yang sudah masuk tetap dilayani.';
        }
        if ((int) $kuota < $ring['maks_per_tanggal']) {
            $err['kuota'] = 'Kuota tidak boleh lebih kecil dari reservasi yang sudah masuk ('
                . $ring['maks_per_tanggal'] . ' pada satu tanggal).';
        }
    }

    $aktifBaru = $asli ? ((int) $asli['aktif'] === 1) : true;
    if (!$err && $aktifBaru && jadwal_bentrok($pdo, $dokterId, $hari, $mulai, $selesai, $id)) {
        $err['jam_mulai'] = 'Bertabrakan dengan jadwal aktif lain pada hari yang sama.';
    }

    if ($err) {
        return ['ok' => false, 'err' => $err];
    }

    $data = [$dokterId, $hari, $mulai . ':00', $selesai . ':00', (int) $kuota];
    if ($id > 0) {
        $data[] = $id;
        $pdo->prepare(
            'UPDATE jadwal_dokter SET dokter_id = ?, hari = ?, jam_mulai = ?, jam_selesai = ?, kuota = ? WHERE id = ?'
        )->execute($data);
        catat_log('ubah_jadwal', 'jadwal ' . $id);
        return ['ok' => true, 'id' => $id, 'pesan' => 'Jadwal diperbarui.'];
    }
    $pdo->prepare(
        'INSERT INTO jadwal_dokter (dokter_id, hari, jam_mulai, jam_selesai, kuota) VALUES (?, ?, ?, ?, ?)'
    )->execute($data);
    $idBaru = (int) $pdo->lastInsertId();
    catat_log('tambah_jadwal', 'jadwal ' . $idBaru . ', dokter ' . $dokterId . ', ' . $hari);
    return ['ok' => true, 'id' => $idBaru, 'pesan' => 'Jadwal ditambahkan.'];
}

function ubah_status_jadwal($pdo, $id, $aktifBaru, $dokterIdPaksa = null)
{
    $j = jadwal_ambil($pdo, $id, $dokterIdPaksa);
    if (!$j) {
        return [false, 'Jadwal tidak ditemukan.'];
    }
    $aktifBaru = $aktifBaru ? 1 : 0;
    if ($aktifBaru === 1
        && jadwal_bentrok($pdo, $j['dokter_id'], $j['hari'], $j['jam_mulai'], $j['jam_selesai'], $j['id'])) {
        return [false, 'Tidak dapat diaktifkan karena bertabrakan dengan jadwal aktif lain pada hari yang sama.'];
    }
    $pdo->prepare('UPDATE jadwal_dokter SET aktif = ? WHERE id = ?')->execute([$aktifBaru, $id]);
    catat_log('ubah_status_jadwal', 'jadwal ' . $id . ' aktif=' . $aktifBaru);
    return [true, $aktifBaru
        ? 'Jadwal diaktifkan.'
        : 'Jadwal dinonaktifkan. Reservasi yang sudah masuk tetap dilayani.'];
}

function hapus_jadwal($pdo, $id, $dokterIdPaksa = null)
{
    $j = jadwal_ambil($pdo, $id, $dokterIdPaksa);
    if (!$j) {
        return [false, 'Jadwal tidak ditemukan.'];
    }
    if (jadwal_jumlah_reservasi($pdo, $id) > 0) {
        return [false, 'Jadwal sudah punya reservasi dan tidak bisa dihapus. Nonaktifkan saja.'];
    }
    try {
        $pdo->prepare('DELETE FROM jadwal_dokter WHERE id = ?')->execute([$id]);
    } catch (PDOException $ex) {
        return [false, 'Jadwal tidak dapat dihapus karena sudah dipakai. Nonaktifkan saja.'];
    }
    catat_log('hapus_jadwal', 'jadwal ' . $id);
    return [true, 'Jadwal dihapus.'];
}