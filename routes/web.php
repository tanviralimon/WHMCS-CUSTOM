<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('client.dashboard');
});

// Old routes redirect to new client.* routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('client.dashboard'))->name('dashboard');
});

require __DIR__.'/auth.php';
