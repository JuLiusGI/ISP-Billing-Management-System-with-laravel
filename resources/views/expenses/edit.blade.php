@extends('layouts.app')

@section('title', 'Edit expense')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">Expenses</a></li>
    <li class="breadcrumb-item"><a href="{{ route('expenses.show', $expense) }}">{{ $expense->expense_reference }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
    <div class="card border-0">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">
            Editing <code class="small">{{ $expense->expense_reference }}</code>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('expenses.update', $expense) }}" novalidate>
                @csrf
                @method('PUT')
                @include('expenses._form')

                <div class="d-flex gap-2 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('expenses.show', $expense) }}" class="btn btn-light border">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
