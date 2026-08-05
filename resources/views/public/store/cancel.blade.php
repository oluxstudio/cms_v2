<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout cancelled — {{ ucwords(str_replace('-', ' ', $site->name)) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-3xl border border-gray-100 shadow-sm p-10 text-center">
        <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-5 text-3xl">🛒</div>
        <h1 class="text-2xl font-extrabold tracking-tight">Checkout cancelled</h1>
        <p class="text-sm text-gray-500 mt-2">No charge was made. Your items are still available.</p>
        <a href="{{ url($site->name.'/store') }}" class="inline-block mt-6 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">Back to store</a>
    </div>
</body>
</html>
