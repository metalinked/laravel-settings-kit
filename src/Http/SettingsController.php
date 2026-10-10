<?php

namespace Metalinked\LaravelSettingsKit\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Metalinked\LaravelSettingsKit\ScopedSettings;
use Metalinked\LaravelSettingsKit\SettingsManager;

/**
 * JSON endpoints registered with Settings::routes().
 *
 * Global settings require the configured Gate ability; "me" endpoints act on
 * the authenticated user. Only defined settings are exposed or accepted.
 */
class SettingsController extends Controller {
    public function __construct(protected SettingsManager $settings) {
    }

    public function index(Request $request): JsonResponse {
        Gate::authorize(config('settings-kit.http.global_ability', 'manage-settings'));

        return $this->panel($this->settings->global(), $request);
    }

    public function update(Request $request): JsonResponse {
        Gate::authorize(config('settings-kit.http.global_ability', 'manage-settings'));

        $scope = $this->settings->global();
        $scope->set($this->incomingValues($request, $scope));

        return $this->panel($scope, $request);
    }

    public function indexMine(Request $request): JsonResponse {
        return $this->panel($this->userSettings($request), $request);
    }

    public function updateMine(Request $request): JsonResponse {
        $scope = $this->userSettings($request);
        $scope->set($this->incomingValues($request, $scope));

        return $this->panel($scope, $request);
    }

    /**
     * Reset one override (DELETE /me/{key}) or all of them (DELETE /me).
     */
    public function destroyMine(Request $request, ?string $key = null): JsonResponse {
        $scope = $this->userSettings($request);

        if ($key === null) {
            $scope->reset();
        } else {
            $this->ensureEditable([$key], $scope);
            $scope->forget($key);
        }

        return $this->panel($scope, $request);
    }

    protected function userSettings(Request $request): ScopedSettings {
        $user = $request->user();

        abort_if($user === null, 401);

        return $this->settings->for($user);
    }

    protected function panel(ScopedSettings $scope, Request $request): JsonResponse {
        $group = $request->query('group');

        return response()->json(['data' => $scope->panel(is_string($group) ? $group : null)]);
    }

    /**
     * Read {"values": {"key": value, …}} from the body. Keys are taken
     * literally, so dotted keys like "appearance.theme" work.
     *
     * @return array<string, mixed>
     */
    protected function incomingValues(Request $request, ScopedSettings $scope): array {
        $values = $request->json('values', $request->input('values'));

        if (!is_array($values) || $values === [] || array_is_list($values)) {
            throw ValidationException::withMessages(['values' => 'Send an object of setting keys and values in "values".']);
        }

        $this->ensureEditable(array_map('strval', array_keys($values)), $scope);

        return $values;
    }

    /**
     * Reject keys that are not defined, or that this scope may not override,
     * before anything is written.
     *
     * @param list<string> $keys
     */
    protected function ensureEditable(array $keys, ScopedSettings $scope): void {
        $modelClass = $scope->scope()->modelClass();
        $errors = [];

        foreach ($keys as $key) {
            $definition = $this->settings->definition($key);

            if ($definition === null) {
                $errors[$key] = "Unknown setting '{$key}'.";
            } elseif (!$scope->scope()->isGlobal() && !$definition->isOverridableBy($modelClass)) {
                $errors[$key] = "The setting '{$key}' cannot be changed here.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
