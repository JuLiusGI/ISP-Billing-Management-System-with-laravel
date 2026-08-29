@extends('layouts.app')

@section('title', 'New subscription')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('subscriptions.index') }}">Subscriptions</a></li>
    <li class="breadcrumb-item active" aria-current="page">New subscription</li>
@endsection

@section('content')
    <div class="card border-0">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">
            New subscription
            <span class="fw-normal small text-secondary">— starts as Pending until the service is activated</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('subscriptions.store') }}" novalidate>
                @csrf
                @include('subscriptions._form')

                <div class="d-flex gap-2 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">Create subscription</button>
                    <a href="{{ route('subscriptions.index') }}" class="btn btn-light border">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
