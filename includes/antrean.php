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
    return '<span class="status-badge ' . $kelas . '">' . e(label_reservasi($status)) . '</span>';
}

function label_reservasi($status)
{
    $label = [
        'menunggu' => 'Menunggu', 'hadir' => 'Hadir', 'diperiksa' => 'Diperiksa',
        'selesai' => 'Selesai', 'batal' => 'Dibatalkan', 'tidak_hadir' => 'Tidak hadir',
    ];
    return isset($label[$status]) ? $label[$status] : str_replace('_', ' ', $status);
}

function pasien_id_dari_user($pdo, $userId)
{
    $st = $pdo->prepare('SELECT id FROM pasien WHERE user_id = ?');
    $st->execute([$userId]);
    $id = $st->fetchColumn();
    return $id ? (int) $id : 0;
}

// Aktif = status masih berjalan DAN tanggal hari ini atau nanti. Reservasi
// yang tanggalnya sudah lewat masuk riwayat apa pun statusnya.
function reservasi_pasien($pdo, $pasienId, $aktifSaja, $hariIni = null)
{
    $hariIni = $hariIni === null ? date('Y-m-d') : $hariIni;
    $filter = $aktifSaja
        ? "r.status IN ('menunggu', 'hadir', 'diperiksa') AND r.tanggal >= ?"
        : "(r.status IN ('selesai', 'batal', 'tidak_hadir') OR r.tanggal < ?)";
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
    $st->execute([$pasienId, $hariIni]);
    return $st->fetchAll();
}

