<?php

use App\Http\Controllers\NutritionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/nutrition/search', [NutritionController::class, 'search']);
