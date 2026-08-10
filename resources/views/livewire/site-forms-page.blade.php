{{--
  site-forms-page.blade.php
  Modes: list | form (create/edit) | detail | responses
--}}
<div class="p-6 space-y-6">

{{-- ════════════════════════════════════════════════════════════
     LIST MODE
════════════════════════════════════════════════════════════ --}}
@if ($mode === 'list')
<x-page-layout title="Forms" subtitle="Collect submissions from your visitors.">
    @php
        $fActive = $forms->where('is_active', true)->count();
        $fFields = $forms->sum(fn ($f) => is_array($f->fields) ? count($f->fields) : 0);
    @endphp
    <x-slot:stats>
        <x-stat-tile label="Forms" :value="$forms->count()" color="#6366f1"
            icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        <x-stat-tile label="Active" :value="$fActive" :sub="$forms->count().' total'" color="#10b981"
            :bar="$forms->count() ? round($fActive / $forms->count() * 100) : 0"
            icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-stat-tile label="Inactive" :value="$forms->count() - $fActive" color="#9ca3af"
            icon="M18.36 6.64A9 9 0 1120.77 15M15 12H3m0 0l4-4m-4 4l4 4" />
        <x-stat-tile label="Total fields" :value="$fFields" color="#f59e0b"
            icon="M4 6h16M4 10h16M4 14h16M4 18h16" />
    </x-slot:stats>

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
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($forms as $form)
                <div class="relative flex flex-col bg-white dark:bg-[#1d1e2a]
                            rounded-2xl border border-gray-100 dark:border-white/[0.06]
                            shadow-sm hover:shadow-md transition-shadow overflow-hidden">

                    {{-- Status + unread badge --}}
                    <div class="absolute top-3 right-3 flex items-center gap-1.5">
                        @if (! $form->is_active)
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full
                                         bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400">
                                Inactive
                            </span>
                        @endif
                        @if ($form->unread_count > 0)
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full
                                         bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300">
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
                                       text-gray-500 dark:text-gray-400
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
</x-page-layout>
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

        {{-- ── Left col: form metadata ─────────────────────────── --}}
        <div class="xl:col-span-1 space-y-4">

            {{--
                slugEdited = true  → user manually touched the slug field; stop auto-generating.
                Start true when editing an existing form so the slug is never overwritten.
            --}}
            <div x-data="{ slugEdited: {{ $activeFormId ? 'true' : 'false' }} }"
                 class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-5 space-y-4">
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                    Form Info
                </h3>

                {{-- Title --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">
                        Title <span class="text-red-500">*</span>
                    </label>
                    <x-field.text wire:model.blur="fbTitle" placeholder="e.g. Contact Us"
                           x-on:input="
                               if (!slugEdited) {
                                   const slug = $event.target.value
                                       .toLowerCase()
                                       .trim()
                                       .replace(/[^a-z0-9\s-]/g, '')
                                       .replace(/\s+/g, '-')
                                       .replace(/-+/g, '-')
                                       .replace(/^-+|-+$/g, '');
                                   $wire.set('fbName', slug);
                               }
                           " />
                    @error('fbTitle')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">
                        Slug <span class="text-red-500">*</span>
                    </label>
                    <x-field.text wire:model.blur="fbName" placeholder="contact-us" mono
                           x-on:input="slugEdited = true" />
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        API endpoint: <span class="font-mono">…/form/{{ $fbName ?: '{slug}' }}</span>
                    </p>
                    @error('fbName')
                        <p class="mt-0.5 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">
                        Description
                    </label>
                    <x-field.textarea model="fbDescription" rows="3" placeholder="What is this form for?" class="resize-none" />
                    @error('fbDescription')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Active toggle --}}
                <div class="flex items-center justify-between pt-1">
                    <div>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Accept submissions</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                            Disable to pause incoming responses.
                        </p>
                    </div>
                    <button wire:click="$toggle('fbIsActive')" type="button"
                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full
                                   transition-colors focus:outline-none
                                   {{ $fbIsActive ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-white/20' }}">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform
                                     {{ $fbIsActive ? 'translate-x-6' : 'translate-x-1' }}"></span>
                    </button>
                </div>
            </div>

            {{-- Save / Cancel --}}
            <div class="flex gap-2">
                <button wire:click="saveForm"
                        class="flex-1 flex items-center justify-center gap-2
                               bg-indigo-600 hover:bg-indigo-700 text-white
                               text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save Form
                </button>
                <button wire:click="backToList"
                        class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-white/10
                               text-sm font-semibold text-gray-600 dark:text-gray-300
                               hover:bg-gray-50 dark:hover:bg-white/[0.05] transition-colors">
                    Cancel
                </button>
            </div>

            {{-- ── Delivery channels ─────────────────────────────── --}}
            <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-5 space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Delivery</h3>
                    <p class="mt-1 text-xs text-gray-400">How you're told about a submission. More channels coming soon.</p>
                </div>

                @foreach ($channels as $key => $channel)
                    <div class="rounded-xl border border-gray-100 dark:border-white/[0.06] p-4 space-y-3
                                {{ $channel['implemented'] ? '' : 'opacity-60' }}">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $channel['label'] }}</span>
                                    @unless ($channel['implemented'])
                                        <span class="text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded
                                                     bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">Coming soon</span>
                                    @endunless
                                </div>
                                <p class="mt-0.5 text-xs text-gray-400">{{ $channel['description'] }}</p>
                            </div>
                            @if ($channel['implemented'])
                                <button type="button" wire:click="$toggle('fbDelivery.channels.{{ $key }}.enabled')"
                                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors
                                               {{ ($fbDelivery['channels'][$key]['enabled'] ?? false) ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-white/20' }}">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform
                                                 {{ ($fbDelivery['channels'][$key]['enabled'] ?? false) ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                </button>
                            @else
                                <span class="inline-flex h-6 w-11 shrink-0 items-center rounded-full bg-gray-200 dark:bg-white/10">
                                    <span class="inline-block h-4 w-4 translate-x-1 transform rounded-full bg-white/70"></span>
                                </span>
                            @endif
                        </div>

                        {{-- Email sub-options --}}
                        @if ($key === 'email' && ($fbDelivery['channels']['email']['enabled'] ?? false))
                            <div class="pt-1 space-y-2 border-t border-gray-100 dark:border-white/[0.06]">
                                <x-field.check model="fbDelivery.channels.email.notify_visitor" text="Send a receipt to the visitor" />
                                <x-field.check model="fbDelivery.channels.email.notify_admin" text="Alert an admin of new submissions" />
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Admin address (optional)</label>
                                    <x-field.text model="fbDelivery.channels.email.admin_address" type="email"
                                                  placeholder="Defaults to the site owner" />
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

        </div>

        {{-- ── Right col: field builder ─────────────────────────── --}}
        <div class="xl:col-span-2 space-y-3">

            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                    Fields <span class="font-normal normal-case text-gray-400">({{ count($fbFields) }})</span>
                </h3>
                <button wire:click="addField"
                        class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5
                               rounded-xl border border-indigo-200 dark:border-indigo-500/40
                               text-indigo-600 dark:text-indigo-400
                               hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Field
                </button>
            </div>

            @if (empty($fbFields))
                <div class="flex flex-col items-center gap-3 py-12
                            rounded-2xl border border-dashed border-gray-200 dark:border-white/10
                            bg-white dark:bg-[#1d1e2a]">
                    <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 6h16M4 10h16M4 14h10"/>
                    </svg>
                    <p class="text-sm text-gray-400 dark:text-gray-500">No fields yet. Click <strong>Add Field</strong> to start.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($fbFields as $index => $field)
                        @php
                            $needsOptions = in_array($field['type'] ?? 'text', ['select', 'radio']);
                            $needsRange   = in_array($field['type'] ?? 'text', ['text','email','tel','number','url','date','textarea']);
                        @endphp

                        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-4 space-y-3">

                            {{-- Field header --}}
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-indigo-50 dark:bg-indigo-500/10
                                             flex items-center justify-center
                                             text-xs font-bold text-indigo-500 shrink-0">
                                    {{ $index + 1 }}
                                </span>
                                <span class="flex-1 text-xs font-semibold text-gray-600 dark:text-gray-400 truncate">
                                    {{ $field['label'] ?: 'Untitled Field' }}
                                    @if ($field['key']) <span class="font-mono font-normal text-gray-400">({{ $field['key'] }})</span> @endif
                                </span>
                                {{-- Move up/down --}}
                                <button wire:click="moveFieldUp({{ $index }})"
                                        @if ($index === 0) disabled @endif
                                        class="p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300
                                               disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                                    </svg>
                                </button>
                                <button wire:click="moveFieldDown({{ $index }})"
                                        @if ($index === count($fbFields) - 1) disabled @endif
                                        class="p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300
                                               disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <button wire:click="removeField({{ $index }})" data-confirm="Remove this field?"
                                        class="p-1 rounded text-red-400 hover:text-red-600 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            {{-- Row 1: label + key + type --}}
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
                                        Label <span class="text-red-500">*</span>
                                    </label>
                                    <x-field.text wire:model.blur="fbFields.{{ $index }}.label" placeholder="First Name" />
                                    @error("fbFields.{$index}.label")
                                        <p class="mt-0.5 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
                                        Key <span class="text-red-500">*</span>
                                    </label>
                                    <x-field.text wire:model.blur="fbFields.{{ $index }}.key" placeholder="first_name" mono />
                                    @error("fbFields.{$index}.key")
                                        <p class="mt-0.5 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Type</label>
                                    <select wire:model.live="fbFields.{{ $index }}.type"
                                            class="bkf-input">
                                        <optgroup label="Text">
                                            <option value="text">Text</option>
                                            <option value="email">Email</option>
                                            <option value="tel">Phone</option>
                                            <option value="url">URL</option>
                                            <option value="textarea">Textarea</option>
                                        </optgroup>
                                        <optgroup label="Numeric / Date">
                                            <option value="number">Number</option>
                                            <option value="date">Date</option>
                                        </optgroup>
                                        <optgroup label="Choice">
                                            <option value="select">Dropdown (select)</option>
                                            <option value="radio">Radio</option>
                                            <option value="checkbox">Checkbox (boolean)</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>

                            {{-- Row 2: placeholder + required + range OR options --}}
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                {{-- Placeholder (not for checkbox) --}}
                                @if (($field['type'] ?? 'text') !== 'checkbox')
                                    <div>
                                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Placeholder</label>
                                        <x-field.text model="fbFields.{{ $index }}.placeholder" placeholder="Optional hint…" />
                                    </div>
                                @endif

                                {{-- Required toggle --}}
                                <div class="flex items-end pb-0.5">
                                    <x-field.check model="fbFields.{{ $index }}.required" text="Required" />
                                </div>

                                {{-- Min / Max (text types) --}}
                                @if ($needsRange)
                                    <div class="flex gap-2">
                                        <div class="flex-1">
                                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Min</label>
                                            <x-field.text model="fbFields.{{ $index }}.min" placeholder="—" />
                                        </div>
                                        <div class="flex-1">
                                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Max</label>
                                            <x-field.text model="fbFields.{{ $index }}.max" placeholder="—" />
                                        </div>
                                    </div>
                                @endif

                                {{-- Options (select / radio) --}}
                                @if ($needsOptions)
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
                                            Options <span class="font-normal">(comma-separated)</span>
                                        </label>
                                        <x-field.text model="fbFields.{{ $index }}.options" placeholder="Option A, Option B, Option C" />
                                    </div>
                                @endif
                            </div>

                        </div>
                    @endforeach
                </div>

                {{-- Add field (bottom shortcut) --}}
                <button wire:click="addField"
                        class="w-full py-3 rounded-2xl border border-dashed border-gray-200 dark:border-white/10
                               text-xs font-semibold text-gray-400 dark:text-gray-500
                               hover:border-indigo-300 dark:hover:border-indigo-500/40
                               hover:text-indigo-500 dark:hover:text-indigo-400 transition-colors">
                    + Add another field
                </button>
            @endif

        </div>
    </div>

    {{-- ── Receipt email template (per form) ─────────────────────── --}}
    <div class="mt-6 bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Receipt email</h3>
                <p class="mt-1 text-xs text-gray-400 max-w-xl">
                    The email the visitor gets after submitting this form. By default it uses your
                    <a href="{{ url($site->name.'/emails') }}" class="underline">site template</a> — turn on customising to give this form its own.
                </p>
            </div>
            <label class="inline-flex items-center gap-2 cursor-pointer shrink-0">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Customise for this form</span>
                <input type="checkbox" wire:model.live="fbTemplate.customized" class="sr-only peer">
                <span class="relative h-6 w-11 rounded-full bg-gray-300 dark:bg-white/20 peer-checked:bg-indigo-600 transition-colors
                             after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-5"></span>
            </label>
        </div>

        @if ($fbTemplate['customized'] ?? false)
            <div class="mt-5 grid lg:grid-cols-2 gap-6">
                {{-- Editor --}}
                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Subject</label>
                        <input wire:model.live.debounce.300ms="fbTemplate.subject" type="text"
                               class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Layout</label>
                            <button wire:click="resetTemplateToSiteDefault" type="button" class="text-[11px] font-semibold text-gray-400 hover:text-indigo-500">Reset to site default</button>
                        </div>
                        <x-email.section-list :sections="$fbTemplate['sections']" :labels="$tplLabels" :editableKeys="$editableKeys"
                                              prefix="fbTemplate.sections" up="moveTplSectionUp" down="moveTplSectionDown" />
                    </div>
                </div>
                {{-- Preview --}}
                <div>
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2">Live preview</p>
                    <x-email.preview :preview="$this->templatePreview" :logo="$siteLogo" :site="$site" />
                </div>
            </div>
        @endif
    </div>

@endif {{-- /form --}}


{{-- ════════════════════════════════════════════════════════════
     DETAIL MODE
════════════════════════════════════════════════════════════ --}}
@if ($mode === 'detail' && $activeForm)

    {{-- Header --}}
    <div class="flex items-center gap-3 flex-wrap">
        <button wire:click="backToList"
                class="flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400
                       hover:text-gray-900 dark:hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Forms
        </button>
        <span class="text-gray-300 dark:text-white/20">/</span>
        <h1 class="text-base font-bold text-gray-900 dark:text-white">{{ $activeForm->displayTitle() }}</h1>

        {{-- Status badge --}}
        @if ($activeForm->is_active)
            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full
                         bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Active
            </span>
        @else
            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full
                         bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400">
                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                Inactive
            </span>
        @endif

        <div class="flex-1"></div>

        <button wire:click="goEdit('{{ $activeForm->id }}')"
                class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl
                       border border-gray-200 dark:border-white/10
                       bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-300
                       hover:bg-gray-50 dark:hover:bg-white/[0.05] transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Edit Form
        </button>
        <button wire:click="goResponses('{{ $activeForm->id }}')"
                class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700
                       text-white text-xs font-semibold px-3 py-1.5 rounded-xl transition-colors">
            View Responses
            @if ($activeForm->unread_count > 0)
                <span class="bg-white/20 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">
                    {{ $activeForm->unread_count }}
                </span>
            @endif
        </button>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- Meta card --}}
        <div class="xl:col-span-1 bg-white dark:bg-[#1d1e2a] rounded-2xl
                    border border-gray-100 dark:border-white/[0.06] p-5 space-y-4 h-fit">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Slug</p>
                <p class="font-mono text-sm text-gray-800 dark:text-gray-200">{{ $activeForm->name }}</p>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">API Endpoint</p>
                <p class="font-mono text-xs text-gray-600 dark:text-gray-400 break-all">
                    POST /api/sites/{{ $site->name }}/form/{{ $activeForm->name }}
                </p>
            </div>
            @if ($activeForm->description)
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Description</p>
                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $activeForm->description }}</p>
            </div>
            @endif
            <div class="flex items-center gap-4 pt-1 border-t border-gray-100 dark:border-white/[0.05]">
                <div class="text-center">
                    <p class="text-xl font-extrabold text-gray-900 dark:text-white">
                        {{ $activeForm->responses_count }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">Total</p>
                </div>
                @if ($activeForm->unread_count > 0)
                <div class="text-center">
                    <p class="text-xl font-extrabold text-indigo-600 dark:text-indigo-400">
                        {{ $activeForm->unread_count }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">Unread</p>
                </div>
                @endif
                <div class="text-center">
                    <p class="text-xl font-extrabold text-gray-900 dark:text-white">
                        {{ count($activeForm->fields ?? []) }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">Fields</p>
                </div>
            </div>
        </div>

        {{-- Fields table --}}
        <div class="xl:col-span-2 bg-white dark:bg-[#1d1e2a] rounded-2xl
                    border border-gray-100 dark:border-white/[0.06] overflow-hidden">

            <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.05]">
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300">Form Fields & Validations</h3>
            </div>

            @if (empty($activeForm->fields))
                <div class="flex flex-col items-center gap-2 py-12">
                    <svg class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h10"/>
                    </svg>
                    <p class="text-sm text-gray-400 dark:text-gray-500">No fields defined.</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        This form will accept any submitted fields (raw mode).
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                                <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 whitespace-nowrap">#</th>
                                <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 whitespace-nowrap">Label</th>
                                <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 whitespace-nowrap">Key</th>
                                <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 whitespace-nowrap">Type</th>
                                <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 whitespace-nowrap">Req.</th>
                                <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 whitespace-nowrap">Validation Rules</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-white/[0.03]">
                            @foreach ($activeForm->fields as $i => $field)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-5 py-3 text-xs font-semibold text-gray-400 dark:text-gray-500">
                                        {{ $i + 1 }}
                                    </td>
                                    <td class="px-5 py-3 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                        {{ $field['label'] ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 font-mono text-xs text-indigo-600 dark:text-indigo-400 whitespace-nowrap">
                                        {{ $field['key'] ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium
                                                     bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-400">
                                            {{ $field['type'] ?? 'text' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        @if ($field['required'] ?? false)
                                            <span class="text-emerald-500 dark:text-emerald-400 font-bold text-base">✓</span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400 max-w-xs">
                                        {{ \App\Models\Form::fieldValidationSummary($field) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
    </div>

@endif {{-- /detail --}}


{{-- ════════════════════════════════════════════════════════════
     RESPONSES MODE
════════════════════════════════════════════════════════════ --}}
@if ($mode === 'responses' && $activeForm)

    {{-- Header --}}
    <div class="sticky top-0 z-10 -mx-6 -mt-6 px-6 pt-5 pb-4
                bg-[#f0f2f7]/90 dark:bg-[#16171d]/90 backdrop-blur-sm
                border-b border-gray-200/50 dark:border-white/[0.05]">
        <div class="flex items-center gap-3 flex-wrap">
            <button wire:click="backToDetail"
                    class="flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400
                           hover:text-gray-900 dark:hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                {{ $activeForm->displayTitle() }}
            </button>
            <span class="text-gray-300 dark:text-white/20">/</span>
            <h1 class="text-base font-bold text-gray-900 dark:text-white">Responses</h1>

            @if ($activeForm->unread_count > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                             bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300">
                    {{ $activeForm->unread_count }} unread
                </span>
            @endif

            <div class="flex-1"></div>

            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ number_format($activeForm->responses_count) }} {{ Str::plural('response', $activeForm->responses_count) }}
            </span>

            @if ($activeForm->unread_count > 0)
                <button wire:click="markAllRead"
                        class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl
                               border border-gray-200 dark:border-white/10
                               bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-300
                               hover:bg-gray-50 dark:hover:bg-white/[0.05] transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Mark all read
                </button>
            @endif
        </div>
    </div>

    {{-- Empty --}}
    @if ($responses->isEmpty())
        <div class="flex flex-col items-center gap-3 py-20 rounded-2xl
                    border border-dashed border-gray-200 dark:border-white/10
                    bg-white dark:bg-[#1d1e2a]">
            <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
            <p class="text-sm text-gray-400 dark:text-gray-500 font-medium">No responses yet</p>
        </div>

    @else
        <div class="space-y-2">
            @foreach ($responses as $response)
                @php
                    $isOpen  = $openId === $response->id;
                    $fields  = $response->fields ?? [];
                    $preview = collect($fields)->filter(fn($v) => is_string($v) && $v !== '')->first() ?? '—';
                @endphp

                <div class="rounded-2xl border overflow-hidden transition-all
                            {{ $isOpen ? 'border-indigo-200 dark:border-indigo-500/30 shadow-sm' : 'border-gray-100 dark:border-white/[0.06]' }}
                            bg-white dark:bg-[#1d1e2a]">

                    <button wire:click="toggleOpen('{{ $response->id }}')"
                            class="w-full flex items-center gap-3 px-4 py-3.5 text-left
                                   hover:bg-gray-50 dark:hover:bg-white/[0.03] transition-colors">

                        <span class="w-2 h-2 rounded-full shrink-0
                                     {{ $response->read_at ? 'bg-transparent ring-1 ring-gray-300 dark:ring-white/20' : 'bg-indigo-500' }}">
                        </span>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">
                                {{ Str::limit((string) $preview, 90) }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                {{ $response->created_at->format('M j, Y · g:i A') }}
                                @if ($response->ip_address)
                                    · <span class="font-mono">{{ $response->ip_address }}</span>
                                @endif
                            </p>
                        </div>

                        <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform {{ $isOpen ? 'rotate-180' : '' }}"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    @if ($isOpen)
                        <div class="border-t border-gray-100 dark:border-white/[0.05] px-4 pb-4 pt-4 space-y-4">

                            {{-- Fields grid --}}
                            @if (! empty($fields))
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach ($fields as $key => $value)
                                        <div class="rounded-xl bg-gray-50 dark:bg-white/[0.04] px-4 py-3">
                                            <p class="text-xs font-semibold uppercase tracking-wider
                                                       text-gray-400 dark:text-gray-500 mb-1">
                                                {{ str_replace(['-', '_'], ' ', $key) }}
                                            </p>
                                            <p class="text-sm text-gray-800 dark:text-gray-200 break-words">
                                                @if (is_array($value))
                                                    {{ implode(', ', $value) }}
                                                @elseif (is_bool($value))
                                                    {{ $value ? 'Yes' : 'No' }}
                                                @else
                                                    {{ $value ?: '—' }}
                                                @endif
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Meta + delete --}}
                            <div class="flex items-center justify-between flex-wrap gap-3 pt-3
                                        border-t border-gray-100 dark:border-white/[0.05]">
                                <div class="flex items-center gap-4 text-xs text-gray-400 dark:text-gray-500 flex-wrap">
                                    <span>{{ $response->created_at->format('M j, Y \a\t g:i A') }}</span>
                                    @if ($response->ip_address)
                                        <span class="font-mono">IP: {{ $response->ip_address }}</span>
                                    @endif
                                    @if ($response->read_at)
                                        <span class="text-emerald-600 dark:text-emerald-400">
                                            ✓ Read {{ $response->read_at->diffForHumans() }}
                                        </span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    {{-- Convert to Contact / Converted indicator --}}
                                    @if ($response->contact)
                                        <a href="{{ url($site->name.'/contacts').'?contact='.$response->contact->id }}"
                                           class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl
                                                  text-emerald-600 dark:text-emerald-400
                                                  border border-emerald-200 dark:border-emerald-500/30
                                                  hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            View Contact
                                        </a>
                                    @else
                                        <button wire:click="convertToContact('{{ $response->id }}')"
                                                class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl
                                                       text-indigo-600 dark:text-indigo-400
                                                       border border-indigo-200 dark:border-indigo-500/30
                                                       hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                            </svg>
                                            Convert to Contact
                                        </button>
                                    @endif

                                    <button wire:click="deleteResponse('{{ $response->id }}')" data-confirm="Delete this response?"
                                            data-confirm="Delete this response permanently?"
                                            class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl
                                                   text-red-600 dark:text-red-400
                                                   border border-red-200 dark:border-red-500/30
                                                   hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            @endforeach
        </div>

        @if ($responses->hasPages())
            <div class="pt-2">{{ $responses->links() }}</div>
        @endif
    @endif

@endif {{-- /responses --}}

</div>
