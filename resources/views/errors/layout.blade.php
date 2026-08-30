<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.scss'])
</head>
{{--
    Deliberately dependency-free.

    An error page has to render when the thing it is reporting has already
    broken, so it reads config('app.name') rather than the ISP name in system
    settings: a 500 caused by the database being unreachable must not try to
    query the database to draw itself.

    No JavaScript either, for the same reason.
--}}
<body class="error-body">
    <main class="error-panel" role="main">
        <div class="error-panel__badge">
            <i class="bi bi-@yield('icon', 'exclamation-triangle')" aria-hidden="true"></i>
        </div>

        <p class="error-panel__code">@yield('code')</p>
        <h1 class="error-panel__title">@yield('title')</h1>
        <p class="error-panel__message">@yield('message')</p>

        <div class="error-panel__actions">
            @yield('actions')
            <a href="{{ url('/') }}" class="btn btn-primary">
                <i class="bi bi-house me-1" aria-hidden="true"></i> Back to the dashboard
            </a>
        </div>
    </main>

    <p class="error-footer">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
</body>
</html>
