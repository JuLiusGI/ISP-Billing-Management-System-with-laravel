<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sign in') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body class="auth-body">

<div class="auth-wrapper">
    <div class="auth-card card border-0">
        <div class="auth-card__brand">
            <i class="bi bi-router-fill fs-3 text-danger"></i>
            <span class="fw-semibold fs-5">{{ config('app.name') }}</span>
        </div>

        <div class="card-body p-4 p-md-5">
            <h1 class="h5 fw-bold text-navy mb-1">@yield('heading', 'Sign in')</h1>
            <p class="text-secondary small mb-4">@yield('subheading')</p>

            @if (session('status'))
                <div class="alert alert-success py-2 small" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            @yield('content')
        </div>
    </div>

    <p class="text-center text-secondary small mt-3 mb-0">
        &copy; {{ date('Y') }} {{ config('app.name') }}
    </p>
</div>

</body>
</html>
