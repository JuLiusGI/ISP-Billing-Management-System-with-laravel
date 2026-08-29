<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100">

    <nav class="navbar navbar-expand-lg bg-navy navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="{{ url('/') }}">
                <i class="bi bi-router-fill text-danger"></i>
                {{ config('app.name') }}
            </a>
            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-gear me-1"></i> System
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-muted">Laravel {{ app()->version() }}</span></li>
                    <li><span class="dropdown-item-text small text-muted">PHP {{ PHP_VERSION }}</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ url('/up') }}">Health check</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container flex-grow-1 py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 border-top border-4 border-danger">
                    <div class="card-body p-4 p-md-5">
                        <span class="badge text-bg-primary mb-3">Phase 1 — Foundation</span>
                        <h1 class="h3 fw-bold text-navy mb-2">ISP Billing &amp; Management System</h1>
                        <p class="text-secondary mb-4">
                            The Laravel foundation is configured and the Bootstrap 5 asset pipeline is
                            building. Business modules are not implemented yet.
                        </p>

                        <dl class="row mb-0 small">
                            <dt class="col-sm-4 text-secondary fw-normal">Framework</dt>
                            <dd class="col-sm-8">Laravel {{ app()->version() }}</dd>

                            <dt class="col-sm-4 text-secondary fw-normal">PHP</dt>
                            <dd class="col-sm-8">{{ PHP_VERSION }}</dd>

                            <dt class="col-sm-4 text-secondary fw-normal">Environment</dt>
                            <dd class="col-sm-8">
                                <span class="badge text-bg-secondary">{{ app()->environment() }}</span>
                            </dd>

                            <dt class="col-sm-4 text-secondary fw-normal">Database connection</dt>
                            <dd class="col-sm-8 mb-0">
                                <code>{{ config('database.default') }}</code>
                                &rarr; <code>{{ config('database.connections.'.config('database.default').'.database') }}</code>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-navy-deep text-white-50 py-3 mt-auto">
        <div class="container small">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </div>
    </footer>

</body>
</html>
