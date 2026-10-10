{{--
  site-forms-page.blade.php
  Modes: list | form (create/edit) | detail | responses
--}}
<div class="{{ $mode === 'detail' ? '' : 'p-6 space-y-6' }}">

{{-- ════════════════════════════════════════════════════════════
     LIST MODE
════════════════════════════════════════════════════════════ --}}
@if ($mode === 'list')
<x-carousel :labels="['📊 Stats', '📋 Forms', '📥 Responses']" :start="1">

    {{-- ════ LEFT RAIL: summary tiles ════ --}}
    <x-carousel.slide class="lg:!w-[280px] lg:shrink-0 pb-24 lg:pb-6 max-h-full overflow-y-auto lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] lg:overflow-y-auto no-scrollbar">
    @php
        $fActive = $forms->filter(fn ($f) => $f->isLive())->count();   // on, and not parked by a template switch
        $fResponses = $forms->sum('responses_count');
        $fUnread = $forms->sum('unread_count');
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-stat-tile label="Forms" :value="$forms->count()" color="#6366f1"
            icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        <x-stat-tile label="Active" :value="$fActive" :sub="$forms->count().' total'" color="#10b981"
            :bar="$forms->count() ? round($fActive / $forms->count() * 100) : 0"
            icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-stat-tile label="Responses" :value="number_format($fResponses)" color="#f59e0b"
            icon="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
        <x-stat-tile label="New responses" :value="$fUnread" :sub="$fUnread ? 'awaiting review' : 'all read'" color="#ef4444"
            icon="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    </div>
    </x-carousel.slide>

    {{-- ════ MAIN: forms, centered column ════ --}}
    <x-carousel.slide class="lg:flex-1 lg:min-w-0 pb-24 lg:pb-6 max-h-full overflow-y-auto lg:overflow-y-visible no-scrollbar">
    <div class="max-w-[50rem] mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Forms</h1>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                Create and manage forms for this site.
            </p>
        </div>
        <button wire:click="goCreate"
                class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700
                       text-white text-sm font-semibold px-4 py-2.5 rounded-xl
                       transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            New Form
        </button>
    </div>

    @if ($forms->isEmpty())
        <div class="mt-6 flex flex-col items-center justify-center gap-4 py-20
                    rounded-2xl border border-dashed border-gray-200 dark:border-white/10
                    bg-white dark:bg-[#1d1e2a]">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center">
                <svg class="w-7 h-7 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 8.414V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div class="text-center">
                <p class="font-semibold text-gray-700 dark:text-gray-300">No forms yet</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Create your first form to start collecting responses.
                </p>
            </div>
            <button wire:click="goCreate"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold
                           px-4 py-2 rounded-xl transition-colors">
                Create Form
            </button>
        </div>

    @else
        {{-- Form cards (unread-first) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($forms as $form)
                <div class="relative flex flex-col bg-white dark:bg-[#1d1e2a]
                            rounded-2xl shadow-sm hover:shadow-md transition-shadow overflow-hidden
                            {{ $form->unread_count > 0
                                ? 'border-2 border-rose-300 dark:border-rose-500/40 bg-gradient-to-br from-rose-50/60 dark:from-rose-500/[0.06] to-white dark:to-[#1d1e2a]'
                                : 'border border-gray-100 dark:border-white/[0.06]' }}">

                    {{-- Status + unread badge --}}
                    <div class="absolute top-3 right-3 flex items-center gap-1.5">
                        @if (! $form->is_active && $form->template_active !== false)
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full
                                         bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400">
                                Inactive
                            </span>
                        @endif
                        @if ($form->unread_count > 0)
                            <span class="flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full
                                         bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                {{ $form->unread_count }} new
                            </span>
                        @endif
                    </div>

                    <div class="p-5 flex-1 flex flex-col gap-3">
                        {{-- Icon + title --}}
                        <div class="flex items-start gap-3 pr-16">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/10
                                        flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 8.414V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h2 class="font-bold text-gray-900 dark:text-white truncate text-sm">
                                    {{ $form->displayTitle() }}
                                </h2>
                                <p class="text-xs font-mono text-gray-400 dark:text-gray-500 mt-0.5">
                                    {{ $form->name }}
                                </p>
                                @include('partials.template-inactive', ['item' => $form, 'action' => 'activateForm', 'noun' => 'form'])
                            </div>
                        </div>

                        {{-- Description --}}
                        @if ($form->description)
                            <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                                {{ $form->description }}
                            </p>
                        @endif

                        {{-- Stats --}}
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M4 6h16M4 10h16M4 14h10"/>
                                </svg>
                                {{ count($form->fields ?? []) }} {{ Str::plural('field', count($form->fields ?? [])) }}
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                                {{ number_format($form->responses_count) }} {{ Str::plural('response', $form->responses_count) }}
                            </div>
                        </div>

                        @if ($form->last_at)
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                Last response <time class="font-medium text-gray-600 dark:text-gray-300">
                                    {{ \Carbon\Carbon::parse($form->last_at)->diffForHumans() }}
                                </time>
                            </p>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="px-5 pb-5 flex items-center gap-2">
                        <button wire:click="goDetail('{{ $form->id }}')"
                                class="flex-1 text-center text-sm font-semibold px-3 py-2
                                       bg-indigo-600 hover:bg-indigo-700 text-white
                                       rounded-xl transition-colors">
                            View Details
                        </button>
                        <button wire:click="goEdit('{{ $form->id }}')"
                                class="p-2 rounded-xl border border-gray-200 dark:border-white/10
                                       bg-white dark:bg-[#1d1e2a] text-gray-500 dark:text-gray-400
                                       hover:bg-gray-50 dark:hover:bg-white/[0.05] transition-colors"
                                title="Edit form">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button wire:click="deleteForm('{{ $form->id }}')" data-confirm="Delete this form and its responses?"
                                data-confirm="Delete '{{ addslashes($form->displayTitle()) }}' and all its responses? This cannot be undone."
                                class="p-2 rounded-xl border border-red-100 dark:border-red-500/20
                                       text-red-500 dark:text-red-400
                                       hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                                title="Delete form">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>

                </div>
            @endforeach
        </div>

    @endif
    </div>
    </x-carousel.slide>

    {{-- ════ RIGHT RAIL: recent responses ════ --}}
    <x-carousel.slide class="lg:!w-[340px] lg:shrink-0 pb-24 lg:pb-6 max-h-full overflow-y-auto lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] lg:overflow-y-auto no-scrollbar">
        {{-- ── Recent responses (site-wide, newest first) ── --}}
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Recent responses</h2>
                @if ($fUnread > 0)
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300">{{ $fUnread }} new</span>
                @endif
            </div>
            @forelse ($recentResponses as $r)
                @php $who = $r->extractContactData(); @endphp
                <button wire:click="openResponse('{{ $r->id }}')"
                        class="w-full text-left px-4 py-3 flex items-start gap-2.5 border-b border-gray-50 dark:border-white/[0.03] last:border-0
                               hover:bg-indigo-50/50 dark:hover:bg-white/[0.04] transition-colors {{ $r->read_at ? '' : 'bg-rose-50/40 dark:bg-rose-500/[0.05]' }}">
                    <span class="mt-1.5 w-2 h-2 rounded-full shrink-0 {{ $r->read_at ? 'bg-gray-200 dark:bg-white/10' : 'bg-rose-500 animate-pulse' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-gray-800 dark:text-gray-100 truncate">
                                {{ $who['name'] ?? $who['email'] ?? 'Anonymous' }}
                            </span>
                            <time class="text-[10px] text-gray-400 shrink-0">{{ $r->created_at->diffForHumans(null, true) }}</time>
                        </span>
                        <span class="mt-0.5 flex items-center gap-1.5 min-w-0">
                            <span class="text-[10px] font-semibold px-1.5 py-px rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 shrink-0">
                                {{ $r->form?->displayTitle() ?? 'Form' }}
                            </span>
                            <span class="text-[11px] text-gray-400 truncate">
                                {{ Str::limit(collect($r->fields ?? [])->filter(fn ($v) => is_scalar($v))->implode(' · '), 60) }}
                            </span>
                        </span>
                    </span>
                </button>
            @empty
                <p class="px-4 py-10 text-center text-xs text-gray-400">No responses yet — they'll appear here the moment a visitor submits a form or books.</p>
            @endforelse
        </div>
    </x-carousel.slide>
    </x-carousel>
@endif {{-- /list --}}


{{-- ════════════════════════════════════════════════════════════
     FORM MODE (create / edit)
════════════════════════════════════════════════════════════ --}}
@if ($mode === 'form')

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <button wire:click="backToList"
                class="flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400
                       hover:text-gray-900 dark:hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Forms
        </button>
        <span class="text-gray-300 dark:text-white/20">/</span>
        <h1 class="text-base font-bold text-gray-900 dark:text-white">
            {{ $activeFormId ? 'Edit Form' : 'New Form' }}
        </h1>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- ── Left col: form metadata + delivery ── --}}
        <div class="xl:col-span-1 space-y-4">
            @include('livewire.partials.form-editor-info')
            @include('livewire.partials.form-editor-delivery')
        </div>
        @include('livewire.partials.form-editor-fields')
    </div>

    @include('livewire.partials.form-editor-receipt')

@endif {{-- /form --}}


{{-- ════════════════════════════════════════════════════════════
     DETAIL MODE — 3-pane: detail tiles | tabbed centre | side details
════════════════════════════════════════════════════════════ --}}
@if ($mode === 'detail' && $activeForm)
<x-tri-layout :title="$activeForm->displayTitle()" :labels="['📄 Details', '📥 Form', 'ℹ️ More']"
              :site-name="$site->name" quick-width="lg:!w-[340px]">

    <x-slot:header>
        <div class="flex items-center gap-2 flex-wrap">
            @if ($activeForm->unread_count > 0)
                <button wire:click="markAllRead" class="fx flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.05]">
                    Mark all read <span class="text-[10px] font-bold px-1.5 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300">{{ $activeForm->unread_count }}</span>
                </button>
            @endif
            <button wire:click="backToList" class="fx flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1d1e2a] text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.05]">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                All forms
            </button>
        </div>
    </x-slot:header>

    {{-- ══ LEFT rail: form details as tiles ══ --}}
    <x-slot:rail>
        <div class="grid grid-cols-2 gap-3">
            <x-stat-tile label="Responses" :value="number_format($activeForm->responses_count)" color="#f59e0b"
                icon="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            <x-stat-tile label="Unread" :value="$activeForm->unread_count" :sub="$activeForm->unread_count ? 'awaiting review' : 'all read'" color="#6366f1"
                icon="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            <x-stat-tile label="Fields" :value="count($activeForm->fields ?? [])" color="#10b981"
                icon="M4 6h16M4 10h16M4 14h10" />
            <x-stat-tile :label="$activeForm->is_active ? 'Active' : 'Paused'" :value="$activeForm->is_active ? 'On' : 'Off'"
                :sub="$activeForm->is_active ? 'accepting submissions' : 'submissions paused'" :color="$activeForm->is_active ? '#22c55e' : '#9ca3af'"
                icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </div>
        <button wire:click="deleteForm('{{ $activeForm->id }}')" data-confirm="Delete this form and all of its responses?"
                class="fx w-full mt-4 flex items-center justify-center gap-1.5 text-xs font-semibold px-3 py-2.5 rounded-xl text-red-600 dark:text-red-400 border border-red-200 dark:border-red-500/30 bg-white dark:bg-[#1d1e2a] hover:bg-red-50 dark:hover:bg-red-500/10">
            Delete form
        </button>
    </x-slot:rail>

    {{-- ══ CENTER: tabs — Responses | Edit form | Delivery ══ --}}
    <div class="max-w-[52rem] mx-auto space-y-4">
        <div class="flex items-center gap-2">
            @foreach (['responses' => 'Responses', 'edit' => 'Edit form', 'delivery' => 'Delivery'] as $tk => $tl)
                <button wire:click="$set('dtab', '{{ $tk }}')"
                        class="fx flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors
                               {{ $dtab === $tk
                                    ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                                    : 'bg-white dark:bg-[#1d1e2a] border-gray-200 dark:border-white/[0.08] text-gray-500 dark:text-gray-400 hover:border-gray-400' }}">
                    {{ $tl }}
                    @if ($tk === 'responses')<span class="text-[10px] font-bold {{ $dtab === $tk ? 'opacity-70' : 'text-gray-400' }}">{{ number_format($activeForm->responses_count) }}</span>@endif
                </button>
            @endforeach
        </div>

        @if ($dtab === 'responses')
            @include('livewire.partials.form-responses-list')
        @elseif ($dtab === 'edit')
            <div class="space-y-4">
                @include('livewire.partials.form-editor-info')
                @include('livewire.partials.form-editor-fields')
                @include('livewire.partials.form-editor-receipt')
            </div>
        @elseif ($dtab === 'delivery')
            <div class="space-y-4">
                @include('livewire.partials.form-editor-delivery')
                <div class="flex justify-end">
                    <button wire:click="saveForm" class="fx flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl shadow-sm">Save delivery settings</button>
                </div>
            </div>
        @endif
    </div>

    {{-- ══ RIGHT rail: side details ══ --}}
    <x-slot:quick>
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4 space-y-3.5">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Details</h3>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Slug</p>
                <p class="font-mono text-sm text-gray-800 dark:text-gray-200">{{ $activeForm->name }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">API endpoint</p>
                <p class="font-mono text-[11px] text-gray-600 dark:text-gray-400 break-all">POST /api/sites/{{ $site->name }}/form/{{ $activeForm->name }}</p>
            </div>
            @if ($activeForm->description)
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Description</p>
                <p class="text-[12.5px] text-gray-700 dark:text-gray-300 leading-relaxed">{{ $activeForm->description }}</p>
            </div>
            @endif
            <p class="text-[11px] text-gray-400 pt-1 border-t border-gray-50 dark:border-white/[0.05]">Created {{ $activeForm->created_at->format('j M Y') }} · last response {{ $activeForm->responses()->latest()->value('created_at')?->diffForHumans() ?? 'never' }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Fields</h3>
                <button wire:click="$set('dtab', 'edit')" class="text-[11px] font-semibold text-indigo-500 hover:underline">Edit →</button>
            </div>
            @forelse ($activeForm->fields ?? [] as $i => $field)
                <div class="flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="shrink-0 w-6 h-6 rounded-lg grid place-items-center text-[10px] font-bold bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-300">{{ $i + 1 }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[12px] font-bold text-gray-800 dark:text-gray-100 truncate">{{ $field['label'] ?? $field['name'] ?? 'Field' }} @if($field['required'] ?? false)<span class="text-rose-500">*</span>@endif</span>
                        <span class="block text-[10px] font-mono text-gray-400 truncate">{{ $field['name'] ?? '' }} · {{ \App\Models\Form::fieldValidationSummary($field) }}</span>
                    </span>
                    <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-300">{{ $field['type'] ?? 'text' }}</span>
                </div>
            @empty
                <p class="text-[11px] text-gray-400 py-2">No fields defined — raw mode.</p>
            @endforelse
        </div>

        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Delivery</h3>
                <button wire:click="$set('dtab', 'delivery')" class="text-[11px] font-semibold text-indigo-500 hover:underline">Edit →</button>
            </div>
            @php $dcfg = $activeForm->deliveryConfig(); @endphp
            @foreach ($channels as $key => $channel)
                @php $on = (bool) ($dcfg['channels'][$key]['enabled'] ?? false); @endphp
                <div class="flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }} {{ $channel['implemented'] ? '' : 'opacity-50' }}">
                    <span class="shrink-0 w-2 h-2 rounded-full" style="background:{{ $on ? '#22c55e' : '#d1d5db' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[12px] font-bold text-gray-800 dark:text-gray-100">{{ $channel['label'] }}</span>
                        <span class="block text-[10px] text-gray-400 truncate">{{ $channel['implemented'] ? ($on ? 'On' : 'Off') : 'Coming soon' }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    </x-slot:quick>
</x-tri-layout>
@endif {{-- /detail --}}

<livewire:refer-to-partner :site="$site" />




</div>
