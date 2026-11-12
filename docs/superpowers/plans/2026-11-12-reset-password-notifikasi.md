# Reset Password Email & Notifikasi Antrean — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambah notifikasi dalam aplikasi saat antrean pasien tinggal ≤ N, reset password pasien via email Gmail, serta reset antar Super Admin dan skrip CLI darurat.

**Architecture:** Logika bisnis ditaruh di file `includes/*.php` berupa fungsi yang menerima `$pdo` (pola yang sudah dipakai `includes/antrean.php`), sehingga bisa diuji terhadap database uji terpisah. Halaman hanya memanggil fungsi, mencatat log, dan merender. Skema diubah lewat file migrasi aditif yang aman dijalankan ulang.

**Tech Stack:** PHP 7.3 (harus juga jalan di PHP 8.x), PDO MySQL, MariaDB 10.1, Tailwind CDN yang sudah dipakai, JavaScript biasa, PHPMailer 6.9.1 disertakan manual.

**Spec:** `docs/superpowers/specs/2026-11-12-reset-password-notifikasi-design.md`

## Global Constraints

- Tidak boleh memakai sintaks atau fungsi PHP 8 (`match`, `str_contains`, named args, nullsafe `?->`, typed properties, `mixed`). Tidak boleh memakai fungsi yang dihapus di PHP 8.
- Semua query memakai prepared statement. Semua output HTML lewat `e()`. Semua form POST memakai `csrf_field()` / `csrf_verify()`.
- Migrasi hanya `CREATE TABLE IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS`, tanpa `DROP`, `TRUNCATE`, atau `UPDATE` data.
- Test **tidak pernah** menyentuh database `DB_NAME`. Database uji: `klinik_pintar_test`.
- Kredensial SMTP hanya di `config/config.local.php`, tidak pernah di-commit.
- Teks antarmuka berbahasa Indonesia, gaya mengikuti halaman yang ada (kelas Tailwind `klinik`, `bata`, `garis`, `paper`, `ink`).
- Nilai: `NOTIF_ANTREAN_SISA` = 3, `NOTIF_MENIT_SEBELUM_PRAKTIK` = 60, token reset 60 menit, batas 3 permintaan / 15 menit per akun dan 5 / 15 menit per IP, polling 15 detik.

## Review Focus

- Polling notifikasi tiap 15 detik tidak boleh membuat sesi tidak pernah habis → Task 4 menguji bahwa `last_activity` tidak diperbarui oleh endpoint polling.
- Dua permintaan bersamaan tidak boleh membuat dua notifikasi untuk reservasi yang sama → Task 3 menguji pemanggilan `proses_notif_antrean_dekat` dua kali hanya menghasilkan satu notifikasi.
- Reservasi tanggal lain (besok) dengan posisi kecil tidak boleh dinotifikasi hari ini → Task 3 menguji itu.
- Token reset yang sudah dipakai, kedaluwarsa, atau milik akun staf/nonaktif harus ditolak → Task 6 menguji ketiganya.
- `tools/reset_demo.php` mengosongkan `users` dengan FK dimatikan; tabel baru harus ikut dikosongkan agar tidak ada baris yatim → Task 1 menambahkan dan memverifikasi.

---

## File Structure

| File | Tanggung jawab |
|---|---|
| `database/migrations/2026-11-12_notifikasi_reset.sql` (baru) | Migrasi aditif untuk DB yang sudah berisi data |
| `database/schema.sql` (ubah) | Skema lengkap untuk instalasi baru |
| `tests/bootstrap.php`, `tests/run.php` (baru) | Runner test tanpa framework, DB uji terpisah |
| `includes/notifikasi.php` (baru) | CRUD notifikasi + logika "antrean dekat" |
| `user/notifikasi.php`, `user/notifikasi_cek.php` (baru) | Halaman daftar notifikasi & endpoint JSON polling |
| `assets/notifikasi.js` (baru) | Polling, badge, toast, bunyi, getar, update posisi |
| `lib/PHPMailer/*` (baru), `includes/mailer.php` (baru) | Pengiriman email SMTP |
| `includes/reset_password.php` (baru) | Token reset: minta, validasi, pakai |
| `auth/lupa_password.php`, `auth/reset_password.php` (baru) | Halaman lupa & reset password |
| `includes/akun.php` (baru) | `atur_password_sementara()` dipakai halaman staf & CLI |
| `tools/reset_superadmin.php` (baru) | Skrip CLI darurat Super Admin |

