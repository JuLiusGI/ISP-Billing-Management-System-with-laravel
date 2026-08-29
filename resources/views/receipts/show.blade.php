@extends('layouts.app')

@section('title', $receipt->receipt_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('receipts.index') }}">Receipts</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $receipt->receipt_number }}</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-0 text-navy">Receipt {{ $receipt->receipt_number }}</h2>
            <p class="small text-secondary mb-0">
                For payment
                <a href="{{ route('payments.show', $payment) }}" class="text-decoration-none">
                    {{ $payment->payment_reference }}
                </a>
            </p>
        </div>

        <a href="{{ route('receipts.print', $receipt) }}" target="_blank" class="btn btn-sm btn-primary">
            <i class="bi bi-printer me-1"></i> Print receipt
        </a>
    </div>

    <div class="card border-0">
        <div class="card-body p-4 p-md-5">
            @include('receipts._document')
        </div>
    </div>
@endsection
