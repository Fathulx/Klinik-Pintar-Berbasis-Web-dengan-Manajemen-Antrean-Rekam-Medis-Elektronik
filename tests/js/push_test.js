// node tests/js/push_test.js
var assert = require('assert');
var keadaanPush = require('../../assets/push.js').keadaanPush;

var dasar = { aman: true, didukung: true, ios: false, standalone: false, izin: 'default', berlangganan: false };
function k(ubah) { return keadaanPush(Object.assign({}, dasar, ubah)); }

assert.strictEqual(k({}), 'belum', 'didukung, belum diaktifkan');
assert.strictEqual(k({ izin: 'granted', berlangganan: true }), 'aktif', 'sudah aktif');
assert.strictEqual(k({ izin: 'granted', berlangganan: false }), 'belum', 'izin ada tapi belum berlangganan');
assert.strictEqual(k({ izin: 'denied' }), 'diblokir', 'izin ditolak');
assert.strictEqual(k({ aman: false }), 'butuh_https', 'alamat http biasa');
assert.strictEqual(k({ aman: false, didukung: false }), 'butuh_https', 'https lebih dulu dicek');
assert.strictEqual(k({ ios: true, didukung: false }), 'ios_layar_utama', 'iPhone belum dipasang di Layar Utama');
assert.strictEqual(k({ ios: true, standalone: true, didukung: false }), 'tidak_didukung', 'iOS lama walau di Layar Utama');
assert.strictEqual(k({ didukung: false }), 'tidak_didukung', 'browser lama');
assert.strictEqual(k({ ios: true, standalone: true }), 'belum', 'iPhone di Layar Utama');

console.log('push_test.js: semua lulus');
