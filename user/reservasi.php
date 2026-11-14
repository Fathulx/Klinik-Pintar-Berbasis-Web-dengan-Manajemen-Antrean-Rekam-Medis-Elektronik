<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';
require_once __DIR__ . '/../includes/notifikasi.php';

$user = require_role('user');
$pdo  = db();

$pasienId = pasien_id_dari_user($pdo, $user['id']);
if (!$pasienId) {
    http_response_code(500);
    exit('Data pasien tidak ditemukan. Hubungi admin.');
}

$poliId   = isset($_GET['poli']) && is_string($_GET['poli']) ? (int) $_GET['poli'] : 0;
$dokterId = isset($_GET['dokter']) && is_string($_GET['dokter']) ? (int) $_GET['dokter'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $hasil = buat_reservasi($pdo, $pasienId, (int) post('jadwal_id'), post('tanggal'));
    if ($hasil['ok']) {
        catat_log('buat_reservasi', 'id ' . $hasil['id'] . ', ' . $hasil['kode']);
        picu_notif_antrean($pdo, $pasienId);
        set_flash('Reservasi berhasil. Nomor antrean Anda ' . $hasil['kode'] . '.');
        redirect('user/antrean.php');
    }
    set_flash($hasil['pesan'], 'error');
    redirect('user/reservasi.php?poli=' . (int) post('poli') . '&dokter=' . (int) post('dokter'));
}

$langkah = 1;
$dokterInfo = null;

// Langkah 3: pilih tanggal
if ($dokterId > 0) {
    $st = $pdo->prepare(
        "SELECT d.id, d.poli_id, d.bio, u.nama, p.nama_poli
         FROM dokter d
         JOIN users u ON u.id = d.user_id AND u.status = 'aktif'
         JOIN poli p ON p.id = d.poli_id
         WHERE d.id = ?"
    );
    $st->execute([$dokterId]);
    $dokterInfo = $st->fetch();
    if ($dokterInfo) {
        $langkah = 3;
        $poliId  = (int) $dokterInfo['poli_id'];
    } else {
        $dokterId = 0;
    }
}

$poliList = [];
$dokterList = [];
$slot = [];
$poliAktif = null;

if ($langkah === 3) {
    $st = $pdo->prepare(
        'SELECT id, hari, SUBSTR(jam_mulai, 1, 5) AS jam_mulai, SUBSTR(jam_selesai, 1, 5) AS jam_selesai, kuota
         FROM jadwal_dokter WHERE dokter_id = ? AND aktif = 1 ORDER BY jam_mulai'
    );
    $st->execute([$dokterId]);
    $perHari = [];
    $idJadwal = [];
    foreach ($st->fetchAll() as $j) {
        $perHari[$j['hari']][] = $j;
        $idJadwal[] = (int) $j['id'];
    }

    $awal  = date('Y-m-d');
    $akhir = date('Y-m-d', strtotime('+13 days'));
    $terisi = [];
    $milik  = [];
    if ($idJadwal) {
        $in = implode(',', array_fill(0, count($idJadwal), '?'));
        $q = $pdo->prepare(
            "SELECT jadwal_id, tanggal, COUNT(*) AS n FROM reservasi
             WHERE jadwal_id IN ($in) AND tanggal BETWEEN ? AND ? AND status <> 'batal'
             GROUP BY jadwal_id, tanggal"
        );
        $q->execute(array_merge($idJadwal, [$awal, $akhir]));
        foreach ($q->fetchAll() as $b) {
            $terisi[$b['jadwal_id'] . '|' . $b['tanggal']] = (int) $b['n'];
        }
        $q = $pdo->prepare(
            "SELECT jadwal_id, tanggal FROM reservasi
             WHERE pasien_id = ? AND jadwal_id IN ($in) AND tanggal BETWEEN ? AND ?
               AND status IN ('menunggu', 'hadir', 'diperiksa')"
        );
        $q->execute(array_merge([$pasienId], $idJadwal, [$awal, $akhir]));
        foreach ($q->fetchAll() as $b) {
            $milik[$b['jadwal_id'] . '|' . $b['tanggal']] = true;
        }
    }

    for ($i = 0; $i < 14; $i++) {
        $tgl  = date('Y-m-d', strtotime("+$i days"));
        $hari = hari_dari_tanggal($tgl);
        if (empty($perHari[$hari])) {
            continue;
        }
        foreach ($perHari[$hari] as $j) {
            $kunci = $j['id'] . '|' . $tgl;
            $slot[] = [
                'jadwal_id' => (int) $j['id'],
                'tanggal'   => $tgl,
                'jam'       => $j['jam_mulai'] . ' - ' . $j['jam_selesai'],
                'sisa'      => (int) $j['kuota'] - (isset($terisi[$kunci]) ? $terisi[$kunci] : 0),
                'kuota'     => (int) $j['kuota'],
                'lewat'     => ($tgl === $awal && $j['jam_selesai'] <= date('H:i')),
                'dipesan'   => isset($milik[$kunci]),
            ];
        }
    }
    $langkah = 3;
} elseif ($poliId > 0) {
    // Langkah 2: pilih dokter
    $st = $pdo->prepare('SELECT id, nama_poli FROM poli WHERE id = ?');
    $st->execute([$poliId]);
    $poliAktif = $st->fetch();
    if ($poliAktif) {
        $langkah = 2;
        $st = $pdo->prepare(
            "SELECT d.id, u.nama, d.bio FROM dokter d
             JOIN users u ON u.id = d.user_id AND u.status = 'aktif'
             WHERE d.poli_id = ?
               AND EXISTS (SELECT 1 FROM jadwal_dokter j WHERE j.dokter_id = d.id AND j.aktif = 1)
             ORDER BY u.nama"
        );
        $st->execute([$poliId]);
        $dokterList = $st->fetchAll();

        $hariDokter = [];
        if ($dokterList) {
            $ids = array_map('intval', array_column($dokterList, 'id'));
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $q = $pdo->prepare("SELECT DISTINCT dokter_id, hari FROM jadwal_dokter WHERE aktif = 1 AND dokter_id IN ($in)");
            $q->execute($ids);
            foreach ($q->fetchAll() as $b) {
                $hariDokter[$b['dokter_id']][] = $b['hari'];
            }
            $urutan = daftar_hari();
            foreach ($hariDokter as $k => $daftarHari) {
                usort($daftarHari, function ($a, $b) use ($urutan) {
                    return array_search($a, $urutan) - array_search($b, $urutan);
                });
                $hariDokter[$k] = $daftarHari;
            }
        }
    }
}

