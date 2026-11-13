<?php
// Web Push tanpa pustaka luar (cocok untuk PHP 7.3+): enkripsi pesan
// RFC 8291 (aes128gcm), tanda tangan VAPID RFC 8292 (ES256), dan
// penyimpanan langganan per perangkat di tabel push_langganan.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helper.php';

const WEBPUSH_TTL = 3600;        // detik; pesan antrean tidak berguna lagi setelah 1 jam
const WEBPUSH_RECORD = 4096;

// Layanan push resmi browser. Server hanya mau mengirim ke host ini agar
// formulir langganan tidak bisa dipakai untuk menyuruh server memanggil
// alamat sembarang.
const WEBPUSH_HOST_TEPAT   = ['fcm.googleapis.com', 'android.googleapis.com', 'updates.push.services.mozilla.com'];
const WEBPUSH_HOST_AKHIRAN = ['.push.apple.com', '.notify.windows.com'];

// ---------- utilitas ----------

function b64url_enkode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_dekode($teks)
{
    $hasil = base64_decode(strtr((string) $teks, '-_', '+/'), true);
    return $hasil === false ? '' : $hasil;
}

function webpush_pem_publik($titik65)
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $titik65;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function webpush_pem_privat($d32, $titik65)
{
    $der = hex2bin('30770201010420') . $d32 . hex2bin('a00a06082a8648ce3d030107a144034200') . $titik65;
    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}

function _webpush_kunci_baru()
{
    $opsi = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
    $kunci = @openssl_pkey_new($opsi);
    if (!$kunci) {
        // OpenSSL di Windows/XAMPP kadang butuh lokasi openssl.cnf
        $cnf = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
        $kunci = openssl_pkey_new($opsi + ['config' => $cnf]);
    }
    if (!$kunci) {
        throw new RuntimeException('Gagal membuat kunci EC: ' . openssl_error_string());
    }
    $d = openssl_pkey_get_details($kunci)['ec'];
    $pad = function ($b) { return str_pad($b, 32, "\0", STR_PAD_LEFT); };
    return ['kunci' => $kunci, 'publik' => "\x04" . $pad($d['x']) . $pad($d['y']), 'privat' => $pad($d['d'])];
}

// Kunci VAPID baru: ['publik' => b64url 65 byte, 'privat' => b64url 32 byte]
function webpush_buat_kunci()
{
    $k = _webpush_kunci_baru();
    return ['publik' => b64url_enkode($k['publik']), 'privat' => b64url_enkode($k['privat'])];
}

// ---------- enkripsi pesan (RFC 8291) ----------

