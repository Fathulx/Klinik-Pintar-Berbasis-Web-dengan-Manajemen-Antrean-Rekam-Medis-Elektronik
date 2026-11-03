<?php require_once __DIR__ . '/../config/config.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($judul ?? 'Klinik Pintar') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <meta name="description" content="Sistem informasi klinik: reservasi, antrean, dan rekam medis elektronik (demo tugas kuliah).">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' fill='%230F5C55'/%3E%3Cpath d='M13 7h6v6h6v6h-6v6h-6v-6H7v-6h6z' fill='%23F6F4EE'/%3E%3C/svg%3E">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            paper: '#F6F4EE', ink: '#14211F', klinik: '#0F5C55',
            bata: '#D9582B', garis: '#D9D5C9'
          },
          fontFamily: {
            serif: ['Fraunces', 'serif'],
            sans: ['"IBM Plex Sans"', 'sans-serif'],
            mono: ['"IBM Plex Mono"', 'monospace']
          }
        }
      }
    }
  </script>
  <link rel="stylesheet" href="<?= e(url('assets/app.css?v=' . filemtime(__DIR__ . '/../assets/app.css'))) ?>">
</head>
<body class="bg-paper text-ink font-sans antialiased min-h-screen flex flex-col">