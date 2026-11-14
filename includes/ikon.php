<?php
// Ikon garis sederhana (24x24, mengikuti warna teks). Digambar khusus untuk
// aplikasi ini; tidak memakai pustaka luar.
function ikon($nama, $kelas = 'ikon')
{
    $jalur = [
        'dasbor'    => '<rect x="3" y="3" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2"/>',
        'karcis'    => '<path d="M4 6h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4z"/><path d="M14.5 6.5v11" stroke-dasharray="2 2.5"/>',
        'grafik'    => '<path d="M4 20h16"/><rect x="5.5" y="11" width="3.5" height="6" rx="1"/><rect x="10.25" y="6" width="3.5" height="11" rx="1"/><rect x="15" y="13" width="3.5" height="4" rx="1"/>',
        'stetoskop' => '<path d="M6 3v6a5 5 0 0 0 10 0V3"/><path d="M11 14v2.5a4 4 0 0 0 8 0V14"/><circle cx="19" cy="11.5" r="2.2"/>',
        'kalender'  => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'pesan'     => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4M12 13v5M9.5 15.5h5"/>',
        'lonceng'   => '<path d="M18 8.5a6 6 0 0 0-12 0c0 6.5-2.5 8.5-2.5 8.5h17S18 15 18 8.5"/><path d="M13.7 20.5a2 2 0 0 1-3.4 0"/>',
        'dokumen'   => '<path d="M6 3h8l5 5v13H6z"/><path d="M14 3v5h5M9.5 13h6M9.5 17h6"/>',
        'pengguna'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M21.5 20a6.5 6.5 0 0 0-3.8-5.9"/>',
        'gedung'    => '<path d="M4 21V8l8-4.5L20 8v13"/><path d="M12 9.5v6M9 12.5h6M2.5 21h19"/>',
        'daftar'    => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1.2"/><circle cx="4.5" cy="12" r="1.2"/><circle cx="4.5" cy="18" r="1.2"/>',
        'periksa'   => '<rect x="5" y="4.5" width="14" height="16.5" rx="2.5"/><path d="M9 4.5V3h6v1.5M9 11h6M9 15h4"/>',
        'gigi'      => '<path d="M7.5 3C5 3 3.5 5 3.5 7.5c0 3 1.5 4.5 2 7.5.4 2.5 1 6 2.5 6s1.7-4 2.5-5.5c.4-.7.8-1 1.5-1s1.1.3 1.5 1c.8 1.5 1 5.5 2.5 5.5s2.1-3.5 2.5-6c.5-3 2-4.5 2-7.5C20.5 5 19 3 16.5 3c-2 0-2.7 1-4.5 1S9.5 3 7.5 3z"/>',
        'anak'      => '<circle cx="12" cy="13" r="7.5"/><path d="M9.5 12.5h.01M14.5 12.5h.01"/><path d="M9.3 16a3.6 3.6 0 0 0 5.4 0"/><path d="M12 5.5c0-1.6 1.1-2.8 2.6-2.8"/>',
        'jantung'   => '<path d="M12 20.5s-8-4.6-8-10.4A4.6 4.6 0 0 1 12 7.2a4.6 4.6 0 0 1 8 2.9c0 5.8-8 10.4-8 10.4z"/><path d="M4.5 12.5h4l1.5-2.5 2 4.5 1.5-2h6"/>',
    ];
    $isi = isset($jalur[$nama]) ? $jalur[$nama] : $jalur['dokumen'];
    return '<svg class="' . e($kelas) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $isi . '</svg>';
}

// Ikon untuk tiap poli, selaras dengan kelas_poli().
function ikon_poli($namaPoli)
{
    $n = strtolower($namaPoli);
    if (strpos($n, 'gigi') !== false) {
        return ikon('gigi');
    }
    if (strpos($n, 'anak') !== false) {
        return ikon('anak');
    }
    if (strpos($n, 'dalam') !== false) {
        return ikon('jantung');
    }
    return ikon('stetoskop');
}
