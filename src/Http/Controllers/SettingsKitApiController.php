<?php

namespace Metalinked\LaravelSettingsKit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Metalinked\LaravelSettingsKit\Facades\Settings;

class SettingsKitApiController extends Controller {
    /**
     * Get all settings with optional filtering.
     */
    public function index(Request $request): JsonResponse {
        try {
            $locale = $request->get('locale');
            $role = $request->get('role');
            $userId = $request->get('user_id');
            $category = $request->get('category');

            if ($userId && !$this->canAccessUserSettings($request, $userId)) {
                return response()->json(['error' => 'Unauthorized to access user settings'], 403);
            }

            // Get settings with translations if locale is specified
            if ($locale) {
                $settings = Settings::allWithTranslations($locale, $role, $userId);
            } else {
                $settings = Settings::all($role, $userId);
            }

            // Filter by category if specified
            if ($category) {
                $settings = array_filter($settings, function ($setting) use ($category) {
                    return ($setting['category'] ?? null) === $category;
                });
            }

            return response()->json([
                'success' => true,
                'data' => $settings,
                'meta' => [
                    'count' => count($settings),
                    'locale' => $locale,
                    'role' => $role,
                    'user_id' => $userId,
                    'category' => $category,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve settings',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific setting.
     */
    public function show(Request $request, string $key): JsonResponse {
        try {
            $userId = $request->get('user_id');
            $locale = $request->get('locale');

            if ($userId && !$this->canAccessUserSettings($request, $userId)) {
                return response()->json(['error' => 'Unauthorized to access user settings'], 403);
            }

            if (!Settings::has($key)) {
                return response()->json(['error' => 'Setting not found'], 404);
            }

            $value = Settings::get($key, $userId);
            $data = [
                'key' => $key,
                'value' => $value,
                'user_id' => $userId,
            ];

            // Add translations if locale is specified
            if ($locale) {
                $data['label'] = Settings::label($key, $locale);
                $data['description'] = Settings::description($key, $locale);
                $data['locale'] = $locale;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve setting',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create or update a setting value.
     */
    public function store(Request $request, string $key): JsonResponse {
        try {
            $request->validate([
                'value' => 'required',
                'user_id' => 'nullable|integer',
                'auto_create' => 'nullable|boolean',
            ]);

            $value = $request->input('value');
            $userId = $request->input('user_id');
            $autoCreate = $request->boolean('auto_create', config('settings-kit.api.auto_create_missing_settings', false));

            if ($userId && !$this->canModifyUserSettings($request, $userId)) {
                return response()->json(['error' => 'Unauthorized to modify user settings'], 403);
            }

            if (!Settings::has($key) && !$autoCreate) {
                return response()->json([
                    'success' => false,
                    'error' => 'Setting not found',
                    'message' => 'Setting does not exist. Set auto_create=true to create it automatically, or enable API auto-creation in config.',
                ], 404);
            }

            if (!Settings::has($key)) {
                // Auto-create setting if it doesn't exist
                Settings::setWithAutoCreate($key, $value, $userId);
                $message = 'Setting created and updated successfully';
            } else {
                // Update existing setting
                Settings::set($key, $value, $userId);
                $message = 'Setting updated successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'key' => $key,
                    'value' => $value,
                    'user_id' => $userId,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update setting',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a setting value (alias for store).
     */
    public function update(Request $request, string $key): JsonResponse {
        return $this->store($request, $key);
    }

    /**
     * Delete a setting value (reset to default).
     */
    public function destroy(Request $request, string $key): JsonResponse {
        try {
            $userId = $request->input('user_id');

            if ($userId && !$this->canModifyUserSettings($request, $userId)) {
                return response()->json(['error' => 'Unauthorized to modify user settings'], 403);
            }

            if (!Settings::has($key)) {
                return response()->json(['error' => 'Setting not found'], 404);
            }

            Settings::forget($key, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Setting reset to default successfully',
                'data' => [
                    'key' => $key,
                    'user_id' => $userId,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to reset setting',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available categories.
     */
    public function categories(): JsonResponse {
        try {
            $categories = Settings::getCategories();

            return response()->json([
                'success' => true,
                'data' => $categories,
                'meta' => [
                    'count' => count($categories),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve categories',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new preference with optional translations.
     */
    public function createPreference(Request $request): JsonResponse {
        try {
            $request->validate([
                'key' => 'required|string|unique:preferences,key',
                'type' => 'required|in:string,boolean,integer,json,select',
                'default_value' => 'required',
                'category' => 'nullable|string',
                'role' => 'nullable|string',
                'required' => 'nullable|boolean',
                'options' => 'nullable|array',
                'translations' => 'nullable|array',
                'translations.*.title' => 'required_with:translations|string',
                'translations.*.description' => 'nullable|string',
            ]);

            $data = $request->only(['key', 'type', 'default_value', 'category', 'role', 'required']);

            if ($request->has('options')) {
                $data['options'] = json_encode($request->input('options'));
            }

            $translations = $request->input('translations', []);

            if (!empty($translations)) {
                $preference = Settings::createWithTranslations($data['key'], $data, $translations);
            } else {
                $preference = Settings::create($data);
            }

            return response()->json([
                'success' => true,
                'message' => 'Preference created successfully',
                'data' => [
                    'id' => $preference->id,
                    'key' => $preference->key,
                    'type' => $preference->type,
                    'category' => $preference->category,
                    'translations_count' => count($translations),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create preference',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if the request can access user-specific settings.
     */
    protected function canAccessUserSettings(Request $request, int $userId): bool {
        $authMode = config('settings-kit.api.auth_mode');

        // For token auth, allow access to any user settings
        if ($authMode === 'token') {
            return true;
        }

        // For user-based auth, only allow access to own settings or if admin
        $user = auth()->user();

        return $user && ($user->id == $userId || $this->isAdmin($user));
    }

    /**
     * Check if the request can modify user-specific settings.
     */
    protected function canModifyUserSettings(Request $request, int $userId): bool {
        return $this->canAccessUserSettings($request, $userId);
    }

    /**
     * Check if the user is an admin.
     */
    protected function isAdmin($user): bool {
        // You can customize this logic based on your application
        return method_exists($user, 'isAdmin') ? $user->isAdmin() : false;
    }

    // ============================================
    // Global Settings Methods (explicit endpoints)
    // ============================================

    /**
     * Get a specific global setting.
     */
    public function showGlobal(Request $request, string $key): JsonResponse {
        try {
            $locale = $request->get('locale');
            
            if ($locale) {
                $value = Settings::getWithTranslations($key, $locale, null);
            } else {
                $value = Settings::get($key, null);
            }

            if ($value === null && !Settings::exists($key)) {
                if (config('settings-kit.api.auto_create_missing_settings', false) || $request->boolean('auto_create')) {
                    // Auto-create the setting as global
                    $autoCreated = Settings::set($key, $request->get('default_value', ''), null);
                    if ($autoCreated) {
                        $value = Settings::get($key, null);
                    }
                } else {
                    return response()->json(['error' => 'Global setting not found'], 404);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'key' => $key,
                    'value' => $value,
                    'user_id' => null,
                    'type' => 'global'
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a global setting value.
     */
    public function storeGlobal(Request $request, string $key): JsonResponse {
        try {
            $value = $request->get('value');
            $updated = Settings::set($key, $value, null);

            return response()->json([
                'success' => true,
                'message' => 'Global setting updated successfully',
                'data' => [
                    'key' => $key,
                    'value' => $value,
                    'user_id' => null,
                    'type' => 'global'
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a global setting value.
     */
    public function updateGlobal(Request $request, string $key): JsonResponse {
        return $this->storeGlobal($request, $key);
    }

    /**
     * Delete a global setting value.
     */
    public function destroyGlobal(Request $request, string $key): JsonResponse {
        try {
            $deleted = Settings::forget($key, null);

            return response()->json([
                'success' => true,
                'message' => $deleted ? 'Global setting deleted successfully' : 'Global setting was already at default value',
                'data' => [
                    'key' => $key,
                    'user_id' => null,
                    'type' => 'global'
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================
    // User Settings Methods (explicit endpoints)
    // =========================================

    /**
     * Get a specific user setting.
     */
    public function showUser(Request $request, string $key): JsonResponse {
        try {
            $userId = $request->get('user_id') ?? auth()->id();
            if (!$userId) {
                return response()->json(['error' => 'User ID required for user settings'], 400);
            }

            if (!$this->canAccessUserSettings($request, $userId)) {
                return response()->json(['error' => 'Unauthorized to access user settings'], 403);
            }

            $locale = $request->get('locale');
            
            if ($locale) {
                $value = Settings::getWithTranslations($key, $locale, $userId);
            } else {
                $value = Settings::get($key, $userId);
            }

            if ($value === null && !Settings::exists($key)) {
                if (config('settings-kit.api.auto_create_missing_settings', false) || $request->boolean('auto_create')) {
                    // Auto-create the setting as user customizable
                    $autoCreated = Settings::set($key, $request->get('default_value', ''), $userId);
                    if ($autoCreated) {
                        $value = Settings::get($key, $userId);
                    }
                } else {
                    return response()->json(['error' => 'User setting not found'], 404);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'key' => $key,
                    'value' => $value,
                    'user_id' => $userId,
                    'type' => 'user'
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a user setting value.
     */
    public function storeUser(Request $request, string $key): JsonResponse {
        try {
            $userId = $request->get('user_id') ?? auth()->id();
            if (!$userId) {
                return response()->json(['error' => 'User ID required for user settings'], 400);
            }

            if (!$this->canModifyUserSettings($request, $userId)) {
                return response()->json(['error' => 'Unauthorized to modify user settings'], 403);
            }

            $value = $request->get('value');
            $updated = Settings::set($key, $value, $userId);

            return response()->json([
                'success' => true,
                'message' => 'User setting updated successfully',
                'data' => [
                    'key' => $key,
                    'value' => $value,
                    'user_id' => $userId,
                    'type' => 'user'
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a user setting value.
     */
    public function updateUser(Request $request, string $key): JsonResponse {
        return $this->storeUser($request, $key);
    }

    /**
     * Delete a user setting value.
     */
    public function destroyUser(Request $request, string $key): JsonResponse {
        try {
            $userId = $request->get('user_id') ?? auth()->id();
            if (!$userId) {
                return response()->json(['error' => 'User ID required for user settings'], 400);
            }

            if (!$this->canModifyUserSettings($request, $userId)) {
                return response()->json(['error' => 'Unauthorized to modify user settings'], 403);
            }

            $deleted = Settings::forget($key, $userId);

            return response()->json([
                'success' => true,
                'message' => $deleted ? 'User setting deleted successfully' : 'User setting was already at default value',
                'data' => [
                    'key' => $key,
                    'user_id' => $userId,
                    'type' => 'user'
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
