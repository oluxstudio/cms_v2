{{-- Default right-rail content: the house Quick access links.
     Expects $siteName. Used by x-tri-layout and custom three-pane shells. --}}
                <div class="flex items-center justify-between pt-1 shrink-0">
                    <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Quick access</h3>
                </div>
                @foreach([
                    ['New page',       ($siteName ?? '').'/pages',     'M12 4v16m8-8H4',                                                                                                                                                                                                        '#eef2ff','#6366f1'],
                    ['Upload assets',  ($siteName ?? '').'/media',     'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',                                                                                                                                                       '#fef2f2','#ef4444'],
                    ['New form',       ($siteName ?? '').'/forms',     'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',                                                                                     '#fffbeb','#d97706'],
                    ['View analytics', ($siteName ?? '').'/analytics', 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',              '#f0fdf4','#16a34a'],
                ] as [$qLabel, $qHref, $qIcon, $qBg, $qFg])
                <a href="{{ url($qHref) }}" class="shrink-0 flex items-center gap-3 bg-white dark:bg-[#1d1e2a] rounded-2xl px-4 py-3 shadow-sm border border-gray-100/80 dark:border-white/[0.05] hover:shadow-md transition-shadow group">
                    <span class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" style="background:{{ $qBg }};color:{{ $qFg }}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $qIcon }}"/></svg>
                    </span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200 group-hover:text-gray-900 dark:group-hover:text-white">{{ $qLabel }}</span>
                    <svg class="w-4 h-4 ml-auto text-gray-300 group-hover:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
                @endforeach
