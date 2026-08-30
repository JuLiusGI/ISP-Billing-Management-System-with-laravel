@php
    $toasts = collect([
        ['key' => 'success', 'icon' => 'check-circle-fill', 'class' => 'text-bg-success'],
        ['key' => 'error',   'icon' => 'exclamation-triangle-fill', 'class' => 'text-bg-danger'],
        ['key' => 'status',  'icon' => 'info-circle-fill', 'class' => 'text-bg-primary'],
    ])->filter(fn ($toast) => session()->has($toast['key']));
@endphp

@if ($toasts->isNotEmpty())
    {{--
        Below the top bar rather than under it: at the default offset the toast
        covers the user menu, so dismissing one becomes a prerequisite for
        navigating.
    --}}
    <div class="toast-container position-fixed end-0 p-3" style="top: 3.25rem; z-index: 1080;">
        @foreach ($toasts as $toast)
            {{--
                An error is announced assertively; a success is polite, so it
                does not interrupt a screen reader mid-sentence to say a save
                worked.
            --}}
            <div class="toast align-items-center border-0 {{ $toast['class'] }}"
                 role="{{ $toast['key'] === 'error' ? 'alert' : 'status' }}"
                 aria-live="{{ $toast['key'] === 'error' ? 'assertive' : 'polite' }}"
                 aria-atomic="true" data-app-toast
                 data-persist="{{ $toast['key'] === 'error' ? 'true' : 'false' }}">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-{{ $toast['icon'] }} me-1"></i>
                        {{ session($toast['key']) }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto"
                            data-bs-dismiss="toast" aria-label="Dismiss this message"></button>
                </div>
            </div>
        @endforeach
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('[data-app-toast]').forEach((el) => {
                // An error stays until it is dismissed. A failure that
                // disappears after five seconds is a failure someone misses.
                const persist = el.dataset.persist === 'true';

                new window.bootstrap.Toast(el, {
                    autohide: !persist,
                    delay: 6000,
                }).show();
            });
        </script>
    @endpush
@endif
