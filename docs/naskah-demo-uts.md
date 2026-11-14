# Naskah demo UTS: Klinik Pintar

Perkiraan durasi: 10 menit demo + tanya jawab. Semua akun demo memakai password
`Demo@12345`.

## Sehari sebelum / pagi hari presentasi

1. Jalankan **Apache** dan **MySQL** di XAMPP Control Panel.
2. Isi data demo untuk hari itu (dashboard, grafik, laporan, dan antrean hari
   ini jadi terisi):
   ```
   D:\XAMPP\php\php.exe tools\data_demo.php isi
   ```
3. Cek jadwal praktik hari presentasi. Pasien hanya bisa memesan untuk hari
   ini **sebelum jam praktik selesai**:

   | Hari | Dokter yang praktik |
   | --- | --- |
   | Senin | Budi (Gigi), Laras (Anak), Andi (Penyakit Dalam), 08.00-12.00 |
   | Rabu, Jumat | Siti (Umum), Budi, Laras, Andi, 08.00-12.00 |
   | Kamis | Siti (Umum), 12.00-19.00 |
   | Selasa, Sabtu, Minggu | tidak ada |

   Presentasi di luar jam itu? Jadikan bagian dari demo: di langkah 2 admin
   menambah jadwal hari ini lewat **Jadwal Praktik** (misalnya Siti, jam
   sekarang sampai 23.00), lalu pasien memesan.
4. Siapkan tiga jendela browser (sesi login terpisah):
   - **Chrome biasa**: pasien `pasien@demo.klinik`
   - **Chrome Incognito**: admin `admin@demo.klinik`
   - **Edge**: dokter `siti.rahma@demo.klinik` (atau dokter yang praktik hari itu)
5. Untuk demo notifikasi HP (langkah 5):
   - Laptop dan HP tersambung internet (hotspot HP bila WiFi kampus memblokir).
   - Jalankan `cloudflared tunnel --protocol http2 --url http://127.0.0.1:80`,
     buka alamat `https://...trycloudflare.com/klinik` di HP, login dengan akun
     pasien Anda, buka **Antrean** dan tekan **Aktifkan notifikasi**.
     Alamat terowongan berubah tiap dijalankan, jadi aktifkan ulang di HP pada
     hari itu.
   - Windows: matikan **Focus assist**, izinkan notifikasi Chrome.

## Alur demo

**1. Pasien memesan (Chrome biasa), sekitar 2 menit**
- Halaman depan, lalu masuk sebagai pasien. Tunjukkan dashboard pasien.
- **Buat Reservasi**: pilih poli, dokter, lalu tanggal hari ini, **Pesan**.
- Tunjukkan karcis nomor antrean dan perkiraan jam dilayani.

**2. Admin menerima (Incognito), sekitar 1,5 menit**
- **Antrean**: nomor pasien tadi muncul sendiri dalam 10 detik tanpa refresh
  (baris baru disorot). Ceritakan: daftar ini diperbarui otomatis.
- Tekan **Konfirmasi hadir**. Pesan berhasil muncul lalu hilang sendiri.

**3. Dokter memeriksa (Edge), sekitar 2 menit**
- **Antrean Hari Ini**: tombol **Panggil** muncul sendiri begitu admin
  mengonfirmasi hadir (halaman dokter juga diperbarui otomatis). Tekan **Panggil**.
- Bila ditanya "bagaimana kalau pasien dipanggil tapi tidak masuk?": dokter
  menekan **Tidak datang**, antrean bergeser dan pasien berikutnya diberi
  notifikasi. Pembagian tugas: admin mencatat kedatangan, dokter memanggil dan
  menyelesaikan pemeriksaan.
- Isi keluhan, hasil pemeriksaan, dan satu obat. Tekan
  **Selesaikan dan kunci**. Jelaskan: rekam medis yang sudah dikunci tidak bisa
  diubah lagi.

**4. Pasien melihat hasil (Chrome biasa), sekitar 1 menit**
- **Riwayat Kunjungan**, lalu **Lihat rekam medis**: keluhan, hasil, dan resep
  yang baru ditulis dokter.

**5. Notifikasi ke HP, sekitar 2 menit**
- Di terminal laptop:
  ```
  D:\XAMPP\php\php.exe tools\antrean_uji.php isi <email-akun-pasien-di-HP> 5
  ```
- Kunci layar HP. Di jendela admin, tandai **Pasien Uji 1** dan **2** tidak
  hadir. Dalam beberapa detik HP berbunyi: "tinggal 3 pasien lagi".
- Tandai satu lagi: notifikasi baru "tinggal 2 pasien lagi".
- Tanpa internet? Tunjukkan lonceng **Notifikasi** di aplikasi pasien; itu
  tetap jalan di localhost.

**6. Laporan admin, sekitar 1 menit**
- **Dashboard**: grafik 7 hari (arahkan mouse ke batang untuk detail).
- **Laporan**: filter per dokter/poli, **Unduh CSV**, **Cetak**.

**7. Super admin, sekitar 30 detik**
- Masuk `superadmin@demo.klinik`, buka **Log Aktivitas**: semua aksi tadi
  tercatat (siapa, kapan), tanpa isi data medis.

## Poin keamanan bila ditanya

- Password disimpan dengan hash (`password_hash`), bukan teks asli.
- Setiap formulir dilindungi token CSRF; setiap halaman memeriksa peran.
- Akun terkunci sementara setelah beberapa kali gagal login; sesi berakhir
  setelah 30 menit tanpa aktivitas.
- Rekam medis hanya bisa dilihat pasien pemiliknya dan dokternya; setelah
  dikunci tidak bisa diubah.
- Reset password lewat email memakai token sekali pakai yang kedaluwarsa.

## Bila ada masalah

| Masalah | Solusi |
| --- | --- |
| Pasien tidak bisa memesan hari ini | Jam praktik sudah lewat. Admin tambah jadwal hari ini. |
| Notifikasi HP tidak muncul | Cek internet, terowongan masih jalan, Focus assist mati. Pakai lonceng di aplikasi sebagai cadangan. |
| Login gagal berkali-kali lalu terkunci | Tunggu beberapa menit, atau pakai akun demo lain. |
| Dashboard kosong | Jalankan lagi `tools\data_demo.php isi`. |

## Sesudah presentasi

```
D:\XAMPP\php\php.exe tools\antrean_uji.php hapus
D:\XAMPP\php\php.exe tools\data_demo.php hapus
```
