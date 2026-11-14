<?php
// Data demo untuk presentasi: pasien contoh beserta riwayat kunjungan,
// rekam medis, resep, antrean hari ini, dan reservasi mendatang pada jadwal
// praktik yang sudah ada. Semua akun memakai email @contoh.klinik dan dapat
// dihapus lagi dengan hapus_data_demo() tanpa menyentuh data lain.
//
// Isinya fiktif. Hasil acak ditentukan oleh tanggal, jadi isi ulang pada
// hari yang sama menghasilkan data yang sama.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/antrean.php';

const DEMO_DOMAIN_EMAIL = '@contoh.klinik';
const DEMO_PASSWORD     = 'Demo@12345';

// [nama, jenis kelamin, tanggal lahir]
const DEMO_PASIEN = [
    ['Rina Kartika', 'P', '1994-03-12'],
    ['Agus Setiawan', 'L', '1987-08-25'],
    ['Dewi Lestari', 'P', '1999-11-02'],
    ['Bayu Prasetyo', 'L', '2001-01-19'],
    ['Siti Nurhaliza', 'P', '1979-06-07'],
    ['Hendra Gunawan', 'L', '1968-12-30'],
    ['Putri Maharani', 'P', '2015-04-14'],
    ['Fajar Nugroho', 'L', '1992-09-09'],
    ['Maya Anggraini', 'P', '1985-02-21'],
    ['Rizky Ramadhan', 'L', '2018-07-03'],
    ['Wulan Sari', 'P', '1973-10-16'],
    ['Teguh Santoso', 'L', '1990-05-28'],
    ['Indah Permatasari', 'P', '1996-08-11'],
    ['Yusuf Hidayat', 'L', '1982-04-05'],
    ['Nabila Azzahra', 'P', '2012-12-22'],
    ['Joko Susilo', 'L', '1960-03-17'],
    ['Ratna Dewi', 'P', '1988-01-30'],
    ['Arif Wibowo', 'L', '1977-11-08'],
    ['Lina Marlina', 'P', '2003-06-26'],
    ['Dimas Saputra', 'L', '2016-09-14'],
];

