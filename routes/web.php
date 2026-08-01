<?php

use App\Http\Controllers\AlgorithmSelectionController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PostController;
use App\Http\Middleware\ForceAlgorithmSelection;
use App\Http\Middleware\RedirectIfFirstTime;
use Illuminate\Support\Facades\Route;

// routes/web.php — add above the auth group, or inside it but excluded via the middleware check above

Route::middleware(['auth'])->group(function () {
    Route::get('/algorithm', [AlgorithmSelectionController::class, 'show'])->name('algorithm.select');
    Route::post('/algorithm', [AlgorithmSelectionController::class, 'store'])->name('algorithm.select.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/', 'home.index')
        ->middleware([RedirectIfFirstTime::class, ForceAlgorithmSelection::class])
        ->name('home');
    Route::get('/onboarding/interests', [OnboardingController::class, 'interests'])->name('onboarding-interests');
    Route::post('/onboarding/interests', [OnboardingController::class, 'storeInterests'])->name('onboarding.interests.store');
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::resource('posts', PostController::class)->only('index', 'show');
