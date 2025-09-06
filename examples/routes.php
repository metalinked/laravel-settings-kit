<?php

// routes/web.php

use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\AdminSettingsController;

// Routes for normal users
Route::middleware(['auth'])->group(function () {
    Route::get('/settings', [UserSettingsController::class, 'show'])->name('user.settings');
    Route::post('/settings', [UserSettingsController::class, 'update'])->name('user.settings.update');
});

// Routes for admins
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/settings', [AdminSettingsController::class, 'globalSettings'])->name('admin.settings');
    Route::post('/settings/global', [AdminSettingsController::class, 'updateGlobal'])->name('admin.settings.global.update');
    Route::post('/settings/personal', [AdminSettingsController::class, 'updatePersonal'])->name('admin.settings.personal.update');
});
