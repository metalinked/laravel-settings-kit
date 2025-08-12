<?php

// Example usage of Laravel Settings Kit

use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Models\Preference;
use Metalinked\LaravelSettingsKit\Models\PreferenceContent;

// 1. Create some basic settings
$preferences = [
    [
        'key' => 'allow_comments',
        'type' => 'boolean',
        'default_value' => '1',
        'category' => 'general',
        'role' => null,
    ],
    [
        'key' => 'site_maintenance',
        'type' => 'boolean',
        'default_value' => '0',
        'category' => 'general',
        'role' => 'admin',
    ],
    [
        'key' => 'posts_per_page',
        'type' => 'integer',
        'default_value' => '10',
        'category' => 'display',
        'role' => null,
    ],
    [
        'key' => 'theme_config',
        'type' => 'json',
        'default_value' => json_encode(['dark_mode' => false, 'sidebar' => 'left']),
        'category' => 'appearance',
        'role' => null,
    ],
    [
        'key' => 'user_language',
        'type' => 'select',
        'default_value' => 'en',
        'category' => 'localization',
        'role' => null,
        'options' => json_encode([
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch'
        ]),
    ],
    [
        'key' => 'email_notifications',
        'type' => 'boolean',
        'default_value' => '1',
        'category' => 'notifications',
        'role' => null,
    ]
];

foreach ($preferences as $prefData) {
    Settings::create($prefData);
}

// 2. Add translations for settings
$translations = [
    'allow_comments' => [
        'en' => ['title' => 'Allow Comments', 'text' => 'Enable or disable comments on posts and pages'],
        'es' => ['title' => 'Permitir Comentarios', 'text' => 'Activar o desactivar comentarios en publicaciones y páginas'],
        'ca' => ['title' => 'Permetre Comentaris', 'text' => 'Activar o desactivar comentaris en publicacions i pàgines'],
    ],
    'site_maintenance' => [
        'en' => ['title' => 'Maintenance Mode', 'text' => 'Put the site in maintenance mode'],
        'es' => ['title' => 'Modo Mantenimiento', 'text' => 'Poner el sitio en modo mantenimiento'],
        'ca' => ['title' => 'Mode Manteniment', 'text' => 'Posar el lloc en mode manteniment'],
    ],
    'posts_per_page' => [
        'en' => ['title' => 'Posts per Page', 'text' => 'Number of posts to display per page'],
        'es' => ['title' => 'Publicaciones por Página', 'text' => 'Número de publicaciones a mostrar por página'],
        'ca' => ['title' => 'Publicacions per Pàgina', 'text' => 'Nombre de publicacions a mostrar per pàgina'],
    ],
    'theme_config' => [
        'en' => ['title' => 'Theme Configuration', 'text' => 'Advanced theme settings and appearance options'],
        'es' => ['title' => 'Configuración del Tema', 'text' => 'Configuración avanzada del tema y opciones de apariencia'],
        'ca' => ['title' => 'Configuració del Tema', 'text' => 'Configuració avançada del tema i opcions d\'aparença'],
    ],
    'user_language' => [
        'en' => ['title' => 'Language', 'text' => 'Preferred language for the interface'],
        'es' => ['title' => 'Idioma', 'text' => 'Idioma preferido para la interfaz'],
        'ca' => ['title' => 'Idioma', 'text' => 'Idioma preferit per a la interfície'],
    ],
    'email_notifications' => [
        'en' => ['title' => 'Email Notifications', 'text' => 'Receive email notifications for important events'],
        'es' => ['title' => 'Notificaciones por Email', 'text' => 'Recibir notificaciones por email para eventos importantes'],
        'ca' => ['title' => 'Notificacions per Email', 'text' => 'Rebre notificacions per email per a esdeveniments importants'],
    ],
];

foreach ($translations as $key => $langs) {
    $preference = Preference::where('key', $key)->first();
    if ($preference) {
        foreach ($langs as $lang => $content) {
            PreferenceContent::create([
                'preference_id' => $preference->id,
                'lang' => $lang,
                'title' => $content['title'],
                'text' => $content['text'],
            ]);
        }
    }
}

// 3. Basic usage examples

// Get global settings
$allowComments = Settings::get('allow_comments'); // true
$postsPerPage = Settings::get('posts_per_page'); // 10
$themeConfig = Settings::get('theme_config'); // ['dark_mode' => false, 'sidebar' => 'left']

// Set global settings
Settings::set('allow_comments', false);
Settings::set('posts_per_page', 15);
Settings::set('theme_config', ['dark_mode' => true, 'sidebar' => 'right']);

