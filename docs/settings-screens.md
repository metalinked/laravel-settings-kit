# Building settings screens

The package ships no UI. `panel()` returns everything a form needs, and your app renders it with the stack it already uses. This page shows the same "user preferences" screen three ways: Blade, Livewire and a JavaScript SPA.

All examples assume the `User` model uses the `HasSettings` trait and these definitions:

```php
// config/settings-kit.php
'settings' => [
    'appearance.theme' => ['options' => ['light' => 'Light', 'dark' => 'Dark'], 'default' => 'light', 'overridable' => true],
    'notifications.email' => ['default' => true, 'overridable' => true],
    'items_per_page' => ['default' => 20, 'rules' => 'min:5|max:100', 'overridable' => true],
    'integrations.api_token' => ['type' => 'string', 'nullable' => true, 'encrypted' => true, 'overridable' => true],
],
```

Each panel item looks like this (see the README for every field):

```php
['key' => 'appearance.theme', 'type' => 'select', 'label' => 'Theme', 'description' => null,
 'value' => 'dark', 'default' => 'light', 'filled' => true, 'overridden' => true, 'encrypted' => false,
 'nullable' => false, 'options' => [['value' => 'light', 'label' => 'Light'], ...], 'meta' => []]
```

Three rules apply to every screen:

1. **Render by `type`**: `boolean` is a checkbox, `select` a dropdown, `integer` and `float` number inputs, anything else a text input. `array` settings need a widget of their own and are left out here.
2. **Secrets are write-only.** For `encrypted` settings, `value` is always `null`. Show `filled` ("configured" or not) and only send a value when the user types a new one.
3. **Errors are keyed by setting**, e.g. `appearance.theme`, so you can show them next to each field.

The same code works for a global admin screen: use `Settings::panel()` and `Settings::set()` instead of `$user->settings()`, behind your own authorization.

## Blade

```php
// routes/web.php
Route::middleware('auth')->group(function () {
    Route::get('preferences', [PreferencesController::class, 'edit'])->name('preferences.edit');
    Route::put('preferences', [PreferencesController::class, 'update'])->name('preferences.update');
});
```

```php
// app/Http/Controllers/PreferencesController.php
class PreferencesController extends Controller {
    public function edit(Request $request): View {
        return view('preferences', ['panel' => $request->user()->settings()->panel()]);
    }

    public function update(Request $request): RedirectResponse {
        $settings = $request->user()->settings();
        $values = $request->input('settings', []);

        // An empty secret field means "keep the current value".
        foreach ($settings->panel() as $item) {
            if ($item['encrypted'] && blank($values[$item['key']] ?? null)) {
                unset($values[$item['key']]);
            }
        }

        // Validates everything first; on failure Laravel redirects back with the errors.
        $settings->set($values);

        return back()->with('status', 'Preferences saved.');
    }
}
```

Input names like `settings[appearance.theme]` keep the dot, so keys arrive as they are defined.

```blade
{{-- resources/views/preferences.blade.php --}}
<form method="POST" action="{{ route('preferences.update') }}">
    @csrf
    @method('PUT')

    @foreach ($panel as $item)
        @continue($item['type'] === 'array')
        @php($name = "settings[{$item['key']}]")
        @php($value = old('settings')[$item['key']] ?? $item['value'])

        <div>
            <label>{{ $item['label'] }}</label>

            @if ($item['encrypted'])
                <input type="password" name="{{ $name }}" autocomplete="off"
                       placeholder="{{ $item['filled'] ? 'Configured. Type to replace it.' : 'Not set' }}">
            @elseif ($item['type'] === 'boolean')
                <input type="hidden" name="{{ $name }}" value="0">
                <input type="checkbox" name="{{ $name }}" value="1" @checked($value)>
            @elseif ($item['type'] === 'select')
                <select name="{{ $name }}">
                    @foreach ($item['options'] as $option)
                        <option value="{{ $option['value'] }}" @selected((string) $option['value'] === (string) $value)>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            @elseif (in_array($item['type'], ['integer', 'float']))
                <input type="number" name="{{ $name }}" value="{{ $value }}" step="{{ $item['type'] === 'float' ? 'any' : 1 }}">
            @else
                <input type="text" name="{{ $name }}" value="{{ $value }}">
            @endif

            @if ($item['description'])
                <small>{{ $item['description'] }}</small>
            @endif

            @error($item['key'])
                <p class="error">{{ $message }}</p>
            @enderror
        </div>
    @endforeach

    <button type="submit">Save</button>
</form>
```

