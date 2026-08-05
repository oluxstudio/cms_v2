{{-- In-page "Generate site files" form. Expects: $siteName, $genTemplates, $canGenerate --}}
<div class="max-w-2xl mx-auto">

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Generate site files</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Bake this site into a self-contained static app (HTML + <code class="text-xs">content.json</code>) and choose where it goes.
        </p>
    </div>

    {{-- Result / error flashes --}}
    @if (session('generate_ok'))
        <div class="mb-5 rounded-2xl border border-emerald-200 dark:border-emerald-500/20 bg-emerald-50 dark:bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">
            {!! session('generate_ok') !!}
        </div>
    @endif
    @if (session('generate_err'))
        <div class="mb-5 rounded-2xl border border-rose-200 dark:border-rose-500/20 bg-rose-50 dark:bg-rose-500/10 px-4 py-3 text-sm text-rose-700 dark:text-rose-300">
            {{ session('generate_err') }}
        </div>
    @endif

    @if (! $canGenerate)
        <div class="rounded-2xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a] p-6 text-sm text-gray-500 dark:text-gray-400">
            Only the site owner or an admin can generate &amp; export this site.
        </div>
    @else
    <form method="POST" action="{{ url($siteName.'/generate') }}"
          x-data="{ dest: 'folder' }"
          class="space-y-6 rounded-2xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a] p-6 shadow-sm">
        @csrf

        {{-- Template --}}
        <div>
            <label class="block text-sm font-semibold text-gray-800 dark:text-gray-100 mb-1.5">Template</label>
            <p class="text-xs text-gray-400 mb-2">Which template app to bundle. Defaults to this site's template.</p>
            <select name="template"
                    class="w-full px-3 py-2.5 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @foreach ($genTemplates as $t)
                    <option value="{{ $t['key'] }}" @selected(($genCurrentKey ?? null) === $t['key'])>{{ $t['name'] }}</option>
                @endforeach
            </select>
        </div>

        {{-- Where --}}
        <div>
            <label class="block text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Where should the files go?</label>
            <div class="grid sm:grid-cols-3 gap-2.5">
                @php
                    $dests = [
                        ['folder', 'Server folder', 'Writes to public/sites/ on this server.', 'M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z'],
                        ['zip',    'Download ZIP',  'Get the bundle as a .zip to host anywhere.', 'M12 4v12m0 0l-4-4m4 4l4-4M4 20h16'],
                        ['github', 'GitHub repo',   'Push to a connected GitHub repository.', 'M12 2a10 10 0 00-3.16 19.49c.5.09.68-.22.68-.48v-1.7c-2.78.6-3.37-1.34-3.37-1.34-.45-1.15-1.1-1.46-1.1-1.46-.9-.62.07-.6.07-.6 1 .07 1.53 1.03 1.53 1.03.89 1.52 2.34 1.08 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.55-1.11-4.55-4.94 0-1.09.39-1.98 1.03-2.68-.1-.25-.45-1.27.1-2.64 0 0 .84-.27 2.75 1.02a9.5 9.5 0 015 0c1.91-1.29 2.75-1.02 2.75-1.02.55 1.37.2 2.39.1 2.64.64.7 1.03 1.59 1.03 2.68 0 3.84-2.34 4.69-4.57 4.94.36.31.68.92.68 1.85v2.74c0 .27.18.58.69.48A10 10 0 0012 2z'],
                    ];
                @endphp
                @foreach ($dests as [$val, $title, $desc, $icon])
                    <label class="cursor-pointer rounded-xl border p-3 transition-colors"
                           :class="dest === '{{ $val }}' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10' : 'border-gray-200 dark:border-white/[0.08] hover:border-gray-300'">
                        <input type="radio" name="destination" value="{{ $val }}" x-model="dest" class="sr-only">
                        <svg class="w-5 h-5 mb-1.5 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $title }}</p>
                        <p class="text-[11px] text-gray-400 mt-0.5 leading-snug">{{ $desc }}</p>
                    </label>
                @endforeach
            </div>

            {{-- GitHub note --}}
            <p x-show="dest === 'github'" x-cloak class="text-xs text-gray-400 mt-2">
                Needs a connected token — set it up under
                <a href="{{ url($siteName.'/templates') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Templates → Publish to GitHub</a>.
            </p>
        </div>

        <div class="flex items-center justify-end gap-2 pt-1">
            <button type="submit"
                    class="fx inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white shadow-sm hover:opacity-90"
                    style="background:#1f2330">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Generate
            </button>
        </div>
    </form>
    @endif
</div>
