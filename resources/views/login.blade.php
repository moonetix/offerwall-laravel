@extends('layout')

@section('title', 'Sign in')

@section('content')
    <h1>Sign in</h1>
    <p class="muted">Demo sign-in. Create a user with <code>php artisan db:seed</code>.</p>
    <form method="post" action="{{ url('/login') }}" class="card">
        @csrf
        <p><label>Email<br><input type="email" name="email" value="{{ old('email') }}" required autofocus></label></p>
        <p><label>Password<br><input type="password" name="password" required></label></p>
        @error('email')<p class="error">{{ $message }}</p>@enderror
        <button type="submit">Sign in</button>
    </form>
@endsection
