# Klinik Pintar

Aplikasi demo sistem informasi klinik untuk mengelola reservasi antrean dan
rekam medis. Aplikasi dibuat dengan PHP dan MySQL/MariaDB, dengan XAMPP sebagai
salah satu cara menjalankannya secara lokal.

## Persyaratan

- PHP dengan ekstensi PDO MySQL (`pdo_mysql`)
- MySQL atau MariaDB
- Apache, misalnya melalui XAMPP

## Menjalankan secara lokal

1. Clone atau salin proyek ke `C:\xampp\htdocs\klinik`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`, buat database bernama `klinik_pintar`,
   lalu pilih database tersebut.
4. Impor [`database/schema.sql`](./database/schema.sql) melalui tab **Import**
   di phpMyAdmin. File ini membuat tabel dan relasi, tanpa memasukkan data.
5. Untuk demo lokal, impor [`database/seed_demo.sql`](./database/seed_demo.sql)
   setelah skema berhasil dibuat. Seed ini membuat akun fiktif dan jadwal
   contoh; impor hanya sekali pada database kosong. Semua akun memakai password
   `Demo@12345`:

   | Peran | Email |
   | --- | --- |
   | Super Admin | `superadmin@demo.klinik` |
   | Admin | `admin@demo.klinik` |
   | Dokter | `siti.rahma@demo.klinik` |
   | Pasien | `pasien@demo.klinik` |

   Akun dokter demo lainnya: `budi.santoso@demo.klinik`,
   `laras.w@demo.klinik`, dan `andi.pratama@demo.klinik`.
   **Akun dan password ini hanya untuk demo lokal. Jangan gunakan atau impor
   seed ini pada server publik/produksi.**
6. Buat file `config/config.local.php` secara lokal. File ini tidak ada di
   GitHub dan tidak boleh diunggah. Untuk instalasi XAMPP standar, isinya:

   ```php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'klinik_pintar');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('APP_ENV', 'development');
   define('BASE_URL', '/klinik');
   define('TAMPILKAN_AKUN_DEMO', true);
   ```

   Kredensial di atas hanya cocok untuk konfigurasi XAMPP lokal bawaan. Jika
   MySQL Anda memakai password atau nama pengguna berbeda, sesuaikan nilainya.
7. Buka `http://localhost/klinik/` dan masuk menggunakan akun demo di atas.

## Pembaruan database (notifikasi & reset password)

Database yang dibuat **sebelum** fitur notifikasi antrean dan reset password
perlu tabel tambahan. Instalasi baru dari `schema.sql` sudah mencakupnya.

1. **Backup dulu**: di phpMyAdmin pilih database `klinik_pintar` → tab
   **Export** → **Go**, lalu simpan file `.sql`-nya di tempat aman (jangan
   di-commit).
2. Masih di database yang sama, buka tab **Import** dan impor file di
   [`database/migrations/`](./database/migrations/) **berurutan sesuai nama**:
   - `2026-11-12_notifikasi_reset.sql`: tabel `notifikasi`, `password_resets`,
     kolom `reservasi.notif_dekat_at`
   - `2026-11-13_notif_tiap_turun.sql`: kolom `reservasi.notif_di_depan`
   - `2026-11-13_web_push.sql`: tabel `push_langganan`

   Semua hanya menambah tabel/kolom; data lama tidak diubah. Aman diimpor ulang.
3. Di server **MySQL** (bukan MariaDB), jalankan baris `ALTER TABLE` di file
   itu tanpa `IF NOT EXISTS`, sekali saja.

Notifikasi "giliran sudah dekat" muncul di aplikasi pasien saat pasien aktif
di depannya tinggal `NOTIF_ANTREAN_SISA` (bawaan 3), lalu lagi setiap kali
angka itu turun. Berlaku mulai `NOTIF_MENIT_SEBELUM_PRAKTIK` (bawaan 60) menit
sebelum jam praktik. Kedua nilai bisa diubah di `config.local.php`.

Untuk mencoba tanpa membuat akun manual:
`php tools/antrean_uji.php isi <email-pasien> 5` (hapus lagi dengan
`php tools/antrean_uji.php hapus`).

