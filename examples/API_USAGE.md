# API Usage Examples

This document provides practical examples of how to use the Settings Kit REST API endpoints.

## Configuration

First, enable the API in your `.env` file:

```env
SETTINGS_KIT_API_ENABLED=true
SETTINGS_KIT_API_PREFIX=api/settings-kit
SETTINGS_KIT_API_AUTH=token
SETTINGS_KIT_API_TOKEN=your-secure-api-token-here
```

Or for Sanctum authentication:

```env
SETTINGS_KIT_API_ENABLED=true
SETTINGS_KIT_API_PREFIX=api/settings-kit
SETTINGS_KIT_API_AUTH=sanctum
```

## Authentication

### Token Authentication

Include the token in the Authorization header:

```bash
curl -H "Authorization: Bearer your-secure-api-token-here" \
     -H "Content-Type: application/json" \
     http://your-app.com/api/settings-kit
```

### Sanctum Authentication

First obtain a token:

```javascript
// Login to get token
const response = await fetch('/api/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: 'user@example.com', password: 'password' })
});
const { token } = await response.json();

// Use token for API calls
const settingsResponse = await fetch('/api/settings-kit', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});
```

## API Endpoints

### 1. Get All Settings

**GET** `/api/settings-kit`

**Query Parameters:**
- `locale` (optional): Language code for translations (e.g., `ca`, `es`, `en`)
- `role` (optional): Filter by role
- `user_id` (optional): Get user-specific settings
- `category` (optional): Filter by category

**Examples:**

```bash
# Get all global settings
curl -H "Authorization: Bearer your-token" http://your-app.com/api/settings-kit

# Get settings with Catalan translations
curl -H "Authorization: Bearer your-token" \
     http://your-app.com/api/settings-kit?locale=ca

# Get user-specific settings
curl -H "Authorization: Bearer your-token" \
     http://your-app.com/api/settings-kit?user_id=123&locale=en

# Get settings by category
curl -H "Authorization: Bearer your-token" \
     http://your-app.com/api/settings-kit?category=notifications&locale=es
```

**Response:**

```json
{
    "success": true,
    "data": {
        "maintenance_mode": {
            "value": false,
            "type": "boolean",
            "category": "system",
            "label": "Mode Manteniment",
            "description": "Activar el mode de manteniment",
            "key": "maintenance_mode"
        }
    },
    "meta": {
        "count": 1,
        "locale": "ca",
        "role": null,
        "user_id": null,
        "category": null
    }
}
```

### 2. Get Specific Setting

**GET** `/api/settings-kit/{key}`

**Query Parameters:**
- `user_id` (optional): Get user-specific value
- `locale` (optional): Get translated label and description

**Examples:**

```bash
# Get global setting
curl -H "Authorization: Bearer your-token" \
     http://your-app.com/api/settings-kit/maintenance_mode

# Get user-specific setting with translations
curl -H "Authorization: Bearer your-token" \
     http://your-app.com/api/settings-kit/email_notifications?user_id=123&locale=ca
```

**Response:**

```json
{
    "success": true,
    "data": {
        "key": "maintenance_mode",
        "value": false,
        "user_id": null,
        "label": "Mode Manteniment",
        "description": "Activar el mode de manteniment",
        "locale": "ca"
    }
}
```

### 3. Set Setting Value

**POST** `/api/settings-kit/{key}`

**Body Parameters:**
- `value` (required): The value to set
- `user_id` (optional): Set user-specific value
- `auto_create` (optional): Create preference if it doesn't exist

**Examples:**

```bash
# Set global setting
curl -X POST \
     -H "Authorization: Bearer your-token" \
     -H "Content-Type: application/json" \
     -d '{"value": true}' \
     http://your-app.com/api/settings-kit/maintenance_mode

# Set user-specific setting
curl -X POST \
     -H "Authorization: Bearer your-token" \
     -H "Content-Type: application/json" \
     -d '{"value": false, "user_id": 123}' \
     http://your-app.com/api/settings-kit/email_notifications

# Create new setting automatically
curl -X POST \
     -H "Authorization: Bearer your-token" \
     -H "Content-Type: application/json" \
     -d '{"value": "dark", "auto_create": true}' \
     http://your-app.com/api/settings-kit/theme_mode
```

**Response:**

```json
{
    "success": true,
    "message": "Setting updated successfully",
    "data": {
        "key": "maintenance_mode",
        "value": true,
        "user_id": null
    }
}
```

### 4. Update Setting Value

**PUT** `/api/settings-kit/{key}`

Same as POST method.

### 5. Reset Setting

**DELETE** `/api/settings-kit/{key}`

**Body Parameters:**
- `user_id` (optional): Reset user-specific value

```bash
# Reset global setting to default
curl -X DELETE \
     -H "Authorization: Bearer your-token" \
     http://your-app.com/api/settings-kit/maintenance_mode

# Reset user-specific setting
curl -X DELETE \
     -H "Authorization: Bearer your-token" \
     -H "Content-Type: application/json" \
     -d '{"user_id": 123}' \
     http://your-app.com/api/settings-kit/email_notifications
```

**Response:**

```json
{
    "success": true,
    "message": "Setting reset to default successfully",
    "data": {
        "key": "maintenance_mode",
        "user_id": null
    }
}
```

### 6. Get Categories

**GET** `/api/settings-kit/categories`

```bash
curl -H "Authorization: Bearer your-token" \
     http://your-app.com/api/settings-kit/categories
```

