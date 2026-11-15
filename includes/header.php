<?php require_once __DIR__ . '/../config/config.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($judul ?? 'Klinik Pintar') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Barlow+Semi+Condensed:wght@500;600;700&display=swap" rel="stylesheet">
  <meta name="description" content="Sistem informasi klinik: reservasi, antrean, dan rekam medis elektronik (demo tugas kuliah).">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%231B4F82'/%3E%3Cpath d='M13 7h6v6h6v6h-6v6h-6v-6H7v-6h6z' fill='%23FFFFFF'/%3E%3C/svg%3E">
  <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
  <link rel="apple-touch-icon" href="<?= e(url('assets/ikon-192.png')) ?>">
  <meta name="theme-color" content="#1B4F82">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Klinik Pintar">
  <script>
    // tema dipasang sebelum halaman digambar agar tidak berkedip terang
    (function () {
      var t = null;
      try { t = localStorage.getItem('tema'); } catch (e) {}
      if (t !== 'terang' && t !== 'gelap') {
        t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'gelap' : 'terang';
      }
      document.documentElement.setAttribute('data-tema', t);
    })();
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    // Warna memakai variabel CSS (assets/app.css, :root dan [data-tema="gelap"])
    // sehingga tema gelap cukup mengganti nilai variabelnya.
    var w = function (nama) { return 'rgb(var(--w-' + nama + ') / <alpha-value>)'; };
    tailwind.config = {
      theme: {
        extend: {
          // Palet "ruang tunggu klinik": dinding abu kebiruan, biru papan
          // petunjuk, kuning layar panggilan, dan satu warna per poli.
          colors: {
            paper: w('paper'), ink: w('ink'), klinik: w('klinik'),
            bata: w('bata'), garis: w('garis'), panggil: '#F2B705',
            layar: '#173A5E', mint: w('mint'),
            poli: { umum: '#1B6FB8', gigi: '#0E8C83', anak: '#E07A1F', dalam: '#7A4EB0' }
          },
          // Plus Jakarta Sans (huruf karya desainer Indonesia) untuk teks dan
          // judul; Barlow Semi Condensed hanya untuk angka, seperti layar antrean.
          // "serif" dan "mono" dipetakan ulang agar halaman lama ikut berganti.
          fontFamily: {
            serif: ['"Plus Jakarta Sans"', 'sans-serif'],
            sans: ['"Plus Jakarta Sans"', 'sans-serif'],
            mono: ['"Barlow Semi Condensed"', 'sans-serif']
          }
        }
      }
    }
  </script>
  <link rel="stylesheet" href="<?= e(url('assets/app.css?v=' . filemtime(__DIR__ . '/../assets/app.css'))) ?>">
  <script src="<?= e(url('assets/tema.js?v=' . filemtime(__DIR__ . '/../assets/tema.js'))) ?>" defer></script>
</head>
<body class="bg-paper text-ink font-sans antialiased min-h-screen flex flex-col">