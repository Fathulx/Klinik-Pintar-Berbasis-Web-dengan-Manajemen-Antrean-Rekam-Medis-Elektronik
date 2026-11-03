# Klinik Pintar

Aplikasi demo sistem informasi klinik untuk mengelola reservasi antrean dan
rekam medis. Aplikasi dibuat dengan PHP dan MySQL/MariaDB, dengan XAMPP sebagai
salah satu cara menjalankannya secara lokal.

## Persyaratan

- PHP dengan ekstensi PDO MySQL (`pdo_mysql`)
- MySQL atau MariaDB
- Apache, misalnya melalui XAMPP

## Menjalankan secara lokal

1. Letakkan atau clone proyek ke `htdocs/klinik`.
2. Jalankan Apache dan MySQL dari XAMPP.
3. Buat database kosong bernama `klinik_pintar` melalui phpMyAdmin atau MySQL.
4. Pilih database `klinik_pintar`, lalu impor [`database/schema.sql`](./database/schema.sql)
   melalui phpMyAdmin atau klien MySQL. Skema membuat tabel dan relasi tanpa
   memasukkan data pasien maupun akun demo.
5. Untuk mencoba aplikasi secara lokal, impor [`database/seed_demo.sql`](./database/seed_demo.sql)
   sekali pada database kosong. File ini membuat akun fiktif untuk setiap
   peran dan jadwal praktik contoh. Semua akun demo memakai password
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
6. Buat `config/config.local.php` secara lokal. File ini sengaja tidak
   disimpan di Git. Contoh konfigurasi:

   ```php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'klinik_pintar');
   define('DB_USER', 'nama_user_database');
   define('DB_PASS', 'password_database');
   define('APP_ENV', 'development');
   define('BASE_URL', '/klinik');
   define('TAMPILKAN_AKUN_DEMO', true);
   ```

   Ganti nama pengguna dan password sesuai konfigurasi MySQL lokal Anda.
   Jangan masukkan kredensial produksi ke file yang di-commit.
7. Buka `http://localhost/klinik/` di browser.

## Sebelum deploy ke server

- Buat konfigurasi lokal di luar Git dengan kredensial database yang kuat.
- Atur `APP_ENV` ke `production` dan `BASE_URL` sesuai alamat aplikasi.
- Pastikan error PHP tidak ditampilkan ke pengunjung dan HTTPS diaktifkan.
- Tinjau akses serta penggunaan `tools/reset_demo.php`. Skrip ini menghapus
  data aplikasi sebelum mengisi ulang data demo; jangan aktifkan endpoint reset
  untuk penggunaan publik/produksi.
- Jangan impor `database/seed_demo.sql` di produksi. Buat akun administrator
  awal melalui proses provisioning aman, lalu gunakan akun tersebut untuk
  membuat akun staf dengan password unik.
- Jangan gunakan data pasien sungguhan pada demo atau lingkungan pengembangan.

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
