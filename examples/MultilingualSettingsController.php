<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Metalinked\LaravelSettingsKit\Facades\Settings;

/**
 * Example controller showing how to use the multilingual features
 */
class MultilingualSettingsController extends Controller
{
    /**
     * Initialize settings with multilingual support
     */
    public function initializeWithTranslations()
    {
        // Create settings with translations in multiple languages
        Settings::createWithTranslations('maintenance_mode', [
            'type' => 'boolean',
            'default_value' => '0',
            'category' => 'system',
            'role' => null,
        ], [
            'ca' => [
                'title' => 'Mode Manteniment',
                'description' => 'Activar el mode de manteniment per a manteniments del sistema'
            ],
            'es' => [
                'title' => 'Modo Mantenimiento', 
                'description' => 'Activar el modo de mantenimiento para mantenimientos del sistema'
            ],
            'en' => [
                'title' => 'Maintenance Mode',
                'description' => 'Enable maintenance mode for system maintenance'
            ],
            'fr' => [
                'title' => 'Mode Maintenance',
                'description' => 'Activer le mode maintenance pour la maintenance du système'
            ]
        ]);

        Settings::createWithTranslations('admin_notify_new_users', [
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'notifications',
            'role' => 'admin',
        ], [
            'ca' => [
                'title' => 'Notificar Nous Usuaris',
                'description' => 'Enviar notificació quan es registri un nou usuari al sistema'
            ],
            'es' => [
                'title' => 'Notificar Nuevos Usuarios',
                'description' => 'Enviar notificación cuando se registre un nuevo usuario en el sistema'
            ],
            'en' => [
                'title' => 'Notify New Users',
                'description' => 'Send notification when a new user registers in the system'
            ],
            'fr' => [
                'title' => 'Notifier Nouveaux Utilisateurs',
                'description' => 'Envoyer une notification quand un nouvel utilisateur s\'inscrit'
            ]
        ]);

        Settings::createWithTranslations('max_file_size', [
            'type' => 'integer',
            'default_value' => '10',
            'category' => 'uploads',
            'role' => null,
        ], [
            'ca' => [
                'title' => 'Mida Màxima d\'Arxiu',
                'description' => 'Mida màxima dels arxius que es poden pujar (en MB)'
            ],
            'es' => [
                'title' => 'Tamaño Máximo de Archivo',
                'description' => 'Tamaño máximo de los archivos que se pueden subir (en MB)'
            ],
            'en' => [
                'title' => 'Maximum File Size',
                'description' => 'Maximum size of files that can be uploaded (in MB)'
            ],
            'fr' => [
                'title' => 'Taille Maximale de Fichier',
                'description' => 'Taille maximale des fichiers pouvant être téléchargés (en MB)'
            ]
        ]);

        Settings::createWithTranslations('preferred_language', [
            'type' => 'select',
            'default_value' => 'ca',
            'category' => 'appearance',
            'role' => null,
            'options' => json_encode([
                'ca' => 'Català',
                'es' => 'Español', 
                'en' => 'English',
                'fr' => 'Français'
            ]),
        ], [
            'ca' => [
                'title' => 'Idioma Preferit',
                'description' => 'Selecciona l\'idioma de la interfície d\'usuari'
            ],
            'es' => [
                'title' => 'Idioma Preferido',
                'description' => 'Selecciona el idioma de la interfaz de usuario'
            ],
            'en' => [
                'title' => 'Preferred Language',
                'description' => 'Select the user interface language'
            ],
            'fr' => [
                'title' => 'Langue Préférée',
                'description' => 'Sélectionner la langue de l\'interface utilisateur'
            ]
        ]);

        return redirect()->back()->with('success', 'Settings with translations initialized!');
    }

    /**
     * Show settings form with proper translations
     */
    public function show(Request $request)
    {
        $locale = $request->get('locale', app()->getLocale());
        
        // Get all settings with translations for the current locale
        $settingsWithTranslations = Settings::allWithTranslations($locale);
        
        // Organize by category for better display
        $categorizedSettings = [];
        foreach ($settingsWithTranslations as $key => $setting) {
            $category = $setting['category'] ?? 'general';
            $categorizedSettings[$category][$key] = $setting;
        }

        return view('admin.multilingual-settings', [
            'settings' => $categorizedSettings,
            'currentLocale' => $locale,
            'availableLocales' => ['ca', 'es', 'en', 'fr']
        ]);
    }

    /**
     * Show user settings with translations 
     */
    public function showUserSettings(Request $request)
    {
        $user = $request->user();
        $locale = $request->get('locale', app()->getLocale());
        
        // Get only settings that users can modify (no role or user role)
        $userSettings = Settings::allWithTranslations($locale, null, $user->id);
        
        // Filter out admin-only settings
        $userSettings = array_filter($userSettings, function($setting) {
            return empty($setting['role']) || $setting['role'] !== 'admin';
        });

        return view('user.settings', [
            'settings' => $userSettings,
            'currentLocale' => $locale,
            'user' => $user
        ]);
    }

    /**
     * Add translations to existing settings
     */
    public function addTranslations(Request $request)
    {
        $key = $request->input('setting_key');
        $translations = $request->input('translations', []);

        try {
            Settings::addTranslations($key, $translations);
            return response()->json(['success' => true, 'message' => 'Translations added successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Example of how to create a settings form with translations
     */
    public function createSettingsForm()
    {
        // This would typically be in a view, but showing the logic here
        $categories = Settings::getCategories();
        $currentLocale = app()->getLocale();
        
        $formData = [];
        
        foreach ($categories as $category) {
            $categorySettings = Settings::getByCategory($category);
            
            foreach ($categorySettings as $key => $setting) {
                $formData[$category][] = [
                    'key' => $key,
                    'label' => Settings::label($key, $currentLocale),
                    'description' => Settings::description($key, $currentLocale),
                    'type' => $setting['type'],
                    'value' => $setting['value'],
                    'options' => $setting['options'] ?? null,
                    'required' => $setting['required'] ?? false,
                ];
            }
        }

        return view('admin.settings-form', compact('formData', 'currentLocale'));
    }

    /**
     * Example of dynamic settings creation with immediate translations
     */
    public function createDynamicSetting(Request $request)
    {
        $request->validate([
            'key' => 'required|string|unique:preferences,key',
            'type' => 'required|in:string,boolean,integer,json,select',
            'category' => 'required|string',
            'default_value' => 'required',
            'translations' => 'required|array',
            'translations.*.title' => 'required|string',
            'translations.*.description' => 'nullable|string',
        ]);

        try {
            $preference = Settings::createWithTranslations(
                $request->input('key'),
                [
                    'type' => $request->input('type'),
                    'default_value' => $request->input('default_value'),
                    'category' => $request->input('category'),
                    'role' => $request->input('role'),
                    'options' => $request->input('options') ? json_encode($request->input('options')) : null,
                ],
                $request->input('translations')
            );

            if ($preference) {
                return redirect()->back()->with('success', 'Setting created with translations!');
            } else {
                return redirect()->back()->with('error', 'Setting already exists');
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error creating setting: ' . $e->getMessage());
        }
    }
}
