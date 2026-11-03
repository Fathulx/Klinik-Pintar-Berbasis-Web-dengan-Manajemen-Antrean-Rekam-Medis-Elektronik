<?php
define('MENIT_PER_PASIEN', 15); // perkiraan sementara, bisa dihitung dari data nyata nanti

function daftar_hari()
{
    return ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
}

function hari_dari_tanggal($ymd)
{
    $h = daftar_hari();
    return $h[(int) date('N', strtotime($ymd)) - 1];
}

function tanggal_indonesia($ymd)
{
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
              'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $t = strtotime($ymd);
    return hari_dari_tanggal($ymd) . ', ' . date('j', $t) . ' '
        . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
}

function kode_antrean($namaPoli, $no)
{
    return strtoupper(mb_substr($namaPoli, 0, 1)) . '-' . str_pad((string) $no, 2, '0', STR_PAD_LEFT);
}

function perkiraan_jam($jamMulai, $diDepan)
{
    $t = strtotime('2000-01-01 ' . substr($jamMulai, 0, 5)) + $diDepan * MENIT_PER_PASIEN * 60;
    return date('H:i', $t);
}

function badge_reservasi($status)
{
    $peta = [
        'menunggu'    => 'status-badge--warning',
        'hadir'       => 'status-badge--success',
        'diperiksa'   => 'status-badge--info',
        'selesai'     => 'status-badge--neutral',
        'batal'       => 'status-badge--danger',
        'tidak_hadir' => 'status-badge--danger',
    ];
    $kelas = isset($peta[$status]) ? $peta[$status] : 'status-badge--neutral';
    return '<span class="status-badge ' . $kelas . '">' . e(str_replace('_', ' ', $status)) . '</span>';
}

function pasien_id_dari_user($pdo, $userId)
{
    $st = $pdo->prepare('SELECT id FROM pasien WHERE user_id = ?');
    $st->execute([$userId]);
    $id = $st->fetchColumn();
    return $id ? (int) $id : 0;
}

function reservasi_pasien($pdo, $pasienId, $aktifSaja)
{
    $filter = $aktifSaja
        ? "r.status IN ('menunggu', 'hadir', 'diperiksa')"
        : "r.status IN ('selesai', 'batal', 'tidak_hadir')";
    $urut = $aktifSaja ? 'ASC' : 'DESC';
    $st = $pdo->prepare(
        "SELECT r.id, r.tanggal, r.no_antrean, r.status, j.hari,
                SUBSTR(j.jam_mulai, 1, 5) AS jam_mulai, SUBSTR(j.jam_selesai, 1, 5) AS jam_selesai,
                u.nama AS dokter, p.nama_poli,
                (SELECT COUNT(*) FROM reservasi r2
                  WHERE r2.jadwal_id = r.jadwal_id AND r2.tanggal = r.tanggal
                    AND r2.no_antrean < r.no_antrean
                    AND r2.status IN ('menunggu', 'hadir', 'diperiksa')) AS di_depan
         FROM reservasi r
         JOIN jadwal_dokter j ON j.id = r.jadwal_id
         JOIN dokter d ON d.id = j.dokter_id
         JOIN users u ON u.id = d.user_id
         JOIN poli p ON p.id = d.poli_id
         WHERE r.pasien_id = ? AND $filter
         ORDER BY r.tanggal $urut, r.id LIMIT 50"
    );
    $st->execute([$pasienId]);
    return $st->fetchAll();
}

