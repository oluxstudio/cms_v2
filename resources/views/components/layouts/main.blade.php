<html>
    <head>
        <title>{{ $title ?? 'Todo Manager' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="App" class="xl:container mx-auto my-14 p-4">
            <div class="flex flex-col lg:flex-row lg:justify-between items-center mb-6">
                <div class=" bg-slate-900 text-accent-foreground w-80 h-24 flex items-center justify-center rounded-xl">Logo</div>
                <div id="MenuBar" class="flex ">
                    <ul class="flex gap-3 text-base leading-normal">
                        <li><a href="/">Home</a></li>
                        <li><a href="/about">messages</a></li>
                        <li><a href="/about">notifications</a></li>
                        <li><a href="/about">register</a></li>
                        <li><a href="/about">login</a></li>
                    </ul>
                </div>
            </div>
            <div id="ContentBody" class="ContentBody">
                {{ $slot }}
            </div>
        </div>
        <x-confirm-modal />
</body>
</html>