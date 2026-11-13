<?php
// Menjalankan semua test: php tests/run.php [filter-nama-file]
require __DIR__ . '/bootstrap.php';

$filter = isset($argv[1]) ? $argv[1] : '';
$gagal = 0;
$total = 0;

foreach (glob(__DIR__ . '/*_test.php') as $file) {
    if ($filter !== '' && strpos(basename($file), $filter) === false) {
        continue;
    }
    $sebelum = get_defined_functions()['user'];
    require $file;
    $baru = array_diff(get_defined_functions()['user'], $sebelum);

    foreach ($baru as $fungsi) {
        if (strpos($fungsi, 'test_') !== 0) {
            continue;
        }
        $total++;
        try {
            test_db_baru();
            $fungsi();
            echo "OK     " . basename($file) . " :: $fungsi\n";
        } catch (Throwable $ex) {
            $gagal++;
            echo "GAGAL  " . basename($file) . " :: $fungsi\n       "
                . get_class($ex) . ': ' . $ex->getMessage() . "\n";
        }
    }
}

// test JavaScript (butuh Node.js; dilewati bila tidak tersedia)
if ($filter === '' || strpos('js', $filter) !== false) {
    exec('node --version 2>&1', $abaikan, $adaNode);
    foreach (glob(__DIR__ . '/js/*_test.js') as $fileJs) {
        $namaJs = 'js/' . basename($fileJs);
        if ($adaNode !== 0) {
            echo "LEWATI $namaJs (Node.js tidak ditemukan)\n";
            continue;
        }
        $total++;
        $keluaranJs = [];
        exec('node ' . escapeshellarg($fileJs) . ' 2>&1', $keluaranJs, $kodeJs);
        if ($kodeJs === 0) {
            echo "OK     $namaJs\n";
        } else {
            $gagal++;
            echo "GAGAL  $namaJs\n       " . implode("\n       ", $keluaranJs) . "\n";
        }
    }
}

echo "\n" . ($total - $gagal) . "/$total lulus\n";
exit($gagal > 0 || $total === 0 ? 1 : 0);