---

### Task 1: Migrasi skema & runner test

**Files:**
- Create: `database/migrations/2026-11-12_notifikasi_reset.sql`, `tests/bootstrap.php`, `tests/run.php`, `tests/skema_test.php`
- Modify: `database/schema.sql` (tambah di akhir), `tools/reset_demo.php:7-9`, `.gitignore`

**Interfaces:**
- Produces: tabel `notifikasi(id INT UNSIGNED PK AI, user_id INT UNSIGNED NOT NULL FK users ON DELETE CASCADE, judul VARCHAR(120) NOT NULL, pesan VARCHAR(255) NOT NULL, tautan VARCHAR(255) NULL, dibaca_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY (user_id, dibaca_at))`; tabel `password_resets(id INT UNSIGNED PK AI, user_id INT UNSIGNED NOT NULL FK users ON DELETE CASCADE, token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, used_at DATETIME NULL, ip_address VARCHAR(45) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY (user_id, created_at))`; kolom `reservasi.notif_dekat_at DATETIME NULL`. Semua InnoDB utf8mb4_unicode_ci seperti tabel lain.
- Produces (test): `tests/bootstrap.php` menyediakan `test_pdo(): PDO` (DB `klinik_pintar_test` dibuat ulang dari `schema.sql` lalu migrasi diterapkan **dua kali**), `cek($kondisi, $pesan)`, `sama($harap, $nyata, $pesan)`, `buat_user_uji(PDO $pdo, $role, $status = 'aktif'): int`, `buat_reservasi_uji(PDO $pdo, $tanggal, $jamMulai, array $statusUrut): array` (membuat poli, dokter, jadwal, dan satu reservasi per status dengan `no_antrean` 1..n; mengembalikan `['jadwal_id' => int, 'reservasi' => [no => ['id' => int, 'user_id' => int]]]`). Setiap file `tests/*_test.php` berisi fungsi `test_*()`; `run.php` memanggil semuanya dengan DB segar per file, mencetak `OK`/`GAGAL` per test, exit code 1 jika ada yang gagal.

- [ ] **Step 1:** Tulis `tests/bootstrap.php` dan `tests/run.php`. Bootstrap me-`require` `config/config.php` untuk `DB_HOST/DB_USER/DB_PASS`, berhenti dengan pesan jika `DB_NAME === 'klinik_pintar_test'`, lalu `DROP DATABASE IF EXISTS klinik_pintar_test` dan buat ulang. Jalankan isi `schema.sql` via `$pdo->exec()`, lalu isi migrasi dua kali.
- [ ] **Step 2:** Tulis `tests/skema_test.php`: `test_tabel_baru_ada()` mengecek `notifikasi` dan `password_resets` ada di `information_schema.tables`; `test_kolom_notif_dekat_ada()` mengecek `reservasi.notif_dekat_at` ada dan nullable.
- [ ] **Step 3:** Jalankan `D:\XAMPP\php\php.exe tests\run.php`. Expected: GAGAL (file migrasi belum ada).
- [ ] **Step 4:** Tulis migrasi (`CREATE TABLE IF NOT EXISTS` ×2, `ALTER TABLE reservasi ADD COLUMN IF NOT EXISTS notif_dekat_at DATETIME DEFAULT NULL`) dengan komentar di atas: "Backup database lewat phpMyAdmin > Export sebelum mengimpor file ini." Salin definisi dua tabel dan kolom ke `schema.sql`.
  Catatan di komentar migrasi: `ADD COLUMN IF NOT EXISTS` didukung MariaDB, tetapi **tidak** oleh MySQL; di server MySQL jalankan baris ALTER tanpa `IF NOT EXISTS`, sekali saja.
