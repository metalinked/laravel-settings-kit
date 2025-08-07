<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Metalinked\LaravelSettingsKit\Facades\Settings;

/**
 * Example controller showing different ways to handle settings that might not exist
 */
class SettingsControllerExample extends Controller
{
    /**
     * Display settings form.
     * This method demonstrates safe ways to get settings that might not exist.
     */
    public function show()
    {
        $settings = [];
        
        // Method 1: Use has() to check first
        if (Settings::has('maintenance_mode')) {
            $settings['maintenance_mode'] = (bool) Settings::get('maintenance_mode');
        } else {
            $settings['maintenance_mode'] = false; // Default value
        }
        
        // Method 2: Use get() with default value (since get() returns null for non-existent settings)
        $settings['admin_notify_new_users'] = (bool) Settings::get('admin_notify_new_users', false);
        $settings['contact_form_enabled'] = (bool) Settings::get('contact_form_enabled', true);
        $settings['registration_open'] = (bool) Settings::get('registration_open', true);
        
        return view('settings-test', compact('settings'));
    }

    /**
     * Save settings using the new auto-create methods.
     * This is the cleanest approach for simple use cases.
     */
    public function saveWithAutoCreate(Request $request)
    {
        // These will automatically create preferences if they don't exist
        Settings::setWithAutoCreate('maintenance_mode', $request->has('maintenance_mode'));
        Settings::setWithAutoCreate('admin_notify_new_users', $request->has('admin_notify_new_users'));
        Settings::setWithAutoCreate('contact_form_enabled', $request->has('contact_form_enabled'));
        Settings::setWithAutoCreate('registration_open', $request->has('registration_open'));
        
        return redirect()->route('settings.test')->with('success', 'Configuration saved!');
    }

    /**
     * Save settings with manual preference creation.
     * This approach gives you more control over preference configuration.
     */
    public function saveWithManualCreation(Request $request)
    {
        $settingsToProcess = [
            'maintenance_mode' => [
                'value' => $request->has('maintenance_mode'),
                'type' => 'boolean',
                'category' => 'system',
                'default_value' => false
            ],
            'admin_notify_new_users' => [
                'value' => $request->has('admin_notify_new_users'),
                'type' => 'boolean',
                'category' => 'notifications',
                'default_value' => true
            ],
            'contact_form_enabled' => [
                'value' => $request->has('contact_form_enabled'),
                'type' => 'boolean',
                'category' => 'frontend',
                'default_value' => true
            ]
        ];

        foreach ($settingsToProcess as $key => $config) {
            // Create preference if it doesn't exist
            Settings::createIfNotExists($key, [
                'type' => $config['type'],
                'default_value' => $config['default_value'],
                'category' => $config['category'],
                'role' => null,
            ]);
            
            // Then set the value
            Settings::set($key, $config['value']);
        }

        return redirect()->route('settings.test')->with('success', 'Configuration saved!');
    }

    /**
     * Save settings with validation and error handling.
     * This is the most robust approach for production applications.
     */
    public function saveWithValidation(Request $request)
    {
        $request->validate([
            'maintenance_mode' => 'nullable|boolean',
            'admin_notify_new_users' => 'nullable|boolean',
            'contact_form_enabled' => 'nullable|boolean',
        ]);

        $settingsMap = [
            'maintenance_mode' => [
                'value' => $request->boolean('maintenance_mode'),
                'category' => 'system',
                'description' => 'Put the application in maintenance mode'
            ],
            'admin_notify_new_users' => [
                'value' => $request->boolean('admin_notify_new_users'),
                'category' => 'notifications',
                'description' => 'Send notifications to admins when new users register'
            ],
            'contact_form_enabled' => [
                'value' => $request->boolean('contact_form_enabled'),
                'category' => 'frontend',
                'description' => 'Enable the contact form on the website'
            ]
        ];

        foreach ($settingsMap as $key => $config) {
            try {
                if (!Settings::has($key)) {
                    Settings::createIfNotExists($key, [
                        'type' => 'boolean',
                        'default_value' => $config['value'] ? '1' : '0',
                        'category' => $config['category'],
                        'role' => null,
                    ]);
                }
                
                Settings::set($key, $config['value']);
                
            } catch (\Exception $e) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['error' => "Failed to save setting '{$key}': " . $e->getMessage()]);
            }
        }

        return redirect()->route('settings.test')->with('success', 'Configuration saved successfully!');
    }

    /**
     * Initialize default settings.
     * Call this method to set up basic settings for your application.
     */
    public function initializeDefaults()
    {
        $defaults = [
            'maintenance_mode' => ['type' => 'boolean', 'value' => false, 'category' => 'system'],
            'admin_notify_new_users' => ['type' => 'boolean', 'value' => true, 'category' => 'notifications'],
            'contact_form_enabled' => ['type' => 'boolean', 'value' => true, 'category' => 'frontend'],
            'registration_open' => ['type' => 'boolean', 'value' => true, 'category' => 'system'],
            'max_upload_size' => ['type' => 'integer', 'value' => 10, 'category' => 'system'],
            'site_name' => ['type' => 'string', 'value' => 'My Application', 'category' => 'general'],
            'allowed_extensions' => ['type' => 'json', 'value' => ['jpg', 'png', 'gif'], 'category' => 'uploads'],
        ];

        foreach ($defaults as $key => $config) {
            Settings::createIfNotExists($key, [
                'type' => $config['type'],
                'default_value' => match($config['type']) {
                    'boolean' => $config['value'] ? '1' : '0',
                    'integer' => (string) $config['value'],
                    'json' => json_encode($config['value']),
                    default => (string) $config['value']
                },
                'category' => $config['category'],
                'role' => null,
            ]);
        }

        return redirect()->back()->with('success', 'Default settings initialized!');
    }
}
