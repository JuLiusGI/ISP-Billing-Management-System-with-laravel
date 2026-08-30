@props([
    'action',
    'from' => null,
    'to' => null,
    'dated' => true,
    'exportable' => true,
])

{{-- Filters submit to the server; nothing is filtered client side. --}}
<div class="card border-0 mb-3">
    <div class="card-body">
        <form method="GET" action="{{ $action }}" class="row g-2 align-items-end">
            @if ($dated)
                <div class="col-6 col-lg-2">
                    <label for="from" class="form-label small">From</label>
                    <input type="date" name="from" id="from" class="form-control form-control-sm"
                           value="{{ request('from', $from?->toDateString()) }}">
                </div>

                <div class="col-6 col-lg-2">
                    <label for="to" class="form-label small">To</label>
                    <input type="date" name="to" id="to" class="form-control form-control-sm"
                           value="{{ request('to', $to?->toDateString()) }}">
                </div>
            @endif

            {{ $slot }}

            <div class="col-12 col-lg-auto d-flex gap-2 ms-lg-auto">
                @if ($dated)
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i> Apply
                    </button>
                    <a href="{{ $action }}" class="btn btn-sm btn-light border">Reset</a>
                @endif

                @if ($exportable)
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
                       class="btn btn-sm btn-light border">
                        <i class="bi bi-download me-1"></i> CSV
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>
