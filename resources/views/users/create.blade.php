@extends('layouts.app')

@section('title', 'Add user')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add user</li>
@endsection

@section('content')
    <div class="card border-0">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">New staff account</div>
        <div class="card-body">
            <form method="POST" action="{{ route('users.store') }}" novalidate>
                @csrf
                @include('users._form')

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Create user</button>
                    <a href="{{ route('users.index') }}" class="btn btn-light border">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
