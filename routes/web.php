<?php

use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PostController;
use App\Http\Middleware\RedirectIfFirstTime;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/', 'home.index')->middleware(RedirectIfFirstTime::class)->name('home');
    Route::get('/onboarding/interests', [OnboardingController::class, 'interests'])->name('onboarding-interests');
    Route::post('/onboarding/interests', [OnboardingController::class, 'storeInterests'])->name('onboarding.interests.store');
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::resource('posts', PostController::class)->only('index', 'show');
