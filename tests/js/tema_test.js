// node tests/js/tema_test.js
var assert = require('assert');
var temaAwal = require('../../assets/tema.js').temaAwal;

assert.strictEqual(temaAwal(null, false), 'terang', 'tanpa pilihan, perangkat terang');
assert.strictEqual(temaAwal(null, true), 'gelap', 'tanpa pilihan, perangkat gelap');
assert.strictEqual(temaAwal('terang', true), 'terang', 'pilihan pengguna menang');
assert.strictEqual(temaAwal('gelap', false), 'gelap', 'pilihan pengguna menang');
assert.strictEqual(temaAwal('ungu', true), 'gelap', 'nilai tak dikenal diabaikan');

console.log('tema_test.js: semua lulus');
