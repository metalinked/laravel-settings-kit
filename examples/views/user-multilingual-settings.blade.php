{{-- resources/views/user/settings.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>{{ __('My Settings') }}</h4>
                </div>

                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('user.settings.save') }}">
                        @csrf

                        @foreach($settings as $key => $setting)
                            <div class="mb-4">
                                <label class="form-label">
                                    <strong>{{ $setting['label'] }}</strong>
                                </label>
                                
                                {{-- Show description with icon --}}
                                @if($setting['description'])
                                    <div class="text-muted small mb-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        {{ $setting['description'] }}
                                    </div>
                                @endif

                                @if($setting['type'] === 'boolean')
                                    <div class="form-check form-switch">
                                        <input type="checkbox" 
                                               class="form-check-input" 
                                               name="settings[{{ $key }}]" 
                                               id="user_{{ $key }}"
                                               value="1"
                                               @if($setting['value']) checked @endif>
                                        <label class="form-check-label" for="user_{{ $key }}">
                                            {{ $setting['value'] ? __('Enabled') : __('Disabled') }}
                                        </label>
                                    </div>

                                @elseif($setting['type'] === 'select')
                                    <select name="settings[{{ $key }}]" class="form-select">
                                        @if($setting['options'])
                                            @foreach($setting['options'] as $optionValue => $optionLabel)
                                                <option value="{{ $optionValue }}" 
                                                        @if($setting['value'] == $optionValue) selected @endif>
                                                    {{ $optionLabel }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>

                                @elseif($setting['type'] === 'integer')
                                    <input type="number" 
                                           name="settings[{{ $key }}]" 
                                           class="form-control" 
                                           value="{{ $setting['value'] }}">

                                @else
                                    <input type="text" 
                                           name="settings[{{ $key }}]" 
                                           class="form-control" 
                                           value="{{ $setting['value'] }}">
                                @endif
                            </div>
                        @endforeach

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                {{ __('Save My Settings') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Quick settings card --}}
            <div class="card mt-4">
                <div class="card-header">
                    <h5>{{ __('Quick Settings') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @if(isset($settings['email_notifications']))
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $settings['email_notifications']['label'] }}</strong>
                                    <br><small class="text-muted">{{ $settings['email_notifications']['description'] }}</small>
                                </div>
                                <div class="form-check form-switch">
                                    <input type="checkbox" 
                                           class="form-check-input quick-setting" 
                                           data-setting="email_notifications"
                                           @if($settings['email_notifications']['value']) checked @endif>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if(isset($settings['marketing_emails']))
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $settings['marketing_emails']['label'] }}</strong>
                                    <br><small class="text-muted">{{ $settings['marketing_emails']['description'] }}</small>
                                </div>
                                <div class="form-check form-switch">
                                    <input type="checkbox" 
                                           class="form-check-input quick-setting" 
                                           data-setting="marketing_emails"
                                           @if($settings['marketing_emails']['value']) checked @endif>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle quick settings toggles
    document.querySelectorAll('.quick-setting').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const settingKey = this.dataset.setting;
            const value = this.checked;
            
            // Make AJAX request to save setting immediately
            fetch('{{ route("user.settings.quick-save") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    key: settingKey,
                    value: value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success feedback
                    showToast('{{ __("Setting saved successfully") }}', 'success');
                } else {
                    // Revert toggle and show error
                    this.checked = !value;
                    showToast('{{ __("Error saving setting") }}', 'error');
                }
            })
            .catch(error => {
                // Revert toggle and show error
                this.checked = !value;
                showToast('{{ __("Error saving setting") }}', 'error');
            });
        });
    });
});

function showToast(message, type) {
    // Simple toast implementation (you can replace with your preferred toast library)
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>
@endpush
@endsection
