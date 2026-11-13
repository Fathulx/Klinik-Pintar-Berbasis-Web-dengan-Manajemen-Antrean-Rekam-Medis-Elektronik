<?php
// Panel "Notifikasi di perangkat ini" untuk pasien. Tampil hanya bila kunci
// Web Push sudah diatur. Isinya diatur oleh assets/push.js sesuai keadaan
// browser (didukung, diizinkan, sudah aktif, dll.).
require_once __DIR__ . '/webpush.php';
if (!webpush_aktif()) {
    return;
}
?>
<section id="push-panel" hidden
         data-kunci="<?= e(WEBPUSH_PUBLIC_KEY) ?>"
         data-sw="<?= e(url('sw.js')) ?>"
         data-simpan="<?= e(url('user/push_langganan.php')) ?>"
         data-csrf="<?= e(csrf_token()) ?>"
         aria-live="polite"
         class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-garis bg-white px-4 py-3">
  <div class="min-w-0 flex-1">
    <p class="text-sm font-semibold text-ink">Notifikasi di perangkat ini</p>
    <p class="mt-0.5 text-sm text-ink/70" data-push-keterangan></p>
  </div>
  <button type="button" data-push-tombol hidden class="px-4 py-2 text-sm font-medium"></button>
</section>
<script src="<?= e(url('assets/push.js?v=' . filemtime(__DIR__ . '/../assets/push.js'))) ?>" defer></script>