if ($langkah === 1) {
    $poliList = $pdo->query(
        "SELECT p.id, p.nama_poli, p.deskripsi, COUNT(DISTINCT d.id) AS jml_dokter
         FROM poli p
         JOIN dokter d ON d.poli_id = p.id
         JOIN users u ON u.id = d.user_id AND u.status = 'aktif'
         JOIN jadwal_dokter j ON j.dokter_id = d.id AND j.aktif = 1
         GROUP BY p.id, p.nama_poli, p.deskripsi ORDER BY p.nama_poli"
    )->fetchAll();
}

$judul = 'Buat Reservasi';
$aktif = 'user/reservasi.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<h1 class="font-serif text-4xl font-bold">Buat reservasi</h1>

<?php $namaLangkah = [1 => 'Pilih poli', 2 => 'Pilih dokter', 3 => 'Pilih tanggal']; ?>
<ol class="langkah-pesan mt-5" aria-label="Langkah reservasi">
  <?php foreach ($namaLangkah as $no => $nama):
      $kelas = $no < $langkah ? 'is-selesai' : ($no === $langkah ? 'is-aktif' : '');
      $tautan = $no === 1 ? url('user/reservasi.php') : ($no === 2 && $langkah > 2 ? url('user/reservasi.php?poli=' . $poliId) : null); ?>
    <li class="<?= $kelas ?>"<?= $no === $langkah ? ' aria-current="step"' : '' ?>>
      <span class="langkah-pesan__no"><?= $no ?></span>
      <?php if ($tautan && $no < $langkah): ?>
        <a href="<?= e($tautan) ?>"><?= e($nama) ?></a>
      <?php else: ?>
        <span><?= e($nama) ?></span>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ol>

<?php if ($langkah === 1): ?>
  <div class="pilih-poli mt-6">
    <?php foreach ($poliList as $p): ?>
      <a class="pilih-poli__kartu <?= kelas_poli($p['nama_poli']) ?>" href="<?= e(url('user/reservasi.php?poli=' . (int) $p['id'])) ?>">
        <span class="pilih-poli__ikon"><?= ikon_poli($p['nama_poli']) ?></span>
        <span class="min-w-0">
          <span class="block text-lg font-bold">Poli <?= e($p['nama_poli']) ?></span>
          <span class="block text-sm text-ink/70"><?= e($p['deskripsi']) ?></span>
          <span class="mt-1 block text-sm font-semibold text-klinik"><?= (int) $p['jml_dokter'] ?> dokter praktik</span>
        </span>
      </a>
    <?php endforeach; ?>
    <?php if (!$poliList): ?>
      <p class="text-ink/70">Belum ada poli dengan jadwal praktik yang tersedia.</p>
    <?php endif; ?>
  </div>

