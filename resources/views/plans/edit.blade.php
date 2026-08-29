@extends('layouts.app')

@section('title', 'Edit internet plan')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('plans.index') }}">Internet plans</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $plan->name }}</li>
@endsection

@section('content')
    <div class="card border-0">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">
            Editing {{ $plan->name }}
            <code class="small ms-1">{{ $plan->plan_code }}</code>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('plans.update', $plan) }}" novalidate>
                @csrf
                @method('PUT')
                @include('plans._form')

                <div class="d-flex gap-2 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('plans.index') }}" class="btn btn-light border">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
