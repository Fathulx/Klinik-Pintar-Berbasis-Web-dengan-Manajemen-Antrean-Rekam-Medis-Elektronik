-- Migrasi: notifikasi Web Push ke HP/laptop pasien (muncul walau browser ditutup).
-- Satu baris per perangkat yang mengaktifkan notifikasi.
--
-- Impor SETELAH 2026-11-13_notif_tiap_turun.sql. Hanya menambah tabel; aman
-- diimpor lebih dari sekali.

CREATE TABLE IF NOT EXISTS push_langganan (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    endpoint VARCHAR(1000) NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    p256dh VARCHAR(100) NOT NULL,
    auth VARCHAR(40) NOT NULL,
    perangkat VARCHAR(160) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_push_langganan_endpoint (endpoint_hash),
    KEY idx_push_langganan_user (user_id),
    CONSTRAINT fk_push_langganan_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
