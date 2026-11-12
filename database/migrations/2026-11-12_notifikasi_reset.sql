-- Migrasi: notifikasi dalam aplikasi dan reset password via email.
-- Untuk database Klinik Pintar yang SUDAH berisi data.
--
-- PENTING: backup database lewat phpMyAdmin > Export sebelum mengimpor file ini.
--
-- File ini hanya MENAMBAH tabel dan kolom; tidak menghapus atau mengubah data,
-- dan aman diimpor lebih dari sekali.
--
-- Catatan: "ADD COLUMN IF NOT EXISTS" didukung MariaDB (XAMPP), tetapi tidak
-- oleh MySQL. Di server MySQL, jalankan baris ALTER tanpa "IF NOT EXISTS",
-- sekali saja.

CREATE TABLE IF NOT EXISTS notifikasi (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    judul VARCHAR(120) NOT NULL,
    pesan VARCHAR(255) NOT NULL,
    tautan VARCHAR(255) DEFAULT NULL,
    dibaca_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifikasi_user_dibaca (user_id, dibaca_at),
    CONSTRAINT fk_notifikasi_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_resets_token (token_hash),
    KEY idx_password_resets_user_created (user_id, created_at),
    KEY idx_password_resets_ip_created (ip_address, created_at),
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE reservasi ADD COLUMN IF NOT EXISTS notif_dekat_at DATETIME DEFAULT NULL;
