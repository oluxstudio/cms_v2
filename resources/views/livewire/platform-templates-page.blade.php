@php
    $gbp = fn (int $c) => \App\Support\Money::format($c, 'gbp');
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $statusPill = fn (string $s) => match ($s) {
        'published' => ['#dcfce7', '#15803d', 'Published'],
        'in_review' => ['#fef3c7', '#92400e', 'In review'],
        'private' => ['#e0f2fe', '#0369a1', 'Private'],
        'rejected' => ['#ffe4e6', '#be123c', 'Rejected'],
        'archived' => ['#e5e7eb', '#4b5563', 'Hidden'],
        default => ['#f3f4f6', '#374151', ucfirst($s)],
    };
    $sourceLabel = fn (?string $s) => match ($s) {
        'builtin' => 'Olux Studio',
        'upload' => 'Uploaded',
        'custom' => 'Creator',
        default => ucfirst((string) $s),
    };
    $uploadPill = fn (string $s) => match ($s) {
        'ready' => ['#dcfce7', '#15803d', 'Ready'],
        'failed' => ['#ffe4e6', '#be123c', 'Failed'],
        default => ['#e0f2fe', '#0369a1', ucfirst($s)],
    };
    $btn = 'fx inline-flex items-center justify-center min-h-[36px] px-3.5 rounded-xl text-[12.5px] font-bold';
    $btnOutline = $btn.' border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200';
@endphp

