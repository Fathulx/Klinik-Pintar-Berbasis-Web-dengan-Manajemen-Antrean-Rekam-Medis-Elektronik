<?php
require_once __DIR__ . '/menu.php';
require_once __DIR__ . '/ikon.php';
require __DIR__ . '/header.php';
require_once __DIR__ . '/akun_saya.php';
$menu  = menu_panel($user['role']);
$aktif = isset($aktif) ? $aktif : '';

// Kartu akun di atas menu: inisial, nama, peran -> halaman Akun saya.
$diAkun = $aktif === 'auth/akun.php';
$kartuAkun = '<div class="kartu-akun">'
    . '<a href="' . e(url('auth/akun.php')) . '" class="kartu-akun__tautan' . ($diAkun ? ' is-aktif' : '') . '"'
    . ($diAkun ? ' aria-current="page"' : '') . ' aria-label="Akun saya: ' . e($user['nama']) . '">'
    . '<span class="akun-avatar" aria-hidden="true">' . e(inisial_nama($user['nama'])) . '</span>'
    . '<span class="min-w-0"><span class="kartu-akun__nama">' . e($user['nama']) . '</span>'
    . '<span class="kartu-akun__peran">' . e(label_peran($user['role'])) . '</span></span></a>'
    . tombol_tema_html() . '</div>';
$tombolKeluar = '<form method="post" action="' . e(url('auth/logout.php')) . '" class="menu-keluar">'
    . csrf_field() . '<button class="menu-tautan">' . ikon('keluar') . '<span>Keluar</span></button></form>';
?>
<div class="flex flex-1 flex-col md:flex-row">
  <aside class="border-b border-garis bg-white md:w-60 md:shrink-0 md:border-b-0 md:border-r print:hidden">
    <div class="panel-brand-row px-5 py-5">
      <div>
        <a href="<?= e(url(dashboard_path($user['role']))) ?>" class="merek"><span class="merek__tanda" aria-hidden="true"></span>Klinik Pintar</a>
      </div>
      <details class="panel-mobile-menu panel-mobile-menu--overlay panel-mobile-menu--hamburger">
        <summary class="panel-mobile-menu__toggle" aria-label="Buka menu" aria-expanded="false">
          <span class="panel-mobile-menu__hamburger" aria-hidden="true"></span>
        </summary>
        <div class="panel-mobile-menu__backdrop" aria-hidden="true"></div>
        <div class="panel-mobile-menu__content">
          <?= $kartuAkun ?>
          <nav class="flex gap-1 overflow-x-auto px-3 pb-3 md:block md:space-y-1 md:px-3 md:pb-5">
            <?php foreach ($menu as $m): $on = ($m[1] === $aktif); ?>
              <a href="<?= e(url($m[1])) ?>" class="menu-tautan <?= $on ? 'is-aktif border-klinik' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>>
                <?= ikon($m[2]) ?><span><?= e($m[0]) ?></span>
              </a>
            <?php endforeach; ?>
          </nav>
          <?= $tombolKeluar ?>
        </div>
      </details>
      <script>
        (() => {
          const menu = document.querySelector('.panel-mobile-menu--hamburger');
          if (!menu) return;

          const toggle = menu.querySelector('summary');
          const backdrop = menu.querySelector('.panel-mobile-menu__backdrop');
          const panel = menu.querySelector('.panel-mobile-menu__content');
          const duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 280;
          let currentAnimations = [];
          let transitionId = 0;

          const animateMenu = async (open) => {
            const id = ++transitionId;
            currentAnimations.forEach((animation) => animation.cancel());
            currentAnimations = [];

            if (open) {
              menu.open = true;
              currentAnimations = [
                panel.animate(
                  [{ transform: 'translateX(-100%)' }, { transform: 'translateX(0)' }],
                  { duration, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' }
                ),
                backdrop.animate(
                  [{ opacity: 0 }, { opacity: 1 }],
                  { duration, easing: 'ease-out' }
                ),
              ];
            } else if (menu.open) {
              currentAnimations = [
                panel.animate(
                  [{ transform: 'translateX(0)' }, { transform: 'translateX(-100%)' }],
                  { duration, easing: 'cubic-bezier(0.4, 0, 1, 1)' }
                ),
                backdrop.animate(
                  [{ opacity: 1 }, { opacity: 0 }],
                  { duration, easing: 'ease-in' }
                ),
              ];
            } else {
              return;
            }

            await Promise.all(currentAnimations.map((animation) => animation.finished.catch(() => {})));
            if (id !== transitionId) return;
            currentAnimations = [];
            if (!open) menu.open = false;
          };

          toggle.addEventListener('click', (event) => {
            event.preventDefault();
            animateMenu(!menu.open);
          });

          backdrop.addEventListener('click', () => animateMenu(false));

          menu.addEventListener('toggle', () => {
            toggle.setAttribute('aria-label', menu.open ? 'Tutup menu' : 'Buka menu');
            toggle.setAttribute('aria-expanded', menu.open ? 'true' : 'false');
          });

          document.addEventListener('click', (event) => {
            if (menu.open && !menu.contains(event.target)) {
              animateMenu(false);
            }
          });

          document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && menu.open) {
              animateMenu(false).then(() => toggle.focus());
            }
          });
        })();
      </script>
    </div>
    <div class="panel-desktop-menu">
      <?= $kartuAkun ?>
      <nav class="space-y-1 px-3 pb-5">
        <?php foreach ($menu as $m): $on = ($m[1] === $aktif); ?>
          <a href="<?= e(url($m[1])) ?>" class="menu-tautan <?= $on ? 'is-aktif' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>>
            <?= ikon($m[2]) ?><span><?= e($m[0]) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <?= $tombolKeluar ?>
    </div>
  </aside>
  <main class="min-w-0 flex-1 px-4 py-8 md:px-8 md:py-10">
    <?php
    $lonceng = null;
    if ($user['role'] === 'user') {
        require_once __DIR__ . '/notifikasi.php';
        $lonceng = data_lonceng(db(), $user['id']);
    }
    if ($lonceng !== null):
        $notifBelum   = $lonceng['belum'];
        $notifSetelah = $lonceng['setelah'];
    ?>
      <div class="mb-4 flex justify-end print:hidden">
        <a id="notif-lonceng" href="<?= e(url('user/notifikasi.php')) ?>"
           data-cek-url="<?= e(url('user/notifikasi_cek.php')) ?>" data-setelah="<?= $notifSetelah ?>"
           data-login-url="<?= e(url('auth/login.php')) ?>"
           aria-label="Notifikasi, <?= (int) $notifBelum ?> belum dibaca"
           class="relative inline-flex items-center gap-2 rounded-xl border border-garis bg-white px-3 py-2 text-sm hover:bg-paper">
          <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span>Notifikasi</span>
          <span id="notif-jumlah" <?= $notifBelum > 0 ? '' : 'hidden' ?>
                class="rounded-full bg-bata px-2 py-0.5 font-mono text-[11px] font-semibold text-white"><?= $notifBelum > 99 ? '99+' : (int) $notifBelum ?></span>
        </a>
      </div>
      <script src="<?= e(url('assets/notifikasi.js?v=' . filemtime(__DIR__ . '/../assets/notifikasi.js'))) ?>" defer></script>
    <?php endif; ?>
    <?= tampil_flash() ?>