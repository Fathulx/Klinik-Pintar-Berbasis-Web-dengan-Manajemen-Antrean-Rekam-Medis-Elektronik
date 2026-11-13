-- Migrasi: notifikasi antrean dikirim lagi setiap kali jumlah pasien di depan
-- turun (3, 2, 1, berikutnya). Kolom baru menyimpan angka terakhir yang sudah
-- dikirim agar angka yang sama tidak dikirim dua kali.
--
-- Impor SETELAH 2026-11-12_notifikasi_reset.sql. Hanya menambah kolom; aman
-- diimpor lebih dari sekali (MariaDB). Di MySQL, hapus "IF NOT EXISTS".

ALTER TABLE reservasi ADD COLUMN IF NOT EXISTS notif_di_depan SMALLINT UNSIGNED DEFAULT NULL;
