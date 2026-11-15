// node tests/js/lihat_password_test.js
var assert = require('assert');
var alihTampil = require('../../assets/lihat_password.js').alihTampil;

function kolom(tipe) { return { type: tipe }; }
function tombol() {
  var atribut = {};
  return { setAttribute: function (k, v) { atribut[k] = v; }, atribut: atribut };
}

var k = kolom('password'), t = tombol();
assert.strictEqual(alihTampil(k, t), true, 'kini terlihat');
assert.strictEqual(k.type, 'text', 'jenis kolom berubah ke teks');
assert.strictEqual(t.atribut['aria-pressed'], 'true', 'tombol tertekan');
assert.strictEqual(t.atribut['aria-label'], 'Sembunyikan password', 'label berganti');

assert.strictEqual(alihTampil(k, t), false, 'kini tersembunyi lagi');
assert.strictEqual(k.type, 'password', 'kembali ke password');
assert.strictEqual(t.atribut['aria-pressed'], 'false', 'tombol dilepas');
assert.strictEqual(t.atribut['aria-label'], 'Tampilkan password', 'label kembali');

console.log('lihat_password_test.js: semua lulus');
