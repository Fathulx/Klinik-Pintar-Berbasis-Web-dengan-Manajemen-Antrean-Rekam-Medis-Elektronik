<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/antrean.php';

$user = require_role('dokter');
$pdo  = db();

$dokterId = dokter_id_dari_user($pdo, $user['id']);
if (!$dokterId) {
    http_response_code(500);
    exit('Data dokter tidak ditemukan. Hubungi admin.');
}

$id = isset($_GET['id']) && is_string($_GET['id']) ? (int) $_GET['id'] : 0;

// Dokter hanya dapat membuka reservasi pada jadwal miliknya sendiri.
$st = $pdo->prepare(
    'SELECT r.id, r.tanggal, r.no_antrean, r.status, p.nama_poli, up.nama AS pasien,
            ps.jenis_kelamin, ps.tanggal_lahir, ps.no_hp
     FROM reservasi r
     JOIN jadwal_dokter j ON j.id = r.jadwal_id
     JOIN dokter d ON d.id = j.dokter_id
     JOIN poli p ON p.id = d.poli_id
     JOIN pasien ps ON ps.id = r.pasien_id
     JOIN users up ON up.id = ps.user_id
     WHERE r.id = ? AND j.dokter_id = ?'
);
$st->execute([$id, $dokterId]);
$r = $st->fetch();

if (!$r) {
    set_flash('Data pemeriksaan tidak ditemukan.', 'error');
    redirect('dokter/antrean.php');
}
if (!in_array($r['status'], ['diperiksa', 'selesai'], true)) {
    set_flash('Pasien ini belum dipanggil.', 'error');
    redirect('dokter/antrean.php');
}
$bisaUbah = ($r['status'] === 'diperiksa');
if (!$bisaUbah && $_SERVER['REQUEST_METHOD'] === 'GET') {
    catat_log('lihat_rekam_medis', 'reservasi ' . $id);
}

$st = $pdo->prepare('SELECT id, keluhan, pemeriksaan, catatan_dokter FROM rekam_medis WHERE reservasi_id = ?');
$st->execute([$id]);
$rm = $st->fetch();
$form = $rm ? $rm : ['keluhan' => '', 'pemeriksaan' => '', 'catatan_dokter' => ''];

$resepForm = [];
if ($rm) {
    $q = $pdo->prepare('SELECT nama_obat, dosis, aturan_pakai FROM resep WHERE rekam_medis_id = ? ORDER BY id');
    $q->execute([$rm['id']]);
    foreach ($q->fetchAll() as $o) {
        $resepForm[] = ['nama' => $o['nama_obat'], 'dosis' => $o['dosis'], 'aturan' => $o['aturan_pakai']];
    }
}
$JUMLAH_RESEP = 5;
while (count($resepForm) < $JUMLAH_RESEP) {
    $resepForm[] = ['nama' => '', 'dosis' => '', 'aturan' => ''];
}

