-- Data akun dan jadwal DEMO untuk pengembangan lokal.
-- Jangan impor file ini ke server produksi atau database berisi data nyata.
-- Password semua akun: Demo@12345
-- Impor sekali setelah schema.sql pada database kosong.

INSERT INTO users (nama, email, password, role, status, wajib_ganti_password) VALUES
    ('Super Admin Demo', 'superadmin@demo.klinik', '$2y$10$.1NTDXhyGb/p4ObGsAxnge2mA9plgrdulkTLMx0B0IBR87bL870qG', 'super_admin', 'aktif', 0),
    ('Admin Demo', 'admin@demo.klinik', '$2y$10$.1NTDXhyGb/p4ObGsAxnge2mA9plgrdulkTLMx0B0IBR87bL870qG', 'admin', 'aktif', 0),
    ('Pasien Demo', 'pasien@demo.klinik', '$2y$10$.1NTDXhyGb/p4ObGsAxnge2mA9plgrdulkTLMx0B0IBR87bL870qG', 'user', 'aktif', 0),
    ('Pasien Demo 2', 'pasien2@demo.klinik', '$2y$10$.1NTDXhyGb/p4ObGsAxnge2mA9plgrdulkTLMx0B0IBR87bL870qG', 'user', 'aktif', 0),
    ('dr. Siti Rahma', 'siti.rahma@demo.klinik', '$2y$10$.1NTDXhyGb/p4ObGsAxnge2mA9plgrdulkTLMx0B0IBR87bL870qG', 'dokter', 'aktif', 0),
    ('drg. Budi Santoso', 'budi.santoso@demo.klinik', '$2y$10$.1NTDXhyGb/p4ObGsAxnge2mA9plgrdulkTLMx0B0IBR87bL870qG', 'dokter', 'aktif', 0),
    ('dr. Laras Wulandari, Sp.A', 'laras.w@demo.klinik', '$2y$10$.1NTDXhyGb/p4ObGsAxnge2mA9plgrdulkTLMx0B0IBR87bL870qG', 'dokter', 'aktif', 0),
    ('dr. Andi Pratama, Sp.PD', 'andi.pratama@demo.klinik', '$2y$10$.1NTDXhyGb/p4ObGsAxnge2mA9plgrdulkTLMx0B0IBR87bL870qG', 'dokter', 'aktif', 0);

INSERT INTO pasien (user_id)
SELECT id FROM users WHERE email IN ('pasien@demo.klinik', 'pasien2@demo.klinik');

INSERT INTO poli (nama_poli, deskripsi) VALUES
    ('Umum', 'Pemeriksaan umum dan keluhan sehari-hari'),
    ('Gigi', 'Pemeriksaan dan perawatan gigi serta mulut'),
    ('Anak', 'Pemeriksaan kesehatan bayi dan anak'),
    ('Penyakit Dalam', 'Konsultasi penyakit dalam untuk dewasa');

INSERT INTO dokter (user_id, poli_id, no_str, bio)
SELECT u.id, p.id, 'DEMO-0001', 'Dokter umum, melayani pemeriksaan keluhan sehari-hari.'
FROM users u JOIN poli p ON p.nama_poli = 'Umum'
WHERE u.email = 'siti.rahma@demo.klinik';

INSERT INTO dokter (user_id, poli_id, no_str, bio)
SELECT u.id, p.id, 'DEMO-0002', 'Dokter gigi, pemeriksaan dan perawatan rutin.'
FROM users u JOIN poli p ON p.nama_poli = 'Gigi'
WHERE u.email = 'budi.santoso@demo.klinik';

INSERT INTO dokter (user_id, poli_id, no_str, bio)
SELECT u.id, p.id, 'DEMO-0003', 'Dokter spesialis anak.'
FROM users u JOIN poli p ON p.nama_poli = 'Anak'
WHERE u.email = 'laras.w@demo.klinik';

INSERT INTO dokter (user_id, poli_id, no_str, bio)
SELECT u.id, p.id, 'DEMO-0004', 'Dokter spesialis penyakit dalam.'
FROM users u JOIN poli p ON p.nama_poli = 'Penyakit Dalam'
WHERE u.email = 'andi.pratama@demo.klinik';

INSERT INTO jadwal_dokter (dokter_id, hari, jam_mulai, jam_selesai, kuota)
SELECT d.id, jadwal.hari, jadwal.jam_mulai, jadwal.jam_selesai, 20
FROM dokter d
JOIN users u ON u.id = d.user_id
JOIN (
    SELECT 'siti.rahma@demo.klinik' AS email, 'Senin' AS hari, '08:00:00' AS jam_mulai, '12:00:00' AS jam_selesai
    UNION ALL SELECT 'siti.rahma@demo.klinik', 'Rabu', '08:00:00', '12:00:00'
    UNION ALL SELECT 'siti.rahma@demo.klinik', 'Jumat', '08:00:00', '12:00:00'
    UNION ALL SELECT 'budi.santoso@demo.klinik', 'Senin', '08:00:00', '12:00:00'
    UNION ALL SELECT 'budi.santoso@demo.klinik', 'Rabu', '08:00:00', '12:00:00'
    UNION ALL SELECT 'budi.santoso@demo.klinik', 'Jumat', '08:00:00', '12:00:00'
    UNION ALL SELECT 'laras.w@demo.klinik', 'Senin', '08:00:00', '12:00:00'
    UNION ALL SELECT 'laras.w@demo.klinik', 'Rabu', '08:00:00', '12:00:00'
    UNION ALL SELECT 'laras.w@demo.klinik', 'Jumat', '08:00:00', '12:00:00'
    UNION ALL SELECT 'andi.pratama@demo.klinik', 'Senin', '08:00:00', '12:00:00'
    UNION ALL SELECT 'andi.pratama@demo.klinik', 'Rabu', '08:00:00', '12:00:00'
    UNION ALL SELECT 'andi.pratama@demo.klinik', 'Jumat', '08:00:00', '12:00:00'
) AS jadwal ON jadwal.email = u.email;
