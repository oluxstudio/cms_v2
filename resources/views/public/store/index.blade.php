<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store — {{ ucwords(str_replace('-', ' ', $site->name)) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased">

    <header class="bg-white border-b border-gray-100">
        <div class="max-w-5xl mx-auto px-6 py-5 flex items-center justify-between">
            <h1 class="text-lg font-bold tracking-tight">{{ ucwords(str_replace('-', ' ', $site->name)) }} <span class="text-gray-400 font-normal">Store</span></h1>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-6 py-10">
        @if(session('store_error'))
        <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">{{ session('store_error') }}</div>
        @endif

        @if($products->isEmpty())
        <div class="text-center py-24">
            <span class="text-5xl">🛍️</span>
            <p class="text-gray-500 mt-4">No products available yet.</p>
        </div>
        @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($products as $p)
            <a href="{{ url($site->name.'/store/'.$p->slug) }}" class="group bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                <div class="aspect-square bg-gray-100">
                    @if($p->image)
                        <img src="{{ Storage::url($p->image) }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-5xl">🛍️</div>
                    @endif
                </div>
                <div class="p-4">
                    <h2 class="text-sm font-bold truncate">{{ $p->name }}</h2>
                    <p class="text-xs text-gray-400 mt-1 line-clamp-2">{{ $p->description }}</p>
                    <p class="text-base font-extrabold mt-3">{{ $p->formattedPrice() }}</p>
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </main>

    <footer class="max-w-5xl mx-auto px-6 py-10 text-center text-xs text-gray-400">
        Secure checkout powered by Stripe.
    </footer>
</body>
</html>
