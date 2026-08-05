<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->name }} — {{ ucwords(str_replace('-', ' ', $site->name)) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased">

    <header class="bg-white border-b border-gray-100">
        <div class="max-w-4xl mx-auto px-6 py-5 flex items-center gap-3">
            <a href="{{ url($site->name.'/store') }}" class="text-gray-400 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-lg font-bold tracking-tight">{{ ucwords(str_replace('-', ' ', $site->name)) }} <span class="text-gray-400 font-normal">Store</span></h1>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-10">
        @if(session('store_error'))
        <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">{{ session('store_error') }}</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 bg-white rounded-3xl border border-gray-100 shadow-sm p-6">
            <div class="aspect-square rounded-2xl bg-gray-100 overflow-hidden">
                @if($product->image)
                    <img src="{{ Storage::url($product->image) }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center text-6xl">🛍️</div>
                @endif
            </div>

            <div class="flex flex-col">
                <h2 class="text-2xl font-extrabold tracking-tight">{{ $product->name }}</h2>
                <p class="text-2xl font-extrabold text-indigo-600 mt-2">{{ $product->formattedPrice() }}</p>
                <p class="text-sm text-gray-500 mt-4 leading-relaxed whitespace-pre-line flex-1">{{ $product->description }}</p>

                @if($product->inventory !== null && $product->inventory <= 0)
                <p class="mt-6 text-sm font-semibold text-red-500">Out of stock</p>
                @else
                <form method="POST" action="{{ url($site->name.'/store/checkout') }}" class="mt-6 space-y-3">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <div class="flex items-center gap-3">
                        <label class="text-sm text-gray-500">Qty</label>
                        <input type="number" name="qty" value="1" min="1" max="99" class="w-20 border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <input type="email" name="email" placeholder="Email for your receipt" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    <button type="submit" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl transition-colors shadow-sm">
                        Buy now · {{ $product->formattedPrice() }}
                    </button>
                    <p class="text-xs text-gray-400 text-center">You'll be redirected to Stripe's secure checkout.</p>
                </form>
                @endif
            </div>
        </div>
    </main>
</body>
</html>
