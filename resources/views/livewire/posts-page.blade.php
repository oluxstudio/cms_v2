@php
    $canManage = $site->canManageTeam(auth()->user());
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $total = $stats['total'];
    $filters = [
        'all' => ['All', $total],
        'published' => ['Published', $stats['published']],
        'draft' => ['Drafts', $stats['drafts']],
        'attention' => ['Needs attention', $stats['attention']],
        'comments' => ['Comments to review', $stats['posts_with_pending']],
    ];
    $statusColor = ['published' => '#10b981', 'draft' => '#f59e0b'];
    $catColors = ['#6366f1', '#0ea5e9', '#8b5cf6', '#f97316', '#14b8a6', '#94a3b8'];
    $cover = fn ($p) => $p->cover_image ? \App\Models\Media::resolveRef($site->id, (string) $p->cover_image) : null;
    $badge = fn ($p) => $p->isPublished()
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400'
        : 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400';
    $iconEdit = 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z';
    $iconTrash = 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16';
@endphp
<x-tri-layout title="Posts" subtitle="Write, publish and see which posts your visitors love." :site-name="$site->name"
    :labels="['📊 Stats', '📝 Posts', '📋 Summary']">

    {{-- ── LEFT rail: the blog at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="number_format($total)" label="Posts"
                :sub="$stats['published_week'] ? $stats['published_week'].' published this week' : ($stats['created_week'] ? $stats['created_week'].' written this week' : 'nothing new this week')"
                icon="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
        <x-tile accent="lime" :value="number_format($stats['published'])" label="Published" :sub="$total ? round($stats['published'] / $total * 100).'% of posts' : 'none yet'"
                icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="{{ $stats['stale_drafts'] ? 'rose' : 'cocoa' }}" :value="number_format($stats['drafts'])" label="Drafts"
                :sub="$stats['stale_drafts'] ? $stats['stale_drafts'].' untouched '.$staleDays.'d+' : 'awaiting publish'"
                icon="{{ $iconEdit }}" />
        <x-tile accent="{{ $stats['pending_comments'] ? 'rose' : 'sky' }}" :value="number_format($stats['pending_comments'])" label="Comments to review"
                :sub="$stats['pending_comments'] ? 'awaiting moderation' : 'all caught up'"
                icon="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
        <x-tile accent="lavender" :value="number_format($stats['views'])" label="Total views"
                :sub="number_format($stats['likes']).' likes · '.number_format($stats['comments']).' comments'"
                icon="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        <x-tile accent="{{ $stats['no_cover'] + $stats['no_excerpt'] ? 'rose' : 'sky' }}" :value="number_format($stats['attention'])" label="Needs attention"
                :sub="$stats['no_cover'] + $stats['no_excerpt'] ? $stats['no_cover'].' no cover · '.$stats['no_excerpt'].' no excerpt' : 'every post is complete'"
                icon="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
    </div>
    </x-slot:rail>

    {{-- ── CENTER: toolbar + the posts ── --}}
    <div class="space-y-5">

    <div class="{{ $panel }} !rounded-2xl p-3 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[12rem]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <x-field.text wire:model.live.debounce.300ms="search" placeholder="Search by title, excerpt or category…" class="w-full" style="padding-left:2.25rem" />
            </div>
            <select wire:model.live="sort" class="bkf-input !w-auto text-[13px]" title="Order">
                <option value="recent">Newest first</option>
                <option value="updated">Recently edited</option>
                <option value="views">Most viewed</option>
                <option value="engagement">Most engaging</option>
                <option value="title">Title A–Z</option>
            </select>
            <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
            @if ($canManage)
            <button type="button" wire:click="createPost"
                    class="inline-flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New post
            </button>
            @endif
        </div>
        <div class="flex gap-2 overflow-x-auto no-scrollbar">
            @foreach ($filters as $key => [$label, $n])
                <button type="button" wire:click="setFilter('{{ $key }}')"
                    class="shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors
                        {{ $filter === $key
                            ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                            : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}">
                    {{ $label }} <span class="opacity-60">{{ $n }}</span>
                </button>
            @endforeach
        </div>
    </div>

    @if ($posts->isEmpty())
        <div class="{{ $panel }} px-6 py-16 text-center">
            <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
            </span>
            @if ($total === 0)
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">No posts yet</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Posts power your blog, news and updates — write one and publish it when it's ready.</p>
                @if ($canManage)
                    <button type="button" wire:click="createPost" class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Write your first post</button>
                @endif
            @else
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing matches</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Try another search or filter.</p>
                <button type="button" wire:click="resetFilters" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show all posts</button>
            @endif
        </div>
    @elseif ($viewMode === 'grid')
        {{-- ── Cards ── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($posts as $post)
                @php $img = $cover($post); @endphp
                <div class="group flex flex-col {{ $panel }} !rounded-2xl hover:shadow-md transition-shadow overflow-hidden" wire:key="post-{{ $post->id }}">
                    <div class="flex-1 {{ $canManage ? 'cursor-pointer' : '' }}" @if($canManage) wire:click="editPost('{{ $post->id }}')" @endif>
                        <div class="relative aspect-[16/9] max-w-full bg-gray-100 dark:bg-white/[0.05] overflow-hidden">
                            @if ($img)
                                <img src="{{ $img }}" alt="" class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform" loading="lazy">
                            @else
                                <span class="absolute inset-0 grid place-items-center text-[12px] font-semibold text-gray-400 dark:text-gray-500">
                                    <span class="text-center"><span class="block text-2xl mb-1">🖼</span>No cover image</span>
                                </span>
                            @endif
                            <span class="absolute top-3 left-3 px-2.5 py-1 rounded-full text-[11px] font-bold shadow-sm {{ $badge($post) }}">{{ $post->isPublished() ? 'Published' : 'Draft' }}</span>
                            @if ($post->pending_comments_count)
                                <span class="absolute top-3 right-3 px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-500 text-white">{{ $post->pending_comments_count }} to review</span>
                            @endif
                        </div>
                        <div class="p-5">
                            <p class="text-[15px] font-bold text-gray-900 dark:text-white line-clamp-2 group-hover:underline">{{ $post->title }}</p>
                            <p class="mt-1.5 text-[13px] line-clamp-2 min-h-[2.5em] {{ $post->excerpt ? 'text-gray-500 dark:text-gray-400' : 'italic text-amber-600 dark:text-amber-400' }}">{{ $post->excerpt ?: 'No excerpt — add one so it reads well in lists.' }}</p>
                            <span class="mt-3 flex flex-wrap gap-1.5">
                                @if ($post->category)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300">{{ $post->category }}</span>
                                @endif
                                @foreach (array_slice($post->tags ?? [], 0, 3) as $tag)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">#{{ $tag }}</span>
                                @endforeach
                                @if (count($post->tags ?? []) > 3)
                                    <span class="text-[11px] text-gray-400">+{{ count($post->tags) - 3 }}</span>
                                @endif
                            </span>
                            <p class="mt-3 text-[12px] text-gray-400 dark:text-gray-500 truncate">
                                {{ $post->author?->name ?? 'Unknown' }} · {{ ($post->published_at ?? $post->created_at)->format('M j, Y') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 px-3 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                        <span class="flex items-center gap-3 px-1 text-[12px] text-gray-500 dark:text-gray-400 tabular-nums">
                            <span title="Views">👁 {{ number_format($post->views) }}</span>
                            <span title="Likes">❤ {{ number_format($post->likes) }}</span>
                            <span title="Approved comments">💬 {{ number_format($post->comments) }}</span>
                        </span>
                        @if ($canManage)
                        <span class="ml-auto flex items-center gap-0.5">
                            <button type="button" wire:click="togglePublish('{{ $post->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">
                                {{ $post->isPublished() ? 'Unpublish' : 'Publish' }}
                            </button>
                            <button type="button" wire:click="editPost('{{ $post->id }}')" title="Edit"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconEdit }}"/></svg>
                            </button>
                            <button type="button" wire:click="deletePost('{{ $post->id }}')" data-confirm="Delete “{{ $post->title }}”?" title="Delete"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconTrash }}"/></svg>
                            </button>
                        </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- ── List & Compact (table) ── --}}
        @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-4 py-3'; @endphp
        <div class="{{ $panel }} !rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <th class="px-4 py-3">Post</th>
                            <th class="px-4 py-3">Status</th>
                            @unless ($compact)
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3 text-right">Views</th>
                                <th class="px-4 py-3 text-right">Engagement</th>
                                <th class="px-4 py-3">Date</th>
                            @endunless
                            <th class="w-24 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                        @foreach ($posts as $post)
                            @php $img = $cover($post); @endphp
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors group" wire:key="row-{{ $post->id }}">
                                <td class="{{ $pad }}">
                                    <div class="flex items-center gap-2.5 min-w-0 {{ $canManage ? 'cursor-pointer' : '' }}" @if($canManage) wire:click="editPost('{{ $post->id }}')" @endif>
                                        <span class="{{ $compact ? 'w-8 h-8' : 'w-10 h-10' }} rounded-lg shrink-0 overflow-hidden bg-gray-100 dark:bg-white/[0.05] grid place-items-center text-sm">
                                            @if ($img)<img src="{{ $img }}" alt="" class="w-full h-full object-cover" loading="lazy">@else 📝 @endif
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-gray-900 dark:text-white truncate max-w-[18rem] hover:underline">{{ $post->title }}</span>
                                            @unless ($compact)<span class="block text-[11px] text-gray-400 truncate max-w-[18rem]">{{ $post->excerpt ?: 'No excerpt' }}</span>@endunless
                                        </span>
                                        @if ($post->pending_comments_count)<span class="shrink-0 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500 text-white">{{ $post->pending_comments_count }} 💬</span>@endif
                                    </div>
                                </td>
                                <td class="{{ $pad }}">
                                    <button type="button" @if($canManage) wire:click="togglePublish('{{ $post->id }}')" title="Click to toggle publish" @endif
                                            class="text-[11px] font-semibold px-2.5 py-1 rounded-full whitespace-nowrap {{ $canManage ? 'cursor-pointer' : 'cursor-default' }} {{ $badge($post) }}">
                                        {{ $post->isPublished() ? 'Published' : 'Draft' }}
                                    </button>
                                </td>
                                @unless ($compact)
                                    <td class="{{ $pad }} text-[12px] text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $post->category ?: '—' }}</td>
                                    <td class="{{ $pad }} text-right tabular-nums font-semibold text-gray-900 dark:text-white">{{ number_format($post->views) }}</td>
                                    <td class="{{ $pad }} text-right tabular-nums text-[12px] text-gray-500 dark:text-gray-400 whitespace-nowrap">❤ {{ number_format($post->likes) }} · 💬 {{ number_format($post->comments) }}</td>
                                    <td class="{{ $pad }} text-[12px] text-gray-400 whitespace-nowrap">{{ ($post->published_at ?? $post->created_at)->format('M j, Y') }}</td>
                                @endunless
                                <td class="{{ $pad }}">
                                    @if ($canManage)
                                    <div class="flex items-center gap-1 justify-end">
                                        <button type="button" wire:click="editPost('{{ $post->id }}')" title="Edit"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconEdit }}"/></svg>
                                        </button>
                                        <button type="button" wire:click="deletePost('{{ $post->id }}')" data-confirm="Delete “{{ $post->title }}”?" title="Delete"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconTrash }}"/></svg>
                                        </button>
                                    </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($posts->hasPages())
        <div class="{{ $panel }} !rounded-2xl px-5 py-3.5">{{ $posts->links() }}</div>
    @endif

    {{-- ── Create / edit modal ── --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" wire:click="$set('showForm', false)"></div>
        <div class="relative bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-white/[0.05]">
                <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ $editingId ? 'Edit post' : 'Create post' }}</h2>
                <button wire:click="$set('showForm', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form wire:submit="savePost" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Title</label>
                    <input wire:model="title" type="text" placeholder="A headline readers can't skip…"
                           class="w-full text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] px-3.5 py-2.5 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                    @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Excerpt <span class="font-normal text-gray-400">(shown in lists)</span></label>
                    <textarea wire:model="excerpt" rows="2"
                              class="w-full text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] px-3.5 py-2.5 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"></textarea>
                </div>
                <div wire:ignore>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">
                        Body <span class="font-normal text-gray-400">— headings, paragraphs, lists · insert images &amp; video from your Media library</span>
                    </label>
                    @include('partials.post-body-editor', ['mediaAssets' => $this->mediaAssets])
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Category</label>
                        <input wire:model="category" type="text" placeholder="e.g. Hair care" list="post-categories"
                               class="w-full text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] px-3.5 py-2.5 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                        <datalist id="post-categories">
                            @foreach ($this->categories as $cat)
                                <option value="{{ $cat }}"></option>
                            @endforeach
                        </datalist>
                        @error('category') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Tags <span class="font-normal text-gray-400">(comma-separated)</span></label>
                        <input wire:model="tags" type="text" placeholder="styling, summer, tips"
                               class="w-full text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] px-3.5 py-2.5 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                        @error('tags') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid sm:grid-cols-[1fr_auto] gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Cover image</label>
                        <x-asset-picker model="coverImage" :site="$site" type="image" placeholder="Image URL, or pick from assets" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Status</label>
                        <select wire:model="status"
                                class="text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] px-3.5 py-2.5 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" wire:click="$set('showForm', false)"
                            class="px-4 py-2 rounded-xl text-sm font-medium bg-white dark:bg-[#1d1e2a] text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-white/[0.08] hover:border-gray-300 transition-colors">Cancel</button>
                    <button type="submit"
                            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
                        {{ $editingId ? 'Save changes' : 'Create post' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
    </div>

    {{-- ══ RIGHT rail: summary · needs attention · comments · most read · related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Posts summary</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ $total }}</b> {{ Str::plural('post', $total) }} ·
                <b class="text-gray-900 dark:text-white">{{ number_format($stats['views']) }}</b> {{ Str::plural('view', $stats['views']) }} ·
                <b class="text-gray-900 dark:text-white">{{ number_format($stats['likes'] + $stats['comments']) }}</b> engagements
            </p>
            @if ($total)
                <div class="flex h-2.5 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06]">
                    @foreach (['published' => $stats['published'], 'draft' => $stats['drafts']] as $s => $n)
                        @if ($n)<span style="width:{{ round($n / $total * 100, 2) }}%;background:{{ $statusColor[$s] }}" title="{{ ucfirst($s) }} · {{ $n }}"></span>@endif
                    @endforeach
                </div>
                <div class="mt-3 space-y-1.5">
                    @foreach (['published' => ['Published', $stats['published']], 'draft' => ['Drafts', $stats['drafts']]] as $s => [$label, $n])
                        <div class="flex items-center gap-2 text-[12.5px]">
                            <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $statusColor[$s] }}"></span>
                            <span class="text-gray-600 dark:text-gray-300">{{ $label }}</span>
                            <span class="ml-auto font-bold text-gray-900 dark:text-white">{{ $n }}</span>
                        </div>
                    @endforeach
                </div>
                @if ($byCategory)
                    <p class="mt-4 mb-1.5 text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">By category</p>
                    <div class="flex h-2 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06]">
                        @foreach (array_values($byCategory) as $i => $n)
                            <span style="width:{{ round($n / $total * 100, 2) }}%;background:{{ $catColors[$i] ?? '#94a3b8' }}"></span>
                        @endforeach
                    </div>
                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                        @foreach (array_keys($byCategory) as $i => $cat)
                            <span class="inline-flex items-center gap-1.5 text-[12px] text-gray-600 dark:text-gray-300">
                                <span class="w-2 h-2 rounded-full" style="background:{{ $catColors[$i] ?? '#94a3b8' }}"></span>{{ $cat }} <b class="text-gray-900 dark:text-white">{{ $byCategory[$cat] }}</b>
                            </span>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>

        @if ($stats['attention'] || $pendingComments->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2.5">
                @if ($pendingComments->isNotEmpty())
                    <div class="rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">{{ $stats['pending_comments'] }} {{ Str::plural('comment', $stats['pending_comments']) }} to moderate</p>
                        <div class="mt-2 space-y-2">
                            @foreach ($pendingComments as $c)
                                <div class="rounded-xl bg-white/80 dark:bg-white/[0.06] px-3 py-2" wire:key="pc-{{ $c->id }}">
                                    <p class="text-[12px] text-gray-800 dark:text-gray-100 line-clamp-2">“{{ $c->body }}”</p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $c->author_name }} on {{ $c->post?->title ?? 'a post' }}</p>
                                    @if ($canManage)
                                    <div class="mt-1.5 flex gap-1.5">
                                        <button type="button" wire:click="moderateComment('{{ $c->id }}', 'approved')" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white">Approve</button>
                                        <button type="button" wire:click="moderateComment('{{ $c->id }}', 'spam')" data-confirm="Mark this comment as spam?" class="{{ $btnSolid }} px-2.5 py-1 text-[11px]">Spam</button>
                                    </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if ($stats['posts_with_pending'])
                            <button type="button" wire:click="setFilter('comments')" class="mt-2 text-[12px] font-semibold text-rose-700 dark:text-rose-200 hover:underline">Show posts with comments to review →</button>
                        @endif
                    </div>
                @endif
                @if ($stats['stale_drafts'])
                    <button type="button" wire:click="setFilter('draft')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10 hover:ring-2 hover:ring-amber-200 dark:hover:ring-amber-500/30">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">{{ $stats['stale_drafts'] }} {{ Str::plural('draft', $stats['stale_drafts']) }} left untouched</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70">Not edited in {{ $staleDays }}+ days — finish or delete them →</p>
                    </button>
                @endif
                @if ($stats['no_cover'] || $stats['no_excerpt'])
                    <div class="rounded-2xl px-3.5 py-3 bg-gray-50 dark:bg-white/[0.04]">
                        <p class="text-[13px] font-bold text-gray-800 dark:text-gray-100">
                            {{ collect([$stats['no_cover'] ? $stats['no_cover'].' without a cover' : null, $stats['no_excerpt'] ? $stats['no_excerpt'].' without an excerpt' : null])->filter()->implode(' · ') }}
                        </p>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400 mb-1.5">Blog lists and link previews look bare without them.</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($attentionList as $p)
                                @if ($canManage)
                                    <button type="button" wire:click="editPost('{{ $p->id }}')" class="max-w-full truncate px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:underline">✎ {{ Str::limit($p->title, 28) }}</button>
                                @endif
                            @endforeach
                        </div>
                        <button type="button" wire:click="setFilter('attention')" class="mt-2 text-[12px] font-semibold hover:underline" style="color:var(--primary)">Show all {{ $stats['attention'] }} →</button>
                    </div>
                @endif
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Most read</p>
            @if ($mostRead->isEmpty())
                <p class="text-[12.5px] text-gray-400">No views yet — numbers appear as visitors read your posts.</p>
            @else
                @php $maxViews = max(1, $mostRead->max('views')); @endphp
                <div class="space-y-2.5">
                    @foreach ($mostRead as $p)
                        <button type="button" @if($canManage) wire:click="editPost('{{ $p->id }}')" @endif class="block w-full text-left group">
                            <span class="flex items-center justify-between gap-2 text-[12.5px]">
                                <span class="truncate text-gray-700 dark:text-gray-200 group-hover:underline">{{ $p->title }}</span>
                                <span class="font-bold text-gray-900 dark:text-white tabular-nums shrink-0">{{ number_format($p->views) }}</span>
                            </span>
                            <span class="block mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                                <span class="block h-full rounded-full" style="width:{{ round($p->views / $maxViews * 100) }}%;background:var(--primary)"></span>
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($recentlyPublished->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Recently published</p>
            <div class="space-y-2">
                @foreach ($recentlyPublished as $p)
                    <button type="button" @if($canManage) wire:click="editPost('{{ $p->id }}')" @endif class="flex w-full items-center gap-2.5 text-left group">
                        <span class="w-2 h-2 rounded-full shrink-0 bg-emerald-500"></span>
                        <span class="min-w-0 flex-1 text-[12.5px] font-semibold text-gray-700 dark:text-gray-200 truncate group-hover:underline">{{ $p->title }}</span>
                        <span class="text-[11px] text-gray-400 shrink-0">{{ $p->published_at?->diffForHumans(null, true) }}</span>
                    </button>
                @endforeach
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ([
                    ['Edit site', 'Place posts on pages', url($site->name.'/connect')],
                    ['Pages', 'Your blog & news pages', route('pages', $site->name)],
                    ['Assets', 'Cover images', route('media', $site->name)],
                    ['Collections', 'Other structured content', route('collections', $site->name)],
                ] as [$label, $hint, $href])
                    <a href="{{ $href }}" wire:navigate class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
@assets
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
@endassets

@script
<script>
    /**
     * Quill-backed post editor + Media-library lightbox. Quill owns ALL
     * selection/formatting behaviour — the only custom parts are legacy body
     * conversion and inserting library assets at the remembered cursor.
     */
    window.postQuill = function ($wire, assets = []) {
        const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        const gridToHtml = (d) => {
            const blockHtml = (b) => ({
                h1: `<h1>${esc(b.text)}</h1>`, h2: `<h2>${esc(b.text)}</h2>`, h3: `<h3>${esc(b.text)}</h3>`,
                p: `<p>${esc(b.text)}</p>`,
                image: b.url ? `<img src="${esc(b.url)}" alt="${esc(b.alt)}">` : '',
                video: b.url ? `<p><a href="${esc(b.url)}">${esc(b.url)}</a></p>` : '',
                ul: `<ul>${(b.items || []).map(i => `<li>${esc(i)}</li>`).join('')}</ul>`,
                ol: `<ol>${(b.items || []).map(i => `<li>${esc(i)}</li>`).join('')}</ol>`,
            })[b.type] || '';
            return d.rows.flatMap(r => r.cells.flatMap(c => c.blocks.map(blockHtml))).join('');
        };

        const initialHtml = (raw) => {
            const s = (raw || '').trim();
            if (s === '') return '';
            try {
                const d = JSON.parse(s);
                if (d && d.format === 'grid-v1') return gridToHtml(d);
            } catch (_) { /* not JSON */ }
            if (s.startsWith('<')) return s;
            return s.split(/\n{2,}/).map(p => `<p>${esc(p).replace(/\n/g, '<br>')}</p>`).join('');
        };

        return {
            picker: false,
            pickerSearch: '',
            assets,
            savedIndex: null,

            // The Quill instance lives on the DOM node, NOT in Alpine state —
            // Alpine's reactive Proxy breaks Quill's internal registry lookups
            // (every API call dies with "null.offset").
            quill() { return this.$refs.editor.__quill; },

            init() {
                if (!window.Quill) { setTimeout(() => this.init(), 100); return; } // CDN still loading
                const q = new Quill(this.$refs.editor, {
                    theme: 'snow',
                    placeholder: 'Start with a heading, then tell the story…',
                    modules: {
                        table: true,
                        toolbar: {
                            container: [
                                [{ header: 1 }, { header: 2 }, { header: 3 }, { header: 4 }],
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ list: 'ordered' }, { list: 'bullet' }],
                                ['blockquote', 'link'],
                                ['image', 'video'],
                                ['clean'],
                            ],
                            handlers: {
                                // Our Media-library lightbox instead of Quill's file dialog.
                                image: () => this.openPicker(),
                            },
                        },
                    },
                });
                this.$refs.editor.__quill = q;
                const html = initialHtml($wire.body);
                if (html) q.clipboard.dangerouslyPasteHTML(html, 'silent');
                $wire.body = q.root.innerHTML;
                q.on('text-change', () => { $wire.body = q.root.innerHTML; });

                // ── Bullet-proof inline toggles (B/I/U/S) ─────────────────────
                // Some environments (extensions, focus quirks) collapse the native
                // selection during a toolbar click, so inline formats see nothing
                // selected. We track Quill's own {index,length} range — via its
                // selection events AND a capture-phase mousedown snapshot — and
                // format BY COORDINATES with formatText(), which never consults
                // the native selection.
                const el = this.$refs.editor;
                q.on('selection-change', (range) => { if (range && range.length > 0) el.__lastRange = range; });
                const tbEl = el.parentElement.querySelector('.ql-toolbar');
                if (tbEl) tbEl.addEventListener('mousedown', () => {
                    const r = q.getSelection();
                    if (r && r.length > 0) el.__lastRange = r;
                }, true);

                // ── Tables: insert/row/column controls + per-table styling toggles ──
                // Styling = classes ON the <table> element (striped rows/columns,
                // bold header/footer). They ride along in the saved HTML and are
                // re-applied after Quill re-parses the body on edit (see below).
                const tblM = q.getModule('table');
                const currentTable = () => {
                    const sel = q.getSelection();
                    if (sel) {
                        const [t] = tblM.getTable(sel);
                        if (t) return t.domNode;
                    }
                    const all = q.root.querySelectorAll('table');
                    return all.length ? all[all.length - 1] : null;
                };
                const syncBody = () => { $wire.body = q.root.innerHTML; };
                const tblOp = (fn) => () => { if (q.getSelection() && tblM.getTable(q.getSelection())[0]) { fn(); syncBody(); } };
                const tblToggle = (cls) => () => { const t = currentTable(); if (t) { t.classList.toggle(cls); syncBody(); } };

                if (tbEl) {
                    const grp = document.createElement('span');
                    grp.className = 'ql-formats pbe-tblgrp';
                    const mk = (label, title, fn) => {
                        const btn = document.createElement('button');
                        btn.type = 'button'; btn.textContent = label; btn.title = title; btn.className = 'pbe-tbtn';
                        btn.addEventListener('click', fn);
                        grp.appendChild(btn);
                    };
                    mk('⊞', 'Insert a 3×3 table', () => {
                        const sel = q.getSelection(true) || { index: Math.max(0, q.getLength() - 1) };
                        if (tblM.getTable(sel)[0]) return; // no tables inside tables
                        tblM.insertTable(3, 3);
                        const t = currentTable();
                        if (t) t.classList.add('pbe-t-head'); // formatted by default: bold header
                        syncBody();
                    });
                    mk('+⇣', 'Add a row below', tblOp(() => tblM.insertRowBelow()));
                    mk('+⇢', 'Add a column right', tblOp(() => tblM.insertColumnRight()));
                    mk('−R', 'Delete this row', tblOp(() => tblM.deleteRow()));
                    mk('−C', 'Delete this column', tblOp(() => tblM.deleteColumn()));
                    mk('▤', 'Toggle striped ROWS (alternate background)', tblToggle('pbe-t-rows'));
                    mk('▥', 'Toggle striped COLUMNS (alternate background)', tblToggle('pbe-t-cols'));
                    mk('𝐇', 'Toggle bold header row', tblToggle('pbe-t-head'));
                    mk('𝐅', 'Toggle bold footer row', tblToggle('pbe-t-foot'));
                    mk('✕⊞', 'Delete the whole table', tblOp(() => tblM.deleteTable()));
                    tbEl.appendChild(grp);
                }

                // Re-apply table styling classes lost when Quill re-parses the body.
                const restoreTableClasses = (fromHtml) => {
                    const src = [...fromHtml.matchAll(/<table[^>]*class="([^"]*)"/g)].map(m => m[1]);
                    if (!src.length) return;
                    q.root.querySelectorAll('table').forEach((t, i) => {
                        (src[i] || '').split(/\s+/).filter(c => c.startsWith('pbe-t-')).forEach(c => t.classList.add(c));
                    });
                    syncBody();
                };
                if (html) restoreTableClasses(html);

                const tb = q.getModule('toolbar');
                ['bold', 'italic', 'underline', 'strike'].forEach((fmt) => {
                    tb.addHandler(fmt, () => {
                        let r = q.getSelection();
                        if (!r || r.length === 0) r = el.__lastRange || r;
                        if (!r) { q.focus(); return; }
                        if (r.length === 0) {
                            // No selection anywhere: toggle for upcoming typing.
                            q.format(fmt, !(q.getFormat(r.index) || {})[fmt], 'user');
                            return;
                        }
                        const on = !!(q.getFormat(r.index, r.length) || {})[fmt];
                        q.formatText(r.index, r.length, fmt, !on, 'user');
                        try { q.setSelection(r.index, r.length, 'silent'); } catch (_) {}
                        el.__lastRange = { index: r.index, length: r.length };
                    });
                });
            },

            openPicker() {
                const q = this.quill();
                let sel = null;
                try { sel = q.getSelection(); } catch (_) { /* unfocused editor */ }
                this.savedIndex = sel ? sel.index : Math.max(0, q.getLength() - 1);
                this.pickerSearch = '';
                this.picker = true;
            },
            filteredAssets() {
                const q = this.pickerSearch.toLowerCase().trim();
                return q ? this.assets.filter(a => (a.name || '').toLowerCase().includes(q)) : this.assets;
            },
            insertAsset(a) {
                this.picker = false;
                const q = this.quill();
                const i = this.savedIndex ?? Math.max(0, q.getLength() - 1);
                q.insertEmbed(i, a.type === 'image' ? 'image' : 'video', a.url, 'api');
                try { q.setSelection(i + 1, 0, 'silent'); } catch (_) {}
                $wire.body = q.root.innerHTML;
            },
        };
    };
</script>
@endscript