- [ ] **Step 5:** Tambah `'notifikasi', 'password_resets'` di depan daftar TRUNCATE `tools/reset_demo.php`. Buat `tests/.htaccess` berisi `Require all denied` (seperti `includes/.htaccess`).
- [ ] **Step 6:** Jalankan `D:\XAMPP\php\php.exe tests\run.php`. Expected: semua OK, exit 0.
- [ ] **Step 7:** Commit `feat: tambah migrasi notifikasi & reset password serta runner test`.

### Task 2: Pustaka notifikasi

**Files:**
- Create: `includes/notifikasi.php`, `tests/notifikasi_test.php`

**Interfaces:**
- Produces:
  - `buat_notifikasi(PDO $pdo, $userId, $judul, $pesan, $tautan = null): int` → id baru
  - `jumlah_notif_belum_dibaca(PDO $pdo, $userId): int`
  - `daftar_notifikasi(PDO $pdo, $userId, $setelahId = 0, $batas = 20): array` → baris `id, judul, pesan, tautan, dibaca_at, created_at`, terbaru dulu, hanya `id > $setelahId`
  - `tandai_notif_dibaca(PDO $pdo, $userId, $id = null): int` → jumlah baris; `null` = semua milik user

- [ ] **Step 1:** Tulis test: `test_buat_dan_hitung()` (2 dibuat → jumlah 2), `test_tandai_satu_dan_semua()` (tandai satu → 1; tandai semua → 0), `test_tidak_bisa_tandai_milik_orang_lain()` (user B menandai id milik A → 0 baris, jumlah A tetap), `test_daftar_setelah_id()` (hanya id lebih besar yang kembali).
- [ ] **Step 2:** Jalankan runner. Expected: GAGAL, fungsi belum ada.
- [ ] **Step 3:** Implementasi keempat fungsi. `tandai_notif_dibaca` selalu menyaring `user_id = ?` dan `dibaca_at IS NULL`, mengisi `NOW()`.
- [ ] **Step 4:** Jalankan runner. Expected: semua OK.
- [ ] **Step 5:** Commit `feat: pustaka notifikasi dalam aplikasi`.

### Task 3: Logika "antrean sudah dekat" & pemicunya

**Files:**
- Modify: `includes/notifikasi.php`, `config/config.php` (setelah baris `TAMPILKAN_AKUN_DEMO`), `admin/antrean.php:23-38`, `dokter/periksa.php:149-153`, `user/antrean.php:18-20`, `user/reservasi.php:20-23`
- Test: `tests/antrean_dekat_test.php`

**Interfaces:**
- Consumes: `buat_notifikasi()` (Task 2), `kode_antrean()` dari `includes/antrean.php`.
- Produces: `proses_notif_antrean_dekat(PDO $pdo, $tanggal, $jamSekarang, $pasienId = null): int` → jumlah notifikasi yang dibuat. `$jamSekarang` format `'H:i'`. `$pasienId` membatasi ke reservasi satu pasien. Konstanta `NOTIF_ANTREAN_SISA` (3), `NOTIF_MENIT_SEBELUM_PRAKTIK` (60) didefinisikan dengan pola `if (!defined(...))` yang sama.

