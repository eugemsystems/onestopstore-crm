@props([
    'title'   => '',        // The heading inside the card
    'options' => [],        // Array of ['value'=>..., 'label'=>..., 'id'=>..., 'checked'=>bool]
    'wire'    => null,      // e.g. 'wire:model="foo"' or null
])

<div {{ $attributes->merge(['class' => 'card-wrapper border rounded-3 checkbox-checked']) }}>
    @if($title)
        <h6 class="sub-title">{{ $title }}</h6>
    @endif

    @foreach($options as $opt)
        @php
            // allow passing id or fall back to the value
            $optId = $opt['id'] ?? \Str::slug($opt['value']);
            $isChecked = ! empty($opt['checked']);
        @endphp

        <div class="form-check">
            <input
                class="form-check-input"
                type="checkbox"
                id="{{ $optId }}"
                value="{{ $opt['value'] }}"
            @if($wire) {!! $wire !!} @endif
                @checked($isChecked)
            >
            <label class="form-check-label" for="{{ $optId }}">
                {{ $opt['label'] }}
            </label>
        </div>
    @endforeach
</div>
