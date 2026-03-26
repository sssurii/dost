<?php

use App\Livewire\Dashboard\ProgressDashboard;
use App\Livewire\Settings\AudioRetention;
use App\Livewire\Voice\RecordingButton;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', RecordingButton::class)->name('dashboard');
    Route::get('progress', ProgressDashboard::class)->name('progress');
    Route::get('settings/privacy', AudioRetention::class)->name('settings.privacy');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
