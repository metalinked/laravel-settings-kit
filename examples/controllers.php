<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Metalinked\LaravelSettingsKit\Facades\Settings;

class UserSettingsController extends Controller
{
    /**
     * Mostrar les preferències de l'usuari organitzades per categories.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        
        // Get all available categories for this user
        $categories = Settings::getCategories();
        $userSettings = [];
        
        foreach ($categories as $category) {
            // Get category settings with user values
            $categorySettings = Settings::getByCategory($category, $user->id);
            
            if (!empty($categorySettings)) {
                $userSettings[$category] = $categorySettings;
            }
        }
        
        return view('settings.user', compact('userSettings'));
    }
    
    /**
     * Actualitzar les preferències de l'usuari.
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $settings = $request->get('settings', []);
        
        foreach ($settings as $key => $value) {
            if (Settings::exists($key)) {
                // Validate that user can modify this preference
                if ($this->canUserModifySettings($key, $user)) {
                    Settings::set($key, $value, $user->id);
                }
            }
        }
        
        return redirect()->back()->with('success', 'Preferències actualitzades correctament!');
    }
    
    private function canUserModifySettings(string $key, $user): bool
    {
        // Logic to validate permissions (optional)
        return true; // For simplicity
    }
}

class AdminSettingsController extends Controller
{
    /**
     * Panel d'administració per gestionar configuracions globals.
     */
    public function globalSettings(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            abort(403);
        }
        
        // Configuracions globals (role = null)
        $globalSettings = Settings::all();
        
        // Admin-specific configurations
        $adminSettings = Settings::all('admin', $user->id);
        
        return view('admin.settings', compact('globalSettings', 'adminSettings'));
    }
    
    /**
     * Actualitzar configuracions globals.
     */
    public function updateGlobal(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            abort(403);
        }
        
        $settings = $request->get('global_settings', []);
        
        foreach ($settings as $key => $value) {
            if (Settings::exists($key)) {
                Settings::set($key, $value); // Sense user_id = global
            }
        }
        
        return redirect()->back()->with('success', 'Configuracions globals actualitzades!');
    }
}