Aturan (dari spec): reservasi `tanggal = $tanggal`, status `menunggu`/`hadir`, `notif_dekat_at IS NULL`, `$jamSekarang >= jam_mulai − 60 menit`, dan jumlah reservasi lain pada jadwal+tanggal sama dengan `no_antrean` lebih kecil dan status `menunggu`/`hadir`/`diperiksa` ≤ `NOTIF_ANTREAN_SISA`. Klaim dengan `UPDATE reservasi SET notif_dekat_at = NOW() WHERE id = ? AND notif_dekat_at IS NULL`; buat notifikasi hanya jika `rowCount() === 1`. Notifikasi untuk `pasien.user_id`, judul `Giliran Anda sudah dekat`, pesan `Antrean {kode} (Poli {poli}): tinggal {n} pasien lagi sebelum Anda. Silakan bersiap menuju klinik.` — jika n = 0: `Antrean {kode} (Poli {poli}): Anda pasien berikutnya. Silakan menuju klinik sekarang.`, tautan `user/antrean.php`.

- [ ] **Step 1:** Tulis test memakai `buat_reservasi_uji($pdo, '2026-11-12', '09:00', [...])`:
  - `test_hanya_yang_posisinya_dekat()`: 6 reservasi `menunggu` → jam `'09:00'` → hasil 4 (nomor 1–4, di depan 0–3); nomor 5 dan 6 tidak punya notifikasi.
  - `test_tidak_dobel()`: panggil dua kali → total notifikasi tetap 4, panggilan kedua mengembalikan 0.
  - `test_menjadi_dekat_setelah_selesai()`: 6 reservasi, proses, lalu ubah nomor 1 jadi `selesai`, proses lagi → nomor 5 mendapat notifikasi (hasil 1).
  - `test_batal_tidak_dihitung()`: status `['batal','batal','menunggu', ...]` → nomor 3 dianggap di depan 0 dan pesannya memuat `Anda pasien berikutnya`.
  - `test_belum_waktunya()`: jam `'07:59'` → 0; jam `'08:00'` → 4.
  - `test_tanggal_lain_diabaikan()`: reservasi tanggal `'2026-11-13'`, proses untuk `'2026-11-12'` → 0.
  - `test_filter_pasien()`: dengan `$pasienId` nomor 2 → hasil 1.
- [ ] **Step 2:** Jalankan runner. Expected: GAGAL.
- [ ] **Step 3:** Implementasi `proses_notif_antrean_dekat` dan konstanta config.
- [ ] **Step 4:** Jalankan runner. Expected: semua OK.
- [ ] **Step 5:** Pasang pemicu `proses_notif_antrean_dekat($pdo, date('Y-m-d'), date('H:i'))` setelah perubahan berhasil di: `admin/antrean.php` (hadir dan tidak_hadir, di dalam blok `rowCount() > 0`), `dokter/periksa.php` (setelah `commit()` jika `$aksi === 'selesai'`), `user/antrean.php` (setelah batal berhasil), `user/reservasi.php` (setelah `buat_reservasi` sukses). Tambah `require_once` `includes/notifikasi.php` di file-file itu. Bungkus pemanggilan pemicu dengan `try { ... } catch (PDOException $ex) { error_log((string) $ex); }` agar kegagalan notifikasi tidak menggagalkan aksi utama.
- [ ] **Step 6:** `php -l` setiap file yang diubah. Expected: `No syntax errors`.
- [ ] **Step 7:** Commit `feat: notifikasi otomatis saat antrean tinggal sedikit`.

### Task 4: Lonceng, halaman notifikasi, polling

**Files:**
- Create: `user/notifikasi.php`, `user/notifikasi_cek.php`, `assets/notifikasi.js`, `tests/sesi_test.php`
- Modify: `includes/session.php:30`, `includes/panel_start.php` (bagian `panel-account-tools`), `includes/menu.php` (menu role `user`), `includes/antrean.php` (`karcis_html`), `user/antrean.php`

