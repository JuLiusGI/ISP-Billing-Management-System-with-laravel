@extends('layouts.guest')

@section('title', 'Reset password')
@section('heading', 'Forgot your password?')
@section('subheading', 'Enter your email address and we will send you a reset link.')

@section('content')
    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input type="email" name="email" id="email"
                   class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}" required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">Send reset link</button>

        <a href="{{ route('login') }}" class="btn btn-link w-100 mt-2 text-decoration-none">
            Back to sign in
        </a>
    </form>
@endsection
