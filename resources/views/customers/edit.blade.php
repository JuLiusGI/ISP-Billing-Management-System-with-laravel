@extends('layouts.app')

@section('title', 'Edit customer')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('customers.index') }}">Customers</a></li>
    <li class="breadcrumb-item"><a href="{{ route('customers.show', $customer) }}">{{ $customer->full_name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
    <div class="card border-0">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">
            Editing {{ $customer->full_name }}
            <code class="small ms-1">{{ $customer->account_number }}</code>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('customers.update', $customer) }}"
                  enctype="multipart/form-data" novalidate>
                @csrf
                @method('PUT')
                @include('customers._form')

                <div class="d-flex gap-2 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('customers.show', $customer) }}" class="btn btn-light border">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
