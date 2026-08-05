<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book {{ $service->name }} — {{ ucwords(str_replace('-', ' ', $site->name)) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased">

    <header class="bg-white border-b border-gray-100">
        <div class="max-w-3xl mx-auto px-6 py-5 flex items-center justify-between">
            <h1 class="text-lg font-bold tracking-tight">{{ ucwords(str_replace('-', ' ', $site->name)) }}</h1>
            <a href="{{ route('public.book', ['siteName' => $site->name]) }}" class="text-sm text-gray-400 hover:text-gray-700">← All services</a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-6 py-10">
        <div class="mb-6">
            <h2 class="text-xl font-extrabold">{{ $service->name }}</h2>
            <div class="flex items-center gap-3 mt-1.5 text-sm text-gray-500">
                <span>🕒 {{ $service->duration_min }} min</span><span class="text-gray-300">·</span>
                <span class="font-bold text-gray-800">{{ $service->formattedPrice() }}</span>
            </div>
            @if($service->description)<p class="text-sm text-gray-500 mt-3">{{ $service->description }}</p>@endif
        </div>

        @if(session('book_error'))
        <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">{{ session('book_error') }}</div>
        @endif
        @if($errors->any())
        <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        @if(empty($dates))
            <div class="bg-white rounded-2xl border border-gray-100 p-8 text-center text-gray-500">
                No open days are available for booking right now.
            </div>
        @else
        {{-- Date picker (reloads with ?date) --}}
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Pick a day</p>
        <div class="flex flex-wrap gap-2 mb-8">
            @foreach($dates as $d)
            @php $c = \Carbon\Carbon::parse($d); @endphp
            <a href="{{ route('public.book.show', ['siteName' => $site->name, 'service' => $service->slug, 'date' => $d]) }}"
               class="px-3 py-2 rounded-xl border text-sm text-center transition
                   {{ $d === $selected
                        ? 'bg-indigo-600 border-indigo-600 text-white font-semibold'
                        : 'bg-white border-gray-200 text-gray-600 hover:border-indigo-300' }}">
                <span class="block text-[11px] {{ $d === $selected ? 'text-indigo-100' : 'text-gray-400' }}">{{ $c->format('D') }}</span>
                <span class="block font-bold">{{ $c->format('M j') }}</span>
            </a>
            @endforeach
        </div>

        <form method="POST" action="{{ route('public.book.store', ['siteName' => $site->name]) }}">
            @csrf
            <input type="hidden" name="service" value="{{ $service->slug }}">

            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Pick a time</p>
            @if(empty($slots))
                <p class="text-sm text-gray-500 mb-8">No times available on this day — try another.</p>
            @else
            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-8" x-data="{ sel: '' }">
                @foreach($slots as $slot)
                <label class="cursor-pointer">
                    <input type="radio" name="start" value="{{ $slot['iso'] }}" class="peer sr-only" x-model="sel" required>
                    <span class="block px-2 py-2.5 rounded-xl border border-gray-200 text-sm text-center text-gray-700 bg-white
                                 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 hover:border-indigo-300 transition">
                        {{ $slot['label'] }}
                    </span>
                </label>
                @endforeach
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 p-5 space-y-4">
                <p class="text-sm font-bold">Your details</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <input name="name" value="{{ old('name') }}" placeholder="Full name *" required
                           class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-indigo-400 focus:ring-0">
                    <input name="email" type="email" value="{{ old('email') }}" placeholder="Email *" required
                           class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-indigo-400 focus:ring-0">
                </div>
                <input name="phone" value="{{ old('phone') }}" placeholder="Phone (optional)"
                       class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-indigo-400 focus:ring-0">
                <textarea name="notes" rows="3" placeholder="Anything we should know? (optional)"
                          class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-indigo-400 focus:ring-0">{{ old('notes') }}</textarea>
                <button type="submit"
                        class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition">
                    Confirm booking
                </button>
            </div>
            @endif
        </form>
        @endif
    </main>
</body>
</html>
