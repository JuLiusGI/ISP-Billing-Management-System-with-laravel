@extends('layouts.app')

@section('title', 'Edit invoice')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('invoices.index') }}">Invoices</a></li>
    <li class="breadcrumb-item">
        <a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
    <div class="alert alert-info d-flex gap-2 align-items-start" role="alert">
        <i class="bi bi-info-circle mt-1"></i>
        <div class="small">
            This invoice has no payments applied, so it can still be amended. Once a payment lands
            it becomes a historical record and is locked.
        </div>
    </div>

    <div class="card border-0">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">
            Editing {{ $invoice->invoice_number }}
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('invoices.update', $invoice) }}" novalidate>
                @csrf
                @method('PUT')
                @include('invoices._form')

                <div class="d-flex gap-2 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">Save invoice</button>
                    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-light border">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