<?php elseif ($langkah === 2): ?>
  <div class="mt-6 flex items-center gap-3"><?= tanda_poli_html($poliAktif['nama_poli']) ?></div>
  <div class="papan-jadwal mt-3">
    <?php $singkat = ['Senin' => 'Sen', 'Selasa' => 'Sel', 'Rabu' => 'Rab', 'Kamis' => 'Kam', 'Jumat' => 'Jum', 'Sabtu' => 'Sab', 'Minggu' => 'Min']; ?>
    <?php foreach ($dokterList as $d): $hariD = isset($hariDokter[$d['id']]) ? $hariDokter[$d['id']] : []; ?>
      <a class="papan-jadwal__baris papan-jadwal__baris--tautan" href="<?= e(url('user/reservasi.php?poli=' . $poliId . '&dokter=' . (int) $d['id'])) ?>">
        <div>
          <p class="text-lg font-bold"><?= e($d['nama']) ?></p>
          <p class="text-sm text-ink/70"><?= e($d['bio']) ?></p>
        </div>
        <ol class="papan-jadwal__hari <?= kelas_poli($poliAktif['nama_poli']) ?>" aria-label="Hari praktik">
          <?php foreach (daftar_hari() as $h): $ada = in_array($h, $hariD, true); ?>
            <li class="<?= $ada ? 'is-praktik' : '' ?>"><?= e($singkat[$h]) ?><span class="sr-only"><?= $ada ? ' praktik' : ' libur' ?></span></li>
          <?php endforeach; ?>
        </ol>
      </a>
    <?php endforeach; ?>
    <?php if (!$dokterList): ?>
      <p class="py-4 text-ink/70">Belum ada dokter dengan jadwal aktif di poli ini.</p>
    <?php endif; ?>
  </div>

<?php else: ?>
  <p class="mt-4 text-sm text-ink/70">
    <?= e($dokterInfo['nama']) ?>, Poli <?= e($dokterInfo['nama_poli']) ?>. Pilih tanggal kunjungan (14 hari ke depan).
  </p>
  <div class="mt-4 overflow-x-auto">
    <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
      <thead class="bg-paper text-[13px] font-semibold text-ink/60">
        <tr><th class="px-3 py-2">Tanggal</th><th class="px-3 py-2">Jam</th><th class="px-3 py-2">Sisa kuota</th><th class="px-3 py-2"></th></tr>
      </thead>
      <tbody>
        <?php foreach ($slot as $s): ?>
          <tr class="border-t border-garis">
            <td data-label="Tanggal" class="px-3 py-2"><?= e(tanggal_indonesia($s['tanggal'])) ?></td>
            <td data-label="Jam" class="whitespace-nowrap px-3 py-2 font-mono text-xs"><?= e($s['jam']) ?></td>
            <td data-label="Sisa kuota" class="px-3 py-2 font-mono text-xs"><?= max(0, $s['sisa']) ?> dari <?= $s['kuota'] ?></td>
            <td data-label="Aksi" class="px-3 py-2 text-right">
              <?php if ($s['dipesan']): ?>
                <span class="text-xs text-ink/60">Sudah Anda pesan</span>
              <?php elseif ($s['lewat']): ?>
                <span class="text-xs text-ink/60">Sudah berakhir</span>
              <?php elseif ($s['sisa'] <= 0): ?>
                <span class="text-xs text-bata">Penuh</span>
              <?php else: ?>
                <form method="post" onsubmit="return confirm('Pesan antrean pada tanggal ini?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="jadwal_id" value="<?= (int) $s['jadwal_id'] ?>">
                  <input type="hidden" name="tanggal" value="<?= e($s['tanggal']) ?>">
                  <input type="hidden" name="poli" value="<?= (int) $poliId ?>">
                  <input type="hidden" name="dokter" value="<?= (int) $dokterId ?>">
                  <button class="bg-klinik px-4 py-2 text-sm font-semibold text-white">Pesan nomor</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$slot): ?>
          <tr><td colspan="4" class="px-3 py-4 text-ink/60">Tidak ada jadwal praktik dalam 14 hari ke depan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>