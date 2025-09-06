{{-- resources/views/settings/user.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4>Les meves Preferències</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <form method="POST" action="{{ route('user.settings.update') }}">
                        @csrf
                        
                        @foreach($userSettings as $category => $settings)
                            <div class="settings-category mb-4">
                                <h5 class="border-bottom pb-2 mb-3">
                                    <i class="fas fa-{{ $category === 'notifications' ? 'bell' : ($category === 'privacy' ? 'shield-alt' : 'cog') }}"></i>
                                    {{ ucfirst($category) }}
                                </h5>
                                
                                <div class="row">
                                    @foreach($settings as $key => $setting)
                                        <div class="col-md-6 mb-3">
                                            <div class="setting-item">
                                                <label class="form-label fw-bold">
                                                    {{ Settings::label($key) }}
                                                </label>
                                                
                                                @if($setting['description'])
                                                    <p class="text-muted small mb-2">
                                                        {{ Settings::description($key) }}
                                                    </p>
                                                @endif
                                                
                                                @if($setting['type'] === 'boolean')
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" 
                                                               type="checkbox" 
                                                               name="settings[{{ $key }}]" 
                                                               value="1" 
                                                               id="{{ $key }}"
                                                               {{ $setting['value'] ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="{{ $key }}">
                                                            {{ $setting['value'] ? 'Activat' : 'Desactivat' }}
                                                        </label>
                                                    </div>
                                                    
                                                @elseif($setting['type'] === 'select')
                                                    <select name="settings[{{ $key }}]" class="form-select">
                                                        @foreach($setting['options'] as $optionValue => $optionLabel)
                                                            <option value="{{ $optionValue }}" 
                                                                    {{ $setting['value'] == $optionValue ? 'selected' : '' }}>
                                                                {{ $optionLabel }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    
                                                @elseif($setting['type'] === 'integer')
                                                    <input type="number" 
                                                           name="settings[{{ $key }}]" 
                                                           value="{{ $setting['value'] }}" 
                                                           class="form-control"
                                                           min="0">
                                                           
                                                @else
                                                    <input type="text" 
                                                           name="settings[{{ $key }}]" 
                                                           value="{{ $setting['value'] }}" 
                                                           class="form-control">
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Desar Preferències
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // UX improvement: update switch labels
    const switches = document.querySelectorAll('.form-check-input[type="checkbox"]');
    switches.forEach(switch => {
        switch.addEventListener('change', function() {
            const label = this.nextElementSibling;
            label.textContent = this.checked ? 'Activat' : 'Desactivat';
        });
    });
});
</script>
@endpush