**Interfaces:**
- Consumes: Task 2 dan `proses_notif_antrean_dekat` (Task 3), `reservasi_pasien()`.
- Produces:
  - `includes/session.php`: jika konstanta `SESI_TANPA_PERPANJANG` terdefinisi, `last_activity` **tidak** diperbarui (pemeriksaan timeout tetap berjalan).
  - `GET user/notifikasi_cek.php?setelah=<id>` → `Content-Type: application/json`, `Cache-Control: no-store`, body `{"belum_dibaca": int, "baru": [{id, judul, pesan, tautan_url}], "antrean": [{"id": int, "di_depan": int}]}`. Mendefinisikan `SESI_TANPA_PERPANJANG` sebelum `require auth.php`, memanggil `proses_notif_antrean_dekat($pdo, date('Y-m-d'), date('H:i'), $pasienId)` dulu. Jika tidak login: status 401 dengan `{"login": false}` (jangan redirect HTML).
  - `user/notifikasi.php`: daftar 50 terbaru, tombol POST "Tandai semua dibaca", setiap tautan menandai dibaca lewat POST `aksi=baca&id=`.
  - Elemen lonceng: `<a id="notif-lonceng" href=".../user/notifikasi.php" data-cek-url="..." data-setelah="<id terbaru>">` berisi `<span id="notif-jumlah">`. Hanya untuk role `user`.
  - `karcis_html`: angka "di depan" dibungkus `<span data-di-depan="<reservasi id>">`.

- [ ] **Step 1:** Tulis `tests/sesi_test.php`: `test_polling_tidak_memperpanjang_sesi()` menjalankan PHP CLI anak (`proc_open` / `shell_exec` ke `PHP_BINARY`) yang men-set `$_SESSION['last_activity'] = time() - 100`, mendefinisikan `SESI_TANPA_PERPANJANG`, me-`require` `includes/session.php`, lalu mencetak `last_activity`; expected masih `time() - 100`. Tambah kontrol tanpa konstanta → nilai diperbarui. (Untuk CLI, set `session.save_path` ke direktori sementara dan `session_id()` tetap sebelum require.)
- [ ] **Step 2:** Jalankan runner. Expected: GAGAL pada kasus dengan konstanta.
- [ ] **Step 3:** Ubah `includes/session.php` baris terakhir menjadi bersyarat.
- [ ] **Step 4:** Jalankan runner. Expected: semua OK.
- [ ] **Step 5:** Buat `user/notifikasi_cek.php`, `user/notifikasi.php`, lonceng di `panel_start.php`, menu "Notifikasi" untuk role `user`.
- [ ] **Step 6:** Buat `assets/notifikasi.js` (dimuat dengan `<script src defer>` dari `panel_start.php` hanya untuk role `user`): `setInterval` 15000 ms memanggil `fetch(cekUrl + '?setelah=' + setelah, {credentials: 'same-origin'})`; perbarui `#notif-jumlah` (sembunyikan jika 0); untuk tiap item `baru` tampilkan toast di pojok kanan bawah (4 detik, bisa diklik ke `tautan_url`), bunyi beep 0,3 detik via `AudioContext` (abaikan error autoplay), `navigator.vibrate(200)` jika ada; perbarui tiap `[data-di-depan="<id>"]`; jika respons 401, hentikan polling. Gunakan `textContent`, bukan `innerHTML`.
- [ ] **Step 7:** Di `user/antrean.php`, jika ada reservasi aktif hari ini, tampilkan kotak info: "Biarkan halaman ini terbuka. Anda akan diberi tahu saat antrean tinggal 3 pasien lagi." (angka dari `NOTIF_ANTREAN_SISA`).
- [ ] **Step 8:** Verifikasi manual di `http://localhost/klinik` dengan **DB lokal yang sudah di-backup**: login pasien dengan reservasi hari ini, login admin di browser lain → konfirmasi hadir / dokter selesaikan pasien di depan → dalam ≤ 15 detik badge bertambah, toast muncul, angka "di depan" berubah, tanpa reload. Biarkan tab pasien diam 31 menit → polling berhenti dan halaman berikutnya meminta login.
- [ ] **Step 9:** `php -l` semua file diubah, jalankan runner. Commit `feat: lonceng notifikasi dan polling antrean pasien`.