// Isi rekam medis contoh per jenis poli:
// [keluhan, pemeriksaan, catatan dokter, [[obat, dosis, aturan pakai], ...]]
function _demo_kasus($namaPoli)
{
    $kasus = [
        'gigi' => [
            ['Gigi geraham kanan bawah nyeri saat mengunyah sejak 3 hari.', 'Karies dalam pada gigi 46, gusi sekitar tidak bengkak.', 'Tambal sementara, kontrol 1 minggu untuk tambal permanen.',
                [['Asam mefenamat 500 mg', '3x1', 'Sesudah makan bila nyeri'], ['Obat kumur klorheksidin', '2x sehari', 'Kumur 30 detik, jangan ditelan']]],
            ['Gusi berdarah saat menyikat gigi.', 'Karang gigi pada rahang bawah depan, gusi meradang ringan.', 'Pembersihan karang gigi. Edukasi cara menyikat gigi.',
                [['Obat kumur klorheksidin', '2x sehari', 'Kumur 30 detik selama 7 hari']]],
            ['Gigi sensitif terhadap minuman dingin.', 'Abrasi ringan di leher gigi 34 dan 35.', 'Gunakan pasta gigi khusus gigi sensitif.',
                [['Pasta gigi kalium nitrat', '2x sehari', 'Sikat gigi pagi dan malam']]],
        ],
        'anak' => [
            ['Demam 2 hari, batuk dan pilek.', 'Suhu 38,2 C, tenggorokan merah, paru bersih.', 'Infeksi saluran napas atas. Banyak minum, kontrol bila demam lebih dari 3 hari.',
                [['Parasetamol sirup 120 mg/5 ml', '3x1 sendok takar', 'Bila demam'], ['Sirup ambroksol', '3x1/2 sendok takar', 'Sesudah makan']]],
            ['Diare 4 kali sejak kemarin, masih mau minum.', 'Tanda dehidrasi ringan, perut tidak kembung.', 'Lanjutkan ASI dan makan. Kembali bila muntah terus atau lemas.',
                [['Oralit', 'Tiap kali diare', 'Larutkan dalam 200 ml air'], ['Zinc 20 mg', '1x1', 'Selama 10 hari']]],
            ['Imunisasi lanjutan dan cek tumbuh kembang.', 'Berat dan tinggi badan sesuai usia.', 'Tumbuh kembang baik. Jadwalkan imunisasi berikutnya.',
                [['Vitamin A', '1 kapsul', 'Sekali minum']]],
        ],
        'dalam' => [
            ['Kontrol tekanan darah, kadang pusing di tengkuk.', 'Tekanan darah 150/95 mmHg.', 'Hipertensi derajat 1. Kurangi garam, olahraga teratur, kontrol 2 minggu.',
                [['Amlodipin 5 mg', '1x1', 'Pagi hari']]],
            ['Kontrol gula darah, sering haus.', 'Gula darah sewaktu 210 mg/dL.', 'Diabetes tipe 2 belum terkontrol. Atur pola makan, kontrol 2 minggu.',
                [['Metformin 500 mg', '2x1', 'Sesudah makan pagi dan malam']]],
            ['Nyeri ulu hati dan perut kembung.', 'Nyeri tekan epigastrium, tanda vital normal.', 'Dispepsia. Makan teratur, hindari makanan pedas dan kopi.',
                [['Omeprazol 20 mg', '1x1', 'Sebelum makan pagi'], ['Antasida sirup', '3x1 sendok makan', 'Sebelum makan']]],
        ],
        'umum' => [
            ['Batuk berdahak dan pilek sejak 4 hari.', 'Suhu 37,4 C, tenggorokan merah, paru bersih.', 'Infeksi saluran napas atas. Istirahat dan banyak minum air hangat.',
                [['Ambroksol 30 mg', '3x1', 'Sesudah makan'], ['Parasetamol 500 mg', '3x1', 'Bila demam atau nyeri']]],
            ['Demam dan badan pegal sejak 2 hari.', 'Suhu 38,1 C, tanda vital lain normal.', 'Observasi demam. Periksa darah bila demam lebih dari 3 hari.',
                [['Parasetamol 500 mg', '3x1', 'Bila demam']]],
            ['Gatal dan ruam merah di lengan.', 'Bercak kemerahan dengan bintil kecil di lengan kanan.', 'Dermatitis kontak. Hindari sabun yang memicu.',
                [['Cetirizin 10 mg', '1x1', 'Malam hari'], ['Krim hidrokortison 1%', '2x sehari', 'Oleskan tipis pada ruam']]],
            ['Sakit kepala dan sulit tidur.', 'Tekanan darah 120/80 mmHg, pemeriksaan saraf normal.', 'Sakit kepala tegang. Atur jam tidur dan istirahat.',
                [['Parasetamol 500 mg', '3x1', 'Bila nyeri']]],
        ],
    ];
    $n = strtolower($namaPoli);
    if (strpos($n, 'gigi') !== false) {
        return $kasus['gigi'];
    }
    if (strpos($n, 'anak') !== false) {
        return $kasus['anak'];
    }
    if (strpos($n, 'dalam') !== false) {
        return $kasus['dalam'];
    }
    return $kasus['umum'];
}

// Poli Anak hanya untuk pasien di bawah 15 tahun, Poli Penyakit Dalam hanya
// untuk dewasa (18+). Poli lain menerima semua usia.
function _demo_cocok_poli($namaPoli, $tanggalLahir, $tanggal)
{
    $usia = (int) date_diff(date_create($tanggalLahir), date_create($tanggal))->y;
    $n = strtolower($namaPoli);
    if (strpos($n, 'anak') !== false) {
        return $usia < 15;
    }
    if (strpos($n, 'dalam') !== false) {
        return $usia >= 18;
    }
    return true;
}

