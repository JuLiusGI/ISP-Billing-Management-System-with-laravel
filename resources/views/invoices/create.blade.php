@extends('layouts.app')

@section('title', 'Create invoice')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('invoices.index') }}">Invoices</a></li>
    <li class="breadcrumb-item active" aria-current="page">Create invoice</li>
@endsection

@section('content')
    <div class="card border-0">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">
            New invoice
            <span class="fw-normal small text-secondary">— the invoice number is assigned on save</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('invoices.store') }}" novalidate>
                @csrf
                @include('invoices._form')

                <div class="d-flex gap-2 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">Create invoice</button>
                    <a href="{{ route('invoices.index') }}" class="btn btn-light border">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