$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$bisaUbah) {
        set_flash('Rekam medis sudah dikunci dan tidak dapat diubah.', 'error');
        redirect('dokter/periksa.php?id=' . $id);
    }
    $aksi = post('aksi');

    $form = [
        'keluhan'        => post('keluhan'),
        'pemeriksaan'    => post('pemeriksaan'),
        'catatan_dokter' => post('catatan_dokter'),
    ];

    $obatPost = isset($_POST['obat']) && is_array($_POST['obat']) ? $_POST['obat'] : [];
    $ambil = function ($baris, $kunci) {
        return isset($baris[$kunci]) && is_string($baris[$kunci]) ? trim($baris[$kunci]) : '';
    };
    $resepForm = [];
    $resepSah  = [];
    for ($i = 0; $i < $JUMLAH_RESEP; $i++) {
        $b = isset($obatPost[$i]) && is_array($obatPost[$i]) ? $obatPost[$i] : [];
        $baris = ['nama' => $ambil($b, 'nama'), 'dosis' => $ambil($b, 'dosis'), 'aturan' => $ambil($b, 'aturan')];
        $resepForm[] = $baris;
        if ($baris['nama'] === '' && $baris['dosis'] === '' && $baris['aturan'] === '') {
            continue;
        }
        if ($baris['nama'] === '' || mb_strlen($baris['nama']) > 100
            || mb_strlen($baris['dosis']) > 50 || mb_strlen($baris['aturan']) > 120) {
            $err['resep'] = 'Setiap baris resep harus berisi nama obat (maks. 100), dosis (maks. 50), dan aturan pakai (maks. 120).';
        } else {
            $resepSah[] = $baris;
        }
    }

    $lenKeluhan = mb_strlen($form['keluhan']);
    if ($lenKeluhan < 3 || $lenKeluhan > 2000) {
        $err['keluhan'] = 'Keluhan wajib diisi (3 sampai 2000 karakter).';
    }
    if (mb_strlen($form['pemeriksaan']) > 3000) {
        $err['pemeriksaan'] = 'Hasil pemeriksaan maksimal 3000 karakter.';
    }
    if (mb_strlen($form['catatan_dokter']) > 2000) {
        $err['catatan'] = 'Catatan maksimal 2000 karakter.';
    }
    if ($aksi === 'selesai' && $form['pemeriksaan'] === '' && !isset($err['pemeriksaan'])) {
        $err['pemeriksaan'] = 'Hasil pemeriksaan wajib diisi sebelum rekam medis dikunci.';
    }

    if (!$err && ($aksi === 'simpan' || $aksi === 'selesai')) {
        try {
            $pdo->beginTransaction();
            $cek = $pdo->prepare('SELECT status FROM reservasi WHERE id = ? FOR UPDATE');
            $cek->execute([$id]);
            if ($cek->fetchColumn() !== 'diperiksa') {
                $pdo->rollBack();
                set_flash('Status pemeriksaan sudah berubah. Muat ulang halaman.', 'error');
                redirect('dokter/antrean.php');
            }

            $pem = $form['pemeriksaan'] === '' ? null : $form['pemeriksaan'];
            $cat = $form['catatan_dokter'] === '' ? null : $form['catatan_dokter'];

            $ada = $pdo->prepare('SELECT id FROM rekam_medis WHERE reservasi_id = ? FOR UPDATE');
            $ada->execute([$id]);
            $rmId = $ada->fetchColumn();
            if ($rmId) {
                $pdo->prepare('UPDATE rekam_medis SET keluhan = ?, pemeriksaan = ?, catatan_dokter = ? WHERE id = ?')
                    ->execute([$form['keluhan'], $pem, $cat, $rmId]);
            } else {
                $pdo->prepare('INSERT INTO rekam_medis (reservasi_id, keluhan, pemeriksaan, catatan_dokter) VALUES (?, ?, ?, ?)')
                    ->execute([$id, $form['keluhan'], $pem, $cat]);
                $rmId = (int) $pdo->lastInsertId();
            }

            $pdo->prepare('DELETE FROM resep WHERE rekam_medis_id = ?')->execute([$rmId]);
            $ins = $pdo->prepare('INSERT INTO resep (rekam_medis_id, nama_obat, dosis, aturan_pakai) VALUES (?, ?, ?, ?)');
            foreach ($resepSah as $o) {
                $ins->execute([$rmId, $o['nama'], $o['dosis'] === '' ? null : $o['dosis'], $o['aturan'] === '' ? null : $o['aturan']]);
            }

            if ($aksi === 'selesai') {
                $pdo->prepare('UPDATE rekam_medis SET terkunci = 1 WHERE id = ?')->execute([$rmId]);
                $pdo->prepare("UPDATE reservasi SET status = 'selesai' WHERE id = ?")->execute([$id]);
            }
            $pdo->commit();

            // log hanya mencatat aksi, bukan isi medis
            if ($aksi === 'selesai') {
                catat_log('selesaikan_pemeriksaan', 'reservasi ' . $id);
                set_flash('Pemeriksaan selesai dan rekam medis dikunci.');
                redirect('dokter/antrean.php');
            }
            catat_log('simpan_rekam_medis', 'reservasi ' . $id);
            set_flash('Rekam medis disimpan sementara.');
            redirect('dokter/periksa.php?id=' . $id);
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $err['umum'] = 'Terjadi kesalahan saat menyimpan. Silakan coba lagi.';
        }
    }
}

$kelasArea  = 'mt-1 w-full border border-garis bg-white px-3 py-2 text-sm focus:border-klinik focus:outline-none focus:ring-1 focus:ring-klinik';
$kelasBaris = 'w-full border border-garis bg-white px-2 py-1.5 text-sm focus:border-klinik focus:outline-none';
$judul = 'Pemeriksaan';
$aktif = 'dokter/antrean.php';
require __DIR__ . '/../includes/panel_start.php';
?>
<a class="action-button" href="<?= e(url('dokter/antrean.php')) ?>">Kembali ke antrean</a>