// Membuat reservasi. Baris jadwal dikunci (FOR UPDATE) sehingga dua pasien
// tidak bisa mengambil kuota terakhir bersamaan. Batasan unik di tabel
// reservasi menjadi pengaman kedua.
function buat_reservasi($pdo, $pasienId, $jadwalId, $tanggal)
{
    $ok = function ($pesan) {
        return ['ok' => false, 'pesan' => $pesan];
    };

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tanggal, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return $ok('Tanggal tidak valid.');
    }
    $hariIni = date('Y-m-d');
    if ($tanggal < $hariIni || $tanggal > date('Y-m-d', strtotime('+13 days'))) {
        return $ok('Tanggal harus dalam 14 hari ke depan.');
    }

    try {
        $pdo->beginTransaction();

        $kunci = $pdo->prepare('SELECT id FROM jadwal_dokter WHERE id = ? FOR UPDATE');
        $kunci->execute([$jadwalId]);
        if (!$kunci->fetchColumn()) {
            $pdo->rollBack();
            return $ok('Jadwal tidak ditemukan.');
        }

        $st = $pdo->prepare(
            'SELECT j.dokter_id, j.hari, j.jam_selesai, j.kuota, j.aktif,
                    u.status AS status_dokter, p.nama_poli
             FROM jadwal_dokter j
             JOIN dokter d ON d.id = j.dokter_id
             JOIN users u ON u.id = d.user_id
             JOIN poli p ON p.id = d.poli_id
             WHERE j.id = ?'
        );
        $st->execute([$jadwalId]);
        $j = $st->fetch();

        if (!$j['aktif'] || $j['status_dokter'] !== 'aktif') {
            $pdo->rollBack();
            return $ok('Jadwal ini sedang tidak tersedia.');
        }
        if (hari_dari_tanggal($tanggal) !== $j['hari']) {
            $pdo->rollBack();
            return $ok('Tanggal tidak sesuai dengan hari praktik dokter.');
        }
        if ($tanggal === $hariIni && substr($j['jam_selesai'], 0, 5) <= date('H:i')) {
            $pdo->rollBack();
            return $ok('Jam praktik hari ini sudah berakhir.');
        }

        $dup = $pdo->prepare(
            "SELECT 1 FROM reservasi r JOIN jadwal_dokter jj ON jj.id = r.jadwal_id
             WHERE r.pasien_id = ? AND jj.dokter_id = ? AND r.tanggal = ?
               AND r.status IN ('menunggu', 'hadir', 'diperiksa') LIMIT 1"
        );
        $dup->execute([$pasienId, $j['dokter_id'], $tanggal]);
        if ($dup->fetchColumn()) {
            $pdo->rollBack();
            return $ok('Anda sudah punya reservasi aktif dengan dokter ini pada tanggal tersebut.');
        }

        $hit = $pdo->prepare(
            "SELECT COALESCE(SUM(status <> 'batal'), 0) AS terisi, COALESCE(MAX(no_antrean), 0) AS terakhir
             FROM reservasi WHERE jadwal_id = ? AND tanggal = ?"
        );
        $hit->execute([$jadwalId, $tanggal]);
        $h = $hit->fetch();
        if ((int) $h['terisi'] >= (int) $j['kuota']) {
            $pdo->rollBack();
            return $ok('Kuota pada jadwal ini sudah penuh.');
        }

        $no = (int) $h['terakhir'] + 1;
        $pdo->prepare(
            'INSERT INTO reservasi (pasien_id, jadwal_id, tanggal, no_antrean) VALUES (?, ?, ?, ?)'
        )->execute([$pasienId, $jadwalId, $tanggal, $no]);
        $idBaru = (int) $pdo->lastInsertId();
        $pdo->commit();

        return ['ok' => true, 'id' => $idBaru, 'kode' => kode_antrean($j['nama_poli'], $no)];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return $ok($ex->getCode() === '23000'
            ? 'Terjadi bentrok nomor antrean. Silakan coba lagi.'
            : 'Terjadi kesalahan. Silakan coba lagi.');
    }
}

function batalkan_reservasi($pdo, $pasienId, $id)
{
    $st = $pdo->prepare('SELECT tanggal, status FROM reservasi WHERE id = ? AND pasien_id = ?');
    $st->execute([$id, $pasienId]);
    $r = $st->fetch();
    if (!$r) {
        return [false, 'Reservasi tidak ditemukan.'];
    }
    if ($r['status'] !== 'menunggu') {
        return [false, 'Hanya reservasi berstatus menunggu yang dapat dibatalkan.'];
    }
    if ($r['tanggal'] < date('Y-m-d')) {
        return [false, 'Reservasi yang sudah lewat tidak dapat dibatalkan.'];
    }
    $up = $pdo->prepare(
        "UPDATE reservasi SET status = 'batal' WHERE id = ? AND pasien_id = ? AND status = 'menunggu'"
    );
    $up->execute([$id, $pasienId]);
    return $up->rowCount() > 0
        ? [true, 'Reservasi dibatalkan.']
        : [false, 'Status reservasi sudah berubah dan tidak dapat dibatalkan.'];
}

function karcis_html($r)
{
    $kode  = kode_antrean($r['nama_poli'], $r['no_antrean']);
    $aktif = in_array($r['status'], ['menunggu', 'hadir'], true);
    ob_start();
    ?>
    <div class="border border-dashed border-ink/40 bg-white p-5">
      <div class="flex items-start justify-between gap-2">
        <p class="font-mono text-[11px] uppercase tracking-widest text-ink/60">Nomor antrean</p>
        <?= badge_reservasi($r['status']) ?>
      </div>
      <p class="mt-1 font-mono text-6xl font-semibold text-bata"><?= e($kode) ?></p>
      <hr class="my-3 border-garis">
      <p class="font-mono text-[11px] uppercase tracking-widest text-ink/60">Poli <?= e($r['nama_poli']) ?></p>
      <p class="text-sm font-medium"><?= e($r['dokter']) ?></p>
      <p class="text-sm"><?= e(tanggal_indonesia($r['tanggal'])) ?></p>
      <p class="font-mono text-xs text-ink/70"><?= e($r['jam_mulai']) ?> - <?= e($r['jam_selesai']) ?></p>
      <?php if ($aktif): ?>
        <p class="mt-3 border-t border-garis pt-3 text-xs text-ink/70">
          Di depan Anda <span class="font-mono"><?= (int) $r['di_depan'] ?></span> pasien.
          Perkiraan dilayani sekitar
          <span class="font-mono"><?= e(perkiraan_jam($r['jam_mulai'], (int) $r['di_depan'])) ?></span>.
        </p>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function tanggal_valid($s)
{
    return is_string($s)
        && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)
        && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

function tandai_tidak_hadir_otomatis($pdo)
{
    $pdo->prepare("UPDATE reservasi SET status = 'tidak_hadir' WHERE status = 'menunggu' AND tanggal < ?")
        ->execute([date('Y-m-d')]);
}

function dokter_id_dari_user($pdo, $userId)
{
    $st = $pdo->prepare('SELECT id FROM dokter WHERE user_id = ?');
    $st->execute([$userId]);
    $id = $st->fetchColumn();
    return $id ? (int) $id : 0;
}