// $salt, $asPrivat, $asPublik hanya diisi oleh test (nilai tetap RFC);
// dalam pemakaian normal dibuat acak setiap kali kirim.
function webpush_enkripsi($teks, $uaPublik, $authSecret, $salt = null, $asPrivat = null, $asPublik = null)
{
    if ($asPrivat === null) {
        $k = _webpush_kunci_baru();
        $asKunci = $k['kunci'];
        $asPublik = $k['publik'];
    } else {
        $asKunci = openssl_pkey_get_private(webpush_pem_privat($asPrivat, $asPublik));
    }
    $salt = $salt === null ? random_bytes(16) : $salt;

    $ecdh = openssl_pkey_derive(openssl_pkey_get_public(webpush_pem_publik($uaPublik)), $asKunci, 32);
    if ($ecdh === false) {
        throw new RuntimeException('ECDH gagal: ' . openssl_error_string());
    }

    $ikm   = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0" . $uaPublik . $asPublik, $authSecret);
    $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

    $tag = '';
    $sandi = openssl_encrypt($teks . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

    return $salt . pack('N', WEBPUSH_RECORD) . chr(strlen($asPublik)) . $asPublik . $sandi . $tag;
}

// ---------- VAPID (RFC 8292) ----------

function _der_int($bilangan)
{
    $bilangan = ltrim($bilangan, "\0");
    if ($bilangan === '' || ord($bilangan[0]) >= 0x80) {
        $bilangan = "\0" . $bilangan;
    }
    return "\x02" . chr(strlen($bilangan)) . $bilangan;
}

function webpush_sig_mentah_ke_der($mentah)
{
    $isi = _der_int(substr($mentah, 0, 32)) . _der_int(substr($mentah, 32, 32));
    return "\x30" . chr(strlen($isi)) . $isi;
}

function _webpush_sig_der_ke_mentah($der)
{
    $pos = 2;
    $hasil = '';
    for ($i = 0; $i < 2; $i++) {
        $pjg = ord($der[$pos + 1]);
        $hasil .= str_pad(ltrim(substr($der, $pos + 2, $pjg), "\0"), 32, "\0", STR_PAD_LEFT);
        $pos += 2 + $pjg;
    }
    return $hasil;
}

function webpush_jwt_vapid($audience, $subjek, $privatB64, $publikB64, $exp)
{
    $kepala = b64url_enkode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $isi = b64url_enkode(json_encode(['aud' => $audience, 'exp' => (int) $exp, 'sub' => $subjek], JSON_UNESCAPED_SLASHES));
    $kunci = openssl_pkey_get_private(webpush_pem_privat(b64url_dekode($privatB64), b64url_dekode($publikB64)));
    $der = '';
    if (!openssl_sign("$kepala.$isi", $der, $kunci, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Gagal menandatangani VAPID: ' . openssl_error_string());
    }
    return "$kepala.$isi." . b64url_enkode(_webpush_sig_der_ke_mentah($der));
}

// ---------- langganan ----------

function webpush_endpoint_sah($endpoint)
{
    $u = parse_url((string) $endpoint);
    if (!$u || ($u['scheme'] ?? '') !== 'https' || empty($u['host']) || isset($u['port']) || isset($u['user'])) {
        return false;
    }
    $host = strtolower($u['host']);
    if (in_array($host, WEBPUSH_HOST_TEPAT, true)) {
        return true;
    }
    foreach (WEBPUSH_HOST_AKHIRAN as $akhiran) {
        if (substr($host, -strlen($akhiran)) === $akhiran) {
            return true;
        }
    }
    return false;
}

function simpan_langganan_push(PDO $pdo, $userId, $endpoint, $p256dh, $auth, $perangkat)
{
    $kunci = b64url_dekode($p256dh);
    if (strlen($endpoint) > 1000 || !webpush_endpoint_sah($endpoint)) {
        throw new InvalidArgumentException('Alamat layanan push tidak dikenal.');
    }
    if (strlen($kunci) !== 65 || $kunci[0] !== "\x04" || strlen(b64url_dekode($auth)) !== 16) {
        throw new InvalidArgumentException('Kunci langganan tidak sah.');
    }
    $pdo->prepare(
        'INSERT INTO push_langganan (user_id, endpoint, endpoint_hash, p256dh, auth, perangkat)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh),
                                 auth = VALUES(auth), perangkat = VALUES(perangkat)'
    )->execute([(int) $userId, $endpoint, hash('sha256', $endpoint), $p256dh, $auth, mb_substr((string) $perangkat, 0, 160)]);
}

function hapus_langganan_push(PDO $pdo, $userId, $endpoint)
{
    $st = $pdo->prepare('DELETE FROM push_langganan WHERE user_id = ? AND endpoint_hash = ?');
    $st->execute([(int) $userId, hash('sha256', (string) $endpoint)]);
    return $st->rowCount();
}

function langganan_push_user(PDO $pdo, $userId)
{
    $st = $pdo->prepare('SELECT id, endpoint, p256dh, auth FROM push_langganan WHERE user_id = ? ORDER BY id');
    $st->execute([(int) $userId]);
    return $st->fetchAll();
}

// ---------- pengiriman ----------

function webpush_kunci_konfigurasi()
{
    if (isset($GLOBALS['webpush_kunci_uji'])) {
        return $GLOBALS['webpush_kunci_uji'];
    }
    return ['publik' => WEBPUSH_PUBLIC_KEY, 'privat' => WEBPUSH_PRIVATE_KEY, 'subjek' => WEBPUSH_SUBJECT];
}

function webpush_aktif()
{
    $k = webpush_kunci_konfigurasi();
    return $k['publik'] !== '' && $k['privat'] !== '' && $k['subjek'] !== '';
}

function _webpush_transport_curl($url, array $header, $body)
{
    $baris = [];
    foreach ($header as $nama => $nilai) {
        $baris[] = "$nama: $nilai";
    }
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $baris,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5,
    ]);
    $jawaban = curl_exec($c);
    $status = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
    if ($jawaban === false) {
        error_log('webpush: ' . curl_error($c));
    } elseif ($status >= 400) {
        error_log("webpush: HTTP $status " . substr((string) $jawaban, 0, 200));
    }
    curl_close($c);
    return $status;
}

// Mengirim notifikasi ke semua perangkat milik user. Perangkat yang sudah
// berhenti berlangganan (404/410) dihapus. Mengembalikan jumlah yang berhasil.
// Tidak pernah melempar: kegagalan push tidak boleh menggagalkan aksi utama.
function kirim_push_ke_user(PDO $pdo, $userId, $judul, $pesan, $tautan = null, array $kunci = null, callable $transport = null)
{
    $kunci = $kunci === null ? webpush_kunci_konfigurasi() : $kunci;
    if ($kunci['publik'] === '' || $kunci['privat'] === '' || $kunci['subjek'] === '') {
        return 0;
    }
    if ($transport === null) {
        $transport = isset($GLOBALS['webpush_transport_uji']) ? $GLOBALS['webpush_transport_uji'] : '_webpush_transport_curl';
    }

    try {
        $daftar = langganan_push_user($pdo, $userId);
    } catch (PDOException $ex) {
        error_log('webpush: ' . $ex->getMessage()); // mis. migrasi belum diimpor
        return 0;
    }

    $muatan = json_encode([
        'judul' => $judul,
        'pesan' => $pesan,
        // relatif terhadap lingkup service worker, sehingga tetap benar
        // walau situs dibuka lewat alamat lain (mis. terowongan HTTPS)
        'url'   => ltrim($tautan === null ? 'user/notifikasi.php' : $tautan, '/'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $berhasil = 0;
    foreach ($daftar as $l) {
        try {
            $u = parse_url($l['endpoint']);
            $jwt = webpush_jwt_vapid($u['scheme'] . '://' . $u['host'], $kunci['subjek'], $kunci['privat'], $kunci['publik'], time() + 12 * 3600);
            $body = webpush_enkripsi($muatan, b64url_dekode($l['p256dh']), b64url_dekode($l['auth']));
            $status = $transport($l['endpoint'], [
                'TTL'              => (string) WEBPUSH_TTL,
                'Urgency'          => 'high',
                'Content-Type'     => 'application/octet-stream',
                'Content-Encoding' => 'aes128gcm',
                'Authorization'    => "vapid t=$jwt, k={$kunci['publik']}",
            ], $body);
        } catch (Exception $ex) {
            error_log('webpush: ' . $ex->getMessage());
            continue;
        }
        if ($status >= 200 && $status < 300) {
            $berhasil++;
        } elseif ($status === 404 || $status === 410) {
            $pdo->prepare('DELETE FROM push_langganan WHERE id = ?')->execute([$l['id']]);
        }
    }
    return $berhasil;
}
