@extends('layouts.app')

@section('title', 'Edit subscription')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('subscriptions.index') }}">Subscriptions</a></li>
    <li class="breadcrumb-item">
        <a href="{{ route('subscriptions.show', $subscription) }}">{{ $subscription->subscription_code }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
    <div class="card border-0">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">
            Editing {{ $subscription->subscription_code }}
            <span class="badge {{ $subscription->status->badgeClass() }} ms-1">
                {{ $subscription->status->label() }}
            </span>
        </div>
        <div class="card-body">
            <p class="small text-secondary">
                Service status is changed from the subscription page, so every change leaves a log entry.
            </p>

            <form method="POST" action="{{ route('subscriptions.update', $subscription) }}" novalidate>
                @csrf
                @method('PUT')
                @include('subscriptions._form')

                <div class="d-flex gap-2 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('subscriptions.show', $subscription) }}" class="btn btn-light border">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
