<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Metalinked\LaravelSettingsKit\Models\Preference;
use Metalinked\LaravelSettingsKit\Models\PreferenceContent;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        // USER CUSTOMIZABLE PREFERENCES (notifications)
        $this->createPreference([
            'key' => 'email_notifications',
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'notifications',
            'role' => null,
            'is_user_customizable' => true, // Users can customize this setting
        ], [
            'ca' => ['title' => 'Notificacions per Email', 'text' => 'Rebre notificacions importants per correu electrònic'],
            'es' => ['title' => 'Notificaciones por Email', 'text' => 'Recibir notificaciones importantes por correo electrónico'],
            'en' => ['title' => 'Email Notifications', 'text' => 'Receive important notifications via email'],
        ]);

        $this->createPreference([
            'key' => 'marketing_emails',
            'type' => 'boolean',
            'default_value' => '0',
            'category' => 'notifications',
            'is_user_customizable' => true, // Users can customize this setting
            'role' => null,
        ], [
            'ca' => ['title' => 'Emails de Màrqueting', 'text' => 'Rebre ofertes especials i novetats'],
            'es' => ['title' => 'Emails de Marketing', 'text' => 'Recibir ofertas especiales y novedades'],
            'en' => ['title' => 'Marketing Emails', 'text' => 'Receive special offers and news'],
        ]);

        // PRIVACY PREFERENCES
        $this->createPreference([
            'key' => 'profile_public',
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'privacy',
            'role' => null,
        ], [
            'ca' => ['title' => 'Perfil Públic', 'text' => 'Permetre que altres usuaris vegin el meu perfil'],
            'es' => ['title' => 'Perfil Público', 'text' => 'Permitir que otros usuarios vean mi perfil'],
            'en' => ['title' => 'Public Profile', 'text' => 'Allow other users to see my profile'],
        ]);

        $this->createPreference([
            'key' => 'show_email_to_others',
            'type' => 'boolean',
            'default_value' => '0',
            'category' => 'privacy',
            'role' => null,
        ], [
            'ca' => ['title' => 'Mostrar Email', 'text' => 'Mostrar la meva adreça de correu en el perfil públic'],
            'es' => ['title' => 'Mostrar Email', 'text' => 'Mostrar mi dirección de correo en el perfil público'],
            'en' => ['title' => 'Show Email', 'text' => 'Show my email address in public profile'],
        ]);

        // APPEARANCE PREFERENCES
        $this->createPreference([
            'key' => 'preferred_language',
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
            'ca' => ['title' => 'Idioma Preferit', 'text' => 'Idioma de la interfície'],
            'es' => ['title' => 'Idioma Preferido', 'text' => 'Idioma de la interfaz'],
            'en' => ['title' => 'Preferred Language', 'text' => 'Interface language'],
        ]);

        // GLOBAL UNIQUE SETTINGS (only admins can modify, affects everyone)
        $this->createPreference([
            'key' => 'registration_open',
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'access_control',
            'role' => null,
            'is_user_customizable' => false, // Global unique setting
        ], [
            'ca' => ['title' => 'Registre Obert', 'text' => 'Permetre nous registres d\'usuaris'],
            'es' => ['title' => 'Registro Abierto', 'text' => 'Permitir nuevos registros de usuarios'],
            'en' => ['title' => 'Registration Open', 'text' => 'Allow new user registrations'],
        ]);

        $this->createPreference([
            'key' => 'contact_form_enabled',
            'type' => 'boolean',
            'default_value' => '1',
            'is_user_customizable' => false, // Global unique setting
            'category' => 'frontend',
            'role' => null,
        ], [
            'ca' => ['title' => 'Formulari de Contacte', 'text' => 'Activar el formulari de contacte al web'],
            'es' => ['title' => 'Formulario de Contacto', 'text' => 'Activar el formulario de contacto en el web'],
            'en' => ['title' => 'Contact Form', 'text' => 'Enable contact form on website'],
        ]);

        // ADMIN-ONLY SETTINGS
        $this->createPreference([
            'key' => 'admin_notify_new_users',
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'admin_notifications',
            'role' => 'admin',
        ], [
            'ca' => ['title' => 'Notificar Nous Usuaris', 'text' => 'Rebre notificació quan es registri un nou usuari'],
            'es' => ['title' => 'Notificar Nuevos Usuarios', 'text' => 'Recibir notificación cuando se registre un nuevo usuario'],
            'en' => ['title' => 'Notify New Users', 'text' => 'Receive notification when a new user registers'],
        ]);

        $this->createPreference([
            'key' => 'admin_notification_level',
            'type' => 'select',
            'default_value' => 'important',
            'category' => 'admin_notifications',
            'role' => 'admin',
            'options' => json_encode([
                'none' => 'Cap notificació',
                'important' => 'Només importants',
                'all' => 'Totes les accions'
            ]),
        ], [
            'ca' => ['title' => 'Nivell de Notificacions', 'text' => 'Quantes notificacions d\'administrador vols rebre'],
            'es' => ['title' => 'Nivel de Notificaciones', 'text' => 'Cuántas notificaciones de administrador quieres recibir'],
            'en' => ['title' => 'Notification Level', 'text' => 'How many admin notifications you want to receive'],
        ]);
    }

    private function createPreference(array $preferenceData, array $translations)
    {
        $preference = Preference::create($preferenceData);

        foreach ($translations as $locale => $content) {
            PreferenceContent::create([
                'preference_id' => $preference->id,
                'locale' => $locale,
                'title' => $content['title'],
                'text' => $content['text'],
            ]);
        }

        return $preference;
    }
}
