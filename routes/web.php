<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FridgeController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReceiptScanController;

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
        Route::controller(FridgeController::class)->group(function () {
            Route::get('/kulkas', 'index')->name('fridge.index');
            Route::post('/kulkas', 'store')->name('fridge.store');
            Route::get('/kulkas/{item}', 'index')->whereNumber('item')->name('fridge.show');
            Route::get('/kulkas/{item}/ubah', 'edit')->whereNumber('item')->name('fridge.edit');
            Route::put('/kulkas/{item}', 'update')->whereNumber('item')->name('fridge.update');
            Route::post('/kulkas/{item}/pakai', 'use')->whereNumber('item')->name('fridge.use');
            Route::patch('/kulkas/{item}/status', 'updateStatus')->whereNumber('item')->name('fridge.status');
        });

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
        Route::get('/items/manual', [PageController::class, 'manualInput'])->name('scan.manual');

        Route::controller(ReceiptScanController::class)->group(function () {
            Route::post('/scan', 'upload')->name('scan.upload');

            Route::get('/scan/{scan}/processing', 'processing')
                ->name('scan.processing');

            Route::get('/scan/{scan}/status', 'status')
                ->name('scan.status');

            Route::get('/scan/{scan}/hasil', 'review')
                ->name('scan.review');

            Route::post('/scan/{scan}/simpan', 'save')
                ->name('scan.save');

            Route::get('/scan/{scan}/gagal', 'failed')
                ->name('scan.failed');
        });

        // Pratinjau email pengingat harian
        Route::get('/pratinjau/email-pengingat', 'emailPreview')->name('preview.email');
    });
});
