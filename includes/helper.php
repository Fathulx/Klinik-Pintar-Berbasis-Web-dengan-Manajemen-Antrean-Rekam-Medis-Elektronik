<?php
function e($teks)
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

function url($path = '')
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

function post($kunci, $trim = true)
{
    if (!isset($_POST[$kunci]) || !is_string($_POST[$kunci])) {
        return '';
    }
    return $trim ? trim($_POST[$kunci]) : $_POST[$kunci];
}

function set_flash($pesan, $tipe = 'ok')
{
    $_SESSION['flash'] = ['pesan' => $pesan, 'tipe' => $tipe];
}

function tampil_flash()
{
    if (!isset($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $kelas = $f['tipe'] === 'error'
        ? 'border-bata bg-bata/10 text-bata'
        : 'border-klinik bg-klinik/10 text-klinik';
    return '<div class="mb-4 rounded-xl border px-3 py-2.5 text-sm ' . $kelas . '">'
        . e($f['pesan']) . '</div>';
}

function pesan_field($err, $kunci)
{
    return isset($err[$kunci])
        ? '<p class="mt-1 text-xs text-bata">' . e($err[$kunci]) . '</p>'
        : '';
}

function validasi_password($pw)
{
    if (strlen($pw) < 8) {
        return 'Password minimal 8 karakter.';
    }
    if (strlen($pw) > 72) {
        return 'Password maksimal 72 karakter.';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'Password harus mengandung huruf dan angka.';
    }
    return null;
}

function buat_password_sementara()
{
    $huruf = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
    $angka = '23456789';
    $hasil = '';
    for ($i = 0; $i < 8; $i++) {
        $hasil .= $huruf[random_int(0, strlen($huruf) - 1)];
    }
    for ($i = 0; $i < 2; $i++) {
        $hasil .= $angka[random_int(0, strlen($angka) - 1)];
    }
    return str_shuffle($hasil);
}

function badge_status($status)
{
    if ($status === 'aktif') {
        $kelas = 'status-badge--success';
    } elseif ($status === 'pending') {
        $kelas = 'status-badge--warning';
    } elseif ($status === 'nonaktif') {
        $kelas = 'status-badge--danger';
    } else {
        $kelas = 'status-badge--neutral';
    }
    return '<span class="status-badge ' . $kelas . '">' . e($status) . '</span>';
}