**Response:**

```json
{
    "success": true,
    "data": ["system", "notifications", "appearance", "privacy"],
    "meta": {
        "count": 4
    }
}
```

### 7. Create New Preference

**POST** `/api/settings-kit/preferences`

**Body Parameters:**
- `key` (required): Unique preference key
- `type` (required): One of: string, boolean, integer, json, select
- `default_value` (required): Default value
- `category` (optional): Category name
- `role` (optional): Role restriction
- `required` (optional): Whether the setting is required
- `options` (optional): Array of options for select type
- `translations` (optional): Object with translations per language

```bash
curl -X POST \
     -H "Authorization: Bearer your-token" \
     -H "Content-Type: application/json" \
     -d '{
       "key": "theme_color",
       "type": "select",
       "default_value": "blue",
       "category": "appearance",
       "options": {"blue": "Blue", "red": "Red", "green": "Green"},
       "translations": {
         "en": {"title": "Theme Color", "description": "Choose your preferred theme color"},
         "ca": {"title": "Color del Tema", "description": "Tria el color del tema que prefereixes"}
       }
     }' \
     http://your-app.com/api/settings-kit/preferences
```

**Response:**

```json
{
    "success": true,
    "message": "Preference created successfully",
    "data": {
        "id": 15,
        "key": "theme_color",
        "type": "select",
        "category": "appearance",
        "translations_count": 2
    }
}
```

## JavaScript Examples

### React Hook Example

```javascript
import { useState, useEffect } from 'react';

const useSettings = (locale = 'en', userId = null) => {
    const [settings, setSettings] = useState({});
    const [loading, setLoading] = useState(true);
    
    const apiToken = process.env.REACT_APP_SETTINGS_API_TOKEN;
    
    const fetchSettings = async () => {
        try {
            const params = new URLSearchParams();
            if (locale) params.append('locale', locale);
            if (userId) params.append('user_id', userId);
            
            const response = await fetch(`/api/settings-kit?${params}`, {
                headers: {
                    'Authorization': `Bearer ${apiToken}`,
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();
            if (data.success) {
                setSettings(data.data);
            }
        } catch (error) {
            console.error('Failed to fetch settings:', error);
        } finally {
            setLoading(false);
        }
    };
    
    const updateSetting = async (key, value, userIdOverride = null) => {
        try {
            const response = await fetch(`/api/settings-kit/${key}`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiToken}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    value,
                    user_id: userIdOverride || userId,
                    auto_create: true
                })
            });
            
            const data = await response.json();
            if (data.success) {
                // Update local state
                setSettings(prev => ({
                    ...prev,
                    [key]: { ...prev[key], value }
                }));
            }
            return data;
        } catch (error) {
            console.error('Failed to update setting:', error);
            return { success: false, error: error.message };
        }
    };
    
    useEffect(() => {
        fetchSettings();
    }, [locale, userId]);
    
    return { settings, loading, updateSetting, refetch: fetchSettings };
};

// Usage in component
const SettingsPanel = () => {
    const { settings, loading, updateSetting } = useSettings('ca', 123);
    
    if (loading) return <div>Loading...</div>;
    
    return (
        <div>
            {Object.entries(settings).map(([key, setting]) => (
                <div key={key}>
                    <label>{setting.label}</label>
                    <p>{setting.description}</p>
                    {setting.type === 'boolean' && (
                        <input
                            type="checkbox"
                            checked={setting.value}
                            onChange={(e) => updateSetting(key, e.target.checked)}
                        />
                    )}
                </div>
            ))}
        </div>
    );
};
```

### Vue.js Composable Example

```javascript
import { ref, reactive, onMounted } from 'vue';

export const useSettings = (locale = 'en', userId = null) => {
    const settings = reactive({});
    const loading = ref(true);
    const error = ref(null);
    
    const apiToken = import.meta.env.VITE_SETTINGS_API_TOKEN;
    
    const fetchSettings = async () => {
        try {
            loading.value = true;
            const params = new URLSearchParams();
            if (locale) params.append('locale', locale);
            if (userId) params.append('user_id', userId);
            
            const response = await fetch(`/api/settings-kit?${params}`, {
                headers: {
                    'Authorization': `Bearer ${apiToken}`,
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();
            if (data.success) {
                Object.assign(settings, data.data);
            } else {
                error.value = data.error;
            }
        } catch (err) {
            error.value = err.message;
        } finally {
            loading.value = false;
        }
    };
    
    const updateSetting = async (key, value) => {
        try {
            const response = await fetch(`/api/settings-kit/${key}`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiToken}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    value,
                    user_id: userId,
                    auto_create: true
                })
            });
            
            const data = await response.json();
            if (data.success && settings[key]) {
                settings[key].value = value;
            }
            return data;
        } catch (err) {
            error.value = err.message;
            return { success: false, error: err.message };
        }
    };
    
    onMounted(fetchSettings);
    
    return {
        settings: readonly(settings),
        loading: readonly(loading),
        error: readonly(error),
        updateSetting,
        refetch: fetchSettings
    };
};
```

## Error Responses

All error responses follow this format:

```json
{
    "success": false,
    "error": "Error type",
    "message": "Detailed error message"
}
```

Common error codes:
- **401**: Unauthorized (invalid or missing token)
- **403**: Forbidden (insufficient permissions)
- **404**: Not found (setting/preference doesn't exist, or API disabled)
- **422**: Validation error
- **500**: Server error
