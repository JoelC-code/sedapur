<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.supabase.url' => 'https://test.supabase.co']);
    Http::preventStrayRequests();
    Http::fake(['test.supabase.co/rest/v1/household_members*' => Http::response([
        ['user_id' => '00000000-0000-4000-8000-00000000000a', 'role' => 'owner', 'joined_at' => '2026-11-12T08:00:00+00:00', 'profiles' => ['full_name' => 'Hans Vere Liem']],
    ])]);
});

it('menampilkan halaman masuk dan daftar untuk tamu', function (string $uri) {
    $this->get($uri)->assertOk();
})->with(['/masuk', '/daftar']);

it('menampilkan semua halaman aplikasi untuk anggota rumah', function (string $uri) {
    $this->withSession(supabaseSession())->get($uri)->assertOk();
})->with([
    '/', '/kulkas', '/kulkas?status=dimasak', '/kulkas?kategori=sayur', '/kulkas/3',
    '/scan', '/scan?jenis=kulkas', '/scan/gagal', '/scan/hasil', '/bahan/manual',
    '/menu', '/menu?hari=2', '/resep', '/resep/1',
    '/belanja', '/gizi', '/gizi?tab=kulkas', '/food-waste', '/riwayat', '/riwayat?aksi=dibuang', '/rumah',
    '/pratinjau/email-pengingat',
]);

it('menampilkan halaman buat atau gabung rumah untuk user tanpa rumah', function () {
    $this->withSession(supabaseSession(household: null))->get('/rumah/mulai')
        ->assertOk()
        ->assertSee('Halo, Hans');
});

it('mengembalikan 404 untuk bahan dan resep yang tidak ada', function () {
    $this->withSession(supabaseSession())->get('/kulkas/999')->assertNotFound();
    $this->withSession(supabaseSession())->get('/resep/999')->assertNotFound();
});
