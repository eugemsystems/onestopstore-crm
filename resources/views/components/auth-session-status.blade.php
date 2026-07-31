@props(['status','error', 'success', 'warning'])

@isset($status)
    <div {{ $attributes->merge(['class' => 'alert alert-success dark ']) }} role="alert" style="border-radius: .5rem !important;">
        {{ $status }}
    </div>
@endif

@isset($error)
    <div {{ $attributes->merge(['class' => 'alert alert-danger dark ']) }} role="alert" style="border-radius: .5rem !important;">
        {{ $error }}
    </div>
@endif

@isset ($success)
    <div {{ $attributes->merge(['class' => 'alert alert-success dark ']) }} role="alert" style="border-radius: .5rem !important;">
        {{ $status }}
    </div>
@endif

@isset ($warning)
    <div {{ $attributes->merge(['class' => 'alert alert-warning dark ']) }} role="alert" style="border-radius: .5rem !important;">
        {{ $status }}
    </div>
@endif
