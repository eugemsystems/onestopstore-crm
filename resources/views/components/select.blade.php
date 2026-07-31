@props([
    'id'            => null,
    'name'          => null,
    'label'         => '',
    'options'       => [],  // Array of options (if keyAsValue=true, array of values)
    'keyAsValue'    => false,
    'value'         => null, // currently selected value
    'required'      => false,
    'disabled'      => false,
    'searchThreshold' => 20,
])

@php
    $selectId = $id ?? $name;
    // Prepare options mapping for search (value => label)
    if ($keyAsValue) {
        $mapped = [];
        foreach (array_values($options) as $opt) {
            $mapped[$opt] = str_replace(['_', '-'], ' ', $opt);
        }
    } else {
        $mapped = $options;
    }
    $jsonOptions = json_encode($mapped);
    $optionCount = count($mapped);
@endphp

<div
    x-data="{
        open: false,
        filter: '',
        options: {{ $jsonOptions }},
        selected: @entangle($name),
        get filtered() {
            return Object.entries(this.options)
                .filter(([key, label]) =>
                    label.toLowerCase().includes(this.filter.toLowerCase())
                );
        }
    }"
    x-on:keydown.escape="open = false"
    x-on:click.outside="open = false"
    class="position-relative mb-4"
>
    <label
        for="{{ $selectId }}"
        class="position-absolute txt-primary start-3 ms-2 top-0 border-bottom-info translate-middle-y px-0"
        style="z-index:1; font-size:11px; background:white; padding:1px 10px; border-radius:20px;"
    >
        {{ $label }} {!! $required ? '<span class="text-danger">*</span>' : '' !!}
    </label>

    <div class="form-input position-relative">
        @if($optionCount <= $searchThreshold)
            <!-- Simple select when few options -->
            <select
                id="{{ $selectId }}"
                name="{{ $name }}"
                wire:model="{{ $name }}"
                {{ $attributes->merge(['class' => 'form-select ps-2 py-2 border rounded']) }}
                @if($disabled) disabled @endif
                @if($required) required @endif
            >
                <option value=""></option>
                @foreach($mapped as $optValue => $optLabel)
                    <option value="{{ $optValue }}" @selected((string)$optValue === (string)$value)>
                        {{ $optLabel }}
                    </option>
                @endforeach
            </select>
        @else
            <!-- Searchable input when many options -->
            <input
                type="text"
                x-model="filter"
                @input="open = filter.length > 0"
                class="border rounded ps-2 py-2 w-full"
                placeholder="Select {{ $label }}"
                @keydown.enter.prevent="
                    if(filtered.length === 1) {
                        selected = filtered[0][0];
                        filter = options[selected];
                        open = false;
                    }
                "
                autocomplete="off"
                :value="options[selected]"
            />

            <div
                x-show="open && filter.length > 0"
                x-transition
                x-ref="dropdown"
                class="absolute mt-1 w-full bg-white border rounded shadow-lg z-50 max-h-60 overflow-auto"
            >
                <template x-for="([key, label]) in filtered" :key="key">
                    <div
                        @click="selected = key; filter = label; open = false"
                        class="px-2 py-1 hover:bg-gray-100 cursor-pointer"
                    >
                        <span x-text="label"></span>
                    </div>
                </template>
                <div x-show="filtered.length === 0" class="px-2 py-1 text-gray-500">
                    No results.
                </div>
            </div>

            <!-- Hidden native select for validation/model binding -->
            <select
                id="{{ $selectId }}"
                name="{{ $name }}"
                wire:model="{{ $name }}"
                class="d-none"
                @if($required) required @endif
                @if($disabled) disabled @endif
            >
                @foreach($mapped as $optValue => $optLabel)
                    <option value="{{ $optValue }}" @selected((string)$optValue === (string)$value)></option>
                @endforeach
            </select>
        @endif
    </div>

    <x-input-error :messages="$errors->get($name)" />
</div>
