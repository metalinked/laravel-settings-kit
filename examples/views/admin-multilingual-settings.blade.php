{{-- resources/views/admin/multilingual-settings.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>{{ __('System Settings') }}</h4>
                    
                    {{-- Language Selector --}}
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            {{ strtoupper($currentLocale) }}
                        </button>
                        <ul class="dropdown-menu">
                            @foreach($availableLocales as $locale)
                                <li>
                                    <a class="dropdown-item" href="?locale={{ $locale }}">
                                        {{ strtoupper($locale) }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('admin.settings.save') }}">
                        @csrf

                        {{-- Loop through categories --}}
                        @foreach($settings as $categoryName => $categorySettings)
                            <div class="mb-4">
                                <h5 class="text-primary border-bottom pb-2">{{ ucfirst(str_replace('_', ' ', $categoryName)) }}</h5>
                                
                                @foreach($categorySettings as $key => $setting)
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <strong>{{ $setting['label'] }}</strong>
                                            @if($setting['required'])
                                                <span class="text-danger">*</span>
                                            @endif
                                        </label>
                                        
                                        {{-- Show description if available --}}
                                        @if($setting['description'])
                                            <small class="form-text text-muted d-block mb-2">
                                                {{ $setting['description'] }}
                                            </small>
                                        @endif

                                        {{-- Render different input types --}}
                                        @if($setting['type'] === 'boolean')
                                            <div class="form-check">
                                                <input type="checkbox" 
                                                       class="form-check-input" 
                                                       name="settings[{{ $key }}]" 
                                                       id="{{ $key }}"
                                                       value="1"
                                                       @if($setting['value']) checked @endif>
                                                <label class="form-check-label" for="{{ $key }}">
                                                    {{ __('Enable') }}
                                                </label>
                                            </div>

                                        @elseif($setting['type'] === 'select')
                                            <select name="settings[{{ $key }}]" class="form-select" @if($setting['required']) required @endif>
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
                                                   value="{{ $setting['value'] }}"
                                                   @if($setting['required']) required @endif>

                                        @else
                                            {{-- String type --}}
                                            <input type="text" 
                                                   name="settings[{{ $key }}]" 
                                                   class="form-control" 
                                                   value="{{ $setting['value'] }}"
                                                   @if($setting['required']) required @endif>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                {{ __('Save Settings') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
