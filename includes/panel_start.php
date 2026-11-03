<?php
require_once __DIR__ . '/menu.php';
require __DIR__ . '/header.php';
$menu  = menu_panel($user['role']);
$aktif = isset($aktif) ? $aktif : '';
?>
<div class="flex flex-1 flex-col md:flex-row">
  <aside class="border-b border-garis bg-white md:w-60 md:shrink-0 md:border-b-0 md:border-r print:hidden">
    <div class="panel-brand-row px-5 py-5">
      <div>
        <p class="font-serif text-xl font-bold text-klinik">Klinik Pintar</p>
        <p class="mt-0.5 font-mono text-[11px] uppercase tracking-widest text-ink/60"><?= e(str_replace('_', ' ', $user['role'])) ?></p>
      </div>
      <details class="panel-mobile-menu panel-mobile-menu--overlay panel-mobile-menu--hamburger">
        <summary class="panel-mobile-menu__toggle" aria-label="Buka menu" aria-expanded="false">
          <span class="panel-mobile-menu__hamburger" aria-hidden="true"></span>
        </summary>
        <div class="panel-mobile-menu__backdrop" aria-hidden="true"></div>
        <div class="panel-mobile-menu__content">
          <nav class="flex gap-1 overflow-x-auto px-3 pb-3 md:block md:space-y-1 md:px-3 md:pb-5">
            <?php foreach ($menu as $m): $on = ($m[1] === $aktif); ?>
              <a href="<?= e(url($m[1])) ?>"
                 class="whitespace-nowrap rounded-xl border-b-2 px-3 py-2 text-sm md:block md:border-b-0 md:border-l-2 <?= $on ? 'border-bata bg-paper font-medium text-klinik' : 'border-transparent hover:bg-paper' ?>">
                <?= e($m[0]) ?>
              </a>
            <?php endforeach; ?>
          </nav>
          <div class="panel-account-tools flex flex-wrap items-center gap-3 border-t border-garis px-5 py-4 text-sm md:block md:space-y-3">
            <p class="font-medium"><?= e($user['nama']) ?></p>
            <a href="<?= e(url('auth/ganti_password.php')) ?>" class="panel-account-link">Ganti password</a>
            <form method="post" action="<?= e(url('auth/logout.php')) ?>">
              <?= csrf_field() ?>
              <button class="action-button border-ink hover:bg-ink hover:text-paper">Keluar</button>
            </form>
          </div>
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
      <nav class="space-y-1 px-3 pb-5">
        <?php foreach ($menu as $m): $on = ($m[1] === $aktif); ?>
          <a href="<?= e(url($m[1])) ?>"
             class="block whitespace-nowrap rounded-xl border-l-2 px-3 py-2 text-sm <?= $on ? 'border-bata bg-paper font-medium text-klinik' : 'border-transparent hover:bg-paper' ?>">
            <?= e($m[0]) ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="space-y-3 border-t border-garis px-5 py-4 text-sm">
        <p class="font-medium"><?= e($user['nama']) ?></p>
        <a href="<?= e(url('auth/ganti_password.php')) ?>" class="block text-klinik underline underline-offset-4">Ganti password</a>
        <form method="post" action="<?= e(url('auth/logout.php')) ?>">
          <?= csrf_field() ?>
          <button class="action-button border-ink hover:bg-ink hover:text-paper">Keluar</button>
        </form>
      </div>
    </div>
  </aside>
  <main class="min-w-0 flex-1 px-4 py-8 md:px-8 md:py-10">
    <?= tampil_flash() ?>