### Task 5: Pengirim email (PHPMailer + Gmail)

**Files:**
- Create: `lib/PHPMailer/PHPMailer.php`, `lib/PHPMailer/SMTP.php`, `lib/PHPMailer/Exception.php`, `lib/PHPMailer/LICENSE`, `lib/.htaccess`, `includes/mailer.php`, `tests/mailer_test.php`
- Modify: `config/config.php`

**Interfaces:**
- Produces: konstanta config dengan default: `APP_URL` (default `'http://localhost' . BASE_URL`), `SMTP_HOST` `'smtp.gmail.com'`, `SMTP_PORT` `587`, `SMTP_SECURE` `'tls'`, `SMTP_USER` `''`, `SMTP_PASS` `''`, `SMTP_FROM` `''` (jika kosong pakai `SMTP_USER`), `SMTP_FROM_NAME` `'Klinik Pintar'`.
- Produces: `url_absolut($path): string` di `includes/helper.php` → `rtrim(APP_URL, '/') . '/' . ltrim($path, '/')`.
- Produces: `kirim_email($ke, $namaKe, $subjek, $html, $teks): bool` di `includes/mailer.php`. Mengembalikan `false` (dan `error_log` pesan PHPMailer, **tanpa** password) jika `SMTP_USER` kosong atau pengiriman gagal. `CharSet = 'UTF-8'`, `Timeout = 15`.

- [ ] **Step 1:** Unduh `src/PHPMailer.php`, `src/SMTP.php`, `src/Exception.php`, dan `LICENSE` dari tag `v6.9.1` di `https://github.com/PHPMailer/PHPMailer` ke `lib/PHPMailer/`. `lib/.htaccess` berisi `Require all denied`. Cek `php -l` ketiganya.
- [ ] **Step 2:** Tulis `tests/mailer_test.php`: `test_tanpa_kredensial_mengembalikan_false()` (dengan `SMTP_USER` kosong, yang merupakan default ketika test tidak memuat kredensial — jika `config.local.php` berisi kredensial, test ini dilewati dengan pesan `LEWATI`); `test_url_absolut()` → `url_absolut('auth/x.php')` sama dengan `rtrim(APP_URL,'/') . '/auth/x.php'`.
- [ ] **Step 3:** Jalankan runner. Expected: GAGAL.
- [ ] **Step 4:** Implementasi konstanta, `url_absolut`, `kirim_email`.
- [ ] **Step 5:** Jalankan runner. Expected: OK.
- [ ] **Step 6:** Commit `feat: pengirim email SMTP dengan PHPMailer`.

### Task 6: Logika token reset password

**Files:**
- Create: `includes/reset_password.php`, `tests/reset_password_test.php`

**Interfaces:**
- Produces:
  - `minta_reset_password(PDO $pdo, $email, $ip): array` → `['kirim' => bool, 'token' => string|null, 'nama' => string|null, 'email' => string|null]`. `kirim = true` hanya untuk user `role='user'`, `status='aktif'`, belum melewati batas. Pada sukses: token lama yang belum dipakai milik user di-set `used_at = NOW()`, lalu token baru `bin2hex(random_bytes(32))` disimpan sebagai `hash('sha256', token)` dengan `expires_at = NOW() + 60 menit`. Batas: ≥ 3 baris `password_resets` user itu dalam 15 menit terakhir, atau ≥ 5 baris dengan `ip_address = $ip` dalam 15 menit terakhir → `kirim = false`.
  - `cek_token_reset(PDO $pdo, $token): array|null` → `['reset_id' => int, 'user_id' => int]` jika hash cocok, `used_at IS NULL`, `expires_at > NOW()`, user `role='user'` dan `status='aktif'`; selain itu `null`. Token yang bukan 64 karakter hex langsung `null`.
  - `pakai_token_reset(PDO $pdo, $token, $passwordBaru): array` → `['ok' => bool, 'pesan' => string, 'user_id' => int|null]`. Validasi dengan `validasi_password()` yang ada; dalam transaksi kunci baris token `FOR UPDATE`, cek ulang, update `users SET password, wajib_ganti_password = 0, login_gagal = 0, terkunci_hingga = NULL`, set `used_at = NOW()` untuk **semua** token user itu.

