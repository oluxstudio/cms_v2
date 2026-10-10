@extends('memberships-public-shell')
@section('title', 'Link expired')
@section('content')
    <h1 class="text-2xl font-extrabold tracking-tight">This sign-in link has expired</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Sign-in links only work for a short time. Enter your email for a fresh one.</p>
    @include('memberships-public-login-form')
@endsection
