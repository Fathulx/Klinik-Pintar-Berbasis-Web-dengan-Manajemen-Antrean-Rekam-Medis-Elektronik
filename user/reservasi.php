<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';

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
<h1 class="font-serif text-3xl font-bold text-klinik">Buat reservasi</h1>

<nav class="mt-3 font-mono text-xs uppercase tracking-widest text-ink/60">
  <a class="<?= $langkah === 1 ? 'text-bata' : 'underline' ?>" href="<?= e(url('user/reservasi.php')) ?>">1 Poli</a>
  <span class="mx-2">/</span>
  <?php if ($langkah >= 2): ?>
    <a class="<?= $langkah === 2 ? 'text-bata' : 'underline' ?>" href="<?= e(url('user/reservasi.php?poli=' . $poliId)) ?>">2 Dokter</a>
  <?php else: ?>
    <span>2 Dokter</span>
  <?php endif; ?>
  <span class="mx-2">/</span>
  <span class="<?= $langkah === 3 ? 'text-bata' : '' ?>">3 Tanggal</span>
</nav>

<?php if ($langkah === 1): ?>
  <p class="mt-4 text-sm text-ink/70">Pilih poli yang Anda tuju.</p>
  <div class="mt-4 border border-garis bg-white">
    <?php foreach ($poliList as $p): ?>
      <a class="flex items-center justify-between gap-4 border-b border-garis px-4 py-3 last:border-b-0 hover:bg-paper"
         href="<?= e(url('user/reservasi.php?poli=' . (int) $p['id'])) ?>">
        <span>
          <span class="block font-medium">Poli <?= e($p['nama_poli']) ?></span>
          <span class="block text-xs text-ink/60"><?= e($p['deskripsi']) ?></span>
        </span>
        <span class="font-mono text-xs text-ink/60"><?= (int) $p['jml_dokter'] ?> dokter</span>
      </a>
    <?php endforeach; ?>
    <?php if (!$poliList): ?>
      <p class="px-4 py-4 text-sm text-ink/60">Belum ada poli dengan jadwal praktik yang tersedia.</p>
    <?php endif; ?>
  </div>

<?php elseif ($langkah === 2): ?>
  <p class="mt-4 text-sm text-ink/70">Pilih dokter di Poli <?= e($poliAktif['nama_poli']) ?>.</p>
  <div class="mt-4 border border-garis bg-white">
    <?php foreach ($dokterList as $d): ?>
      <a class="block border-b border-garis px-4 py-3 last:border-b-0 hover:bg-paper"
         href="<?= e(url('user/reservasi.php?poli=' . $poliId . '&dokter=' . (int) $d['id'])) ?>">
        <span class="block font-medium"><?= e($d['nama']) ?></span>
        <span class="block text-xs text-ink/60"><?= e($d['bio']) ?></span>
        <span class="mt-1 block font-mono text-xs text-klinik">
          Praktik: <?= e(implode(', ', isset($hariDokter[$d['id']]) ? $hariDokter[$d['id']] : [])) ?>
        </span>
      </a>
    <?php endforeach; ?>
    <?php if (!$dokterList): ?>
      <p class="px-4 py-4 text-sm text-ink/60">Belum ada dokter dengan jadwal aktif di poli ini.</p>
    <?php endif; ?>
  </div>

<?php else: ?>
  <p class="mt-4 text-sm text-ink/70">
    <?= e($dokterInfo['nama']) ?>, Poli <?= e($dokterInfo['nama_poli']) ?>. Pilih tanggal kunjungan (14 hari ke depan).
  </p>
  <div class="mt-4 overflow-x-auto">
    <table class="responsive-table w-full border border-garis bg-white text-left text-sm">
      <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
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
                  <button class="bg-klinik px-3 py-1 text-xs font-medium text-white hover:bg-ink">Pesan</button>
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