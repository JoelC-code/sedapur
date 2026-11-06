<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// Tahap frontend: semua halaman memakai data contoh (App\Support\DemoData).

Route::controller(PageController::class)->group(function () {
    // Auth & rumah
    Route::get('/masuk', 'login')->name('login');
    Route::get('/daftar', 'register')->name('register');
    Route::get('/rumah/mulai', 'householdSetup')->name('household.setup');

    // Aplikasi
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

    // Pratinjau email pengingat harian
    Route::get('/pratinjau/email-pengingat', 'emailPreview')->name('preview.email');
});
