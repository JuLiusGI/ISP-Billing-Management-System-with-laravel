@props([
    'label',
    'value',
    'accent' => 'navy',
    'money' => false,
    'hint' => null,
])

<div class="card border-0 h-100">
    <div class="card-body">
        <div class="text-secondary small">{{ $label }}</div>
        <div class="fs-4 fw-bold text-{{ $accent }} lh-1 mt-1">
            @if ($money)
                &#8369;{{ number_format((float) $value, 2) }}
            @else
                {{ is_numeric($value) ? number_format((float) $value) : $value }}
            @endif
        </div>
        @if ($hint)
            <div class="small text-secondary mt-1">{{ $hint }}</div>
        @endif
    </div>
</div>
