@props([
    'id'        => null,
    'name'      => '',
    'label'     => '',
    'required'  => false,
    'multiple'  => false,
    'accept'    => '',
])

@php
    $inputId = $id ?? $name;
@endphp

<div class="mb-3">
    <label for="{{ $inputId }}" class="form-label">
        {{ $label }} @if($required)<span class="text-danger">*</span>@endif
    </label>
    <input
        type="file"
        id="{{ $inputId }}"
        name="{{ $name }}{{ $multiple ? '[]' : '' }}"
        wire:model="{{ $name }}"
        class="form-control"
        @if($required) required @endif
        @if($multiple) multiple @endif
        @if($accept) accept="{{ $accept }}" @endif
    />
    <x-input-error :messages="$errors->get($name)" />
    <div wire:loading wire:target="{{ $name }}" class="text-sm text-gray-600 mt-1">
        <span class="spinner-border spinner-border-sm"></span> Uploading...
    </div>
</div>
