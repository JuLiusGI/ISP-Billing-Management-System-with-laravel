<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $receipt->receipt_number }} &middot; {{ $company['name'] }}</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body class="doc-body">

<div class="doc-toolbar d-print-none">
    <div class="container d-flex gap-2 align-items-center py-2">
        <a href="{{ route('receipts.show', $receipt) }}" class="btn btn-sm btn-light border">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <button type="button" class="btn btn-sm btn-primary ms-auto" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print
        </button>
    </div>
</div>

<main class="doc-sheet">
    @include('receipts._document')
</main>

</body>
</html>
