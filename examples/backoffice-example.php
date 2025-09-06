<?php

// Practical example for a backoffice

use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Models\Preference;

// 1. GLOBAL SETTINGS (manageable by admins)
$globalSettings = [
    // Access control
    [
        'key' => 'registration_open',
        'type' => 'boolean',
        'default_value' => '1',
        'category' => 'access_control',
        'role' => null, // Global
    ],
    [
        'key' => 'contact_form_enabled',
        'type' => 'boolean', 
        'default_value' => '1',
        'category' => 'frontend',
        'role' => null,
    ],
    [
        'key' => 'maintenance_message',
        'type' => 'string',
        'default_value' => 'Site under maintenance',
        'category' => 'frontend',
        'role' => null,
    ],
];

// 2. USER PREFERENCES (normal users)
$userSettings = [
    // Notificacions
    [
        'key' => 'email_notifications',
        'type' => 'boolean',
        'default_value' => '1',
        'category' => 'notifications',
        'role' => null,
    ],
    [
        'key' => 'marketing_emails',
        'type' => 'boolean',
        'default_value' => '0',
        'category' => 'notifications', 
        'role' => null,
    ],
    
    // Privacitat
    [
        'key' => 'profile_public',
        'type' => 'boolean',
        'default_value' => '1',
        'category' => 'privacy',
        'role' => null,
    ],
    [
        'key' => 'show_email_to_others',
        'type' => 'boolean',
        'default_value' => '0',
        'category' => 'privacy',
        'role' => null,
    ],
    [
        'key' => 'allow_data_export',
        'type' => 'boolean',
        'default_value' => '1',
        'category' => 'privacy',
        'role' => null,
    ],
];

// 3. ADMIN-ONLY SETTINGS
$adminSettings = [
    // Notificacions admin
    [
        'key' => 'notify_new_users',
        'type' => 'boolean',
        'default_value' => '1',
        'category' => 'admin_notifications',
        'role' => 'admin',
    ],
    [
        'key' => 'notify_new_orders',
        'type' => 'boolean',
        'default_value' => '1', 
        'category' => 'admin_notifications',
        'role' => 'admin',
    ],
    [
        'key' => 'notify_user_actions',
        'type' => 'select',
        'default_value' => 'important',
        'category' => 'admin_notifications',
        'role' => 'admin',
        'options' => json_encode([
            'none' => 'Cap notificació',
            'important' => 'Només importants',
            'all' => 'Totes les accions'
        ]),
    ],
    
    // Data access
    [
        'key' => 'can_export_all_data',
        'type' => 'boolean',
        'default_value' => '0',
        'category' => 'data_access',
        'role' => 'admin',
    ],
];

// Create all preferences
$allSettings = array_merge($globalSettings, $userSettings, $adminSettings);

foreach ($allSettings as $setting) {
    Settings::create($setting);
}

// USAGE EXAMPLES IN CONTROLLERS

class UserController extends Controller
{
    public function register(Request $request)
    {
        // Check if registration is open
        if (!Settings::isEnabled('registration_open')) {
            return redirect()->back()->with('error', 'Registrations are currently closed');
        }
        
        // Processar registre...
        $user = User::create($request->validated());
        
        // Notify admins if they have the preference enabled
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            if (Settings::isEnabled('notify_new_users', $admin->id)) {
                // Send notification to this admin
                $admin->notify(new NewUserRegistered($user));
            }
        }
    }
}

class SettingsController extends Controller 
{
    public function userSettings(Request $request)
    {
        $user = $request->user();
        
        // Get user preferences by categories
        $notifications = Settings::getByCategory('notifications', $user->id);
        $privacy = Settings::getByCategory('privacy', $user->id);
        
        return view('user.settings', compact('notifications', 'privacy'));
    }
    
    public function updateUserSettings(Request $request)
    {
        $user = $request->user();
        $settings = $request->get('settings', []);
        
        foreach ($settings as $key => $value) {
            Settings::set($key, $value, $user->id);
        }
        
        return redirect()->back()->with('success', 'Preferències actualitzades!');
    }
    
    public function adminSettings(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            abort(403);
        }
        
        // Global settings they can modify
        $globalSettings = Settings::all();
        
        // Personal admin preferences
        $adminNotifications = Settings::getByCategory('admin_notifications', $user->id);
        $dataAccess = Settings::getByCategory('data_access', $user->id);
        
        return view('admin.settings', compact('globalSettings', 'adminNotifications', 'dataAccess'));
    }
}

// MIDDLEWARE TO CHECK ACCESS

class CheckFeatureEnabled
{
    public function handle($request, Closure $next, $feature)
    {
        if (!Settings::isEnabled($feature)) {
            if ($feature === 'contact_form_enabled') {
                return redirect()->route('home')->with('error', 'Contact form is currently disabled');
            }
            
            abort(404);
        }
        
        return $next($request);
    }
}

// Usage in routes:
// Route::get('/contact', [ContactController::class, 'show'])->middleware('feature:contact_form_enabled');

// BLADE TEMPLATES

/*
// resources/views/user/settings.blade.php

<form method="POST" action="{{ route('user.settings.update') }}">
    @csrf
    
    <h3>Notificacions</h3>
    @foreach($notifications as $key => $setting)
        <div class="form-check">
            <input type="checkbox" name="settings[{{ $key }}]" value="1" 
                   {{ $setting['value'] ? 'checked' : '' }}>
            <label>{{ Settings::label($key) }}</label>
            <small class="text-muted">{{ Settings::description($key) }}</small>
        </div>
    @endforeach
    
    <h3>Privacitat</h3>
    @foreach($privacy as $key => $setting)
        <div class="form-check">
            <input type="checkbox" name="settings[{{ $key }}]" value="1"
                   {{ $setting['value'] ? 'checked' : '' }}>
            <label>{{ Settings::label($key) }}</label>
            <small class="text-muted">{{ Settings::description($key) }}</small>
        </div>
    @endforeach
    
    <button type="submit">Desar Preferències</button>
</form>
*/

// CHECKS IN MODELS/OBSERVERS

class UserObserver
{
    public function created(User $user)
    {
        // Check if admin wants to be notified
        if (Settings::isEnabled('notify_new_users', auth()->id())) {
            // Send notification
        }
    }
}

// ADVANCED EXAMPLE: DATA VISIBILITY CONFIGURATION

class UserProfileController extends Controller
{
    public function show(User $user)
    {
        $currentUser = auth()->user();
        
        $profile = [
            'name' => $user->name,
            'avatar' => $user->avatar,
        ];
        
        // Check if user allows showing email
        if (Settings::get('show_email_to_others', $user->id)) {
            $profile['email'] = $user->email;
        }
        
        // Check if profile is public
        if (!Settings::get('profile_public', $user->id) && $currentUser->id !== $user->id) {
            abort(404);
        }
        
        return view('users.profile', compact('profile'));
    }
}