## Data demo untuk presentasi

Agar dashboard, grafik, laporan, dan riwayat tidak kosong:

```powershell
D:\XAMPP\php\php.exe tools\data_demo.php isi     # 20 pasien contoh + riwayat 4 minggu + 7 hari ke depan
D:\XAMPP\php\php.exe tools\data_demo.php hapus   # hapus semua data demo
```

Data memakai jadwal praktik yang sudah ada. Pasien contoh memakai email
`@contoh.klinik` (password `Demo@12345`); isinya fiktif. Antrean hari ini
hanya terisi bila hari itu ada jadwal aktif, jadi jalankan `isi` pada hari
presentasi. Menjalankan `isi` lagi mengganti data demo lama, bukan menambah.

## Notifikasi di HP (Web Push)

Pasien dapat menerima notifikasi antrean di HP/laptop walau browser atau layar
sedang ditutup. Pengiriman ditulis tanpa pustaka luar (`includes/webpush.php`).

1. Buat kunci sekali per server:
   `php tools/buat_kunci_webpush.php --simpan` (menulis `WEBPUSH_*` ke
   `config.local.php`; jangan di-commit). Tanpa kunci, fitur ini nonaktif.
2. Pasien membuka **Antrean** atau **Notifikasi**, lalu menekan
   **Aktifkan notifikasi**.

Syarat browser:
- Alamat harus **https://** (atau `http://localhost`). Membuka lewat IP WiFi
  (`http://192.168.x.x`) tidak bisa. Untuk mencoba di HP sebelum deploy, pakai
  terowongan HTTPS, misalnya
  `cloudflared tunnel --protocol http2 --url http://127.0.0.1:80`
  (`--protocol http2` diperlukan bila jaringan memblokir UDP/QUIC), lalu buka
  `https://<alamat-acak>.trycloudflare.com/klinik` di HP. Laptop harus tetap
  menyala selama percobaan.
- **Android/laptop**: Chrome, Edge, atau Firefox langsung bisa.
- **iPhone/iPad**: iOS 16.4+, dan situs harus dipasang dulu lewat
  Bagikan → **Tambahkan ke Layar Utama**, lalu dibuka dari ikon tersebut.

Mengganti kunci membuat semua perangkat harus mengaktifkan notifikasi lagi.

## Email (reset password pasien)

Pasien yang lupa password dapat meminta tautan reset lewat email. Aplikasi
mengirim email melalui SMTP Gmail memakai PHPMailer (sudah disertakan di
`lib/PHPMailer`, tanpa Composer).

1. Pakai akun Gmail **khusus aplikasi**, bukan Gmail pribadi.
2. Aktifkan **Verifikasi 2 Langkah** di <https://myaccount.google.com/security>.
3. Buat **App Password** di <https://myaccount.google.com/apppasswords>
   (16 huruf, hanya tampil sekali).
4. Tambahkan ke `config/config.local.php` (contoh nilai fiktif):

   ```php
   define('APP_URL', 'http://localhost/klinik');   // server: https://domain-anda
   define('SMTP_USER', 'akun.aplikasi@gmail.com');
   define('SMTP_PASS', 'abcdefghijklmnop');         // App Password, bukan password Gmail
   ```

   Opsional: `SMTP_HOST` (bawaan `smtp.gmail.com`), `SMTP_PORT` (587),
   `SMTP_SECURE` (`tls`), `SMTP_FROM`, `SMTP_FROM_NAME`.

Email reset bisa masuk ke folder **Spam** pada awalnya. Beberapa hosting
memblokir koneksi SMTP keluar (port 587/465); jika begitu, ganti nilai `SMTP_*`
dengan SMTP dari penyedia hosting tanpa mengubah kode. Gmail membatasi sekitar
500 email per hari.

Hanya akun **pasien** yang dapat mereset lewat email. Password dokter direset
oleh Admin, password admin oleh Super Admin, dan Super Admin oleh Super Admin
lain atau lewat skrip `php tools/reset_superadmin.php` di server.

## Menjalankan test

Test memakai database terpisah `klinik_pintar_test` yang dibuat ulang otomatis
(database aplikasi tidak disentuh):

