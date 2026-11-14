// node tests/js/karcis_test.js
// Teks sisa antrean harus sama dengan teks_sisa_antrean() di PHP.
var assert = require('assert');
var teksSisa = require('../../assets/notifikasi.js').teksSisa;

assert.strictEqual(teksSisa(0), 'Anda pasien berikutnya', 'nol');
assert.strictEqual(teksSisa(1), '1 pasien lagi sebelum Anda', 'satu');
assert.strictEqual(teksSisa(12), '12 pasien lagi sebelum Anda', 'banyak');

console.log('karcis_test.js: semua lulus');
