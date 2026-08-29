@php
    $toasts = collect([
        ['key' => 'success', 'icon' => 'check-circle-fill', 'class' => 'text-bg-success'],
        ['key' => 'error',   'icon' => 'exclamation-triangle-fill', 'class' => 'text-bg-danger'],
        ['key' => 'status',  'icon' => 'info-circle-fill', 'class' => 'text-bg-primary'],
    ])->filter(fn ($toast) => session()->has($toast['key']));
@endphp

@if ($toasts->isNotEmpty())
    <div class="toast-container position-fixed top-0 end-0 p-3">
        @foreach ($toasts as $toast)
            <div class="toast align-items-center border-0 {{ $toast['class'] }}" role="alert"
                 aria-live="assertive" aria-atomic="true" data-app-toast>
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-{{ $toast['icon'] }} me-1"></i>
                        {{ session($toast['key']) }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto"
                            data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        @endforeach
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('[data-app-toast]').forEach((el) => {
                new window.bootstrap.Toast(el, { delay: 5000 }).show();
            });
        </script>
    @endpush
@endif