<div class="mt-4 flex flex-wrap items-start gap-6 border border-garis bg-white p-5">
  <div>
    <p class="font-mono text-[11px] uppercase tracking-widest text-ink/60">Nomor antrean</p>
    <p class="font-mono text-4xl font-semibold text-bata"><?= e(kode_antrean($r['nama_poli'], $r['no_antrean'])) ?></p>
  </div>
  <div>
    <p class="font-serif text-2xl font-bold"><?= e($r['pasien']) ?></p>
    <p class="text-sm text-ink/70">
      <?= e(tanggal_indonesia($r['tanggal'])) ?> &middot; Poli <?= e($r['nama_poli']) ?>
    </p>
    <p class="mt-1 text-xs text-ink/60">
      <?= $r['jenis_kelamin'] ? ($r['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan') : '' ?>
      <?= $r['tanggal_lahir'] ? ' &middot; Lahir ' . e($r['tanggal_lahir']) : '' ?>
      <?= $r['no_hp'] ? ' &middot; ' . e($r['no_hp']) : '' ?>
    </p>
  </div>
  <div class="ml-auto"><?= badge_reservasi($r['status']) ?></div>
</div>

<?php if (isset($err['umum'])): ?>
  <div class="mt-4 border border-bata bg-bata/10 px-3 py-2 text-sm text-bata"><?= e($err['umum']) ?></div>
<?php endif; ?>

<?php if ($bisaUbah): ?>
<form method="post" action="<?= e(url('dokter/periksa.php?id=' . $id)) ?>" class="mt-6 space-y-6" novalidate>
  <?= csrf_field() ?>
  <div>
    <label class="block text-sm font-medium" for="keluhan">Keluhan pasien</label>
    <textarea id="keluhan" name="keluhan" rows="3" class="<?= $kelasArea ?>"><?= e($form['keluhan']) ?></textarea>
    <?= pesan_field($err, 'keluhan') ?>
  </div>
  <div>
    <label class="block text-sm font-medium" for="pemeriksaan">Hasil pemeriksaan</label>
    <textarea id="pemeriksaan" name="pemeriksaan" rows="4" class="<?= $kelasArea ?>"><?= e($form['pemeriksaan']) ?></textarea>
    <?= pesan_field($err, 'pemeriksaan') ?>
  </div>
  <div>
    <label class="block text-sm font-medium" for="catatan_dokter">Catatan dokter <span class="text-ink/50">(opsional)</span></label>
    <textarea id="catatan_dokter" name="catatan_dokter" rows="3" class="<?= $kelasArea ?>"><?= e($form['catatan_dokter']) ?></textarea>
    <?= pesan_field($err, 'catatan') ?>
  </div>

  <div>
    <p class="text-sm font-medium">Resep <span class="text-ink/50">(maksimal <?= $JUMLAH_RESEP ?> obat)</span></p>
    <div class="mt-2 space-y-2">
      <?php foreach ($resepForm as $i => $o): ?>
        <div class="grid gap-2 sm:grid-cols-3">
          <input name="obat[<?= $i ?>][nama]" value="<?= e($o['nama']) ?>" placeholder="Nama obat" class="<?= $kelasBaris ?>">
          <input name="obat[<?= $i ?>][dosis]" value="<?= e($o['dosis']) ?>" placeholder="Dosis" class="<?= $kelasBaris ?>">
          <input name="obat[<?= $i ?>][aturan]" value="<?= e($o['aturan']) ?>" placeholder="Aturan pakai" class="<?= $kelasBaris ?>">
        </div>
      <?php endforeach; ?>
    </div>
    <?= pesan_field($err, 'resep') ?>
  </div>

  <div class="flex flex-wrap items-center gap-3">
    <button name="aksi" value="simpan" class="px-4 py-2 text-sm">Simpan sementara</button>
    <button name="aksi" value="selesai"
            onclick="return confirm('Setelah dikunci, rekam medis tidak dapat diubah. Lanjutkan?')"
            class="bg-klinik px-4 py-2 text-sm font-medium text-white hover:bg-ink">Selesaikan dan kunci</button>
  </div>
</form>

<?php else: ?>
<div class="mt-6 space-y-6">
  <p class="text-xs text-ink/60">Rekam medis ini sudah dikunci dan hanya dapat dibaca.</p>
  <?php
  $bagian = ['Keluhan pasien' => $form['keluhan'], 'Hasil pemeriksaan' => $form['pemeriksaan'], 'Catatan dokter' => $form['catatan_dokter']];
  foreach ($bagian as $judulBagian => $isi): ?>
    <div>
      <p class="font-mono text-[11px] uppercase tracking-widest text-ink/60"><?= e($judulBagian) ?></p>
      <p class="mt-1 whitespace-pre-line border border-garis bg-white px-3 py-2 text-sm"><?= e($isi !== null && $isi !== '' ? $isi : '-') ?></p>
    </div>
  <?php endforeach; ?>
  <div>
    <p class="font-mono text-[11px] uppercase tracking-widest text-ink/60">Resep</p>
    <?php $adaResep = false; ?>
    <table class="responsive-table mt-1 w-full border border-garis bg-white text-left text-sm">
      <thead class="bg-paper font-mono text-[11px] uppercase tracking-widest text-ink/60">
        <tr><th class="px-3 py-2">Obat</th><th class="px-3 py-2">Dosis</th><th class="px-3 py-2">Aturan pakai</th></tr>
      </thead>
      <tbody>
        <?php foreach ($resepForm as $o): if ($o['nama'] === '') { continue; } $adaResep = true; ?>
          <tr class="border-t border-garis">
            <td data-label="Obat" class="px-3 py-2"><?= e($o['nama']) ?></td>
            <td data-label="Dosis" class="px-3 py-2"><?= e($o['dosis']) ?></td>
            <td data-label="Aturan pakai" class="px-3 py-2"><?= e($o['aturan']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$adaResep): ?>
          <tr><td colspan="3" class="px-3 py-3 text-ink/60">Tidak ada resep.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/panel_end.php'; ?>