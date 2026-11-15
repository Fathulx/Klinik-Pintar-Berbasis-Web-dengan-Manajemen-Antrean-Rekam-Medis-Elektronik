<?php
// Data untuk kartu akun di menu dan halaman "Akun saya".
require_once __DIR__ . '/helper.php';

// Inisial untuk lingkaran akun: huruf awal dua kata pertama nama, tanpa
// gelar depan (dr., drg.) dan tanpa gelar belakang setelah koma.
function inisial_nama($nama)
{
    $nama = trim(explode(',', (string) $nama)[0]);
    $kata = array_values(array_filter(preg_split('/\s+/', $nama), function ($k) {
        return $k !== '' && !preg_match('/^(dr|drg|prof|ir|h|hj)\.?$/i', $k);
    }));
    if (!$kata) {
        return '?';
    }
    $hasil = mb_strtoupper(mb_substr($kata[0], 0, 1));
    if (isset($kata[1])) {
        $hasil .= mb_strtoupper(mb_substr($kata[1], 0, 1));
    }
    return $hasil;
}

// Detail akun: nama, email, peran, dan rincian sesuai peran.
// $hariIni (Y-m-d) dipakai untuk menghitung usia pasien.
function detail_akun(PDO $pdo, $userId, $hariIni = null)
{
    $hariIni = $hariIni === null ? date('Y-m-d') : $hariIni;
    $st = $pdo->prepare('SELECT id, nama, email, role FROM users WHERE id = ?');
    $st->execute([(int) $userId]);
    $u = $st->fetch();
    if (!$u) {
        return null;
    }

    $rincian = [];
    if ($u['role'] === 'user') {
        $st = $pdo->prepare('SELECT no_hp, jenis_kelamin, tanggal_lahir FROM pasien WHERE user_id = ?');
        $st->execute([$u['id']]);
        $p = $st->fetch() ?: [];
        $rincian['No. HP'] = !empty($p['no_hp']) ? $p['no_hp'] : 'Belum diisi';
        $rincian['Jenis kelamin'] = isset($p['jenis_kelamin']) && $p['jenis_kelamin'] !== null
            ? ($p['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan') : 'Belum diisi';
        $rincian['Tanggal lahir'] = !empty($p['tanggal_lahir'])
            ? date('d/m/Y', strtotime($p['tanggal_lahir'])) . ' (' . usia_tahun($p['tanggal_lahir'], $hariIni) . ' tahun)'
            : 'Belum diisi';
    } elseif ($u['role'] === 'dokter') {
        $st = $pdo->prepare(
            'SELECT p.nama_poli, d.no_str FROM dokter d JOIN poli p ON p.id = d.poli_id WHERE d.user_id = ?'
        );
        $st->execute([$u['id']]);
        $d = $st->fetch() ?: [];
        $rincian['Poli'] = isset($d['nama_poli']) ? $d['nama_poli'] : '-';
        $rincian['No. STR'] = isset($d['no_str']) ? $d['no_str'] : '-';
    }

    return [
        'nama'    => $u['nama'],
        'email'   => $u['email'],
        'peran'   => label_peran($u['role']),
        'inisial' => inisial_nama($u['nama']),
        'rincian' => $rincian,
    ];
}