// Dokter menandai pasien yang sudah dipanggil (status diperiksa) tetapi tidak
// masuk ruang periksa sebagai tidak hadir, agar antrean bisa berlanjut.
// Hanya pasien dokter itu sendiri, dan ditolak bila catatan pemeriksaan sudah
// disimpan (selesaikan pemeriksaan saja). Mengembalikan true bila berhasil.
function dokter_tandai_tidak_datang(PDO $pdo, $dokterId, $reservasiId)
{
    $up = $pdo->prepare(
        "UPDATE reservasi r
         JOIN jadwal_dokter j ON j.id = r.jadwal_id
         LEFT JOIN rekam_medis rm ON rm.reservasi_id = r.id
         SET r.status = 'tidak_hadir'
         WHERE r.id = ? AND j.dokter_id = ? AND r.status = 'diperiksa' AND rm.id IS NULL"
    );
    $up->execute([(int) $reservasiId, (int) $dokterId]);
    return $up->rowCount() > 0;
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

function teks_sisa_antrean($n)
{
    $n = (int) $n;
    return $n === 0 ? 'Anda pasien berikutnya' : "$n pasien lagi sebelum Anda";
}

// Teks dan nyala karcis dari status reservasi. Kembarannya di
// assets/notifikasi.js (keadaanKarcis) agar karcis bisa berubah tanpa refresh.
function keadaan_karcis($status, $diDepan, $batas, $hariIni)
{
    $akhir = [
        'tidak_hadir' => 'Anda ditandai tidak hadir',
        'selesai'     => 'Pemeriksaan selesai',
        'batal'       => 'Reservasi dibatalkan',
    ];
    if (isset($akhir[$status])) {
        return ['teks' => $akhir[$status], 'dekat' => false, 'lewat' => true];
    }
    if ($status === 'diperiksa') {
        return ['teks' => 'Silakan masuk ke ruang periksa', 'dekat' => true, 'lewat' => false];
    }
    return [
        'teks'  => teks_sisa_antrean($diDepan),
        'dekat' => $hariIni && (int) $diDepan <= (int) $batas,
        'lewat' => false,
    ];
}

// Status terkini semua reservasi pasien hari ini dan seterusnya (termasuk
// yang baru selesai/tidak hadir), untuk pembaruan langsung karcis.
function antrean_pasien_terkini(PDO $pdo, $pasienId, $hariIni)
{
    $st = $pdo->prepare(
        "SELECT r.id, r.status,
                (SELECT COUNT(*) FROM reservasi r2
                  WHERE r2.jadwal_id = r.jadwal_id AND r2.tanggal = r.tanggal
                    AND r2.no_antrean < r.no_antrean
                    AND r2.status IN ('menunggu', 'hadir', 'diperiksa')) AS di_depan
         FROM reservasi r
         WHERE r.pasien_id = ? AND r.tanggal >= ?
         ORDER BY r.tanggal, r.id LIMIT 50"
    );
    $st->execute([(int) $pasienId, $hariIni]);
    $hasil = [];
    foreach ($st->fetchAll() as $r) {
        $hasil[] = ['id' => (int) $r['id'], 'status' => $r['status'], 'di_depan' => (int) $r['di_depan']];
    }
    return $hasil;
}

// Kunci warna poli (lihat assets/app.css .poli--*): Umum, Gigi, Anak, Dalam.
function kelas_poli($namaPoli)
{
    $n = strtolower($namaPoli);
    foreach (['gigi', 'anak', 'dalam'] as $k) {
        if (strpos($n, $k) !== false) {
            return 'poli--' . $k;
        }
    }
    return 'poli--umum';
}

// Tanda poli: ikon dalam lingkaran berwarna poli + nama poli.
function tanda_poli_html($namaPoli)
{
    require_once __DIR__ . '/ikon.php';
    return '<span class="poli-tanda ' . kelas_poli($namaPoli) . '"><span class="poli-tanda__ikon">'
        . ikon_poli($namaPoli) . '</span>Poli ' . e($namaPoli) . '</span>';
}

// Karcis antrean bergaya layar pemanggil di ruang tunggu. Menyala kuning saat
// giliran sudah dekat (hari ini, sisa <= NOTIF_ANTREAN_SISA) atau saat pasien
// sedang dipanggil. Teks sisa diperbarui oleh assets/notifikasi.js.
function karcis_html($r)
{
    $kode     = kode_antrean($r['nama_poli'], $r['no_antrean']);
    $id       = (int) $r['id'];
    $menunggu = in_array($r['status'], ['menunggu', 'hadir'], true);
    $hariIni  = $r['tanggal'] === date('Y-m-d');
    $batas    = defined('NOTIF_ANTREAN_SISA') ? (int) NOTIF_ANTREAN_SISA : 3;
    $k        = keadaan_karcis($r['status'], (int) $r['di_depan'], $batas, $hariIni);
    ob_start();
    ?>
    <div class="karcis <?= $k['dekat'] ? 'karcis--dekat' : '' ?> <?= $k['lewat'] ? 'karcis--lewat' : '' ?>"
         data-karcis="<?= $id ?>" data-status="<?= e($r['status']) ?>"
         data-hari-ini="<?= $hariIni ? '1' : '0' ?>" data-batas-dekat="<?= $batas ?>">
      <div class="karcis__layar">
        <div class="karcis__kepala">
          <span>Nomor antrean Anda</span>
          <?= tanda_poli_html($r['nama_poli']) ?>
        </div>
        <p class="karcis__nomor"><?= e($kode) ?></p>
        <p class="karcis__sisa" data-sisa="<?= $id ?>" aria-live="polite"><?= e($k['teks']) ?></p>
        <span hidden data-di-depan="<?= $id ?>"><?= (int) $r['di_depan'] ?></span>
      </div>
      <div class="karcis__sobek" aria-hidden="true"></div>
      <dl class="karcis__rincian">
        <div><dt>Dokter</dt><dd><?= e($r['dokter']) ?></dd></div>
        <div><dt>Tanggal</dt><dd><?= e(tanggal_indonesia($r['tanggal'])) ?></dd></div>
        <div><dt>Jam praktik</dt><dd class="font-mono"><?= e($r['jam_mulai']) ?>-<?= e($r['jam_selesai']) ?></dd></div>
        <div data-perkiraan<?= $menunggu ? '' : ' hidden' ?>><dt>Perkiraan dilayani</dt><dd class="font-mono">± <?= e(perkiraan_jam($r['jam_mulai'], (int) $r['di_depan'])) ?></dd></div>
        <div><dt>Status</dt><dd data-status-label><?= badge_reservasi($r['status']) ?></dd></div>
      </dl>
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