@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btn = 'fx inline-flex items-center justify-center min-h-[36px] px-3.5 rounded-xl text-[12.5px] font-bold';
    $btnOutline = $btn.' border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200';
    $statePill = fn (string $s) => match ($s) {
        'published' => ['#dcfce7', '#15803d', 'Published'],
        'pending' => ['#fef3c7', '#92400e', 'Pending review'],
        default => ['#f3f4f6', '#374151', 'Hidden'],
    };
    $stars = fn (?int $n) => $n ? str_repeat('★', $n).str_repeat('☆', 5 - $n) : null;
@endphp

<x-tri-layout title="Testimonials" subtitle="Every testimonial about Olux — approve, edit or delete. Published ones show on the landing page."
    :labels="['📊 Numbers', '💬 Testimonials', 'ℹ️ Summary']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    <x-slot:header>
        <button wire:click="create" class="fx inline-flex items-center gap-1.5 min-h-[40px] px-4 rounded-full text-sm font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add testimonial
        </button>
    </x-slot:header>

    {{-- ══ LEFT rail: numbers ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$stats['published']" label="Published" :sub="'landing shows up to '.$landingCount"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
        <x-tile accent="lime" :value="$stats['pending']" label="Pending" sub="to review"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lavender" :value="$stats['avg'] ?: '—'" label="Avg rating" sub="published"
                icon="M11.48 3.5a.562.562 0 011.04 0l2.125 5.11a.563.563 0 00.475.345l5.518.442c.5.04.7.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557L3.04 10.385a.562.562 0 01.32-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
        <x-tile accent="sky" :value="$stats['week']" label="New this week" sub="all sources"
                icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        <x-tile accent="cocoa" :value="$stats['hidden']" label="Hidden" sub="kept, not shown"
                icon="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
    </div>
    </x-slot:rail>

    {{-- ══ CENTER: tabs + list ══ --}}
    <div class="@container max-w-[52rem] mx-auto">
        <div class="flex flex-wrap gap-2 mb-4" role="tablist" aria-label="Filter testimonials">
            @foreach (['all' => 'All', 'pending' => 'Pending', 'published' => 'Published', 'hidden' => 'Hidden'] as $tk => $tl)
                @php $n = $tk === 'all' ? $counts->sum() : (int) ($counts[$tk] ?? 0); @endphp
                <button wire:click="setTab('{{ $tk }}')" role="tab" aria-selected="{{ $tab === $tk ? 'true' : 'false' }}"
                        class="fx inline-flex items-center gap-1.5 min-h-[38px] px-4 rounded-full text-[13px] font-bold border {{ $tab === $tk ? 'border-transparent' : 'border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200' }}"
                        @if ($tab === $tk) style="background:var(--primary);color:var(--on-primary)" @endif>
                    {{ $tl }} <span class="text-[11px] opacity-80 tabular-nums">{{ $n }}</span>
                </button>
            @endforeach
        </div>

        @if ($tab === 'published' && $rows->isNotEmpty())
            <p class="mb-3 text-[12.5px] text-gray-500 dark:text-gray-400">In landing order — the first {{ $landingCount }} show on the landing page. Use ↑ ↓ to reorder.</p>
        @endif

        <div class="space-y-3">
            @forelse ($rows as $i => $t)
                @php [$pb, $pf, $pl] = $statePill($t->status); $live = in_array($t->id, $onLanding, true); @endphp
                <div class="{{ $panel }} p-5 {{ $t->status === 'hidden' ? 'opacity-70' : '' }}" wire:key="t-{{ $t->id }}">
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:{{ $pb }};color:{{ $pf }}">{{ $pl }}</span>
                        @if ($live)<span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:var(--primary);color:var(--on-primary)">On landing page</span>@endif
                        <span class="text-[12px] text-gray-500 dark:text-gray-400">{{ $t->source === 'admin' ? 'Added by admin' : 'Submitted on the public form' }} · {{ $t->created_at->diffForHumans() }}</span>
                        <span class="ml-auto flex flex-wrap items-center gap-2">
                            @if ($tab === 'published')
                                <button wire:click="move('{{ $t->id }}', -1)" @disabled($i === 0) class="{{ $btnOutline }} !px-2.5 disabled:opacity-30" aria-label="Move up">↑</button>
                                <button wire:click="move('{{ $t->id }}', 1)" @disabled($i === $rows->count() - 1) class="{{ $btnOutline }} !px-2.5 disabled:opacity-30" aria-label="Move down">↓</button>
                            @endif
                            @if ($t->status !== 'published')
                                <button wire:click="setStatus('{{ $t->id }}', 'published')" class="{{ $btn }}" style="background:var(--primary);color:var(--on-primary)">{{ $t->status === 'pending' ? 'Approve' : 'Publish' }}</button>
                            @endif
                            @if ($t->status !== 'hidden')
                                <button wire:click="setStatus('{{ $t->id }}', 'hidden')" class="{{ $btnOutline }}">Hide</button>
                            @endif
                            <button wire:click="edit('{{ $t->id }}')" class="{{ $btnOutline }}">Edit</button>
                            <button wire:click="delete('{{ $t->id }}')" data-confirm="Delete {{ $t->name }}’s testimonial for good?" class="{{ $btnOutline }} !text-rose-600">Delete</button>
                        </span>
                    </div>
                    <blockquote class="text-[15px] leading-relaxed text-gray-800 dark:text-gray-100">“{{ $t->quote }}”</blockquote>
                    <div class="mt-3 flex items-center gap-3">
                        <span class="w-9 h-9 shrink-0 rounded-full grid place-items-center text-[12px] font-extrabold text-white" style="background:var(--primary)">{{ $t->initials() }}</span>
                        <span class="min-w-0 text-[13px]">
                            <span class="block font-bold text-gray-900 dark:text-white">{{ $t->name }}</span>
                            <span class="block text-gray-500 dark:text-gray-400">{{ $t->role ?: '—' }}@if ($t->email) · <span class="font-mono text-[12px]">{{ $t->email }}</span>@endif</span>
                        </span>
                        @if ($s = $stars($t->rating))<span class="ml-auto text-[15px] tracking-wider" style="color:var(--primary)" aria-label="{{ $t->rating }} out of 5 stars">{{ $s }}</span>@endif
                    </div>
                </div>
            @empty
                <div class="{{ $panel }} p-10 text-center">
                    <p class="text-sm font-bold text-gray-900 dark:text-white">Nothing here yet</p>
                    <p class="text-[13px] text-gray-500 dark:text-gray-400 mt-1">
                        {{ $tab === 'pending' ? 'Nothing waiting for approval. New submissions from the public form land here.' : 'Add one, or share the public form with happy customers.' }}
                    </p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ══ RIGHT rail: landing setting + links ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Landing page</h3>
            <p class="text-[12px] text-gray-500 dark:text-gray-400 mt-1 mb-3">How many published testimonials the landing carousel shows, in the order on the Published tab.</p>
            <form wire:submit="saveLandingCount" class="flex items-end gap-2">
                <label class="block flex-1">
                    <span class="bkf-label">Show</span>
                    <input type="number" min="1" max="20" wire:model="landingCount" class="bkf-input w-full">
                </label>
                <button type="submit" class="{{ $btn }} min-h-[42px]" style="background:var(--primary);color:var(--on-primary)">Save</button>
            </form>
            @error('landingCount')<p class="text-[12px] font-semibold text-rose-600 mt-1">{{ $message }}</p>@enderror
            <a href="{{ url('/') }}#testimonials" target="_blank" rel="noopener" class="mt-3 inline-block text-[12px] font-bold hover:underline" style="color:var(--primary)">View on the landing page ↗</a>
        </div>
        <div class="{{ $panel }} p-5 mt-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Public form</h3>
            <p class="text-[12px] text-gray-500 dark:text-gray-400 mt-1">Send this link to happy customers. What they write waits in Pending until you publish it.</p>
            <a href="{{ route('testimonials.create') }}" target="_blank" rel="noopener" class="mt-2 block text-[12px] font-mono font-semibold break-all hover:underline" style="color:var(--primary)">{{ route('testimonials.create') }}</a>
        </div>
    </x-slot:quick>

    {{-- ══ Add / edit drawer ══ --}}
    @if ($editing !== null)
        <x-side-drawer close="close" width="max-w-xl">
            <x-slot:header>
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">{{ $editing === '' ? 'New testimonial' : 'Edit testimonial' }}</p>
                <h2 class="font-display text-xl font-bold text-gray-900 dark:text-white truncate">{{ $form['name'] ?: 'Untitled' }}</h2>
            </x-slot:header>
            <form id="testimonial-form" wire:submit="save" class="p-6 space-y-5">
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="bkf-label">Name</span>
                        <input type="text" wire:model.live.debounce.300ms="form.name" maxlength="80" class="bkf-input w-full" required>
                        @error('form.name')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="bkf-label">Business or role <span class="font-normal text-gray-500">(optional)</span></span>
                        <input type="text" wire:model="form.role" maxlength="120" placeholder="Owner, Bloom Salon" class="bkf-input w-full">
                        @error('form.role')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </label>
                </div>
                <label class="block">
                    <span class="bkf-label">Testimonial</span>
                    <textarea wire:model="form.quote" rows="5" maxlength="600" class="bkf-input w-full" required></textarea>
                    @error('form.quote')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                </label>
                <div class="grid sm:grid-cols-3 gap-4">
                    <label class="block">
                        <span class="bkf-label">Rating</span>
                        <select wire:model="form.rating" class="bkf-input w-full">
                            <option value="">No rating</option>
                            @foreach ([5, 4, 3, 2, 1] as $r)<option value="{{ $r }}">{{ str_repeat('★', $r) }} {{ $r }}</option>@endforeach
                        </select>
                        @error('form.rating')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="bkf-label">Status</span>
                        <select wire:model="form.status" class="bkf-input w-full">
                            <option value="published">Published</option>
                            <option value="pending">Pending review</option>
                            <option value="hidden">Hidden</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="bkf-label">Email <span class="font-normal text-gray-500">(private)</span></span>
                        <input type="email" wire:model="form.email" maxlength="160" class="bkf-input w-full">
                        @error('form.email')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </label>
                </div>
            </form>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="close" class="{{ $btnOutline }}">Cancel</button>
                    <button type="submit" form="testimonial-form" class="{{ $btn }} min-w-[7rem]" style="background:var(--primary);color:var(--on-primary)">
                        <span wire:loading.remove wire:target="save">{{ $editing === '' ? 'Add testimonial' : 'Save' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </x-slot:footer>
        </x-side-drawer>
    @endif
</x-tri-layout>
