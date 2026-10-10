@extends('memberships-public-shell')
@section('title', 'My membership')
@section('content')
    @if (! $member)
        <h1 class="text-2xl font-extrabold tracking-tight">My membership</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Sign in with the email you joined with — we'll send you a one-click link.</p>
        @include('memberships-public-login-form')
    @else
        @php
            $badge = match ($member->status) {
                'active' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                'past_due' => 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
                default => 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300',
            };
        @endphp
        <h1 class="text-2xl font-extrabold tracking-tight">Hi {{ $member->name }}</h1>
        <div class="mt-5 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-5 space-y-2">
            <div class="flex items-center justify-between gap-3">
                <span class="text-lg font-bold">{{ $member->tier?->name ?? 'Member' }}</span>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $member->status)) }}</span>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $member->priceLabel() }} · member since {{ $member->joined_at?->format('j M Y') ?? '—' }}</p>
            @if ($member->status === 'cancelled')
                <p class="text-sm text-gray-600 dark:text-gray-300">Your membership ended {{ $member->cancelled_at?->format('j M Y') }}.</p>
            @elseif ($member->cancel_at_period_end)
                <p class="text-sm text-amber-700 dark:text-amber-300">Cancelled — access continues until {{ $member->renews_at?->format('j M Y') ?? 'the end of this period' }}.</p>
            @elseif ($member->isPaid() && $member->renews_at)
                <p class="text-sm text-gray-600 dark:text-gray-300">Renews on <strong>{{ $member->renews_at->format('j M Y') }}</strong>.</p>
            @endif
            @if ($member->status === 'past_due')
                <p class="text-sm text-amber-700 dark:text-amber-300">Your last payment failed — please update your card to keep your membership.</p>
            @endif
        </div>

        <div class="mt-5 flex flex-col gap-2">
            @if ($canPortal)
                <form method="POST" action="{{ route('memberships.public.portal', $site->name) }}">@csrf
                    <button class="w-full px-5 py-3 rounded-xl text-sm font-semibold bg-gray-900 text-white dark:bg-white dark:text-gray-900">Update card &amp; invoices</button>
                </form>
            @endif
            @if (in_array($member->status, ['active', 'past_due'], true) && ! $member->cancel_at_period_end)
                <form method="POST" action="{{ route('memberships.public.cancel', $site->name) }}">@csrf
                    <button data-confirm="Cancel your membership?{{ $member->isPaid() ? ' You keep access until the end of the period you have paid for.' : '' }}"
                            class="w-full px-5 py-3 rounded-xl text-sm font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/10 text-rose-600">Cancel membership</button>
                </form>
            @endif
            @if ($siteUrl)
                <a href="{{ $siteUrl }}" class="w-full text-center px-5 py-3 rounded-xl text-sm font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/10">Back to {{ $siteTitle }}</a>
            @endif
            <form method="POST" action="{{ route('memberships.public.signout', $site->name) }}">@csrf
                <button class="w-full text-xs text-gray-400 hover:underline py-2">Sign out</button>
            </form>
        </div>
    @endif
@endsection
