<?php

it('menyediakan manifest PWA yang valid beserta semua file ikon PNG dan SVG', function () {
    $manifestPath = public_path('manifest.webmanifest');
    expect($manifestPath)->toBeFile();

    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest)
        ->toHaveKey('name', 'Sedapur')
        ->toHaveKey('start_url', '/')
        ->toHaveKey('display', 'standalone')
        ->toHaveKey('theme_color', '#9a3412');

    $icons = collect($manifest['icons'] ?? []);

    expect($icons->pluck('src')->all())->toContain(
        '/icons/icon-192.png',
        '/icons/icon-512.png',
        '/icons/icon-maskable-512.png',
        '/icon.svg',
    );

    expect($icons->firstWhere('src', '/icons/icon-maskable-512.png')['purpose'] ?? null)->toBe('maskable');

    foreach ([
        'icons/icon-192.png' => [192, 192],
        'icons/icon-512.png' => [512, 512],
        'icons/icon-maskable-512.png' => [512, 512],
        'apple-touch-icon.png' => [180, 180],
    ] as $relativePath => [$width, $height]) {
        $fullPath = public_path($relativePath);
        expect($fullPath)->toBeFile();
        $size = getimagesize($fullPath);
        expect($size)->not->toBeFalse();
        expect([$size[0], $size[1]])->toBe([$width, $height]);
    }
});

it('menyediakan service worker dan halaman offline tanpa men-cache data user', function () {
    $swPath = public_path('sw.js');
    $offlinePath = public_path('offline.html');

    expect($swPath)->toBeFile();
    expect($offlinePath)->toBeFile();

    $sw = (string) file_get_contents($swPath);
    expect($sw)
        ->toContain('OFFLINE_URL = "/offline.html"')
        ->toContain('/build/assets/')
        ->toContain('request.mode === "navigate"')
        ->not->toContain('"/sesi/token"');

    $offline = (string) file_get_contents($offlinePath);
    expect($offline)->toContain('Kamu sedang offline');
});

it('memuat tag manifest dan apple-touch-icon PNG di halaman', function () {
    $this->get('/masuk')
        ->assertOk()
        ->assertSee('manifest.webmanifest', false)
        ->assertSee('apple-touch-icon.png', false)
        ->assertSee('icons/icon-192.png', false);
});