// Menghapus semua akun @contoh.klinik beserta reservasi, rekam medis, dan
// resepnya. Notifikasi dan langganan push ikut terhapus (ON DELETE CASCADE).
// Mengembalikan jumlah akun yang dihapus.
function hapus_data_demo(PDO $pdo)
{
    $suka = '%' . DEMO_DOMAIN_EMAIL;
    $pdo->beginTransaction();
    try {
        $pasien = "SELECT ps.id FROM pasien ps JOIN users u ON u.id = ps.user_id WHERE u.email LIKE ?";
        $pdo->prepare("DELETE rs FROM resep rs
                       JOIN rekam_medis rm ON rm.id = rs.rekam_medis_id
                       JOIN reservasi r ON r.id = rm.reservasi_id
                       WHERE r.pasien_id IN ($pasien)")->execute([$suka]);
        $pdo->prepare("DELETE rm FROM rekam_medis rm
                       JOIN reservasi r ON r.id = rm.reservasi_id
                       WHERE r.pasien_id IN ($pasien)")->execute([$suka]);
        $pdo->prepare("DELETE FROM reservasi WHERE pasien_id IN ($pasien)")->execute([$suka]);
        $pdo->prepare('DELETE ps FROM pasien ps JOIN users u ON u.id = ps.user_id WHERE u.email LIKE ?')->execute([$suka]);
        $st = $pdo->prepare("DELETE FROM users WHERE email LIKE ? AND role = 'user'");
        $st->execute([$suka]);
        $jumlah = $st->rowCount();
        $pdo->commit();
        return $jumlah;
    } catch (Exception $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

// Mengisi data demo relatif terhadap $hariIni (bawaan: hari ini).
// Mengembalikan ['pasien', 'reservasi', 'hari_ini', 'rekam_medis'].
// Melempar InvalidArgumentException bila belum ada jadwal praktik aktif.
function isi_data_demo(PDO $pdo, $hariIni = null, $hariMundur = 28, $hariMaju = 7)
{
    $hariIni = $hariIni === null ? date('Y-m-d') : $hariIni;

    $jadwal = $pdo->query(
        "SELECT j.id, j.hari, j.kuota, p.nama_poli
         FROM jadwal_dokter j
         JOIN dokter d ON d.id = j.dokter_id
         JOIN users u ON u.id = d.user_id AND u.status = 'aktif'
         JOIN poli p ON p.id = d.poli_id
         WHERE j.aktif = 1 AND u.email NOT LIKE '%@uji.klinik'
         ORDER BY j.jam_mulai, j.id"
    )->fetchAll();
    if (!$jadwal) {
        throw new InvalidArgumentException('Belum ada jadwal praktik aktif. Tambahkan jadwal dokter lebih dulu.');
    }

    hapus_data_demo($pdo); // isi ulang mengganti data demo lama

    mt_srand(crc32($hariIni));
    $acak = function (array $daftar) { return $daftar[mt_rand(0, count($daftar) - 1)]; };

    $insUser = $pdo->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, 'user')");
    $insPasien = $pdo->prepare('INSERT INTO pasien (user_id, no_hp, jenis_kelamin, tanggal_lahir) VALUES (?, ?, ?, ?)');
    $infoSlot = $pdo->prepare('SELECT COUNT(*) AS n, COALESCE(MAX(no_antrean), 0) AS maks FROM reservasi WHERE jadwal_id = ? AND tanggal = ?');
    $insRes = $pdo->prepare(
        'INSERT INTO reservasi (pasien_id, jadwal_id, tanggal, no_antrean, status, notif_dekat_at, notif_di_depan)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insRm = $pdo->prepare(
        'INSERT INTO rekam_medis (reservasi_id, keluhan, pemeriksaan, catatan_dokter, terkunci) VALUES (?, ?, ?, ?, 1)'
    );
    $insResep = $pdo->prepare('INSERT INTO resep (rekam_medis_id, nama_obat, dosis, aturan_pakai) VALUES (?, ?, ?, ?)');

    $hasil = ['pasien' => 0, 'reservasi' => 0, 'hari_ini' => 0, 'rekam_medis' => 0];
    $pdo->beginTransaction();
    try {
        $pw = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
        $pasienIds = [];
        $lahir = [];
        foreach (DEMO_PASIEN as $i => $p) {
            $email = strtolower(str_replace(' ', '.', $p[0])) . DEMO_DOMAIN_EMAIL;
            $insUser->execute([$p[0], $email, $pw]);
            $insPasien->execute([$pdo->lastInsertId(), '0812' . str_pad((string) (31000000 + $i * 7919), 8, '0', STR_PAD_LEFT), $p[1], $p[2]]);
            $pasienIds[] = (int) $pdo->lastInsertId();
            $lahir[(int) $pdo->lastInsertId()] = $p[2];
        }
        $hasil['pasien'] = count($pasienIds);

        $mulai = strtotime($hariIni . ' -' . (int) $hariMundur . ' days');
        $akhir = strtotime($hariIni . ' +' . (int) $hariMaju . ' days');
        for ($t = $mulai; $t <= $akhir; $t = strtotime('+1 day', $t)) {
            $tanggal = date('Y-m-d', $t);
            $hari = hari_dari_tanggal($tanggal);
            $sudahPesan = []; // satu pasien satu reservasi per tanggal

            foreach ($jadwal as $j) {
                if ($j['hari'] !== $hari) {
                    continue;
                }
                $infoSlot->execute([$j['id'], $tanggal]);
                $slot = $infoSlot->fetch();
                $sisaKuota = (int) $j['kuota'] - (int) $slot['n'];

                if ($tanggal < $hariIni) {
                    $mau = mt_rand(2, 6);
                } elseif ($tanggal === $hariIni) {
                    $mau = 8;
                } else {
                    $mau = mt_rand(1, 4);
                }
                $calon = [];
                foreach (array_diff($pasienIds, $sudahPesan) as $id) {
                    if (_demo_cocok_poli($j['nama_poli'], $lahir[$id], $tanggal)) {
                        $calon[] = $id;
                    }
                }
                shuffle($calon);
                $jumlah = max(0, min($mau, $sisaKuota, count($calon)));
                $no = (int) $slot['maks'];

                for ($k = 0; $k < $jumlah; $k++) {
                    $pasienId = $calon[$k];
                    $sudahPesan[] = $pasienId;
                    $no++;

                    if ($tanggal < $hariIni) {
                        $r = mt_rand(1, 100);
                        $status = $r <= 76 ? 'selesai' : ($r <= 88 ? 'tidak_hadir' : 'batal');
                    } elseif ($tanggal === $hariIni) {
                        // urutan antrean yang masuk akal: yang awal sudah selesai
                        $status = $k < 2 ? 'selesai' : ($k === 2 ? 'diperiksa' : ($k < 5 ? 'hadir' : 'menunggu'));
                    } else {
                        $status = 'menunggu';
                    }
                    // pasien contoh tidak perlu menerima notifikasi antrean
                    $diam = $tanggal >= $hariIni;
                    $insRes->execute([$pasienId, $j['id'], $tanggal, $no, $status,
                        $diam ? date('Y-m-d H:i:s') : null, $diam ? 0 : null]);
                    $reservasiId = (int) $pdo->lastInsertId();
                    $hasil['reservasi']++;
                    if ($tanggal === $hariIni) {
                        $hasil['hari_ini']++;
                    }

                    if ($status === 'selesai') {
                        $kasus = $acak(_demo_kasus($j['nama_poli']));
                        $insRm->execute([$reservasiId, $kasus[0], $kasus[1], $kasus[2]]);
                        $rmId = (int) $pdo->lastInsertId();
                        foreach ($kasus[3] as $obat) {
                            $insResep->execute([$rmId, $obat[0], $obat[1], $obat[2]]);
                        }
                        $hasil['rekam_medis']++;
                    }
                }
            }
        }
        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    return $hasil;
}
