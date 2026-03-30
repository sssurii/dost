<?php

use App\Livewire\Dashboard\ProgressDashboard;
use App\Livewire\Demo;
use App\Livewire\Settings\AudioRetention;
use App\Livewire\Voice\RecordingButton;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', RecordingButton::class)->name('dashboard');
    Route::get('progress', ProgressDashboard::class)->name('progress');
    Route::get('settings/privacy', AudioRetention::class)->name('settings.privacy');
});

Route::prefix('demo')->group(function () {
    Route::view('/', 'demo.index')->name('demo.index');
    Route::get('/writer', Demo\ContentWriter::class)->name('demo.writer');
    Route::get('/blog', Demo\BlogGenerator::class)->name('demo.blog');
    Route::get('/podcast', Demo\PodcastToolkit::class)->name('demo.podcast');
    Route::get('/helpdesk', Demo\SmartHelpDesk::class)->name('demo.helpdesk');
    Route::get('/analyst', Demo\ContentAnalyst::class)->name('demo.analyst');
    Route::get('/alerts', Demo\AlertWriter::class)->name('demo.alerts');
    Route::get('/studio', Demo\BlogStudio::class)->name('demo.studio');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
