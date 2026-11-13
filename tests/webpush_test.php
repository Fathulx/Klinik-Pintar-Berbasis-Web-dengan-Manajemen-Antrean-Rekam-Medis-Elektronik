<?php
require_once __DIR__ . '/../includes/webpush.php';
require_once __DIR__ . '/../includes/notifikasi.php';

// Contoh resmi RFC 8291 lampiran A
function _rfc8291()
{
    return [
        'teks'        => 'When I grow up, I want to be a watermelon',
        'as_public'   => 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8',
        'as_private'  => 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw',
        'ua_public'   => 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
        'ua_private'  => 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94',
        'salt'        => 'DGv6ra1nlYgDCS1FRnbzlw',
        'auth'        => 'BTBZMqHH6r4Tts7J_aSIgg',
        'hasil'       => 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN',
    ];
}

// Membuka pesan seperti yang dilakukan browser penerima (untuk uji bolak-balik).
function _buka_pesan($body, $uaPrivB64, $uaPubB64, $authB64)
{
    $salt  = substr($body, 0, 16);
    $idlen = ord($body[20]);
    $asPub = substr($body, 21, $idlen);
    $isi   = substr($body, 21 + $idlen);

    $uaPriv = openssl_pkey_get_private(webpush_pem_privat(b64url_dekode($uaPrivB64), b64url_dekode($uaPubB64)));
    $asPubKey = openssl_pkey_get_public(webpush_pem_publik($asPub));
    $ecdh = openssl_pkey_derive($asPubKey, $uaPriv, 32);

    $info = "WebPush: info\0" . b64url_dekode($uaPubB64) . $asPub;
    $ikm  = hash_hkdf('sha256', $ecdh, 32, $info, b64url_dekode($authB64));
    $cek  = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

    $tag = substr($isi, -16);
    $polos = openssl_decrypt(substr($isi, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    cek($polos !== false, 'pesan bisa dibuka');
    return rtrim(substr($polos, 0, strrpos($polos, "\x02")), "\0");
}

function test_base64url_bolak_balik()
{
    $data = random_bytes(37);
    sama($data, b64url_dekode(b64url_enkode($data)), 'bolak-balik');
    cek(strpbrk(b64url_enkode(str_repeat("\xff", 30)), '+/=') === false, 'tanpa + / =');
}

function test_enkripsi_sesuai_rfc8291()
{
    $v = _rfc8291();
    $hasil = webpush_enkripsi(
        $v['teks'],
        b64url_dekode($v['ua_public']),
        b64url_dekode($v['auth']),
        b64url_dekode($v['salt']),
        b64url_dekode($v['as_private']),
        b64url_dekode($v['as_public'])
    );
    sama($v['hasil'], b64url_enkode($hasil), 'body terenkripsi sama dengan RFC 8291');
}

function test_enkripsi_acak_bisa_dibuka_penerima()
{
    $v = _rfc8291();
    $teks = '{"judul":"Giliran Anda sudah dekat","pesan":"tinggal 2 pasien lagi"}';
    $body = webpush_enkripsi($teks, b64url_dekode($v['ua_public']), b64url_dekode($v['auth']));
    sama($teks, _buka_pesan($body, $v['ua_private'], $v['ua_public'], $v['auth']), 'isi kembali utuh');
    cek(substr($body, 0, 16) !== b64url_dekode($v['salt']), 'salt acak setiap kirim');
}

function test_buat_kunci_vapid()
{
    $k = webpush_buat_kunci();
    sama(65, strlen(b64url_dekode($k['publik'])), 'kunci publik 65 byte');
    sama("\x04", b64url_dekode($k['publik'])[0], 'titik tak terkompresi');
    sama(32, strlen(b64url_dekode($k['privat'])), 'kunci privat 32 byte');
}

function test_jwt_vapid_bisa_diverifikasi()
{
    $k = webpush_buat_kunci();
    $jwt = webpush_jwt_vapid('https://fcm.googleapis.com', 'mailto:uji@contoh.id', $k['privat'], $k['publik'], 1900000000);
    $bagian = explode('.', $jwt);
    sama(3, count($bagian), 'tiga bagian');

    $kepala = json_decode(b64url_dekode($bagian[0]), true);
    $isi    = json_decode(b64url_dekode($bagian[1]), true);
    sama(['typ' => 'JWT', 'alg' => 'ES256'], $kepala, 'header');
    sama('https://fcm.googleapis.com', $isi['aud'], 'aud');
    sama(1900000000, $isi['exp'], 'exp');
    sama('mailto:uji@contoh.id', $isi['sub'], 'sub');

    $sig = b64url_dekode($bagian[2]);
    sama(64, strlen($sig), 'tanda tangan mentah r||s 64 byte');
    $der = webpush_sig_mentah_ke_der($sig);
    $pub = openssl_pkey_get_public(webpush_pem_publik(b64url_dekode($k['publik'])));
    sama(1, openssl_verify($bagian[0] . '.' . $bagian[1], $der, $pub, OPENSSL_ALGO_SHA256), 'tanda tangan sah');
}

function test_endpoint_hanya_layanan_push_dikenal()
{
    cek(webpush_endpoint_sah('https://fcm.googleapis.com/fcm/send/abc123'), 'Chrome/Android');
    cek(webpush_endpoint_sah('https://updates.push.services.mozilla.com/wpush/v2/abc'), 'Firefox');
    cek(webpush_endpoint_sah('https://web.push.apple.com/QGx'), 'Safari/iPhone');
    cek(webpush_endpoint_sah('https://wns2-sg2p.notify.windows.com/w/?token=abc'), 'Edge');
    cek(!webpush_endpoint_sah('http://fcm.googleapis.com/fcm/send/abc'), 'tolak http');
    cek(!webpush_endpoint_sah('https://localhost/curi'), 'tolak host lain');
    cek(!webpush_endpoint_sah('https://fcm.googleapis.com.penyerang.id/x'), 'tolak akhiran palsu');
    cek(!webpush_endpoint_sah('https://penyerangpush.apple.com/x'), 'tolak subdomain palsu');
}

// ---- langganan dan pengiriman ----

function _langganan_uji()
{
    $v = _rfc8291();
    return ['p256dh' => $v['ua_public'], 'auth' => $v['auth']];
}

function _kunci_uji()
{
    static $k = null;
    if ($k === null) {
        $k = webpush_buat_kunci();
    }
    return ['publik' => $k['publik'], 'privat' => $k['privat'], 'subjek' => 'mailto:uji@contoh.id'];
}

function test_simpan_langganan_tidak_dobel_dan_pindah_pemilik()
{
    $pdo = test_pdo();
    $a = buat_user_uji($pdo, 'user');
    $b = buat_user_uji($pdo, 'user');
    $l = _langganan_uji();
    $ep = 'https://fcm.googleapis.com/fcm/send/perangkat1';

    simpan_langganan_push($pdo, $a, $ep, $l['p256dh'], $l['auth'], 'UA');
    simpan_langganan_push($pdo, $a, $ep, $l['p256dh'], $l['auth'], 'UA');
    sama(1, count(langganan_push_user($pdo, $a)), 'satu baris per perangkat');

    // perangkat yang sama dipakai login akun lain: langganan pindah
    simpan_langganan_push($pdo, $b, $ep, $l['p256dh'], $l['auth'], 'UA');
    sama(0, count(langganan_push_user($pdo, $a)), 'lepas dari A');
    sama(1, count(langganan_push_user($pdo, $b)), 'milik B');
}

function test_simpan_langganan_menolak_data_tidak_sah()
{
    $pdo = test_pdo();
    $a = buat_user_uji($pdo, 'user');
    $l = _langganan_uji();
    foreach ([
        ['https://penyerang.id/x', $l['p256dh'], $l['auth']],
        ['https://fcm.googleapis.com/fcm/send/x', 'pendek', $l['auth']],
        ['https://fcm.googleapis.com/fcm/send/x', $l['p256dh'], 'xx'],
    ] as $i => $data) {
        try {
            simpan_langganan_push($pdo, $a, $data[0], $data[1], $data[2], 'UA');
            cek(false, "data $i seharusnya ditolak");
        } catch (InvalidArgumentException $ex) {
            cek(true, 'ditolak');
        }
    }
    sama(0, count(langganan_push_user($pdo, $a)), 'tidak ada yang tersimpan');
}

function test_hapus_langganan_hanya_milik_sendiri()
{
    $pdo = test_pdo();
    $a = buat_user_uji($pdo, 'user');
    $b = buat_user_uji($pdo, 'user');
    $l = _langganan_uji();
    $ep = 'https://fcm.googleapis.com/fcm/send/milikA';
    simpan_langganan_push($pdo, $a, $ep, $l['p256dh'], $l['auth'], 'UA');
    sama(0, hapus_langganan_push($pdo, $b, $ep), 'B tidak bisa menghapus milik A');
    sama(1, hapus_langganan_push($pdo, $a, $ep), 'A menghapus miliknya');
}

function test_kirim_push_ke_semua_perangkat_dan_buang_yang_kedaluwarsa()
{
    $pdo = test_pdo();
    $a = buat_user_uji($pdo, 'user');
    $l = _langganan_uji();
    simpan_langganan_push($pdo, $a, 'https://fcm.googleapis.com/fcm/send/hp', $l['p256dh'], $l['auth'], 'HP');
    simpan_langganan_push($pdo, $a, 'https://fcm.googleapis.com/fcm/send/lama', $l['p256dh'], $l['auth'], 'Lama');

    $terkirim = [];
    $transport = function ($url, array $header, $body) use (&$terkirim) {
        $terkirim[] = ['url' => $url, 'header' => $header, 'body' => $body];
        return substr($url, -4) === 'lama' ? 410 : 201;
    };
    $berhasil = kirim_push_ke_user($pdo, $a, 'Giliran Anda sudah dekat', 'tinggal 2 pasien lagi',
        'user/antrean.php', _kunci_uji(), $transport);

    sama(1, $berhasil, 'satu perangkat berhasil');
    sama(2, count($terkirim), 'dicoba ke dua perangkat');
    sama(1, count(langganan_push_user($pdo, $a)), 'perangkat 410 dihapus');

    $h = $terkirim[0]['header'];
    sama('aes128gcm', $h['Content-Encoding'], 'content-encoding');
    cek(strpos($h['Authorization'], 'vapid t=') === 0 && strpos($h['Authorization'], ', k=' . _kunci_uji()['publik']) !== false, 'header vapid');
    cek((int) $h['TTL'] > 0, 'TTL');

    $isi = json_decode(_buka_pesan($terkirim[0]['body'], _rfc8291()['ua_private'], $l['p256dh'], $l['auth']), true);
    sama('Giliran Anda sudah dekat', $isi['judul'], 'judul di payload');
    sama('tinggal 2 pasien lagi', $isi['pesan'], 'pesan di payload');
    cek(substr($isi['url'], -strlen('user/antrean.php')) === 'user/antrean.php', 'url tujuan');
}

function test_kirim_push_tanpa_kunci_tidak_melakukan_apa_pun()
{
    $pdo = test_pdo();
    $a = buat_user_uji($pdo, 'user');
    $l = _langganan_uji();
    simpan_langganan_push($pdo, $a, 'https://fcm.googleapis.com/fcm/send/hp', $l['p256dh'], $l['auth'], 'HP');
    $dipanggil = 0;
    $transport = function () use (&$dipanggil) { $dipanggil++; return 201; };
    sama(0, kirim_push_ke_user($pdo, $a, 'J', 'P', null, ['publik' => '', 'privat' => '', 'subjek' => ''], $transport), 'nol');
    sama(0, $dipanggil, 'transport tidak dipanggil');
}

function test_notif_antrean_juga_dikirim_sebagai_push()
{
    $pdo = test_pdo();
    $d = buat_reservasi_uji($pdo, '2026-11-12', '09:00', ['menunggu', 'menunggu']);
    $l = _langganan_uji();
    simpan_langganan_push($pdo, $d['reservasi'][2]['user_id'], 'https://fcm.googleapis.com/fcm/send/hp2',
        $l['p256dh'], $l['auth'], 'HP');

    $terkirim = [];
    $GLOBALS['webpush_transport_uji'] = function ($url) use (&$terkirim) { $terkirim[] = $url; return 201; };
    $GLOBALS['webpush_kunci_uji'] = _kunci_uji();
    try {
        sama(2, proses_notif_antrean_dekat($pdo, '2026-11-12', '09:00'), 'dua notifikasi');
    } finally {
        unset($GLOBALS['webpush_transport_uji'], $GLOBALS['webpush_kunci_uji']);
    }
    sama(['https://fcm.googleapis.com/fcm/send/hp2'], $terkirim, 'push hanya ke perangkat pasien nomor 2');
}

function test_tabel_push_langganan_ada()
{
    $st = test_pdo()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $st->execute([TEST_DB_NAME, 'push_langganan']);
    sama(1, (int) $st->fetchColumn(), 'tabel push_langganan');
}