```powershell
D:\XAMPP\php\php.exe tests\run.php
```

## Panduan serah-terima deploy

Bagikan URL repository ini kepada pengelola server. Source code di branch
`main` tidak berisi konfigurasi lokal atau data pasien. Untuk memasangnya:

1. Pastikan server menyediakan PHP versi yang masih mendapat dukungan keamanan,
   ekstensi `pdo_mysql` dan `mbstring`, MySQL/MariaDB, serta Apache. Uji aplikasi
   pada versi PHP server sebelum dibuka untuk pengguna.
2. Clone repository dan arahkan document root Apache ke folder proyek. Aktifkan
   `mod_rewrite`/dukungan `.htaccess` jika diperlukan, lalu pastikan HTTPS aktif.
3. Buat database kosong untuk aplikasi dan impor
   [`database/schema.sql`](./database/schema.sql). Jangan impor
   [`database/seed_demo.sql`](./database/seed_demo.sql) di server produksi.
4. Buat `config/config.local.php` hanya di server (jangan commit atau kirim
   melalui repository). Isi dengan kredensial database khusus aplikasi, lalu
   atur `APP_ENV` menjadi `production` dan `BASE_URL` sesuai URL/letak aplikasi.
   Contoh format:

   ```php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'nama_database');
   define('DB_USER', 'user_aplikasi');
   define('DB_PASS', 'password_kuat');
   define('APP_ENV', 'production');
   define('BASE_URL', '');
   define('TAMPILKAN_AKUN_DEMO', false);
   ```

   Sesuaikan `BASE_URL` jika aplikasi dipasang di subdirektori, serta gunakan
   kredensial yang diberikan penyedia hosting.
5. Siapkan akun Super Admin awal secara aman. Aplikasi belum memiliki proses
   provisioning Super Admin produksi yang terdokumentasi; jangan gunakan akun
   demo atau password yang ada di `seed_demo.sql`. Verifikasi akun awal dan
   minta pemilik mengganti password sementara sebelum akses diberikan.
6. Sebelum membuka akses, uji login dan hak akses setiap peran, registrasi
   pasien, reservasi, antrean, serta penyimpanan rekam medis pada lingkungan
   staging dengan data fiktif.
7. Jangan biarkan `tools/reset_demo.php` dapat diakses dari internet. Skrip itu
   dapat menghapus dan membuat ulang data; hapus atau blokir endpoint tersebut
   pada deployment produksi.

Jangan gunakan aplikasi ini untuk data pasien sungguhan sebelum keamanan,
privasi, backup/pemulihan, dan kesiapan operasionalnya ditinjau oleh pihak yang
bertanggung jawab.

## Jangan unggah ke GitHub

`.gitignore` proyek sudah mengecualikan beberapa file lokal dan data. Tetap
periksa daftar file sebelum commit, terutama:

- `config/config.local.php`, `.env`, atau file konfigurasi lain yang berisi
  password database, token, API key, atau kredensial server.
- `AKUN_DEMO.md` dan dokumen internal sejenis yang berisi detail akses.
- Dump/backup database, termasuk `backup*.sql` dan `*.sql.gz`, khususnya yang
  berisi nama, email, rekam medis, resep, atau data pribadi lainnya.
- Log aplikasi/server, file sementara, dan cadangan lokal seperti `*.log`,
  `error_log`, `*.bak`, dan `*.tmp`.
- Private key, sertifikat privat, file rahasia deployment, serta data pasien
  atau kredensial sungguhan.

Jangan mengakali `.gitignore` dengan `git add -f` untuk file-file tersebut.
Jika rahasia terlanjur pernah di-commit atau di-push, menghapus file pada
commit berikutnya saja tidak cukup: cabut/ganti rahasia itu dan bersihkan
riwayat Git sebelum repositori dibagikan.

## Pemeriksaan sebelum push

```powershell
git status --short
git diff --cached --name-only
git diff --cached
```

Pastikan hanya source code dan dokumentasi yang memang boleh dibagikan berada
di staging area. Jangan push data rahasia, data pasien, atau konfigurasi lokal.
