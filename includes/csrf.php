<?php
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify()
{
    $kirim = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : '';
    if (!hash_equals(csrf_token(), $kirim)) {
        http_response_code(403);
        exit('Permintaan tidak valid (token CSRF salah). Muat ulang halaman lalu coba lagi.');
    }
}