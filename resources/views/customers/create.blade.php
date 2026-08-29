@extends('layouts.app')

@section('title', 'Add customer')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('customers.index') }}">Customers</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add customer</li>
@endsection

@section('content')
    <div class="card border-0">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">
            New customer
            <span class="fw-normal small text-secondary">— the account number is generated on save</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('customers.store') }}" enctype="multipart/form-data" novalidate>
                @csrf
                @include('customers._form')

                <div class="d-flex gap-2 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">Create customer</button>
                    <a href="{{ route('customers.index') }}" class="btn btn-light border">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
