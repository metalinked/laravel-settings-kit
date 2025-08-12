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
        
        // Get a specific setting
        Route::get('/{key}', [SettingsKitApiController::class, 'show']);
        
        // Create or update a setting value
        Route::post('/{key}', [SettingsKitApiController::class, 'store']);
        
        // Update a setting value
        Route::put('/{key}', [SettingsKitApiController::class, 'update']);
        
        // Delete a setting value (reset to default)
        Route::delete('/{key}', [SettingsKitApiController::class, 'destroy']);
    });
