<?php

namespace Metalinked\LaravelSettingsKit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Metalinked\LaravelSettingsKit\Facades\Settings;

class SettingsKitApiController extends Controller {
    // ============================================
    // Global listing / creation
    // ============================================

    public function index(Request $request): JsonResponse {
        try {
            $locale = $request->get('locale');
            $role = $request->get('role');
            $userId = $request->get('user_id') ? (int) $request->get('user_id') : null;
            $category = $request->get('category');

            if ($userId && !$this->canAccessUserSettings($request, $userId)) {
                return $this->forbidden('Unauthorized to access user settings');
            }

            $settings = $locale
                ? Settings::allWithTranslations($locale, $role, $userId, $category)
                : Settings::all($role, $userId, $category);

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
            return $this->serverError($e);
        }
    }

    public function categories(): JsonResponse {
        try {
            $categories = Settings::getCategories();

            return response()->json([
                'success' => true,
                'data' => $categories,
                'meta' => ['count' => count($categories)],
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    public function createPreference(Request $request): JsonResponse {
        try {
            $preferencesTable = config('settings-kit.tables.preferences', 'preferences');

            $request->validate([
                'key' => "required|string|unique:{$preferencesTable},key",
                'type' => 'required|in:string,boolean,integer,json,select',
                'default_value' => 'required',
                'category' => 'nullable|string',
                'role' => 'nullable|string',
                'required' => 'nullable|boolean',
                'is_user_customizable' => 'nullable|boolean',
                'options' => 'nullable|array',
                'translations' => 'nullable|array',
                'translations.*.title' => 'required_with:translations|string',
                'translations.*.text' => 'nullable|string',
            ]);

            $data = $request->only(['key', 'type', 'default_value', 'category', 'role', 'required', 'is_user_customizable']);

            if ($request->has('options')) {
                $data['options'] = $request->input('options');
            }

            $translations = $request->input('translations', []);

            $preference = !empty($translations)
                ? Settings::createWithTranslations($data['key'], $data, $translations)
                : Settings::create($data);

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
            ], 201);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    // ============================================
    // Global settings endpoints
    // ============================================

    public function showGlobal(Request $request, string $key): JsonResponse {
        try {
            if (!Settings::exists($key)) {
                return $this->notFound('Setting not found');
            }

            $locale = $request->get('locale');
            $value = Settings::get($key, null);
            $data = ['key' => $key, 'value' => $value, 'type' => 'global'];

            if ($locale) {
                $data['label'] = Settings::label($key, $locale);
                $data['description'] = Settings::description($key, $locale);
                $data['locale'] = $locale;
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    public function storeGlobal(Request $request, string $key): JsonResponse {
        try {
            if (!Settings::exists($key)) {
                return $this->notFound('Setting not found');
            }

            $value = $request->get('value');
            Settings::set($key, $value, null);

            return response()->json([
                'success' => true,
                'message' => 'Global setting updated successfully',
                'data' => ['key' => $key, 'value' => $value, 'type' => 'global'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    public function updateGlobal(Request $request, string $key): JsonResponse {
        return $this->storeGlobal($request, $key);
    }

    /**
     * Permanently deletes a preference and all its user overrides and translations.
     * This is irreversible — use with care.
     */
    public function destroyGlobal(Request $request, string $key): JsonResponse {
        try {
            if (!Settings::exists($key)) {
                return $this->notFound('Setting not found');
            }

            Settings::delete($key);

            return response()->json([
                'success' => true,
                'message' => 'Preference deleted successfully',
                'data' => ['key' => $key],
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    // ============================================
    // User settings endpoints
    // ============================================

    /**
     * Get all user-customisable settings with resolved values for a user.
     * Returns global default for settings the user hasn't personalised.
     */
    public function indexUser(Request $request): JsonResponse {
        try {
            $userId = $this->resolveUserId($request);

            if (!$userId) {
                return $this->badRequest('User ID required');
            }

            if (!$this->canAccessUserSettings($request, $userId)) {
                return $this->forbidden('Unauthorized to access user settings');
            }

            $locale = $request->get('locale');
            $category = $request->get('category');
            $settings = Settings::allForUser($userId, $locale, $category);

            return response()->json([
                'success' => true,
                'data' => $settings,
                'meta' => ['count' => count($settings), 'user_id' => $userId, 'locale' => $locale],
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    /**
     * Update multiple user settings in a single request.
     * Ideal for saving a preferences panel without N individual requests.
     */
    public function batchUser(Request $request): JsonResponse {
        try {
            $userId = $this->resolveUserId($request);

            if (!$userId) {
                return $this->badRequest('User ID required');
            }

            if (!$this->canModifyUserSettings($request, $userId)) {
                return $this->forbidden('Unauthorized to modify user settings');
            }

            $request->validate(['settings' => 'required|array']);

            $keyValues = $request->input('settings');
            Settings::setMultiple($keyValues, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => ['user_id' => $userId, 'updated' => count($keyValues), 'keys' => array_keys($keyValues)],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    public function showUser(Request $request, string $key): JsonResponse {
        try {
            $userId = $this->resolveUserId($request);

            if (!$userId) {
                return $this->badRequest('User ID required');
            }

            if (!$this->canAccessUserSettings($request, $userId)) {
                return $this->forbidden('Unauthorized to access user settings');
            }

            if (!Settings::exists($key)) {
                return $this->notFound('Setting not found');
            }

            $locale = $request->get('locale');
            $value = Settings::get($key, $userId);
            $data = ['key' => $key, 'value' => $value, 'user_id' => $userId, 'type' => 'user'];

            if ($locale) {
                $data['label'] = Settings::label($key, $locale);
                $data['description'] = Settings::description($key, $locale);
                $data['locale'] = $locale;
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    public function storeUser(Request $request, string $key): JsonResponse {
        try {
            $userId = $this->resolveUserId($request);

            if (!$userId) {
                return $this->badRequest('User ID required');
            }

            if (!$this->canModifyUserSettings($request, $userId)) {
                return $this->forbidden('Unauthorized to modify user settings');
            }

            if (!Settings::exists($key)) {
                return $this->notFound('Setting not found');
            }

            $value = $request->get('value');
            Settings::set($key, $value, $userId);

            return response()->json([
                'success' => true,
                'message' => 'User setting updated successfully',
                'data' => ['key' => $key, 'value' => $value, 'user_id' => $userId, 'type' => 'user'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    public function updateUser(Request $request, string $key): JsonResponse {
        return $this->storeUser($request, $key);
    }

    /**
     * Resets a single user setting to the global default by removing the override.
     */
    public function destroyUser(Request $request, string $key): JsonResponse {
        try {
            $userId = $this->resolveUserId($request);

            if (!$userId) {
                return $this->badRequest('User ID required');
            }

            if (!$this->canModifyUserSettings($request, $userId)) {
                return $this->forbidden('Unauthorized to modify user settings');
            }

            if (!Settings::exists($key)) {
                return $this->notFound('Setting not found');
            }

            Settings::forget($key, $userId);

            return response()->json([
                'success' => true,
                'message' => 'User setting reset to default successfully',
                'data' => ['key' => $key, 'user_id' => $userId],
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    /**
     * Removes all overrides for a user, reverting every setting to its global default.
     */
    public function destroyAllUser(Request $request): JsonResponse {
        try {
            $userId = $this->resolveUserId($request);

            if (!$userId) {
                return $this->badRequest('User ID required');
            }

            if (!$this->canModifyUserSettings($request, $userId)) {
                return $this->forbidden('Unauthorized to modify user settings');
            }

            $count = Settings::forgetAll($userId);

            return response()->json([
                'success' => true,
                'message' => 'All user overrides reset to defaults',
                'data' => ['user_id' => $userId, 'reset_count' => $count],
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    // ============================================
    // Legacy endpoints (backwards compatibility)
    // ============================================

    public function show(Request $request, string $key): JsonResponse {
        try {
            $userId = $request->get('user_id') ? (int) $request->get('user_id') : null;
            $locale = $request->get('locale');

            if ($userId && !$this->canAccessUserSettings($request, $userId)) {
                return $this->forbidden('Unauthorized to access user settings');
            }

            if (!Settings::has($key)) {
                return $this->notFound('Setting not found');
            }

            $value = Settings::get($key, $userId);
            $data = ['key' => $key, 'value' => $value, 'user_id' => $userId];

            if ($locale) {
                $data['label'] = Settings::label($key, $locale);
                $data['description'] = Settings::description($key, $locale);
                $data['locale'] = $locale;
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    public function store(Request $request, string $key): JsonResponse {
        try {
            $request->validate([
                'value' => 'required',
                'user_id' => 'nullable|integer',
                'auto_create' => 'nullable|boolean',
            ]);

            $value = $request->input('value');
            $userId = $request->input('user_id') ? (int) $request->input('user_id') : null;
            $autoCreate = $request->boolean('auto_create', config('settings-kit.api.auto_create_missing_settings', false));

            if ($userId && !$this->canModifyUserSettings($request, $userId)) {
                return $this->forbidden('Unauthorized to modify user settings');
            }

            if (!Settings::has($key) && !$autoCreate) {
                return response()->json([
                    'success' => false,
                    'error' => 'Setting not found',
                    'message' => 'Set auto_create=true to create it automatically.',
                ], 404);
            }

            $created = !Settings::has($key);
            Settings::set($key, $value, $userId, $autoCreate);

            return response()->json([
                'success' => true,
                'message' => $created ? 'Setting created and updated successfully' : 'Setting updated successfully',
                'data' => ['key' => $key, 'value' => $value, 'user_id' => $userId],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    public function update(Request $request, string $key): JsonResponse {
        return $this->store($request, $key);
    }

    public function destroy(Request $request, string $key): JsonResponse {
        try {
            $userId = $request->input('user_id') ? (int) $request->input('user_id') : null;

            if ($userId && !$this->canModifyUserSettings($request, $userId)) {
                return $this->forbidden('Unauthorized to modify user settings');
            }

            if (!Settings::has($key)) {
                return $this->notFound('Setting not found');
            }

            Settings::forget($key, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Setting reset to default successfully',
                'data' => ['key' => $key, 'user_id' => $userId],
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e);
        }
    }

    // ============================================
    // Helpers
    // ============================================

    private function resolveUserId(Request $request): ?int {
        if ($request->get('user_id')) {
            return (int) $request->get('user_id');
        }

        return Auth::check() ? (int) Auth::user()->getAuthIdentifier() : null;
    }

    private function notFound(string $message): JsonResponse {
        return response()->json(['success' => false, 'error' => $message], 404);
    }

    private function badRequest(string $message): JsonResponse {
        return response()->json(['success' => false, 'error' => $message], 400);
    }

    private function forbidden(string $message): JsonResponse {
        return response()->json(['success' => false, 'error' => $message], 403);
    }

    private function validationError(ValidationException $e): JsonResponse {
        return response()->json(['success' => false, 'error' => 'Validation failed', 'errors' => $e->errors()], 422);
    }

    private function serverError(\Exception $e): JsonResponse {
        return response()->json(['success' => false, 'error' => 'Internal server error', 'message' => $e->getMessage()], 500);
    }

    protected function canAccessUserSettings(Request $request, int $userId): bool {
        // Token mode is intended for server-to-server integrations and has full access.
        // For user-facing APIs, use Sanctum or Passport which enforce identity.
        if (config('settings-kit.api.auth_mode') === 'token') {
            return true;
        }

        $user = Auth::user();

        return $user && ($user->getAuthIdentifier() == $userId || $this->isAdmin($user));
    }

    protected function canModifyUserSettings(Request $request, int $userId): bool {
        return $this->canAccessUserSettings($request, $userId);
    }

    protected function isAdmin($user): bool {
        return method_exists($user, 'isAdmin') ? $user->isAdmin() : false;
    }
}