- [ ] **Step 1:** Tulis test:
  - `test_pasien_aktif_mendapat_token()`: `kirim` true, token 64 hex, tabel menyimpan hash (bukan token mentah).
  - `test_email_tidak_dikenal_staf_nonaktif_tidak_dikirim()`: email asing, role `dokter`, role `admin`, role `super_admin`, pasien `nonaktif` → semua `kirim` false dan tidak ada baris baru.
  - `test_batas_per_akun()`: permintaan ke-4 dalam 15 menit → false.
  - `test_batas_per_ip()`: 5 pasien berbeda dari IP sama sukses, ke-6 false.
  - `test_token_lama_batal_saat_minta_baru()`: token pertama → `cek_token_reset` null setelah minta kedua.
  - `test_pakai_token_mengganti_password()`: setelah pakai, `password_verify` cocok, `terkunci_hingga` null, token kedua kali → `ok` false.
  - `test_token_kedaluwarsa_ditolak()`: ubah `expires_at` ke masa lalu → `cek_token_reset` null.
  - `test_password_lemah_ditolak()`: `'abc'` → `ok` false, token masih berlaku.
  - `test_token_format_salah()`: `'xyz'` dan `str_repeat('g', 64)` → null.
- [ ] **Step 2:** Jalankan runner. Expected: GAGAL.
- [ ] **Step 3:** Implementasi tiga fungsi.
- [ ] **Step 4:** Jalankan runner. Expected: semua OK.
- [ ] **Step 5:** Commit `feat: logika token reset password pasien`.

### Task 7: Halaman lupa & reset password

**Files:**
- Create: `auth/lupa_password.php`, `auth/reset_password.php`
- Modify: `auth/login.php:163` (tautan di bawah tombol Masuk), `README.md`

**Interfaces:**
- Consumes: Task 5 (`kirim_email`, `url_absolut`), Task 6 (tiga fungsi).

- [ ] **Step 1:** `auth/lupa_password.php`: tata letak seperti `auth/login.php` (header + public_nav + kartu). POST: `csrf_verify()`, `minta_reset_password(db(), strtolower(post('email')), REMOTE_ADDR)`; jika `kirim`, panggil `kirim_email` dengan subjek `Reset password Klinik Pintar` dan tautan `url_absolut('auth/reset_password.php?token=' . $token)`, isi menyebut berlaku 60 menit dan "abaikan jika Anda tidak memintanya"; catat log `lupa_password_minta` (detail email dipotong 100 karakter). Selalu redirect ke halaman yang sama dengan flash pesan dari spec (bagian B), apa pun hasilnya.
- [ ] **Step 2:** `auth/reset_password.php`: GET dengan `token` → `cek_token_reset`; null → pesan "Tautan reset tidak valid atau sudah kedaluwarsa." dengan tautan ke lupa password. Valid → form password baru + konfirmasi, token di input hidden. POST → `csrf_verify()`, cek konfirmasi sama, `pakai_token_reset`; sukses → `catat_log('reset_password_email', null, $userId)`, flash "Password berhasil diubah. Silakan masuk.", redirect `auth/login.php`. Halaman ini mengirim header `Referrer-Policy: no-referrer`.
- [ ] **Step 3:** Tambah tautan "Lupa password?" di `auth/login.php` dekat field password.
- [ ] **Step 4:** README: bagian baru "Email (reset password)" — membuat akun Gmail khusus, mengaktifkan verifikasi 2 langkah, membuat App Password, contoh blok `SMTP_*` dan `APP_URL` di `config.local.php` (nilai contoh fiktif), catatan port 587 mungkin diblokir hosting. Bagian "Pembaruan database" — backup via phpMyAdmin Export, lalu impor `database/migrations/2026-11-12_notifikasi_reset.sql`.
- [ ] **Step 5:** `php -l` semua file diubah. Jalankan runner. Expected: OK.
- [ ] **Step 6:** Verifikasi manual (setelah user mengisi kredensial Gmail di `config.local.php`): minta reset untuk pasien uji dengan email Gmail asli → email masuk (cek juga Spam), tautan membuka form, password baru bisa dipakai login, tautan yang sama kedua kali ditolak. Minta reset untuk email admin → pesan sama, tidak ada email.
- [ ] **Step 7:** Commit `feat: halaman lupa password dan reset via email`.

