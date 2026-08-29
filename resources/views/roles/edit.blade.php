@extends('layouts.app')

@section('title', 'Edit role')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">Roles &amp; permissions</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $role->display_name }}</li>
@endsection

@section('content')
    <div class="card border-0">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">
            Editing {{ $role->display_name }}
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('roles.update', $role) }}" novalidate>
                @csrf
                @method('PUT')
                @include('roles._form')

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Save abilities</button>
                    <a href="{{ route('roles.index') }}" class="btn btn-light border">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
