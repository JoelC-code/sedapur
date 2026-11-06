<?php

it('menampilkan semua halaman frontend', function (string $uri) {
    $this->get($uri)->assertOk();
})->with([
    '/masuk', '/daftar', '/rumah/mulai',
    '/', '/kulkas', '/kulkas?status=dimasak', '/kulkas?kategori=sayur', '/kulkas/3',
    '/scan', '/scan?jenis=kulkas', '/scan/gagal', '/scan/hasil', '/bahan/manual',
    '/menu', '/menu?hari=2', '/resep', '/resep/1',
    '/belanja', '/gizi', '/gizi?tab=kulkas', '/food-waste', '/riwayat', '/riwayat?aksi=dibuang', '/rumah',
    '/pratinjau/email-pengingat',
]);

it('mengembalikan 404 untuk bahan dan resep yang tidak ada', function () {
    $this->get('/kulkas/999')->assertNotFound();
    $this->get('/resep/999')->assertNotFound();
});
