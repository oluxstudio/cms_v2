{{-- Polls admin — same three-pane layout as the Estimates page:
     stats rail | centered main (editor + activity) | manage rail (create · list · tutorial) --}}
<div class="h-full lg:overflow-y-auto p-5 sm:p-6">

    <x-carousel :labels="['📊 Stats', '🗳 Polls', '🛠 Manage']" :start="1">

    {{-- ════ LEFT RAIL: stat tiles ════ --}}
    <x-carousel.slide class="lg:!w-[280px] lg:shrink-0 pb-24 lg:pb-6 max-h-full overflow-y-auto lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] lg:overflow-y-auto no-scrollbar">
        @php $s = $this->stats; @endphp
        <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
            <x-tile accent="ink" :value="$s['polls']" label="polls" sub="all time" />
            <x-tile accent="lime" :value="$s['open']" label="open right now" sub="accepting votes" />
            <x-tile accent="lavender" :value="number_format($s['votes'])" label="total votes" sub="across all polls" />
            <x-tile accent="cocoa" :value="number_format($s['week'])" label="votes this week" sub="last 7 days" />
        </div>
    </x-carousel.slide>

    {{-- ════ MAIN: editor + live activity, centered column ════ --}}
    <x-carousel.slide class="lg:flex-1 lg:min-w-0 pb-24 lg:pb-6 max-h-full overflow-y-auto lg:overflow-y-visible no-scrollbar">
    <div class="max-w-[50rem] mx-auto">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Polls</h1>
            <button wire:click="openPollPage" type="button"
               class="inline-flex items-center gap-1 mt-0.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">See the poll page on your website ↗</button>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-300 mt-0.5">Ask your visitors a question — votes stream in live from your website.</p>
        </div>
    </div>

    @if ($errorMessage)
        <p class="mb-4 px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-sm text-rose-600 dark:text-rose-400">{{ $errorMessage }}</p>
    @endif

    @if ($this->selected)
    {{-- ═══ EDITOR for the selected poll ═══ --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-indigo-100 dark:border-indigo-500/20 shadow-sm mb-6 overflow-hidden" wire:key="poll-editor-{{ $this->selected->id }}">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]" style="background:color-mix(in srgb, #d9f068 14%, transparent)">
            <span class="w-9 h-9 rounded-full flex items-center justify-center text-base" style="background:#d9f068">🗳</span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $this->selected->question }}</p>
                <p class="text-[11px] text-gray-500 dark:text-gray-400">@php $sv = $this->selected->voterCount(); @endphp {{ $sv }} {{ Str::plural('person', $sv) }} voted · {{ $this->selected->votes()->count() }} {{ Str::plural('vote', $this->selected->votes()->count()) }} · {{ $this->selected->acceptsVotes() ? 'open' : 'closed' }}</p>
            </div>
            @if ($this->selected->acceptsVotes())
            <button wire:click="openPollPage('{{ $this->selected->id }}')" title="See just this poll on your website"
                    class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline shrink-0">Open on site ↗</button>
            @endif
            <button wire:click="closeEditor" class="text-xs font-semibold text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">✕ Close</button>
        </div>

        <div class="p-5 space-y-5">
            {{-- Settings --}}
            <form wire:submit="savePoll" class="space-y-3">
                <div>
                    <label class="bkf-label">Question</label>
                    <input wire:model="pQuestion" type="text" maxlength="200"
                           class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-600 dark:text-gray-300 cursor-pointer">
                        <input wire:model="pMultiple" type="checkbox" class="rounded"> Allow multiple choices
                    </label>
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-600 dark:text-gray-300">
                        Closes on
                        <input wire:model="pEndsAt" type="date"
                               class="px-2.5 py-1.5 rounded-lg text-xs bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                        <span class="font-normal text-gray-400">(blank = no end date)</span>
                    </label>
                    <button type="submit" class="ml-auto px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">Save</button>
                </div>
            </form>

            {{-- Options + live results --}}
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-600 dark:text-gray-300 mb-2.5">Options &amp; live results</p>
                <div class="space-y-2">
                    @forelse ($this->results as $r)
                    <div class="rounded-xl bg-gray-50 dark:bg-white/[0.04] px-3.5 py-2.5" wire:key="opt-{{ $r['id'] }}">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex-1 min-w-0 truncate">{{ $r['label'] }}</span>
                            <span class="text-xs font-extrabold tabular-nums text-gray-700 dark:text-gray-200">{{ $r['votes'] }} · {{ $r['share'] }}%</span>
                            <button wire:click="deleteOption('{{ $r['id'] }}')" data-confirm="Remove “{{ $r['label'] }}”? Its {{ $r['votes'] }} votes are deleted with it."
                                    class="text-gray-300 hover:text-rose-500 shrink-0" title="Remove option">✕</button>
                        </div>
                        <div class="mt-1.5 h-2 rounded-full bg-gray-200/70 dark:bg-white/[0.06] overflow-hidden">
                            <div class="h-full rounded-full transition-all" style="width:{{ max(2, $r['share']) }}%; background:linear-gradient(90deg,#6366f1,#8b5cf6)"></div>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-gray-400">No options yet — a poll needs at least two.</p>
                    @endforelse
                </div>

                <form wire:submit="addOption" class="flex gap-2 mt-3">
                    <input wire:model="newOption" type="text" placeholder="Add an option…" maxlength="160" required
                           class="flex-1 min-w-0 px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-semibold shrink-0">＋ Add</button>
                </form>
            </div>
        </div>
    </div>
    @else
    <div class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-white/[0.08] p-10 text-center mb-6">
        <span class="text-3xl">🗳</span>
        <p class="text-sm text-gray-500 dark:text-gray-400 font-medium mt-2">Pick a poll on the right to edit it — or create a new one.</p>
    </div>
    @endif

            {{-- ═══ All polls ═══ --}}
            @if ($this->polls->isNotEmpty())
            <div class="flex items-center gap-2 pt-1">
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-600 dark:text-gray-300">Your polls</p>
                <span class="text-[10px] font-bold min-w-[1.15rem] text-center px-1.5 py-0.5 rounded-full" style="background:#d9f068;color:#2b3110">{{ $this->polls->count() }}</span>
                <div class="flex-1 border-t border-gray-100 dark:border-white/[0.06]"></div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 mb-6">
                @foreach ($this->polls as $poll)
                <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border {{ $selectedId === $poll->id ? 'border-indigo-400 dark:border-indigo-500/50 ring-2 ring-indigo-500/20' : 'border-gray-100 dark:border-white/[0.05]' }} shadow-sm p-4" wire:key="poll-card-{{ $poll->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-900 dark:text-white truncate">🗳 {{ $poll->question }}</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">{{ $poll->options_count }} {{ Str::plural('option', $poll->options_count) }} · @php $pv = $poll->voterCount(); @endphp {{ $pv }} {{ Str::plural('voter', $pv) }} · {{ $poll->votes_count }} {{ Str::plural('vote', $poll->votes_count) }}</p>
                        </div>
                        <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $poll->acceptsVotes() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-gray-200 text-gray-600 dark:bg-white/[0.08] dark:text-gray-300' }}">{{ $poll->acceptsVotes() ? 'Open' : 'Closed' }}</span>
                    </div>
                    @if ($this->canManage)
                    <div class="flex gap-2 mt-3">
                        <button wire:click="select('{{ $poll->id }}')"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-semibold {{ $selectedId === $poll->id ? 'bg-indigo-600 text-white' : 'border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600' }} transition-colors">
                            {{ $selectedId === $poll->id ? 'Open — editing…' : 'Open' }}
                        </button>
                        <button wire:click="toggleOpen('{{ $poll->id }}')"
                                class="px-3 py-1.5 rounded-xl text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:border-indigo-400 transition-colors">
                            {{ $poll->is_open ? '⏸ Stop voting' : '▶ Reopen voting' }}
                        </button>
                        <button wire:click="deletePoll('{{ $poll->id }}')" data-confirm="Delete “{{ $poll->question }}” and all its votes? This cannot be undone."
                                class="px-3 py-1.5 rounded-xl text-xs font-semibold text-gray-400 hover:text-rose-500 transition-colors">Delete</button>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @endif

    {{-- ═══ Recent votes ═══ --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.05]">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Recent votes</h2>
        </div>
        @forelse ($this->recentVotes as $v)
        <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-50 dark:border-white/[0.04] last:border-0">
            <span class="w-2 h-2 rounded-full shrink-0" style="background:#8b5cf6"></span>
            <p class="text-sm text-gray-700 dark:text-gray-200 flex-1 min-w-0 truncate">
                <b>{{ $v->option?->label ?? '—' }}</b> <span class="text-gray-400">on</span> {{ $v->poll?->question ?? 'a deleted poll' }}
            </p>
            <span class="text-[11px] text-gray-400 shrink-0">{{ $v->created_at?->diffForHumans() }}</span>
        </div>
        @empty
        <p class="px-5 py-10 text-center text-sm text-gray-400">No votes yet — put a poll on your site and they'll appear here live.</p>
        @endforelse
    </div>

    </div>{{-- /centered 50rem column --}}
    </x-carousel.slide>

    {{-- ════ RIGHT RAIL: create · your polls · tutorial ════ --}}
    <x-carousel.slide class="lg:!w-[340px] lg:shrink-0 pb-24 lg:pb-6 max-h-full overflow-y-auto lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] lg:overflow-y-auto no-scrollbar">
        <div class="space-y-4">
            @if ($this->canManage)
            {{-- 1 · Create --}}
            <form wire:submit="createPoll"
                  class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-white/[0.08] p-4 flex flex-col justify-center gap-2">
                <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400">New poll — ask the question first</label>
                <div class="flex gap-2">
                    <input wire:model="newQuestion" type="text" placeholder="e.g. Which new service should we add?" required maxlength="200"
                           class="flex-1 min-w-0 px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shrink-0">Create</button>
                </div>
                @error('newQuestion')<p class="text-[11px] text-rose-500">{{ $message }}</p>@enderror
            </form>
            @endif

            {{-- Top polls: most voted + newest --}}
            @foreach ([['🔥 Most popular', $this->popularPolls], ['🕒 Latest', $this->latestPolls]] as [$railLabel, $railPolls])
            @if ($railPolls->isNotEmpty())
            <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/[0.05]">
                    <h2 class="text-xs font-bold text-gray-900 dark:text-white">{{ $railLabel }}</h2>
                </div>
                @foreach ($railPolls as $rp)
                <button wire:click="select('{{ $rp->id }}')" wire:key="rail-{{ $railLabel }}-{{ $rp->id }}"
                        class="w-full flex items-center gap-2.5 px-4 py-2.5 text-left border-b border-gray-50 dark:border-white/[0.04] last:border-0 hover:bg-gray-50 dark:hover:bg-white/[0.03] transition-colors">
                    <span class="w-5 h-5 rounded-lg grid place-items-center text-[10px] font-bold shrink-0
                        {{ $loop->index < 3 ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400' }}">{{ $loop->iteration }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $rp->question }}</span>
                        <span class="block text-[10px] text-gray-400">
                            @if (str_starts_with($railLabel, '🔥')) @php $rv = $rp->voterCount(); @endphp {{ $rv }} {{ Str::plural('voter', $rv) }} · {{ $rp->votes_count }} {{ Str::plural('vote', $rp->votes_count) }}
                            @else {{ $rp->created_at->diffForHumans() }} @endif
                        </span>
                    </span>
                    <span class="shrink-0 w-1.5 h-1.5 rounded-full {{ $rp->acceptsVotes() ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600' }}" title="{{ $rp->acceptsVotes() ? 'Open' : 'Closed' }}"></span>
                </button>
                @endforeach
            </div>
            @endif
            @endforeach

            {{-- 3 · Tutorial --}}
            <div class="rounded-2xl overflow-hidden text-white" style="background:linear-gradient(150deg,#1f2330,#11131c)" x-data="{ step: 0 }">
                <div class="p-4 pb-3">
                    <p class="text-sm font-bold">📖 Polls tutorial</p>
                    <p class="text-[11px] text-white/60 mt-0.5">From question to live results, in 4 steps. Tap a step to expand it.</p>
                </div>
                @foreach([
                    ['1', 'Ask a question', 'Type it in <b>“New poll”</b> above — e.g. <i>Which new service should we add?</i> — and hit Create. Keep it short: one clear question per poll.'],
                    ['2', 'Add the answers', 'In the editor, add <b>at least two options</b> (up to 12). Tick <b>“Allow multiple choices”</b> if voters may pick more than one, and set a closing date if the poll should end itself.'],
                    ['3', 'Collect votes', 'Open polls are served to your website through the polls API — each visitor gets <b>one vote</b> (per option on multi-choice polls); repeat votes are politely refused.'],
                    ['4', 'Watch the results', 'Results update live: the bars in the editor, the vote feed below it, and the tiles on the left. <b>Close</b> a poll any time to freeze its result — Reopen brings it back.'],
                ] as [$n, $title, $body])
                <button type="button" @click="step = step === {{ $n }} ? 0 : {{ $n }}"
                        class="w-full flex items-center gap-2.5 px-4 py-2.5 text-left border-t border-white/[0.07] hover:bg-white/[0.04] transition-colors">
                    <span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-extrabold shrink-0"
                          :class="step === {{ $n }} ? '' : 'bg-white/10'" :style="step === {{ $n }} ? 'background:#d9f068;color:#2b3110' : ''">{{ $n }}</span>
                    <span class="text-xs font-bold flex-1">{{ $title }}</span>
                    <svg class="w-3 h-3 opacity-50 transition-transform" :class="step === {{ $n }} ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="step === {{ $n }}" x-collapse x-cloak>
                    <p class="px-4 pb-3 pl-11 text-[11px] leading-relaxed text-white/75">{!! $body !!}</p>
                </div>
                @endforeach
                <div class="px-4 py-3 border-t border-white/[0.07]">
                    <p class="text-[10px] text-white/50">Votes are deduplicated per visitor automatically — no accounts needed.</p>
                </div>
            </div>
        </div>
    </x-carousel.slide>
    </x-carousel>
</div>
