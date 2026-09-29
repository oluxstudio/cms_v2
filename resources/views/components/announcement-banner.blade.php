{{-- Platform announcements for the signed-in account (admin › Announcements). --}}
@auth
    @foreach (app(\App\Services\Announcements::class)->activeFor(auth()->user()) as $a)
        <x-announcement-item :a="$a" {{ $attributes }} />
    @endforeach
@endauth
