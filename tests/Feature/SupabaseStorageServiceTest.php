<?php

use App\Services\SupabaseStorageService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('creates a signed receipt URL using the Supabase signing endpoint', function () {
    config([
        'services.supabase.url' => 'https://test.supabase.co',
        'services.supabase.publishable_key' => 'test-publishable-key',
        'services.supabase.receipts_bucket' => 'receipts',
    ]);

    Http::fake([
        'https://test.supabase.co/storage/v1/object/sign/receipts/*' => Http::response([
            'signedURL' => '/object/sign/receipts/user-1/scan-1.jpg?token=test-token',
        ]),
    ]);

    $signedUrl = (new SupabaseStorageService)->createSignedURL(
        'user-1/scan-1.jpg',
        'test-access-token',
        900,
    );

    expect($signedUrl)->toBe(
        'https://test.supabase.co/storage/v1/object/sign/receipts/user-1/scan-1.jpg?token=test-token'
    );

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://test.supabase.co/storage/v1/object/sign/receipts/user-1/scan-1.jpg'
            && $request->data() === ['expiresIn' => 900]
            && $request->hasHeader('apikey', 'test-publishable-key')
            && $request->hasHeader('Authorization', 'Bearer test-access-token');
    });
});
