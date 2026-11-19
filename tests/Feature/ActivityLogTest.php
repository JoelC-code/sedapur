<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.supabase.url' => 'https://test.supabase.co']);
    Http::preventStrayRequests();
    Carbon::setTestNow('2026-11-19 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function fakeActivityLogs(array $rows): void
{
    Http::fake([
        'test.supabase.co/rest/v1/household_members*' => Http::response([
            ['user_id' => '00000000-0000-4000-8000-00000000000a', 'role' => 'owner', 'joined_at' => '2026-11-12T08:00:00+00:00', 'profiles' => ['full_name' => 'Hans Vere Liem']],
        ]),
        'test.supabase.co/rest/v1/activity_logs*' => Http::response($rows),
    ]);
}

it('shows the activity written by the database, grouped per day in WIB', function () {
    fakeActivityLogs([
        ['actor_id' => '00000000-0000-4000-8000-00000000000a', 'action' => 'dipakai', 'item_name' => 'Susu UHT', 'description' => 'Dipakai 250 ml · sisa 750 ml', 'created_at' => '2026-11-19T02:15:00+00:00'],
        ['actor_id' => '00000000-0000-4000-8000-00000000000c', 'action' => 'dibuang', 'item_name' => 'Tahu', 'description' => 'Sisa 1 bungkus', 'created_at' => '2026-11-18T13:00:00+00:00'],
    ]);

    $this->withSession(supabaseSession())->get('/riwayat')
        ->assertOk()
        ->assertSeeInOrder(['Hari ini', 'memakai Susu UHT', '09:15', 'Dipakai 250 ml · sisa 750 ml', 'Kemarin', 'Mantan anggota', 'membuang Tahu', '20:00']);
});

it('filters by action in the query and ignores unknown actions', function () {
    fakeActivityLogs([]);

    $this->withSession(supabaseSession())->get('/riwayat?aksi=dibuang')
        ->assertOk()
        ->assertSee('Belum ada aktivitas dibuang');
    $this->withSession(supabaseSession())->get('/riwayat?aksi=hapus')->assertOk();

    Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'action=eq.dibuang'));
    Http::assertNotSent(fn (Request $request) => str_contains(urldecode($request->url()), 'action=eq.hapus'));
});

it('still shows the page when Supabase fails', function () {
    Http::fake([
        'test.supabase.co/rest/v1/household_members*' => Http::response([]),
        'test.supabase.co/rest/v1/activity_logs*' => Http::response(['message' => 'boom'], 500),
    ]);

    $this->withSession(supabaseSession())->get('/riwayat')->assertOk()->assertSee('Belum ada aktivitas');
});
