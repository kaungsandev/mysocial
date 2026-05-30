<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/', 'home.index')->name('home');
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::resource('posts', App\Http\Controllers\PostController::class)->only('index', 'show');
