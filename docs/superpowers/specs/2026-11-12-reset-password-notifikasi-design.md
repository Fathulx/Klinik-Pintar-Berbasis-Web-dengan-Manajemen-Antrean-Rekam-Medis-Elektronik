# Rancangan: Reset Password via Email & Notifikasi Antrean

Tanggal: 2026-11-12 · Status: disetujui dalam diskusi, menunggu review tertulis

## Latar belakang

Dosen meminta dua fitur tambahan untuk Klinik Pintar:

1. Pengguna yang lupa password dapat meresetnya melalui email.
2. Pasien mendapat notifikasi ketika antreannya sudah dekat.

## Keputusan

### A. Notifikasi "giliran sudah dekat"

- Pemicu: jumlah pasien aktif (`menunggu`, `hadir`, `diperiksa`) dengan nomor
  lebih kecil pada jadwal dan tanggal yang sama **≤ N**. N = `NOTIF_ANTREAN_SISA`,
  bawaan **3**, dapat diubah di config. Berbasis jumlah antrean, bukan waktu,
  karena lama konsultasi tidak dapat dipastikan.
- Hanya untuk reservasi **hari ini** berstatus `menunggu` atau `hadir`, dan hanya
  mulai `NOTIF_MENIT_SEBELUM_PRAKTIK` (bawaan **60**) menit sebelum jam mulai
  praktik, supaya pasien nomor awal tidak dikabari sejak dini hari.
- Dikirim **sekali** per reservasi (kolom `reservasi.notif_dekat_at`).
- Saluran: **notifikasi dalam aplikasi saja**, berupa ikon lonceng dengan jumlah
  belum dibaca, halaman daftar notifikasi, dan pengecekan otomatis tiap 15 detik
  dengan toast, bunyi, dan getar. Halaman antrean menampilkan posisi terkini dan
  pesan "biarkan halaman ini terbuka".
- Pengecekan otomatis tidak boleh memperpanjang sesi login (batas 30 menit
  tanpa aktivitas tetap berlaku).
- Tabel `notifikasi` bersifat umum agar Web Push bisa ditambahkan nanti tanpa
  mengubah rancangan.

### B. Reset password via email (pasien saja)

- Tautan "Lupa password?" di halaman login → isi email → pesan selalu sama:
  "Jika email terdaftar sebagai pasien, tautan reset telah dikirim. Untuk akun
  dokter atau admin, hubungi pengelola klinik."
- Email hanya dikirim untuk akun `role = 'user'` dan `status = 'aktif'`.
- Token acak 32 byte, hanya **hash SHA-256** yang disimpan, berlaku **60 menit**,
  sekali pakai. Permintaan baru membatalkan token lama.
- Batas permintaan: **3 per 15 menit per akun** dan **5 per 15 menit per IP**.
- Berhasil reset → password baru di-hash, penguncian login dihapus, semua token
  user dihanguskan, dicatat di `log_aktivitas`.
- Pengiriman: **PHPMailer 6.9.1** (disertakan di `lib/PHPMailer`, tanpa Composer)
  lewat **SMTP Gmail** dengan akun Gmail khusus aplikasi + App Password.
  Kredensial di `config/config.local.php`. Saat deploy cukup ubah config.

### C. Reset akun staf

- Sudah ada: Admin mereset dokter, Super Admin mereset admin (password sementara
  + wajib ganti). Tidak diubah.
- Baru: Super Admin dapat mereset **Super Admin lain** (bukan dirinya sendiri)
  di halaman Kelola Admin. Disarankan minimal 2 akun Super Admin.
- Baru: skrip `php tools/reset_superadmin.php <email>` yang **hanya berjalan dari
  command line**, sebagai jalan terakhir dan cara membuat Super Admin pertama.
- Super Admin tidak memakai reset via email.

## Di luar cakupan

- Web Push, notifikasi email untuk antrean, upgrade PHP (tetap PHP 7.3; kode harus
  juga berjalan di PHP 8.x).

## Batasan yang diterima

- Notifikasi dalam aplikasi hanya terlihat saat halaman aplikasi terbuka.
- Sesi login lain milik pasien tidak otomatis keluar setelah reset password.
- Waktu respons form lupa password sedikit lebih lama untuk email terdaftar
  (karena pengiriman SMTP).

## Data

Database lokal berisi data penting: perubahan skema dilakukan lewat file migrasi
yang hanya **menambah** (tidak menghapus/mengubah data), dan wajib backup sebelum
mengimpor.
