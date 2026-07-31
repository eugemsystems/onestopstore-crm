@props([
    'target' => null,
])

<button
    wire:click="{{ $target }}"
    {{ $attributes->merge(['type' => 'button', 'class' => 'btn']) }}
    wire:loading.attr="disabled"
>
    {{-- spinner only shows while the action is in-flight --}}
    <span
        class="spinner-border spinner-border-sm text-light me-2"
        role="status"
        aria-hidden="true"
        wire:loading
        wire:target="{{ $target }}"
    ></span>

    {{ $slot }}
</button>
