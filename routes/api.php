<?php

use Illuminate\Support\Facades\Route;
use Metalinked\LaravelSettingsKit\Http\Controllers\SettingsKitApiController;

/*
|--------------------------------------------------------------------------
| Settings Kit API Routes
|--------------------------------------------------------------------------
|
| These routes provide REST API access to the Settings Kit functionality.
| All routes are protected by the SettingsKitApiAuth middleware.
|
*/

Route::prefix(config('settings-kit.api.prefix', 'api/settings-kit'))
    ->middleware(array_merge(
        config('settings-kit.api.middleware', ['api']),
        ['Metalinked\LaravelSettingsKit\Http\Middleware\SettingsKitApiAuth']
    ))
    ->group(function () {
        // Get all settings
        Route::get('/', [SettingsKitApiController::class, 'index']);
        
        // Get available categories
        Route::get('/categories', [SettingsKitApiController::class, 'categories']);
        
        // Create a new preference
        Route::post('/preferences', [SettingsKitApiController::class, 'createPreference']);
        
        // Global settings routes (for system-wide settings)
        Route::prefix('global')->group(function () {
            // Get a specific global setting
            Route::get('/{key}', [SettingsKitApiController::class, 'showGlobal']);
            
            // Create or update a global setting value
            Route::post('/{key}', [SettingsKitApiController::class, 'storeGlobal']);
            
            // Update a global setting value
            Route::put('/{key}', [SettingsKitApiController::class, 'updateGlobal']);
            
            // Delete a global setting value (reset to default)
            Route::delete('/{key}', [SettingsKitApiController::class, 'destroyGlobal']);
        });
        
        // User settings routes (for user-specific settings)
        Route::prefix('user')->group(function () {
            // Get a specific user setting
            Route::get('/{key}', [SettingsKitApiController::class, 'showUser']);
            
            // Create or update a user setting value
            Route::post('/{key}', [SettingsKitApiController::class, 'storeUser']);
            
            // Update a user setting value
            Route::put('/{key}', [SettingsKitApiController::class, 'updateUser']);
            
            // Delete a user setting value (reset to default)
            Route::delete('/{key}', [SettingsKitApiController::class, 'destroyUser']);
        });
        
        // Legacy routes (backwards compatibility)
        // Get a specific setting (backwards compatibility - defaults to user if authenticated, global otherwise)
        Route::get('/{key}', [SettingsKitApiController::class, 'show']);
        
        // Create or update a setting value (backwards compatibility)
        Route::post('/{key}', [SettingsKitApiController::class, 'store']);
        
        // Update a setting value (backwards compatibility)
        Route::put('/{key}', [SettingsKitApiController::class, 'update']);
        
        // Delete a setting value (backwards compatibility)
        Route::delete('/{key}', [SettingsKitApiController::class, 'destroy']);
    });
