@extends('memberships-public-shell')
@section('title', 'Welcome')
@section('content')
    <div class="text-center">
        <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-500/15 grid place-items-center mx-auto mb-5">
            <svg class="w-8 h-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        @if ($member && $member->status === 'active')
            <h1 class="text-2xl font-extrabold tracking-tight">You're in, {{ $member->name }}!</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                Your <strong>{{ $member->tier?->name }}</strong> membership ({{ $member->priceLabel() }}) is active.
                We've emailed <strong>{{ $member->email }}</strong> a sign-in link.
            </p>
        @else
            <h1 class="text-2xl font-extrabold tracking-tight">Thanks — payment received</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">We're confirming your membership. You'll get a welcome email with your sign-in link in a moment.</p>
        @endif
        @if ($siteUrl)
            <a href="{{ $siteUrl }}" class="inline-block mt-6 px-6 py-3 rounded-xl text-sm font-semibold bg-gray-900 text-white dark:bg-white dark:text-gray-900">Back to {{ $siteTitle }}</a>
        @endif
    </div>
@endsection
