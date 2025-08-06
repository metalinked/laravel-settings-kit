{{-- resources/views/admin/settings.blade.php --}}
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#global">
                                <i class="fas fa-globe"></i> Configuracions Globals
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#admin">
                                <i class="fas fa-user-shield"></i> Preferències Admin
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body">
                    <div class="tab-content">
                        {{-- CONFIGURACIONS GLOBALS --}}
                        <div class="tab-pane fade show active" id="global">
                            <form method="POST" action="{{ route('admin.settings.global.update') }}">
                                @csrf
                                
                                <div class="row">
                                    @foreach($globalSettings as $key => $setting)
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-light">
                                                <div class="card-body">
                                                    <h6 class="card-title">{{ Settings::label($key) }}</h6>
                                                    <p class="card-text text-muted small">
                                                        {{ Settings::description($key) }}
                                                    </p>
                                                    
                                                    @if($setting['type'] === 'boolean')
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   name="global_settings[{{ $key }}]" 
                                                                   value="1" 
                                                                   {{ $setting['value'] ? 'checked' : '' }}>
                                                            <label class="form-check-label">
                                                                {{ $setting['value'] ? 'Activat' : 'Desactivat' }}
                                                            </label>
                                                        </div>
                                                    @else
                                                        <input type="text" 
                                                               name="global_settings[{{ $key }}]" 
                                                               value="{{ $setting['value'] }}" 
                                                               class="form-control">
                                                    @endif
                                                    
                                                    <small class="text-info">
                                                        <i class="fas fa-info-circle"></i>
                                                        Categoria: {{ $setting['category'] }}
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Actualitzar Configuració Global
                                </button>
                            </form>
                        </div>
                        
                        {{-- PREFERÈNCIES ADMIN --}}
                        <div class="tab-pane fade" id="admin">
                            <form method="POST" action="{{ route('admin.settings.personal.update') }}">
                                @csrf
                                
                                <div class="row">
                                    @foreach($adminSettings as $key => $setting)
                                        @if($setting['category'] === 'admin_notifications' || $setting['role'] === 'admin')
                                            <div class="col-md-6 mb-3">
                                                <div class="setting-item">
                                                    <label class="form-label fw-bold">
                                                        {{ Settings::label($key) }}
                                                    </label>
                                                    <p class="text-muted small">
                                                        {{ Settings::description($key) }}
                                                    </p>
                                                    
                                                    @if($setting['type'] === 'boolean')
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   name="admin_settings[{{ $key }}]" 
                                                                   value="1" 
                                                                   {{ $setting['value'] ? 'checked' : '' }}>
                                                        </div>
                                                    @elseif($setting['type'] === 'select')
                                                        <select name="admin_settings[{{ $key }}]" class="form-select">
                                                            @foreach($setting['options'] as $optionValue => $optionLabel)
                                                                <option value="{{ $optionValue }}" 
                                                                        {{ $setting['value'] == $optionValue ? 'selected' : '' }}>
                                                                    {{ $optionLabel }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-cog"></i> Actualitzar Preferències Personals
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
