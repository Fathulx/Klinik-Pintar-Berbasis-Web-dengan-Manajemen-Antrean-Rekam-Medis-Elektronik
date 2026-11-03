-- Skema dasar Klinik Pintar
-- Target: MySQL/MariaDB dengan InnoDB dan dukungan utf8mb4.
-- Pilih database klinik_pintar di phpMyAdmin sebelum mengimpor file ini.
-- File ini hanya membuat struktur tabel, tanpa memasukkan data.

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'dokter', 'user') NOT NULL,
    status ENUM('aktif', 'nonaktif', 'pending') NOT NULL DEFAULT 'aktif',
    wajib_ganti_password TINYINT(1) NOT NULL DEFAULT 0,
    login_gagal TINYINT UNSIGNED NOT NULL DEFAULT 0,
    terkunci_hingga DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS poli (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama_poli VARCHAR(80) NOT NULL,
    deskripsi VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_poli_nama (nama_poli)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pasien (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    no_hp VARCHAR(15) DEFAULT NULL,
    jenis_kelamin ENUM('L', 'P') DEFAULT NULL,
    tanggal_lahir DATE DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pasien_user (user_id),
    CONSTRAINT fk_pasien_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dokter (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    poli_id INT UNSIGNED NOT NULL,
    no_str VARCHAR(40) NOT NULL,
    bio VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dokter_user (user_id),
    UNIQUE KEY uq_dokter_no_str (no_str),
    KEY idx_dokter_poli (poli_id),
    CONSTRAINT fk_dokter_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_dokter_poli
        FOREIGN KEY (poli_id) REFERENCES poli (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jadwal_dokter (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dokter_id INT UNSIGNED NOT NULL,
    hari ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu') NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    kuota SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_jadwal_dokter_hari_aktif (dokter_id, hari, aktif),
    CONSTRAINT fk_jadwal_dokter
        FOREIGN KEY (dokter_id) REFERENCES dokter (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservasi (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pasien_id INT UNSIGNED NOT NULL,
    jadwal_id INT UNSIGNED NOT NULL,
    tanggal DATE NOT NULL,
    no_antrean INT UNSIGNED NOT NULL,
    status ENUM('menunggu', 'hadir', 'diperiksa', 'selesai', 'batal', 'tidak_hadir')
        NOT NULL DEFAULT 'menunggu',
    PRIMARY KEY (id),
    UNIQUE KEY uq_reservasi_nomor (jadwal_id, tanggal, no_antrean),
    KEY idx_reservasi_pasien_tanggal_status (pasien_id, tanggal, status),
    KEY idx_reservasi_jadwal_tanggal_status (jadwal_id, tanggal, status),
    KEY idx_reservasi_tanggal_status (tanggal, status),
    CONSTRAINT fk_reservasi_pasien
        FOREIGN KEY (pasien_id) REFERENCES pasien (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservasi_jadwal
        FOREIGN KEY (jadwal_id) REFERENCES jadwal_dokter (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rekam_medis (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservasi_id INT UNSIGNED NOT NULL,
    keluhan TEXT NOT NULL,
    pemeriksaan TEXT DEFAULT NULL,
    catatan_dokter TEXT DEFAULT NULL,
    terkunci TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rekam_medis_reservasi (reservasi_id),
    CONSTRAINT fk_rekam_medis_reservasi
        FOREIGN KEY (reservasi_id) REFERENCES reservasi (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resep (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rekam_medis_id INT UNSIGNED NOT NULL,
    nama_obat VARCHAR(100) NOT NULL,
    dosis VARCHAR(50) DEFAULT NULL,
    aturan_pakai VARCHAR(120) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_resep_rekam_medis (rekam_medis_id),
    CONSTRAINT fk_resep_rekam_medis
        FOREIGN KEY (rekam_medis_id) REFERENCES rekam_medis (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS log_aktivitas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED DEFAULT NULL,
    aksi VARCHAR(60) NOT NULL,
    detail VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_log_created_at (created_at),
    KEY idx_log_aksi_created_at (aksi, created_at),
    KEY idx_log_user (user_id),
    CONSTRAINT fk_log_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
