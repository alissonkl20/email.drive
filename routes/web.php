<?php

use App\Http\Controllers\EmailReportController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::post('email-report', EmailReportController::class)->name('email-report');
});

require __DIR__.'/settings.php';