### Task 8: Reset antar Super Admin & skrip CLI

**Files:**
- Create: `includes/akun.php`, `tools/reset_superadmin.php`, `tests/akun_test.php`
- Modify: `superadmin/kelola_admin.php`, `admin/dokter.php:131-135`

**Interfaces:**
- Produces: `atur_password_sementara(PDO $pdo, $userId): string` → password sementara (dari `buat_password_sementara()` yang ada); meng-update `password`, `wajib_ganti_password = 1`, `login_gagal = 0`, `terkunci_hingga = NULL`. Pindahkan `buat_password_sementara` tetap di `helper.php`.

- [ ] **Step 1:** Tulis test: `test_atur_password_sementara()` → password lama tidak cocok lagi, sementara cocok, `wajib_ganti_password = 1`, kunci terhapus.
- [ ] **Step 2:** Jalankan runner. Expected: GAGAL.
- [ ] **Step 3:** Implementasi `includes/akun.php`. Ganti blok UPDATE duplikat di `admin/dokter.php` dan `superadmin/kelola_admin.php` dengan pemanggilan fungsi ini (perilaku sama).
- [ ] **Step 4:** Di `kelola_admin.php` tambah bagian "Super Admin lain": daftar `role = 'super_admin' AND id <> $user['id']` dengan tombol reset (aksi `reset_superadmin`, target divalidasi `role='super_admin' AND id <> ?`), log `reset_password_superadmin`, flash password sementara seperti yang ada. Jika daftar kosong tampilkan "Hanya ada satu Super Admin. Disarankan minimal dua akun."
- [ ] **Step 5:** `tools/reset_superadmin.php`: baris pertama keluar dengan `http_response_code(403)` jika `PHP_SAPI !== 'cli'`. Pemakaian `php tools/reset_superadmin.php <email> [--buat "<Nama>"]`. Email ada & role super_admin → `atur_password_sementara`, cetak password sementara. Email ada tapi role lain → error, exit 1. Email tidak ada tanpa `--buat` → error, exit 1. Dengan `--buat` → buat user `super_admin`, `aktif`, wajib ganti. Catat log `reset_superadmin_cli` dengan `user_id` target.
- [ ] **Step 6:** Jalankan runner; `php -l` file diubah. Verifikasi: buka `http://localhost/klinik/tools/reset_superadmin.php` di browser → 403; `D:\XAMPP\php\php.exe tools\reset_superadmin.php` tanpa argumen → pesan pemakaian, exit 1.
- [ ] **Step 7:** Commit `feat: reset antar super admin dan skrip CLI darurat`.

### Task 9: Review akhir

- [ ] **Step 1:** Jalankan `D:\XAMPP\php\php.exe tests\run.php` dan `php -l` untuk semua file PHP. Expected: semua OK.
- [ ] **Step 2:** `git status --short` — pastikan `config/config.local.php` tidak ter-stage dan tidak ada kredensial di diff (`git diff --cached | grep -i smtp_pass` hanya berisi default kosong).
- [ ] **Step 3:** Commit sisa dokumentasi jika ada.
