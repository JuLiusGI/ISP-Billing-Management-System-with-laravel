@props([
    'title',
    'description' => null,
    'from' => null,
    'to' => null,
])

<div class="d-flex flex-wrap gap-2 align-items-start justify-content-between mb-3">
    <div>
        <h2 class="h5 mb-0 text-navy">{{ $title }}</h2>
        @if ($description)
            <p class="small text-secondary mb-0">{{ $description }}</p>
        @endif
        @if ($from && $to)
            <p class="small text-secondary mb-0">
                {{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}
            </p>
        @endif
    </div>

    <a href="{{ route('reports.index') }}" class="btn btn-sm btn-light border">
        <i class="bi bi-arrow-left me-1"></i> All reports
    </a>
</div>

{{ $slot }}
