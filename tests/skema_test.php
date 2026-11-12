<?php
function test_tabel_baru_ada()
{
    $st = test_pdo()->prepare(
        'SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_name IN (?, ?)'
    );
    $st->execute([TEST_DB_NAME, 'notifikasi', 'password_resets']);
    $ada = $st->fetchAll(PDO::FETCH_COLUMN);
    sort($ada);
    sama(['notifikasi', 'password_resets'], $ada, 'tabel baru');
}

function test_kolom_notif_dekat_ada()
{
    $st = test_pdo()->prepare(
        "SELECT is_nullable FROM information_schema.columns
         WHERE table_schema = ? AND table_name = 'reservasi' AND column_name = 'notif_dekat_at'"
    );
    $st->execute([TEST_DB_NAME]);
    sama('YES', $st->fetchColumn(), 'reservasi.notif_dekat_at nullable');
}
