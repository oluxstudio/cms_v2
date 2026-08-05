@php
/**
 * Gradient class strings must appear as literals so Tailwind's scanner keeps them.
 * from-gray-400 to-gray-600
 * from-indigo-500 to-blue-600
 * from-emerald-400 to-teal-600
 * from-violet-500 to-purple-700
 * from-rose-400 to-orange-500
 */
@endphp

<div class="main-body px-10 py-8">

    {{-- ── Page header ── --}}
    <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase mb-1">Olux CMS</p>
    <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">Templates</h1>
    <p class="mt-2 text-gray-500 text-sm max-w-xl">Pick a starter and we'll scaffold a complete site — pages, components and content — ready to customise.</p>
    <hr class="mt-5 mb-8 border-gray-200">

    {{-- ── Template grid ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($templates as $tpl)
        <div wire:click="selectTemplate('{{ $tpl['key'] }}')"
             class="group rounded-xl border border-gray-200 bg-white overflow-hidden hover:shadow-lg hover:border-gray-300 transition-all cursor-pointer flex flex-col">

            {{-- Gradient strip --}}
            <div class="h-28 bg-gradient-to-br {{ $tpl['gradientClass'] }} relative flex items-center justify-center shrink-0">
                <div class="absolute top-3 left-3 flex gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-white/40"></span>
                    <span class="w-2 h-2 rounded-full bg-white/40"></span>
                    <span class="w-2 h-2 rounded-full bg-white/40"></span>
                </div>
                <span class="absolute top-3 right-3 text-xs font-semibold px-2.5 py-0.5 rounded-full"
                      style="background:rgba(255,255,255,0.20);color:#fff;backdrop-filter:blur(4px)">
                    {{ $tpl['category'] }}
                </span>
                <div class="space-y-2 px-6 w-full mt-4">
                    <div class="h-2.5 bg-white/30 rounded-full w-2/3 mx-auto"></div>
                    <div class="h-1.5 bg-white/20 rounded-full w-1/2 mx-auto"></div>
                    <div class="h-1.5 bg-white/20 rounded-full w-3/5 mx-auto"></div>
                </div>
            </div>

            {{-- Card body --}}
            <div class="p-5 flex flex-col flex-1">
                <h3 class="font-bold text-gray-900 text-base group-hover:text-indigo-600 transition-colors leading-tight">
                    {{ $tpl['name'] }}
                </h3>
                <p class="text-gray-500 text-xs mt-1.5 leading-relaxed line-clamp-2 flex-1">
                    {{ $tpl['description'] }}
                </p>

                <div class="mt-4 flex items-center justify-between">
                    <div class="flex items-center gap-1.5 text-xs text-gray-400">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        {{ $tpl['pageCount'] > 0 ? $tpl['pageCount'].' '.Str::plural('page', $tpl['pageCount']) : 'Blank canvas' }}
                    </div>
                    @if(!empty($tpl['previewUrl']))
                    <a href="{{ $tpl['previewUrl'] }}" target="_blank" rel="noopener" @click.stop
                       title="Open the live demo — exactly what your site will look like"
                       class="flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:border-indigo-400 hover:text-indigo-600 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12C4 7 8 4 12 4s8 3 9.5 8c-1.5 5-5.5 8-9.5 8s-8-3-9.5-8z"/></svg>
                        Preview
                    </a>
                    @endif
                    <span class="flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg text-white" style="background:var(--primary)">
                        Use Template
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </span>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ═══════════════════════════════════════════════════════
         MODAL
         ═══════════════════════════════════════════════════════ --}}
    @if($showModal && count($selectedTemplate) > 0)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.55)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl overflow-hidden flex flex-col"
             style="max-height:90vh">

            {{-- ── Gradient header ── --}}
            <div class="h-20 bg-gradient-to-br {{ $selectedTemplate['gradientClass'] }} shrink-0 relative px-6 flex items-center gap-4">
                {{-- Dots --}}
                <div class="flex gap-1.5 shrink-0">
                    <span class="w-2.5 h-2.5 rounded-full bg-white/40"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-white/40"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-white/40"></span>
                </div>

                {{-- Template name + category --}}
                <div class="flex-1 min-w-0">
                    <p class="text-white/60 text-xs font-semibold uppercase tracking-widest">{{ $selectedTemplate['category'] }}</p>
                    <p class="text-white font-bold text-lg leading-tight truncate">{{ $selectedTemplate['name'] }}</p>
                </div>

                {{-- Close button --}}
                <button wire:click="closeModal"
                        class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center cursor-pointer transition-colors"
                        style="background:rgba(255,255,255,0.15)"
                        onmouseover="this.style.background='rgba(255,255,255,0.25)'"
                        onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- ── Body ── --}}
            <div class="flex flex-1 overflow-hidden">

                {{-- ── LEFT: Site preview ── --}}
                <div class="w-3/5 border-r border-gray-100 overflow-y-auto p-6">

                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">What's included</h3>

                    @if(count($selectedTemplate['pages']) === 0)
                        {{-- Blank template --}}
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                                <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                            </div>
                            <p class="font-semibold text-gray-700 text-sm">Blank canvas</p>
                            <p class="text-gray-400 text-xs mt-1 max-w-xs leading-relaxed">No pages or components are pre-created. You start completely fresh.</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($selectedTemplate['pages'] as $pageIndex => $page)
                            <div class="rounded-xl border border-gray-100 overflow-hidden">

                                {{-- Page header --}}
                                <div class="flex items-center gap-3 px-4 py-2.5 bg-gray-50 border-b border-gray-100">
                                    {{-- Page number badge --}}
                                    <span class="w-5 h-5 rounded-md text-xs font-bold flex items-center justify-center shrink-0 text-white"
                                          style="background:var(--primary);font-size:10px">
                                        {{ $pageIndex + 1 }}
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <span class="font-semibold text-gray-800 text-sm">{{ $page['name'] }}</span>
                                        <span class="ml-2 text-xs text-gray-400 font-mono">{{ $page['url'] }}</span>
                                    </div>
                                    <span class="text-xs text-gray-400 shrink-0">
                                        {{ count($page['components']) }} {{ Str::plural('component', count($page['components'])) }}
                                    </span>
                                </div>

                                {{-- Components list --}}
                                @if(count($page['components']) > 0)
                                <div class="divide-y divide-gray-50">
                                    @foreach($page['components'] as $comp)
                                    <div class="flex items-center gap-3 px-4 py-2">
                                        {{-- Component icon --}}
                                        <div class="w-6 h-6 rounded-md bg-indigo-50 flex items-center justify-center shrink-0">
                                            <svg class="w-3 h-3 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/>
                                            </svg>
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-700 leading-none">{{ $comp['name'] }}</p>
                                            @if($comp['description'])
                                                <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $comp['description'] }}</p>
                                            @endif
                                        </div>

                                        {{-- Node count badge --}}
                                        <span class="shrink-0 text-xs px-2 py-0.5 rounded-full font-medium"
                                              style="background:#eef2ff;color:var(--primary)">
                                            {{ $comp['nodeCount'] }} {{ Str::plural('field', $comp['nodeCount']) }}
                                        </span>
                                    </div>
                                    @endforeach
                                </div>
                                @endif

                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ── RIGHT: Site creation form ── --}}
                <div class="w-2/5 flex flex-col overflow-y-auto">

                    {{-- Form header --}}
                    <div class="px-6 pt-6 pb-4 border-b border-gray-100">
                        <h2 class="text-base font-bold text-gray-900">Create your site</h2>
                        <p class="text-gray-400 text-xs mt-0.5">Fill in the details below to generate your site.</p>
                    </div>

                    <form wire:submit="generate" class="flex flex-col flex-1 px-6 py-5 gap-4">

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                                Site Name <span class="text-red-400">*</span>
                            </label>
                            <input wire:model="siteName" type="text" placeholder="my-awesome-site"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-50 transition"/>
                            @error('siteName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                                Domain <span class="text-red-400">*</span>
                            </label>
                            <input wire:model="domain" type="text" placeholder="example.com"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-50 transition"/>
                            @error('domain') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                                Owner <span class="text-red-400">*</span>
                            </label>
                            <div class="relative">
                                <input wire:model="owner" type="text" placeholder="Your name"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-50 transition pr-8"/>
                                @if($owner)
                                <div class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
                                    <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                @endif
                            </div>
                            @error('owner') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Description</label>
                            <textarea wire:model="description" rows="3" placeholder="Optional brief description…"
                                      class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none resize-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-50 transition"></textarea>
                        </div>

                        {{-- Summary pill --}}
                        @if(count($selectedTemplate['pages']) > 0)
                        <div class="rounded-lg px-3 py-2.5 text-xs" style="background:#f5f3ff">
                            <p class="font-semibold text-indigo-700 mb-1">This will create</p>
                            @php
                                $totalComponents = collect($selectedTemplate['pages'])->sum(fn($p) => count($p['components']));
                                $totalNodes = collect($selectedTemplate['pages'])->reduce(function ($carry, $p) {
                                    return $carry + collect($p['components'])->sum(fn($c) => $c['nodeCount']);
                                }, 0);
                            @endphp
                            <p class="text-indigo-500 leading-relaxed">
                                {{ count($selectedTemplate['pages']) }} {{ Str::plural('page', count($selectedTemplate['pages'])) }},
                                {{ $totalComponents }} {{ Str::plural('component', $totalComponents) }},
                                {{ $totalNodes }} content {{ Str::plural('field', $totalNodes) }}
                            </p>
                        </div>
                        @endif

                        {{-- Actions --}}
                        @if(!empty($selectedTemplate['previewUrl']))
                        <a href="{{ $selectedTemplate['previewUrl'] }}" target="_blank" rel="noopener"
                           class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12C4 7 8 4 12 4s8 3 9.5 8c-1.5 5-5.5 8-9.5 8s-8-3-9.5-8z"/></svg>
                            Live preview — exactly what your site will look like
                        </a>
                        @endif
                        <div class="mt-auto pt-2 flex items-center gap-3">
                            <button type="button" wire:click="closeModal"
                                    class="flex-1 py-2 text-sm font-medium text-gray-500 border border-gray-200 rounded-lg hover:border-gray-300 hover:text-gray-700 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="flex-1 flex items-center justify-center gap-2 py-2 text-sm font-semibold text-white rounded-lg cursor-pointer transition-opacity hover:opacity-90"
                                    style="background:var(--primary)">
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 3l14 9-14 9V3z"/>
                                </svg>
                                Generate Site
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
    @endif

</div>
