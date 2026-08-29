@extends('layouts.app')

@section('title', 'Add role')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">Roles &amp; permissions</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add role</li>
@endsection

@section('content')
    <div class="card border-0">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">New role</div>
        <div class="card-body">
            <form method="POST" action="{{ route('roles.store') }}" novalidate>
                @csrf
                @include('roles._form')

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Create role</button>
                    <a href="{{ route('roles.index') }}" class="btn btn-light border">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
