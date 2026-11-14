// node tests/js/karcis_test.js
// Harus sama dengan teks_sisa_antrean() dan keadaan_karcis() di PHP
// (tests/tampilan_test.php memakai kasus yang sama).
var assert = require('assert');
var m = require('../../assets/notifikasi.js');

assert.strictEqual(m.teksSisa(0), 'Anda pasien berikutnya', 'nol');
assert.strictEqual(m.teksSisa(1), '1 pasien lagi sebelum Anda', 'satu');
assert.strictEqual(m.teksSisa(12), '12 pasien lagi sebelum Anda', 'banyak');

var k = m.keadaanKarcis;
assert.deepStrictEqual(k('menunggu', 5, 3, true), { teks: '5 pasien lagi sebelum Anda', dekat: false, lewat: false }, 'masih jauh');
assert.deepStrictEqual(k('hadir', 3, 3, true), { teks: '3 pasien lagi sebelum Anda', dekat: true, lewat: false }, 'dekat');
assert.deepStrictEqual(k('menunggu', 2, 3, false), { teks: '2 pasien lagi sebelum Anda', dekat: false, lewat: false }, 'bukan hari ini');
assert.deepStrictEqual(k('diperiksa', 0, 3, true), { teks: 'Silakan masuk ke ruang periksa', dekat: true, lewat: false }, 'dipanggil');
assert.deepStrictEqual(k('tidak_hadir', 0, 3, true), { teks: 'Anda ditandai tidak hadir', dekat: false, lewat: true }, 'tidak hadir');
assert.deepStrictEqual(k('selesai', 0, 3, true), { teks: 'Pemeriksaan selesai', dekat: false, lewat: true }, 'selesai');
assert.deepStrictEqual(k('batal', 0, 3, true), { teks: 'Reservasi dibatalkan', dekat: false, lewat: true }, 'batal');

console.log('karcis_test.js: semua lulus');
