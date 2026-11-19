<?php

use App\Exceptions\SupabaseException;
use App\Jobs\ProcessReceiptScan;
use App\Models\ReceiptScan;
use App\Services\SupabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.supabase.url' => 'https://test.supabase.co',
        'services.supabase.publishable_key' => 'sb_publishable_test',
    ]);
    Http::preventStrayRequests();
});

it('uploads a photo into the household folder of a private bucket as the user', function () {
    Http::fake(['test.supabase.co/storage/v1/object/receipts/*' => Http::response(['Key' => 'receipts/7/x.jpg'])]);

    $path = app(SupabaseService::class)->uploadHouseholdPhoto('user-token', 'receipts', 7, UploadedFile::fake()->create('struk.jpg', 20, 'image/jpeg'));

    expect($path)->toMatch('/^7\/[0-9a-f-]{36}\.jpg$/');

    Http::assertSent(fn (Request $request) => $request->url() === "https://test.supabase.co/storage/v1/object/receipts/{$path}"
        && $request->hasHeader('Authorization', 'Bearer user-token')
        && $request->hasHeader('apikey', 'sb_publishable_test')
        && $request->hasHeader('Content-Type', 'image/jpeg'));
});

it('throws when Storage rejects the upload', function () {
    Http::fake(['test.supabase.co/storage/v1/*' => Http::response(['statusCode' => '403', 'error' => 'Unauthorized', 'message' => 'new row violates row-level security policy'], 400)]);

    app(SupabaseService::class)->uploadHouseholdPhoto('user-token', 'receipts', 99, UploadedFile::fake()->create('struk.jpg', 20, 'image/jpeg'));
})->throws(SupabaseException::class, 'row-level security');

it('returns a full signed URL for a private photo', function () {
    Http::fake(['test.supabase.co/storage/v1/object/sign/*' => Http::response(['signedURL' => '/object/sign/receipts/7/x.jpg?token=abc'])]);

    $url = app(SupabaseService::class)->createSignedUrl('user-token', 'receipts', '7/x.jpg');

    expect($url)->toBe('https://test.supabase.co/storage/v1/object/sign/receipts/7/x.jpg?token=abc');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://test.supabase.co/storage/v1/object/sign/receipts/7/x.jpg'
        && $request['expiresIn'] === 300);
});

it('stores a scanned receipt in the receipts bucket under the household folder', function () {
    Queue::fake();
    Http::fake([
        'test.supabase.co/storage/v1/object/sign/*' => Http::response(['signedURL' => '/object/sign/receipts/1/x.jpg?token=abc']),
        'test.supabase.co/storage/v1/object/receipts/*' => Http::response(['Key' => 'receipts/1/x.jpg']),
    ]);

    $this->withSession(supabaseSession())
        ->post(route('scan.upload'), ['receipt' => UploadedFile::fake()->image('struk.jpg')])
        ->assertOk();

    expect(ReceiptScan::sole()->image_path)->toMatch('/^1\/[0-9a-f-]{36}\.jpg$/');
    Queue::assertPushed(ProcessReceiptScan::class);
});

it('rejects a signed URL response without a URL', function () {
    Http::fake(['test.supabase.co/storage/v1/object/sign/*' => Http::response(['message' => 'ok'])]);

    app(SupabaseService::class)->createSignedUrl('user-token', 'receipts', '7/x.jpg');
})->throws(SupabaseException::class);
