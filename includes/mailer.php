<?php
// Pengiriman email lewat SMTP (Gmail) memakai PHPMailer 6.9.1 di lib/PHPMailer.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

// Mengembalikan true jika email diterima server SMTP. Kegagalan dicatat ke
// error_log tanpa kredensial dan tidak pernah ditampilkan ke pengunjung.
function kirim_email($ke, $namaKe, $subjek, $html, $teks)
{
    if (SMTP_USER === '' || SMTP_PASS === '') {
        error_log('kirim_email: SMTP_USER/SMTP_PASS belum diisi di config.local.php');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = (int) SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->Timeout    = 15;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM !== '' ? SMTP_FROM : SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($ke, $namaKe);
        $mail->Subject = $subjek;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = $teks;

        $mail->send();
        return true;
    } catch (Exception $ex) {
        error_log('kirim_email gagal: ' . $mail->ErrorInfo);
        return false;
    }
}