<x-tri-layout title="Templates" subtitle="Every template on the platform: who uses it, what it earns, and what needs a decision."
    :labels="['📊 Numbers', '🧩 Templates', 'ℹ️ Summary']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    <x-slot:header>
        <div class="flex flex-wrap items-center gap-2">
        <button wire:click="openUpload" class="fx inline-flex items-center gap-1.5 min-h-[40px] px-4 rounded-full text-sm font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add template
        </button>
        <div class="flex items-center gap-1 p-1 rounded-full bg-white/70 dark:bg-white/[0.05] shadow-sm">
            @foreach (['catalog' => 'Catalog', 'uploads' => 'Client uploads', 'review' => 'Review'.($stats['in_review'] ? ' ('.$stats['in_review'].')' : '')] as $tk => $tl)
                <button wire:click="setTab('{{ $tk }}')"
                        class="px-4 py-1.5 rounded-full text-sm font-semibold transition-colors {{ $tab === $tk ? 'shadow-sm' : 'text-gray-600 dark:text-gray-300' }}"
                        @if ($tab === $tk) style="background:var(--foreground);color:var(--background)" @endif>{{ $tl }}</button>
            @endforeach
        </div>
        </div>
    </x-slot:header>

    {{-- ══ LEFT rail: numbers ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$stats['published']" label="In the store" :sub="$stats['sites_using'].' sites using templates'"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
        <x-tile accent="sky" :value="$stats['private']" label="Client uploads" sub="private"
                icon="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
        <x-tile accent="cocoa" :value="$stats['in_review']" label="Waiting for review" :sub="$stats['in_review'] ? 'needs a decision' : 'all clear'"
                icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lime" :value="number_format($stats['installs'])" label="Installs" sub="all time"
                icon="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
        <x-tile accent="lavender" :value="$gbp($stats['revenue_30d'])" label="Template sales" :sub="$gbp($stats['fees_30d']).' fees · 30d'"
                icon="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.3 0 2.4.5 2.8 1.3M12 8V7m0 10v-1m0 1c-1.3 0-2.4-.5-2.8-1.3M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="sky" :value="$stats['building']" label="Building now" sub="client uploads"
                icon="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        <x-tile accent="rose" :value="$stats['failed_7d']" label="Failed builds" sub="last 7 days"
                icon="M12 9v2m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
    </div>
    </x-slot:rail>

    {{-- ══ CENTER ══ --}}
    <div class="@container max-w-[52rem] mx-auto" @if ($tab === 'uploads' && $anyInProgress) wire:poll.10s @endif>

        @if ($tab !== 'review')
        {{-- Search + filters --}}
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <div class="flex-1 min-w-[14rem]"><x-field.search model="q" :placeholder="$tab === 'catalog' ? 'Search templates…' : 'Search uploads…'" /></div>
            <select wire:model.live="status" class="bkf-input !w-auto" aria-label="Status">
                <option value="">Any status</option>
                @if ($tab === 'catalog')
                    @foreach (['published' => 'Published', 'draft' => 'Draft', 'in_review' => 'In review', 'private' => 'Private', 'rejected' => 'Rejected', 'archived' => 'Hidden'] as $v => $l)
                        <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                @else
                    @foreach (['queued' => 'Queued', 'scanning' => 'Scanning', 'building' => 'Building', 'ready' => 'Ready', 'failed' => 'Failed'] as $v => $l)
                        <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                @endif
            </select>
            @if ($tab === 'catalog')
                <select wire:model.live="source" class="bkf-input !w-auto" aria-label="Source">
                    <option value="">Any source</option>
                    <option value="builtin">Olux Studio</option>
                    <option value="custom">Creators</option>
                    <option value="upload">Client uploads</option>
                </select>
                <select wire:model.live="sort" class="bkf-input !w-auto" aria-label="Sort">
                    <option value="installs">Most installed</option>
                    <option value="revenue">Top earning</option>
                    <option value="newest">Newest</option>
                    <option value="name">Name</option>
                </select>
            @endif
        </div>
        @endif

        @if ($tab === 'catalog')
            <div class="space-y-3" wire:loading.class="opacity-60">
                @forelse ($catalog as $t)
                    @php
                        [$pb, $pf, $pl] = $statusPill($t->status);
                        $using = (int) ($usedBy[$t->id] ?? 0);
                        $rev = $revenue[$t->id] ?? null;
                        $built = \App\Support\TemplatePaths::hasShell((string) ($t->builtin_key ?: $t->slug));
                    @endphp
                    <div class="{{ $panel }} p-4 flex flex-wrap items-center gap-4">
                        <button wire:click="open('{{ $t->id }}')" class="w-20 h-20 rounded-2xl overflow-hidden bg-gray-100 dark:bg-white/[0.05] shrink-0" aria-label="Details for {{ $t->name }}">
                            @if ($t->thumbnail_url)<img src="{{ $t->thumbnail_url }}" alt="" class="w-full h-full object-cover object-top">@endif
                        </button>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2">
                                <button wire:click="open('{{ $t->id }}')" class="text-[15px] font-bold text-gray-900 dark:text-white hover:underline truncate">{{ $t->name }}</button>
                                <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:{{ $pb }};color:{{ $pf }}">{{ $pl }}</span>
                                <span class="flex items-center gap-1 text-[11px] {{ $built ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600' }}" title="{{ $built ? 'Built site files exist' : 'Not built yet' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $built ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>{{ $built ? 'Built' : 'Not built' }}
                                </span>
                                @if ($t->pending_update)
                                    <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                                          title="The CMS repo has a newer copy of this template (deployed {{ \Illuminate\Support\Carbon::parse($t->pending_update['detected_at'] ?? now())->diffForHumans() }})">↑ Update available</span>
                                @endif
                            </p>
                            <p class="text-[12px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                {{ $t->status === 'private' ? ($t->user?->name ?? 'Client') : ($t->creator?->name ?? $sourceLabel($t->source)) }}
                                · {{ $t->status === 'private' ? 'Client upload' : $sourceLabel($t->source) }} · <span class="font-mono">{{ $t->builtin_key ?: $t->slug }}</span>
                            </p>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[12px] text-gray-700 dark:text-gray-300">
                                <span><b class="tabular-nums">{{ $using }}</b> {{ Str::plural('site', $using) }} using</span>
                                <span><b class="tabular-nums">{{ number_format($t->installs_count) }}</b> installs</span>
                                <span>{{ $t->isFree() ? 'Free' : $gbp((int) $t->price_cents) }}</span>
                                @if ($rev)<span><b>{{ $gbp((int) $rev->gross) }}</b> from {{ $rev->n }} {{ Str::plural('sale', $rev->n) }}</span>@endif
                                @if ($t->rating_count)<span>{{ number_format($t->rating_avg, 1) }}★ ({{ $t->rating_count }})</span>@endif
                                <span>{{ $t->versions_count }} {{ Str::plural('version', $t->versions_count) }}</span>
                            </div>
                        </div>
                        <div class="w-full @2xl:w-auto flex flex-wrap items-center justify-end gap-2 @2xl:shrink-0 pt-3 @2xl:pt-0 border-t @2xl:border-0 border-gray-100 dark:border-white/[0.06]">
                            @if ($preview = $t->previewUrl())
                                <x-preview-button :href="$preview" label="Preview" small />
                            @endif
                            @if ($t->status === 'archived')
                                <button wire:click="show('{{ $t->id }}')" class="{{ $btnOutline }}">Show</button>
                            @elseif ($t->status !== 'private' && $t->status !== 'in_review')
                                <button wire:click="togglePublished('{{ $t->id }}')"
                                        @if ($t->status === 'published') data-confirm="Hide {{ $t->name }} from the Templates store? Sites already using it keep it." @endif
                                        class="{{ $t->status === 'published' ? $btnOutline : $btn }}"
                                        @if ($t->status !== 'published') style="background:var(--primary);color:var(--on-primary)" @endif>
                                    {{ $t->status === 'published' ? 'Unpublish' : 'Publish' }}
                                </button>
                            @endif
                            @if (\App\Livewire\PlatformTemplatesPage::isRepoBuiltin($t))
                                <button wire:click="applyBuiltinUpdate('{{ $t->id }}')" wire:loading.attr="disabled" wire:target="applyBuiltinUpdate('{{ $t->id }}')"
                                        title="Publish the copy of {{ $t->name }} from the deployed CMS code as its next version"
                                        class="{{ $t->pending_update ? $btn : $btnOutline }} disabled:opacity-60"
                                        @if ($t->pending_update) style="background:var(--primary);color:var(--on-primary)" @endif><svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4v5h5M20 20v-5h-5M5.6 15A7 7 0 0018.4 15M18.4 9A7 7 0 005.6 9"/></svg>Update template</button>
                            @endif
                            @if (\App\Livewire\PlatformTemplatesPage::canNewVersion($t))
                                @if ($t->source_repo)
                                    <button wire:click="updateFromGithub('{{ $t->id }}')" wire:loading.attr="disabled" wire:target="updateFromGithub('{{ $t->id }}')"
                                            title="Pull the latest from {{ Str::after($t->source_repo, 'github.com/') }}{{ $t->source_branch ? ' ('.$t->source_branch.')' : '' }} and build a new version"
                                            class="{{ $btnOutline }} disabled:opacity-60"><svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4v5h5M20 20v-5h-5M5.6 15A7 7 0 0018.4 15M18.4 9A7 7 0 005.6 9"/></svg>Update from GitHub</button>
                                @else
                                    <button wire:click="openNewVersion('{{ $t->id }}')" class="{{ $btnOutline }}">New version</button>
                                @endif
                            @endif
                            <button wire:click="startEdit('{{ $t->id }}')" class="{{ $btnOutline }}">Edit</button>
                        </div>
                    </div>
                @empty
                    <div class="{{ $panel }} p-10 text-center text-sm text-gray-500">No templates match.</div>
                @endforelse
            </div>
            <div class="mt-5">{{ $catalog->links() }}</div>

        @elseif ($tab === 'uploads')
            <div class="{{ $panel }} overflow-hidden" wire:loading.class="opacity-60">
                @forelse ($uploads as $u)
                    @php [$pb, $pf, $pl] = $uploadPill($u->status); @endphp
                    <div class="px-5 py-4 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[14px] font-bold text-gray-900 dark:text-white truncate max-w-[18rem]">{{ $u->name ?: $u->original_filename ?: $u->key }}</span>
                            <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:{{ $pb }};color:{{ $pf }}">{{ $pl }}</span>
                            @if ($u->lint_score !== null)<span class="text-[11px] text-gray-500 dark:text-gray-400">quality {{ $u->lint_score }}/100</span>@endif
                            <span class="ml-auto flex items-center gap-2">
                                @if ($u->status === 'ready' && $u->template_id)
                                    <button wire:click="open('{{ $u->template_id }}')" class="{{ $btnOutline }}">Template</button>
                                @endif
                                @unless ($u->inProgress() || ($u->template_id && ! $u->replaces_template_id))
                                    <button wire:click="deleteUpload('{{ $u->id }}')" data-confirm="Delete this upload and its files?" class="{{ $btnOutline }}">Delete</button>
                                @endunless
                            </span>
                        </div>
                        <p class="mt-1 text-[12px] text-gray-500 dark:text-gray-400">
                            @if ($u->user)<a href="{{ route('admin.account', $u->user_id) }}" wire:navigate class="font-semibold hover:underline">{{ $u->user->name }}</a>@endif
                            @if ($u->site) · site {{ $u->site->name }}@endif
                            · uploaded {{ $u->created_at->diffForHumans() }}
                            @if ($u->build_started_at && $u->finished_at) · built in {{ $u->build_started_at->diffForHumans($u->finished_at, true) }}@endif
                            · <span class="font-mono">{{ $u->key }}</span>
                        </p>
                        @if ($u->inProgress())
                            <p class="mt-1.5 flex items-center gap-2 text-[12.5px] text-gray-700 dark:text-gray-300">
                                <svg class="animate-spin w-3.5 h-3.5" style="color:var(--primary)" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                {{ $u->step ?: 'Working' }}…
                            </p>
                        @elseif ($u->error)
                            <p class="mt-1.5 text-[12.5px] text-rose-700 dark:text-rose-400 break-words">{{ $u->error }}</p>
                        @endif
                    </div>
                @empty
                    <p class="p-10 text-center text-sm text-gray-500">No client uploads yet.</p>
                @endforelse
            </div>
            <div class="mt-5">{{ $uploads->links() }}</div>

        @else
            {{-- Review queue --}}
            <div class="space-y-3">
                @forelse ($review as $t)
                    <div class="{{ $panel }} p-5">
                        <div class="flex flex-wrap items-start gap-4">
                            <div class="w-24 h-24 rounded-2xl overflow-hidden bg-gray-100 dark:bg-white/[0.05] shrink-0">
                                @if ($t->thumbnail_url)<img src="{{ $t->thumbnail_url }}" alt="" class="w-full h-full object-cover object-top">@endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[15px] font-bold text-gray-900 dark:text-white">{{ $t->name }}</p>
                                <p class="text-[12px] text-gray-500 dark:text-gray-400">
                                    by {{ $t->creator?->name ?? $t->user?->name ?? 'unknown' }} · {{ $t->isFree() ? 'Free' : $gbp((int) $t->price_cents) }}
                                    · submitted {{ $t->submitted_at?->diffForHumans() ?? 'recently' }}
                                </p>
                                @if ($t->short_description || $t->description)
                                    <p class="mt-2 text-[13px] text-gray-700 dark:text-gray-300 line-clamp-3">{{ $t->short_description ?: $t->description }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                @if ($preview = $t->previewUrl())
                                    <x-preview-button :href="$preview" label="Preview" small />
                                @endif
                                <button wire:click="startReject('{{ $t->id }}')" class="{{ $btnOutline }}">Send back</button>
                                <button wire:click="approve('{{ $t->id }}')" class="{{ $btn }}" style="background:var(--primary);color:var(--on-primary)">Approve</button>
                            </div>
                        </div>
                        @if ($rejectingId === $t->id)
                            <form wire:submit="reject" class="mt-4 space-y-2">
                                <label class="bkf-label" for="reject-{{ $t->id }}">What should the creator change?</label>
                                <textarea id="reject-{{ $t->id }}" wire:model="rejectReason" rows="3" class="bkf-input w-full"></textarea>
                                @error('rejectReason')<p class="text-[12px] font-semibold text-rose-600">{{ $message }}</p>@enderror
                                <div class="flex justify-end gap-2">
                                    <button type="button" wire:click="$set('rejectingId', null)" class="{{ $btnOutline }}">Cancel</button>
                                    <button type="submit" class="{{ $btn }} bg-rose-600 text-white">Send back</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="{{ $panel }} p-10 text-center">
                        <p class="text-sm font-bold text-gray-800 dark:text-gray-100">Nothing waiting for review</p>
                        <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mt-1">Creator templates submitted for the store appear here.</p>
                    </div>
                @endforelse
            </div>
        @endif
    </div>

    {{-- ══ RIGHT rail: summary ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-2">Most installed</h3>
            @forelse ($topInstalled as $t)
                <button wire:click="open('{{ $t->id }}')" class="w-full flex items-center gap-2 py-2 text-left {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="w-6 text-[12px] font-bold text-gray-400">{{ $loop->iteration }}</span>
                    <span class="flex-1 min-w-0 text-[13px] font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $t->name }}</span>
                    <span class="text-[12px] font-bold tabular-nums text-gray-700 dark:text-gray-200">{{ number_format($t->installs_count) }}</span>
                </button>
            @empty
                <p class="py-3 text-[12.5px] text-gray-400">No installs yet.</p>
            @endforelse
        </div>

        <div class="{{ $panel }} p-5">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-[15px] font-bold text-gray-900 dark:text-white">Recent uploads</h3>
                <button wire:click="setTab('uploads')" class="text-[12px] font-bold hover:underline" style="color:var(--primary)">All →</button>
            </div>
            @forelse ($recentUploads as $u)
                @php [$pb, $pf, $pl] = $uploadPill($u->status); @endphp
                <div class="flex items-center gap-2 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="flex-1 min-w-0">
                        <span class="block text-[13px] font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $u->name ?: $u->original_filename ?: $u->key }}</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $u->user?->name }} · {{ $u->created_at->diffForHumans(short: true) }}</span>
                    </span>
                    <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full shrink-0" style="background:{{ $pb }};color:{{ $pf }}">{{ $pl }}</span>
                </div>
            @empty
                <p class="py-3 text-[12.5px] text-gray-400">No uploads yet.</p>
            @endforelse
        </div>

        <div class="rounded-[1.75rem] p-5 shadow-sm" style="background:var(--foreground);color:var(--background)">
            <h3 class="font-display text-[16px] font-bold">How templates reach clients</h3>
            <ul class="mt-2 space-y-1.5 text-[12.5px] opacity-85 list-disc ml-4">
                <li>Olux Studio templates ship with the app and are published here.</li>
                <li>Creators submit theirs for review before they reach the store.</li>
                <li>Client uploads stay private to the account that uploaded them.</li>
            </ul>
        </div>

        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Related</h3>
            @foreach ([['Dashboard', 'platform numbers', route('admin.dashboard')], ['Accounts', 'clients & plans', route('admin.accounts')]] as [$rl, $rd, $ru])
                <a href="{{ $ru }}" wire:navigate class="flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $rl }}</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $rd }}</span>
                    </span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    </x-slot:quick>

    {{-- ══ Edit drawer (right side, fixed header) ══ --}}
    @if ($editingId)
        <x-side-drawer close="cancelEdit" width="max-w-xl">
            <x-slot:header>
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Edit template</p>
                <h2 class="font-display text-xl font-bold text-gray-900 dark:text-white truncate">{{ $edit['name'] ?: 'Untitled' }}</h2>
            </x-slot:header>

            <form id="tpl-edit-form" wire:submit="saveEdit" class="p-6 space-y-5">
                <div class="flex items-start gap-4">
                    <div class="w-28 h-20 rounded-xl overflow-hidden bg-gray-100 dark:bg-white/[0.05] shrink-0">
                        @if ($thumbnail && method_exists($thumbnail, 'temporaryUrl'))
                            <img src="{{ $thumbnail->temporaryUrl() }}" alt="" class="w-full h-full object-cover object-top">
                        @elseif ($editingTemplate?->thumbnail_url)
                            <img src="{{ $editingTemplate->thumbnail_url }}" alt="" class="w-full h-full object-cover object-top">
                        @endif
                    </div>
                    <label class="block flex-1 min-w-0">
                        <span class="bkf-label">Thumbnail <span class="font-normal text-gray-500">(store cards · JPG, PNG or WebP, 4 MB max)</span></span>
                        <input type="file" wire:model="thumbnail" accept="image/jpeg,image/png,image/webp"
                               class="bkf-input w-full file:mr-3 file:rounded-lg file:border-0 file:px-3 file:py-1.5 file:text-[12.5px] file:font-bold file:bg-gray-100 dark:file:bg-white/[0.08] file:text-gray-700 dark:file:text-gray-200">
                        <span wire:loading wire:target="thumbnail" class="text-[12px] text-gray-500">Sending the image…</span>
                        @error('thumbnail')<span class="block text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        @if ($editingTemplate?->thumbnail_url && ! $thumbnail)
                            <button type="button" wire:click="removeThumbnail" data-confirm="Remove this template's thumbnail?" class="mt-1 text-[12px] font-semibold text-rose-600 hover:underline">Remove thumbnail</button>
                        @endif
                    </label>
                </div>
                <label class="block">
                    <span class="bkf-label">Name</span>
                    <input type="text" wire:model.live.debounce.300ms="edit.name" maxlength="80" class="bkf-input w-full">
                    @error('edit.name')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                </label>
                <label class="block">
                    <span class="bkf-label">Tagline <span class="font-normal text-gray-500">(the one-line description on template cards)</span></span>
                    <input type="text" wire:model="edit.short_description" maxlength="200" class="bkf-input w-full">
                    @error('edit.short_description')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                </label>
                <label class="block">
                    <span class="bkf-label">Description <span class="font-normal text-gray-500">(template page)</span></span>
                    <textarea wire:model="edit.description" rows="7" maxlength="5000" class="bkf-input w-full"
                              placeholder="Who it's for, what's included, what makes it stand out…"></textarea>
                    @error('edit.description')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                </label>
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="bkf-label">Category</span>
                        <input type="text" wire:model="edit.category" list="adm-categories" class="bkf-input w-full">
                        <datalist id="adm-categories">
                            @foreach ((array) config('templates.categories') as $c)<option value="{{ $c }}"></option>@endforeach
                        </datalist>
                    </label>
                    <label class="block">
                        <span class="bkf-label">Price (£, empty = free)</span>
                        <input type="number" step="0.01" min="0" wire:model="edit.price" class="bkf-input w-full">
                        @error('edit.price')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </label>
                </div>
                <label class="block">
                    <span class="bkf-label">Tags <span class="font-normal text-gray-500">(comma separated)</span></span>
                    <input type="text" wire:model="edit.tags" maxlength="300" placeholder="church, events, donations" class="bkf-input w-full">
                </label>

                @if ($editingId && ($repoTpl = \App\Models\Template::find($editingId)) && \App\Livewire\PlatformTemplatesPage::canNewVersion($repoTpl))
                    <div class="grid sm:grid-cols-[1fr_10rem] gap-3">
                        <label class="block">
                            <span class="bkf-label">GitHub repository <span class="font-normal text-gray-500">(for "Update from GitHub")</span></span>
                            <input type="url" wire:model="edit.source_repo" placeholder="https://github.com/owner/repo" class="bkf-input w-full">
                            @error('edit.source_repo')<span class="block text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                        <label class="block">
                            <span class="bkf-label">Branch</span>
                            <input type="text" wire:model="edit.source_branch" placeholder="default" class="bkf-input w-full">
                            @error('edit.source_branch')<span class="block text-[12px] font-semibold text-rose-600">That branch name isn't valid.</span>@enderror
                        </label>
                    </div>
                @endif

                <fieldset>
                    <legend class="bkf-label">Features it turns on</legend>
                    <p class="text-[12px] text-gray-500 dark:text-gray-400 mb-2">Switched on automatically for a site when this template is applied.</p>
                    <div class="grid sm:grid-cols-2 gap-2">
                        @foreach ($allFeatures as $fk => $f)
                            <label class="relative flex items-start gap-2.5 rounded-xl border px-3 py-2.5 cursor-pointer transition-colors {{ in_array($fk, $edit['features'], true) ? '' : 'border-gray-200 dark:border-white/[0.1]' }}"
                                   @if (in_array($fk, $edit['features'], true)) style="border-color:var(--primary);background:color-mix(in srgb, var(--primary) 8%, transparent)" @endif>
                                <input type="checkbox" wire:model.live="edit.features" value="{{ $fk }}" class="mt-0.5 accent-[var(--primary)]">
                                <span class="min-w-0">
                                    <span class="block text-[13px] font-bold text-gray-900 dark:text-white">{{ $f['name'] ?? ucfirst($fk) }}</span>
                                    @if (! empty($f['description']))<span class="block text-[11.5px] text-gray-500 dark:text-gray-400 line-clamp-2">{{ $f['description'] }}</span>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                {{-- Which collections reset for each site vs load with the template's entries --}}
                @php $tplCollections = ($t0 = \App\Models\Template::find($editingId)) ? \App\Livewire\PlatformTemplatesPage::templateCollections($t0) : []; @endphp
                @if ($tplCollections)
                    <fieldset>
                        <legend class="bkf-label">Collections when a site uses this template</legend>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400 mb-2">
                            Tick <b>Reset</b> for the site's own content (e.g. Bible Studies, Sermons, Events) — it starts empty and shows only what the site adds.
                            Everything else loads with the template's entries, as in the preview. Sites' existing entries are never changed.
                        </p>
                        <div class="grid sm:grid-cols-2 gap-2">
                            @foreach ($tplCollections as $cname => $ccount)
                                @php $isReset = in_array($cname, (array) ($edit['reset_collections'] ?? []), true); @endphp
                                <label class="flex items-center gap-2.5 rounded-xl border px-3 py-2 cursor-pointer transition-colors {{ $isReset ? '' : 'border-gray-200 dark:border-white/[0.1]' }}"
                                       @if ($isReset) style="border-color:var(--primary);background:color-mix(in srgb, var(--primary) 8%, transparent)" @endif>
                                    <input type="checkbox" wire:model.live="edit.reset_collections" value="{{ $cname }}" class="accent-[var(--primary)]">
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13px] font-bold text-gray-900 dark:text-white truncate">{{ $cname }}</span>
                                        <span class="block text-[11.5px] text-gray-500 dark:text-gray-400">{{ $isReset ? 'Reset — starts empty' : 'Loads '.$ccount.' template '.Str::plural('entry', $ccount) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                @php
                    $editTpl = \App\Models\Template::find($editingId);
                    $lockedPrivate = $editTpl && $editTpl->isAccountUpload();
                    $grants = $editTpl ? $editTpl->entitlements()->with('user:id,name,email')->latest()->get() : collect();
                @endphp
                <fieldset>
                    <legend class="bkf-label">Who can see it</legend>
                    <div class="grid sm:grid-cols-2 gap-2">
                        @foreach (['public' => ['Public', 'Listed in the Templates store for everyone.'], 'private' => ['Private', 'Hidden. Only the accounts you assign below can see and use it.']] as $vk => [$vl, $vd])
                            <label class="flex items-start gap-2.5 rounded-xl border px-3 py-2.5 {{ $lockedPrivate && $vk === 'public' ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer' }} {{ $edit['visibility'] === $vk ? '' : 'border-gray-200 dark:border-white/[0.1]' }}"
                                   @if ($edit['visibility'] === $vk) style="border-color:var(--primary);background:color-mix(in srgb, var(--primary) 8%, transparent)" @endif>
                                <input type="radio" wire:model.live="edit.visibility" value="{{ $vk }}" class="mt-0.5 accent-[var(--primary)]" @disabled($lockedPrivate && $vk === 'public')>
                                <span>
                                    <span class="block text-[13px] font-bold text-gray-900 dark:text-white">{{ $vl }}</span>
                                    <span class="block text-[11.5px] text-gray-500 dark:text-gray-400">{{ $vd }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @if ($lockedPrivate)<p class="text-[11.5px] text-gray-500 dark:text-gray-400 mt-1.5">A client's own upload always stays private to their account.</p>@endif
                </fieldset>

                @if ($edit['visibility'] === 'private')
                    <fieldset>
                        <legend class="bkf-label">Assigned accounts</legend>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400 mb-2">They'll find it under "Made for you" in their Templates store and can use it on any of their sites. Removing an account doesn't change sites already using it.</p>
                        <div class="flex gap-2">
                            <input type="email" wire:model="assignEmail" placeholder="account owner's email" class="bkf-input w-full" wire:keydown.enter.prevent="assignAccount">
                            <button type="button" wire:click="assignAccount" class="{{ $btnOutline }} shrink-0">Assign</button>
                        </div>
                        @error('assignEmail')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        <div class="mt-3 divide-y divide-gray-50 dark:divide-white/[0.04]">
                            @forelse ($grants as $g)
                                <div class="flex items-center gap-3 py-2">
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13px] font-bold text-gray-900 dark:text-white truncate">{{ $g->user?->name ?? 'Deleted account' }}</span>
                                        <span class="block text-[11.5px] text-gray-500 dark:text-gray-400 truncate">{{ $g->user?->email }} · {{ ['granted' => 'assigned', 'upload' => 'their upload', 'purchase' => 'bought', 'free' => 'added'][$g->source] ?? $g->source }}</span>
                                    </span>
                                    @if ($g->source === 'granted' && $g->user)
                                        <button type="button" wire:click="unassignAccount('{{ $g->user->id }}')" data-confirm="Remove {{ $g->user->name }}'s access to this template? Their sites keep their current design." class="text-[12px] font-semibold text-rose-600 hover:underline">Remove</button>
                                    @endif
                                </div>
                            @empty
                                <p class="text-[12.5px] text-gray-500 dark:text-gray-400 py-2">Not assigned to any account yet.</p>
                            @endforelse
                        </div>
                    </fieldset>
                @endif
            </form>

            <x-slot:footer>
                @php
                    $editBlocker = $editTpl ? \App\Livewire\PlatformTemplatesPage::deleteBlocker($editTpl) : null;
                @endphp
                <div class="flex items-center gap-2">
                    {{-- Delete a mistaken / duplicate template (sales & activity records are kept). --}}
                    @if ($editTpl)
                        @if ($editBlocker)
                            <span class="{{ $btnOutline }} opacity-50 cursor-not-allowed !text-rose-600" title="{{ $editBlocker }}">Delete</span>
                        @else
                            <button type="button" wire:click="deleteTemplate('{{ $editTpl->id }}')"
                                    data-confirm="Delete {{ $editTpl->name }}{{ $editTpl->builtin_key ? ' ('.$editTpl->builtin_key.')' : '' }}? Its versions and files are removed for good. Sales and activity records are kept."
                                    class="{{ $btnOutline }} !text-rose-600 !border-rose-200 dark:!border-rose-500/30">Delete</button>
                        @endif
                    @endif
                    <span class="flex-1"></span>
                    <button type="button" wire:click="cancelEdit" class="{{ $btnOutline }}">Cancel</button>
                    <button type="submit" form="tpl-edit-form" class="{{ $btn }} min-w-[7rem]" style="background:var(--primary);color:var(--on-primary)">
                        <span wire:loading.remove wire:target="saveEdit">Save changes</span>
                        <span wire:loading wire:target="saveEdit">Saving…</span>
                    </button>
                </div>
            </x-slot:footer>
        </x-side-drawer>
    @endif

    {{-- ══ Add-template drawer: zip · GitHub · copy a site ══ --}}
    @if ($uploading)
        <x-side-drawer close="closeUpload" width="max-w-xl">
            <x-slot:header>
                @if ($replacingTemplate)
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">New version</p>
                    <h2 class="font-display text-xl font-bold text-gray-900 dark:text-white">{{ $replacingTemplate->name }}</h2>
                @else
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">New template</p>
                    <h2 class="font-display text-xl font-bold text-gray-900 dark:text-white">Add a template</h2>
                @endif
            </x-slot:header>

            <div class="p-6 space-y-5">
                @if ($replacingTemplate)
                    <p class="rounded-2xl bg-amber-50 dark:bg-amber-500/10 p-4 text-[12.5px] text-amber-900 dark:text-amber-200">
                        The app is checked and rebuilt under the same template, as version
                        <b>{{ \App\Services\TemplateUploads\TemplateUploadPipeline::nextVersion($replacingTemplate->versions()->pluck('version')->all()) }}</b>.
                        Its name, price, status and thumbnail stay as they are. Sites using it keep their current version until you update them from its details.
                    </p>
                @endif
                <div class="grid {{ $replacingTemplate ? 'grid-cols-2' : 'grid-cols-3' }} gap-2">
                    @foreach (array_filter([
                        'zip' => ['M12 16V4m0 0l-4 4m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2', 'Upload a .zip', 'A Nuxt app from your computer'],
                        'github' => ['M16 18l6-6-6-6M8 6l-6 6 6 6', 'From GitHub', 'Import a repository'],
                        'site' => $replacingTemplate ? null : ['M8 8V5a1 1 0 011-1h10a1 1 0 011 1v10a1 1 0 01-1 1h-3M5 8h10a1 1 0 011 1v10a1 1 0 01-1 1H5a1 1 0 01-1-1V9a1 1 0 011-1z', 'Copy a site', 'Turn an existing site into a template'],
                    ]) as $mk => [$mi, $ml, $md])
                        <button type="button" wire:click="$set('addMode', '{{ $mk }}')"
                                class="text-left rounded-2xl border px-3 py-3 transition-colors {{ $addMode === $mk ? '' : 'border-gray-200 dark:border-white/[0.1] hover:border-gray-400' }}"
                                @if ($addMode === $mk) style="border-color:var(--primary);background:color-mix(in srgb, var(--primary) 8%, transparent)" @endif>
                            <svg class="w-5 h-5 text-gray-700 dark:text-gray-200" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $mi }}"/></svg>
                            <span class="block text-[13px] font-bold text-gray-900 dark:text-white mt-1">{{ $ml }}</span>
                            <span class="block text-[11px] text-gray-500 dark:text-gray-400 leading-snug">{{ $md }}</span>
                        </button>
                    @endforeach
                </div>

                @if ($addMode === 'zip')
                    <form id="tpl-add-form" wire:submit="uploadTemplate" class="space-y-5">
                        <p class="text-[13px] text-gray-600 dark:text-gray-300">
                            Upload a zipped Nuxt app. It's checked, connected to the editor and built in the background, then appears in
                            the catalog as a <b>draft</b> by Olux Studio. Edit its details and publish it when you're happy.
                        </p>
                        <label class="block">
                            <span class="bkf-label">Nuxt app (.zip, up to 60 MB)</span>
                            <input type="file" wire:model="appZip" accept=".zip,application/zip"
                                   class="bkf-input w-full file:mr-3 file:rounded-lg file:border-0 file:px-3 file:py-1.5 file:text-[12.5px] file:font-bold file:bg-gray-100 dark:file:bg-white/[0.08] file:text-gray-700 dark:file:text-gray-200">
                            <span wire:loading wire:target="appZip" class="text-[12px] text-gray-500">Sending the file…</span>
                            @error('appZip')<span class="block text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                        @if (! $replacingId && $zipMatchId && ($zipMatch = \App\Models\Template::find($zipMatchId)))
                            <div class="rounded-2xl p-4 text-[12.5px] {{ $asSeparate ? 'bg-gray-50 dark:bg-white/[0.04] text-gray-600 dark:text-gray-300' : '' }}"
                                 @unless ($asSeparate) style="background:color-mix(in srgb, var(--primary) 8%, transparent)" @endunless>
                                @if ($asSeparate)
                                    <p>This will be added as a <b>separate new template</b>.</p>
                                @else
                                    <p class="text-gray-800 dark:text-gray-100">This looks like <b>{{ $zipMatch->name }}</b> — it will be uploaded as its <b>next version</b>, not a new template. Sites using it stay on their version until you press "Update sites".</p>
                                @endif
                                <label class="mt-2 flex items-center gap-2 text-[12.5px] text-gray-600 dark:text-gray-300 cursor-pointer">
                                    <input type="checkbox" wire:model.live="asSeparate" class="accent-[var(--primary)]">
                                    Add it as a separate new template instead
                                </label>
                            </div>
                        @endif
                        @unless ($replacingTemplate)
                            <label class="block">
                                <span class="bkf-label">Name (optional — taken from the app otherwise)</span>
                                <input type="text" wire:model="uploadName" maxlength="80" class="bkf-input w-full">
                            </label>
                            @include('partials.template-visibility-choice')
                        @endunless
                        <div class="rounded-2xl bg-gray-50 dark:bg-white/[0.04] p-4 text-[12.5px] text-gray-600 dark:text-gray-300">
                            <p class="font-bold text-gray-800 dark:text-gray-100 mb-1">The zip should contain</p>
                            <ul class="list-disc ml-4 space-y-1">
                                <li><code>package.json</code>, <code>nuxt.config.ts</code> and <code>app/pages</code> (or <code>pages</code>).</li>
                                <li>Page sections as components, so each becomes an editable block.</li>
                                <li>No <code>node_modules</code>, <code>.nuxt</code>, <code>.output</code> or <code>.env</code> files.</li>
                            </ul>
                        </div>
                    </form>
                @elseif ($addMode === 'github')
                    <form id="tpl-add-form" wire:submit="importFromGithub" class="space-y-5">
                        <p class="text-[13px] text-gray-600 dark:text-gray-300">
                            Paste a GitHub repository holding a Nuxt app. We download it and put it through the same checks and build as a zip upload.
                            Private repositories need <code>TEMPLATES_GIT_TOKEN</code> set on the server.
                        </p>
                        <label class="block">
                            <span class="bkf-label">Repository</span>
                            <input type="url" wire:model="repoUrl" placeholder="https://github.com/owner/repo" class="bkf-input w-full">
                            @error('repoUrl')<span class="block text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="bkf-label">Branch (optional)</span>
                                <input type="text" wire:model="repoBranch" placeholder="main" class="bkf-input w-full">
                            </label>
                            @unless ($replacingTemplate)
                                <label class="block">
                                    <span class="bkf-label">Name (optional)</span>
                                    <input type="text" wire:model="uploadName" maxlength="80" class="bkf-input w-full">
                                </label>
                            @endunless
                        </div>
                        @unless ($replacingTemplate)
                            @include('partials.template-visibility-choice')
                        @endunless
                    </form>
                @else
                    <form id="tpl-add-form" wire:submit="createFromSite" class="space-y-5">
                        <p class="text-[13px] text-gray-600 dark:text-gray-300">
                            Copies a site's pages, sections, words, pictures and colours into a new <b>draft</b> template that keeps the site's design.
                            The site itself isn't changed.
                        </p>
                        <label class="block">
                            <span class="bkf-label">Find a site</span>
                            <input type="search" wire:model.live.debounce.300ms="siteQuery" placeholder="Site name or domain" class="bkf-input w-full">
                        </label>
                        @if ($this->siteMatches->isNotEmpty())
                            <div class="rounded-2xl border border-gray-100 dark:border-white/[0.06] divide-y divide-gray-50 dark:divide-white/[0.04] overflow-hidden">
                                @foreach ($this->siteMatches as $sm)
                                    <button type="button" wire:click="pickSite('{{ $sm->id }}')"
                                            class="w-full text-left flex items-center gap-3 px-3 py-2.5 {{ $fromSiteId === $sm->id ? '' : 'hover:bg-gray-50 dark:hover:bg-white/[0.04]' }}"
                                            @if ($fromSiteId === $sm->id) style="background:color-mix(in srgb, var(--primary) 8%, transparent)" @endif>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[13px] font-bold text-gray-900 dark:text-white truncate">{{ \Illuminate\Support\Str::headline($sm->name) }}</span>
                                            <span class="block text-[11.5px] text-gray-500 dark:text-gray-400 truncate">{{ $sm->domain }} · {{ $sm->user?->email }} · design: {{ $sm->template ?: 'blank' }}</span>
                                        </span>
                                        @if ($fromSiteId === $sm->id)<span class="text-[11px] font-bold" style="color:var(--primary)">Selected</span>@endif
                                    </button>
                                @endforeach
                            </div>
                        @elseif (mb_strlen(trim($siteQuery)) >= 2)
                            <p class="text-[12.5px] text-gray-500">No sites match.</p>
                        @endif
                        @error('fromSiteId')<span class="block text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        <label class="block">
                            <span class="bkf-label">Template name</span>
                            <input type="text" wire:model="fromSiteName" maxlength="80" class="bkf-input w-full" placeholder="Church Classic">
                            @error('fromSiteName')<span class="block text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                        @include('partials.template-visibility-choice')
                    </form>
                @endif
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeUpload" class="{{ $btnOutline }}">Cancel</button>
                    <button type="submit" form="tpl-add-form" wire:loading.attr="disabled" wire:target="appZip,uploadTemplate,importFromGithub,createFromSite"
                            class="{{ $btn }} min-w-[8rem] disabled:opacity-50" style="background:var(--primary);color:var(--on-primary)">
                        <span wire:loading.remove wire:target="uploadTemplate,importFromGithub,createFromSite">{{ ['zip' => 'Upload', 'github' => 'Import', 'site' => 'Create template'][$addMode] }}</span>
                        <span wire:loading wire:target="uploadTemplate,importFromGithub,createFromSite">Working…</span>
                    </button>
                </div>
            </x-slot:footer>
        </x-side-drawer>
    @endif

    {{-- ══ Detail drawer ══ --}}
    @if ($opened)
        @php [$pb, $pf, $pl] = $statusPill($opened->status); @endphp
        <x-side-drawer close="close">
            <x-slot:header>
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">{{ $sourceLabel($opened->source) }}</p>
                <h2 class="font-display text-xl font-bold text-gray-900 dark:text-white">{{ $opened->name }}</h2>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-[12px] text-gray-500 dark:text-gray-400">
                    <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:{{ $pb }};color:{{ $pf }}">{{ $pl }}</span>
                    <span class="font-mono">{{ $opened->builtin_key ?: $opened->slug }}</span>
                    · {{ $detail['built'] ? 'site files built' : 'not built yet' }}
                </p>
            </x-slot:header>

            <div class="p-6 space-y-6">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach ([
                        ['Sites using', $detail['sites']->count()],
                        ['In libraries', $detail['owners']],
                        ['Sales', (int) ($detail['purchases']->n ?? 0)],
                        ['Earned', $gbp((int) ($detail['purchases']->gross ?? 0))],
                    ] as [$k, $v])
                        <div class="rounded-2xl bg-gray-50 dark:bg-white/[0.04] px-3 py-3 text-center">
                            <p class="font-display text-lg font-extrabold text-gray-900 dark:text-white">{{ $v }}</p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $k }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($detail['outdated'] > 0)
                    <div class="rounded-2xl bg-amber-50 dark:bg-amber-500/10 p-4 flex flex-wrap items-center gap-3">
                        <p class="flex-1 min-w-[12rem] text-[12.5px] text-amber-900 dark:text-amber-200">
                            <b>{{ $detail['outdated'] }} {{ Str::plural('site', $detail['outdated']) }}</b> {{ $detail['outdated'] === 1 ? 'is' : 'are' }} on an older version.
                            Updating adds the new version's pages and sections. Their content is kept.
                        </p>
                        <button wire:click="updateSites('{{ $opened->id }}')" data-confirm="Move {{ $detail['outdated'] }} {{ Str::plural('site', $detail['outdated']) }} to the latest version of {{ $opened->name }}?"
                                class="{{ $btn }}" style="background:var(--primary);color:var(--on-primary)">Update sites</button>
                    </div>
                @endif

                @if (\App\Livewire\PlatformTemplatesPage::isRepoBuiltin($opened))
                    <div class="rounded-2xl {{ $opened->pending_update ? 'bg-sky-50 dark:bg-sky-500/10' : 'bg-gray-50 dark:bg-white/[0.04]' }} p-4 space-y-2">
                        <p class="text-[12.5px] text-gray-600 dark:text-gray-300">
                            Ships with the CMS code (<span class="font-mono">resources/templates/{{ $opened->builtin_key }}</span>).
                            @if ($opened->pending_update)
                                <b>A newer copy was deployed</b> — press <b>Update template</b> to publish it as the next version, then <b>Update sites</b> to move sites onto it.
                            @else
                                Deploy changes with <span class="font-mono">./ship.sh</span>; they show here as “Update available”.
                            @endif
                        </p>
                        <button wire:click="applyBuiltinUpdate('{{ $opened->id }}')" wire:loading.attr="disabled" wire:target="applyBuiltinUpdate('{{ $opened->id }}')"
                                class="{{ $btn }} disabled:opacity-60" style="background:var(--primary);color:var(--on-primary)">Update template</button>
                    </div>
                @endif

                @if ($opened->source_repo)
                    <div class="rounded-2xl bg-gray-50 dark:bg-white/[0.04] p-4">
                        <p class="text-[12.5px] text-gray-600 dark:text-gray-300">
                            Built from <a href="{{ $opened->source_repo }}{{ $opened->source_branch ? '/tree/'.$opened->source_branch : '' }}" target="_blank" rel="noopener" class="font-bold underline">{{ Str::after($opened->source_repo, 'github.com/') }}</a>{{ $opened->source_branch ? ' · '.$opened->source_branch : ' · default branch' }}.
                            Push your changes, then press <b>Update from GitHub</b> — it builds the next version in the background and tells you when it's ready.
                        </p>
                    </div>
                @endif

                <div class="flex flex-wrap gap-2">
                    @if (\App\Livewire\PlatformTemplatesPage::canNewVersion($opened))
                        @if ($opened->source_repo)
                            <button wire:click="updateFromGithub('{{ $opened->id }}')" wire:loading.attr="disabled" wire:target="updateFromGithub('{{ $opened->id }}')"
                                    class="{{ $btn }} disabled:opacity-60" style="background:var(--primary);color:var(--on-primary)"><svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4v5h5M20 20v-5h-5M5.6 15A7 7 0 0018.4 15M18.4 9A7 7 0 005.6 9"/></svg>Update from GitHub</button>
                        @endif
                        <button wire:click="openNewVersion('{{ $opened->id }}')" class="{{ $btnOutline }}">{{ $opened->source_repo ? 'New version (zip or other repo)' : 'New version' }}</button>
                    @endif
                    @if ($opened->status === 'archived')
                        <button wire:click="show('{{ $opened->id }}')" class="{{ $btnOutline }}">Show</button>
                    @elseif ($opened->status !== 'in_review')
                        <button wire:click="hide('{{ $opened->id }}')" data-confirm="Hide {{ $opened->name }}? It leaves the store and can't be applied to new sites. Sites already using it keep it, and you can show it again any time."
                                class="{{ $btnOutline }}">Hide</button>
                    @endif
                </div>
                @if ($opened->status === 'archived')
                    <p class="-mt-4 text-[11.5px] text-gray-500 dark:text-gray-400">Hidden — not in the store or libraries, and can't be applied to new sites. Sites already using it keep it.</p>
                @endif

                {{-- Delete — e.g. a duplicate import. Refused while live sites use it (hide instead). --}}
                <div class="rounded-2xl border border-rose-100 dark:border-rose-500/20 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[13px] font-bold text-gray-900 dark:text-white">Delete template</p>
                            <p class="text-[11.5px] text-gray-500 dark:text-gray-400">{{ $detail['delete_blocker'] ?? 'Removes it, its versions and its files for good. Sales stay on record.' }}</p>
                        </div>
                        @if ($detail['delete_blocker'])
                            <span class="{{ $btnOutline }} opacity-50 cursor-not-allowed" title="{{ $detail['delete_blocker'] }}">Delete</span>
                        @else
                            <button wire:click="deleteTemplate('{{ $opened->id }}')"
                                    data-confirm="Delete {{ $opened->name }}{{ $opened->builtin_key ? ' ('.$opened->builtin_key.')' : '' }}? Its versions and files are removed and this can't be undone."
                                    class="{{ $btn }} !bg-rose-600 !text-white">Delete</button>
                        @endif
                    </div>
                </div>

                @if ($opened->required_features)
                    <div>
                        <h3 class="text-[13px] font-bold text-gray-900 dark:text-white mb-1.5">Turns on</h3>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ((array) $opened->required_features as $f)
                                <span class="px-2.5 py-1 rounded-full text-[11.5px] font-bold bg-gray-100 dark:bg-white/[0.08] text-gray-700 dark:text-gray-200">{{ \App\Features\FeatureRegistry::get($f)['name'] ?? $f }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <h3 class="text-[13px] font-bold text-gray-900 dark:text-white mb-1.5">Sites using it</h3>
                    @forelse ($detail['sites'] as $row)
                        <div class="flex items-center gap-2 py-2 text-[13px] border-b border-gray-50 dark:border-white/[0.04] last:border-0">
                            <span class="flex-1 min-w-0 font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $row->site?->name ?? 'deleted site' }}</span>
                            @if ($row->site?->user)
                                <a href="{{ route('admin.account', $row->site->user_id) }}" wire:navigate class="text-[12px] hover:underline" style="color:var(--primary)">{{ $row->site->user->name }}</a>
                            @endif
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $row->applied_at?->diffForHumans(short: true) }}</span>
                        </div>
                    @empty
                        <p class="text-[12.5px] text-gray-500">No site uses it yet.</p>
                    @endforelse
                </div>

                <div>
                    <h3 class="text-[13px] font-bold text-gray-900 dark:text-white mb-1.5">Versions</h3>
                    @forelse ($opened->versions as $v)
                        <div class="flex items-center justify-between py-1.5 text-[13px] border-b border-gray-50 dark:border-white/[0.04] last:border-0">
                            <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $v->version }}</span>
                            <span class="text-[11.5px] text-gray-500 dark:text-gray-400">{{ $v->created_at?->format('j M Y') }}</span>
                        </div>
                    @empty
                        <p class="text-[12.5px] text-gray-500">No versions recorded.</p>
                    @endforelse
                </div>

                @if ($detail['upload'])
                    <div>
                        <h3 class="text-[13px] font-bold text-gray-900 dark:text-white mb-1.5">Upload</h3>
                        <p class="text-[12.5px] text-gray-600 dark:text-gray-300">
                            {{ $detail['upload']->original_filename }} · {{ $detail['upload']->created_at->format('j M Y H:i') }}
                            @if ($detail['upload']->lint_score !== null) · quality {{ $detail['upload']->lint_score }}/100 @endif
                        </p>
                        @foreach ((array) $detail['upload']->warnings as $w)
                            <p class="mt-1 text-[12px] text-amber-800 dark:text-amber-300">• {{ $w }}</p>
                        @endforeach
                    </div>
                @endif

                @if ($opened->rejection_reason)
                    <p class="text-[12.5px] text-rose-700">Last rejection: {{ $opened->rejection_reason }}</p>
                @endif
            </div>
        </x-side-drawer>
    @endif
</x-tri-layout>
