<?php

use App\Services\FatSecretService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('authenticates with the FatSecret credentials from config', function () {
    config([
        'services.fatsecret.client_id' => 'test-client-id',
        'services.fatsecret.client_secret' => 'test-client-secret',
    ]);
    Cache::forget('fatsecret_access_token');

    Http::fake([
        'oauth.fatsecret.com/*' => Http::response(['access_token' => 'fake-token']),
    ]);

    $token = (new FatSecretService)->getAccessToken();

    expect($token)->toBe('fake-token');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth.fatsecret.com/connect/token'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-client-id:test-client-secret')));
});
