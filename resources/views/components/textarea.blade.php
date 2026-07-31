@props([
    'id'       => null,
    'name'     => null,
    'label'    => '',
    'value'    => '',
    'disabled' => false,
    'required' => false,
])

@php
    $inputId = $id ?? $name;
    $value   = old($name, $value);
@endphp

<div class="position-relative mb-4">
    <!-- Floating label with optional red asterisk for required fields -->
    <label
        for="{{ $inputId }}"
        class="position-absolute txt-primary start-3 ms-2 top-0 border-bottom-info translate-middle-y px-0"
        style="z-index: 1; font-size: 11px; background-color: white; padding: 1px 10px; border-radius: 20px;"
    >
        {{ $label }} {!! $required ? '<span class="text-danger">*</span>' : '' !!}
    </label>

    <!-- Textarea wrapper -->
    <div class="form-input position-relative">
        <textarea
            id="{{ $inputId }}"
            name="{{ $name }}"
            placeholder=""
            @disabled($disabled)
            {{ $attributes->merge([
                'class' => 'form-control ps-2 py-2 border rounded'
                           . ($errors->has($name) ? ' is-invalid' : '')
            ]) }}
        >{{ $value }}</textarea>
    </div>

    <!-- Error message -->
    <x-input-error :messages="$errors->get($name)" />
</div>
