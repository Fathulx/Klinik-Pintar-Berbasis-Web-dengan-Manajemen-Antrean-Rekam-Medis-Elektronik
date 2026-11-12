<?php
require_once __DIR__ . '/../includes/mailer.php';

function test_tanpa_kredensial_mengembalikan_false()
{
    if (SMTP_USER !== '') {
        echo "LEWATI (config.local.php berisi kredensial SMTP)\n";
        return;
    }
    $logLama = ini_set('error_log', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'klinik_uji_mailer.log');
    $hasil = kirim_email('pasien@contoh.test', 'Pasien', 'Subjek', '<p>Isi</p>', 'Isi');
    ini_set('error_log', $logLama);
    sama(false, $hasil, 'tanpa kredensial tidak mengirim');
}

function test_url_absolut()
{
    sama(rtrim(APP_URL, '/') . '/auth/x.php', url_absolut('auth/x.php'), 'url absolut');
    sama(rtrim(APP_URL, '/') . '/auth/x.php', url_absolut('/auth/x.php'), 'garis miring di depan');
}
