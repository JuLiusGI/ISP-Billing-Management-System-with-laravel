@extends('layouts.app')

@section('title', 'Record expense')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">Expenses</a></li>
    <li class="breadcrumb-item active" aria-current="page">Record expense</li>
@endsection

@section('content')
    <div class="card border-0">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">
            New expense
            <span class="fw-normal small text-secondary">— the reference is generated on save</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('expenses.store') }}" novalidate>
                @csrf
                @include('expenses._form')

                <div class="d-flex gap-2 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">Record expense</button>
                    <a href="{{ route('expenses.index') }}" class="btn btn-light border">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
