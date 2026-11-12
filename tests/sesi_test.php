<?php
// Menjalankan includes/session.php di proses PHP terpisah, karena sesi
// hanya bisa dimulai sekali per proses.
function _last_activity_setelah_require($tanpaPerpanjang)
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'klinik_sesi_uji';
    if (!is_dir($dir)) {
        mkdir($dir);
    }
    $skrip = $dir . DIRECTORY_SEPARATOR . 'anak.php';
    $session = var_export(realpath(__DIR__ . '/../includes/session.php'), true);
    $konstanta = $tanpaPerpanjang ? "define('SESI_TANPA_PERPANJANG', true);" : '';
    file_put_contents($skrip, "<?php
ini_set('session.save_path', " . var_export($dir, true) . ");
session_name('KLINIKSESS');
session_id('uji' . bin2hex(random_bytes(8)));
session_start();
\$_SESSION['last_activity'] = time() - 100;
session_write_close();
$konstanta
require $session;
echo \$_SESSION['last_activity'];
");
    $keluaran = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($skrip) . ' 2>&1');
    return [(int) trim($keluaran), $keluaran];
}

function test_polling_tidak_memperpanjang_sesi()
{
    $awal = time() - 100;
    list($nilai, $mentah) = _last_activity_setelah_require(true);
    cek(abs($nilai - $awal) <= 2, 'last_activity tidak boleh diperbarui: ' . $mentah);
}

function test_halaman_biasa_memperpanjang_sesi()
{
    list($nilai, $mentah) = _last_activity_setelah_require(false);
    cek(abs($nilai - time()) <= 2, 'last_activity harus diperbarui: ' . $mentah);
}
