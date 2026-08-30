@extends('errors.layout')

@section('code', '419')
@section('icon', 'hourglass-bottom')
@section('title', 'Your session expired')
@section('message', 'You were away long enough for the page to go stale. Sign in again and repeat what you were doing — nothing was saved.')

@section('actions')
    <a href="{{ route('login') }}" class="btn btn-light border">
        <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Sign in again
    </a>
@endsection