## Livewire

Livewire treats dots in `wire:model` as nesting, so the component binds values by position and keeps the keys on the server. A user cannot change which settings are written, only their values, which are validated as usual.

```php
// app/Livewire/Preferences.php
use Livewire\Attributes\Computed;
use Livewire\Component;

class Preferences extends Component {
    /** Values in the same order as the panel. */
    public array $values = [];

    #[Computed]
    public function panel(): array {
        return auth()->user()->settings()->panel();
    }

    public function mount(): void {
        $this->values = array_column($this->panel, 'value');
    }

    public function save(): void {
        $values = [];

        foreach ($this->panel as $i => $item) {
            if ($item['type'] === 'array' || ($item['encrypted'] && blank($this->values[$i] ?? null))) {
                continue;
            }

            $values[$item['key']] = $this->values[$i] ?? null;
        }

        // A ValidationException fills the error bag, keyed by setting.
        auth()->user()->settings()->set($values);

        unset($this->panel);
        $this->values = array_column($this->panel, 'value');
        session()->flash('status', 'Preferences saved.');
    }
}
```

```blade
{{-- resources/views/livewire/preferences.blade.php --}}
<form wire:submit="save">
    @foreach ($this->panel as $i => $item)
        @continue($item['type'] === 'array')

        <div wire:key="{{ $item['key'] }}">
            <label>{{ $item['label'] }}</label>

            @if ($item['encrypted'])
                <input type="password" wire:model="values.{{ $i }}" autocomplete="off"
                       placeholder="{{ $item['filled'] ? 'Configured. Type to replace it.' : 'Not set' }}">
            @elseif ($item['type'] === 'boolean')
                <input type="checkbox" wire:model="values.{{ $i }}">
            @elseif ($item['type'] === 'select')
                <select wire:model="values.{{ $i }}">
                    @foreach ($item['options'] as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            @elseif (in_array($item['type'], ['integer', 'float']))
                <input type="number" wire:model="values.{{ $i }}" step="{{ $item['type'] === 'float' ? 'any' : 1 }}">
            @else
                <input type="text" wire:model="values.{{ $i }}">
            @endif

            @error($item['key']) <p class="error">{{ $message }}</p> @enderror
        </div>
    @endforeach

    <button type="submit">Save</button>
</form>
```

## JavaScript SPA

Register the JSON endpoints inside your API auth group (see "JSON endpoints" in the README):

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(fn () => Settings::routes());
```

Every endpoint answers with the updated panel, so the client can re-render from the response. This example uses `axios`, which sends Sanctum's `XSRF-TOKEN` cookie for SPA authentication:

```js
import axios from 'axios';

const api = axios.create({ baseURL: '/api/settings', withCredentials: true, withXSRFToken: true });

// Load the user's panel (optionally one group: { params: { group: 'appearance' } }).
export async function loadPreferences() {
    const { data } = await api.get('/me');
    return data.data;
}

// Send only what changed. Leave secrets out unless the user typed a new one.
export async function savePreferences(changes) {
    try {
        const { data } = await api.patch('/me', { values: changes });
        return { panel: data.data, errors: {} };
    } catch (error) {
        if (error.response?.status === 422) {
            // { "appearance.theme": ["The Theme setting must be one of: light, dark."] }
            return { panel: null, errors: error.response.data.errors };
        }
        throw error;
    }
}

// Go back to the inherited value (global value, or the code default).
export async function resetPreference(key) {
    const { data } = await api.delete(`/me/${encodeURIComponent(key)}`);
    return data.data;
}
```

Use `overridden` to show a "Reset" button only where the user has their own value, and `default` to tell them what they will get after resetting.
