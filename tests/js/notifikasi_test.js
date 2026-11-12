// Jalankan: node tests/js/notifikasi_test.js
const assert = require('assert');
const { nilaiRespons } = require('../../assets/notifikasi.js');

const kasus = [
  [200, 'application/json; charset=utf-8', false, 'data'],
  [401, 'application/json; charset=utf-8', false, 'sesi_habis'],
  [200, 'text/html; charset=UTF-8', true, 'sesi_habis'],   // dialihkan ke login/ganti password
  [403, 'text/html', false, 'sesi_habis'],
  [500, 'text/html', false, 'coba_lagi'],
  [502, '', false, 'coba_lagi'],
  [503, 'text/html', false, 'coba_lagi'],
];

let gagal = 0;
for (const [status, tipe, dialihkan, harap] of kasus) {
  try {
    assert.strictEqual(nilaiRespons(status, tipe, dialihkan), harap);
    console.log(`OK     ${status} ${tipe || '(kosong)'} -> ${harap}`);
  } catch (e) {
    gagal++;
    console.log(`GAGAL  ${status} ${tipe}: ${e.message}`);
  }
}
process.exit(gagal ? 1 : 0);