// User-specific settings (user ID = 1)
$userId = 1;

// Set user preferences
Settings::set('email_notifications', false, $userId);
Settings::set('user_language', 'es', $userId);
Settings::set('theme_config', ['dark_mode' => true, 'sidebar' => 'left'], $userId);

// Get user preferences
$userNotifications = Settings::get('email_notifications', $userId); // false
$userLanguage = Settings::get('user_language', $userId); // 'es'
$userTheme = Settings::get('theme_config', $userId); // ['dark_mode' => true, 'sidebar' => 'left']

// Check boolean settings
if (Settings::isEnabled('allow_comments')) {
    echo "Comments are enabled globally\n";
}

if (!Settings::isEnabled('email_notifications', $userId)) {
    echo "User $userId has disabled email notifications\n";
}

// Get translated labels and descriptions
$label = Settings::label('allow_comments', 'ca'); // "Permetre Comentaris"
$description = Settings::description('allow_comments', 'ca'); // "Activar o desactivar comentaris..."

// Get all settings for a role
$globalSettings = Settings::all(); // All global settings
$adminSettings = Settings::all('admin'); // Global + admin settings
$userSettings = Settings::all(null, $userId); // Global settings with user values

// Get settings by category
$generalSettings = Settings::getByCategory('general');
$notificationSettings = Settings::getByCategory('notifications', $userId);

// Reset user preference to default
Settings::forget('email_notifications', $userId);

// 4. Advanced usage in controllers

class SettingsController extends Controller
{
    public function getUserSettings(Request $request)
    {
        $user = $request->user();
        
        // Get all user settings organized by category
        $categories = Settings::getCategories();
        $userSettings = [];
        
        foreach ($categories as $category) {
            $userSettings[$category] = Settings::getByCategory($category, $user->id);
        }
        
        return view('settings.user', compact('userSettings'));
    }
    
    public function updateUserSettings(Request $request)
    {
        $user = $request->user();
        $settings = $request->get('settings', []);
        
        foreach ($settings as $key => $value) {
            if (Settings::exists($key)) {
                Settings::set($key, $value, $user->id);
            }
        }
        
        return redirect()->back()->with('success', 'Settings updated successfully!');
    }
    
    public function getAdminSettings()
    {
        // Only get settings that admins can modify
        $adminSettings = Settings::all('admin');
        
        return view('admin.settings', compact('adminSettings'));
    }
}

// 5. Usage in Blade templates

/*
// resources/views/settings/user.blade.php
@foreach($userSettings as $category => $settings)
    <div class="setting-category">
        <h3>{{ ucfirst($category) }}</h3>
        
        @foreach($settings as $key => $setting)
            <div class="setting-item">
                <label>{{ Settings::label($key) }}</label>
                <p class="text-muted">{{ Settings::description($key) }}</p>
                
                @if($setting['type'] === 'boolean')
                    <input type="checkbox" name="settings[{{ $key }}]" 
                           value="1" {{ $setting['value'] ? 'checked' : '' }}>
                           
                @elseif($setting['type'] === 'select')
                    <select name="settings[{{ $key }}]">
                        @foreach($setting['options'] as $optionValue => $optionLabel)
                            <option value="{{ $optionValue }}" 
                                    {{ $setting['value'] == $optionValue ? 'selected' : '' }}>
                                {{ $optionLabel }}
                            </option>
                        @endforeach
                    </select>
                    
                @else
                    <input type="{{ $setting['type'] === 'integer' ? 'number' : 'text' }}" 
                           name="settings[{{ $key }}]" 
                           value="{{ $setting['value'] }}">
                @endif
            </div>
        @endforeach
    </div>
@endforeach
*/

// 6. Using in middleware

class MaintenanceModeMiddleware
{
    public function handle($request, Closure $next)
    {
        if (Settings::isEnabled('site_maintenance') && !$request->user()?->isAdmin()) {
            return response()->view('maintenance');
        }
        
        return $next($request);
    }
}

// 7. Cache management

// Clear all settings cache
Settings::clearAllCache();

// 8. Import/Export via Artisan commands

/*
// Export all settings
php artisan settings:export --file=settings.json

// Export only admin settings
php artisan settings:export --role=admin --file=admin-settings.json

// Export by category
php artisan settings:export --category=notifications --file=notifications.json

// Import settings
php artisan settings:import settings.json

// Import with force overwrite
php artisan settings:import settings.json --force

// Dry run to see what would be imported
php artisan settings:import settings.json --dry-run
*/
