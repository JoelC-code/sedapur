<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// Auth: Supabase Auth (email + kata sandi)
Route::middleware('supabase.guest')->controller(AuthController::class)->group(function () {
    Route::get('/masuk', 'showLogin')->name('login');
    Route::post('/masuk', 'login')->middleware('throttle:6,1')->name('login.store');
    Route::get('/daftar', 'showRegister')->name('register');
    Route::post('/daftar', 'register')->middleware('throttle:6,1')->name('register.store');
});

Route::middleware('supabase.auth')->group(function () {
    Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');

    // Rumah: buat baru atau gabung dengan kode undangan
    Route::controller(HouseholdController::class)->group(function () {
        Route::get('/rumah/mulai', 'setup')->name('household.setup');
        Route::post('/rumah', 'store')->name('household.store');
        Route::post('/rumah/gabung', 'join')->middleware('throttle:10,1')->name('household.join');
    });

    // Aplikasi: halaman masih memakai data contoh (App\Support\DemoData) sampai fiturnya tersambung.
    Route::middleware('household')->controller(PageController::class)->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/kulkas', 'fridge')->name('fridge.index');
        Route::get('/kulkas/{item}', 'fridge')->whereNumber('item')->name('fridge.show');

        Route::get('/scan', 'scanProcessing')->name('scan.processing');
        Route::get('/scan/gagal', 'scanFailed')->name('scan.failed');
        Route::get('/scan/hasil', 'scanReview')->name('scan.review');
        Route::get('/bahan/manual', 'manualInput')->name('items.manual');

        Route::get('/menu', 'menu')->name('menu.index');
        Route::get('/resep', 'recipes')->name('recipes.index');
        Route::get('/resep/{recipe}', 'recipe')->whereNumber('recipe')->name('recipes.show');

        Route::get('/belanja', 'shopping')->name('shopping.index');
        Route::get('/gizi', 'nutrition')->name('nutrition.index');
        Route::get('/food-waste', 'waste')->name('waste.index');
        Route::get('/riwayat', 'activity')->name('activity.index');
        Route::get('/rumah', 'householdSettings')->name('household.settings');
        Route::delete('/rumah/anggota/{userId}', [HouseholdController::class, 'removeMember'])->whereUuid('userId')->name('household.members.destroy');
        Route::delete('/rumah/keluar', [HouseholdController::class, 'leave'])->name('household.leave');

        // Pratinjau email pengingat harian
        Route::get('/pratinjau/email-pengingat', 'emailPreview')->name('preview.email');
    });
});
