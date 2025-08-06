<?php

// Exemple pràctic per un backoffice

use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Models\Preference;

// 1. CONFIGURACIONS GLOBALS (administrables pels admins)
$globalSettings = [
    // Control d'accés
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

// 2. PREFERÈNCIES PER USUARIS NORMALS
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

// 3. CONFIGURACIONS NOMÉS PER ADMINS
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
    
    // Accés a dades
    [
        'key' => 'can_export_all_data',
        'type' => 'boolean',
        'default_value' => '0',
        'category' => 'data_access',
        'role' => 'admin',
    ],
];

// Crear totes les preferències
$allSettings = array_merge($globalSettings, $userSettings, $adminSettings);

foreach ($allSettings as $setting) {
    Settings::create($setting);
}

// EXEMPLE D'ÚS EN CONTROLADORS

class UserController extends Controller
{
    public function register(Request $request)
    {
        // Comprovar si el registre està obert
        if (!Settings::isEnabled('registration_open')) {
            return redirect()->back()->with('error', 'Registrations are currently closed');
        }
        
        // Processar registre...
        $user = User::create($request->validated());
        
        // Notificar admins si tenen la preferència activada
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            if (Settings::isEnabled('notify_new_users', $admin->id)) {
                // Enviar notificació a aquest admin
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
        
        // Obtenir preferències de l'usuari per categories
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
        
        // Configuracions globals que pot modificar
        $globalSettings = Settings::all();
        
        // Preferències personals d'admin
        $adminNotifications = Settings::getByCategory('admin_notifications', $user->id);
        $dataAccess = Settings::getByCategory('data_access', $user->id);
        
        return view('admin.settings', compact('globalSettings', 'adminNotifications', 'dataAccess'));
    }
}

// MIDDLEWARE PER COMPROVAR ACCESSOS

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

// Ús en routes:
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

// COMPROVACIONS EN ELS MODELS/OBSERVERS

class UserObserver
{
    public function created(User $user)
    {
        // Comprovar si l'admin vol ser notificat
        if (Settings::isEnabled('notify_new_users', auth()->id())) {
            // Enviar notificació
        }
    }
}

// EXEMPLE AVANÇAT: CONFIGURACIÓ DE VISIBILITAT DE DADES

class UserProfileController extends Controller
{
    public function show(User $user)
    {
        $currentUser = auth()->user();
        
        $profile = [
            'name' => $user->name,
            'avatar' => $user->avatar,
        ];
        
        // Comprovar si l'usuari permet mostrar l'email
        if (Settings::get('show_email_to_others', $user->id)) {
            $profile['email'] = $user->email;
        }
        
        // Comprovar si el perfil és públic
        if (!Settings::get('profile_public', $user->id) && $currentUser->id !== $user->id) {
            abort(404);
        }
        
        return view('users.profile', compact('profile'));
    }
}
