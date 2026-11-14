// node tests/js/antrean_langsung_test.js
var assert = require('assert');
var barisBerubah = require('../../assets/antrean_langsung.js').barisBerubah;

var lama = { '1': 'menunggu', '2': 'hadir' };

assert.deepStrictEqual(barisBerubah(lama, { '1': 'menunggu', '2': 'hadir' }), [], 'tidak ada perubahan');
assert.deepStrictEqual(barisBerubah(lama, { '1': 'menunggu', '2': 'hadir', '3': 'menunggu' }), ['3'], 'reservasi baru');
assert.deepStrictEqual(barisBerubah(lama, { '1': 'hadir', '2': 'hadir' }), ['1'], 'status berubah');
assert.deepStrictEqual(barisBerubah(lama, { '2': 'hadir' }), [], 'baris hilang tidak disorot');
assert.deepStrictEqual(barisBerubah({}, { '5': 'menunggu' }), ['5'], 'dari kosong');

console.log('antrean_langsung_test.js: semua lulus');
