@php $s = $this->stats; $me = auth()->id(); @endphp
<div class="min-h-full flex flex-col lg:flex-row">

    {{-- ════ LEFT: statistics ════ --}}
    <aside class="left-bar w-full lg:w-72 shrink-0 border-b lg:border-b-0 lg:border-r border-gray-100 dark:border-white/[0.05] p-5 space-y-5
                  lg:sticky lg:top-0 lg:self-start lg:max-h-screen lg:overflow-y-auto no-scrollbar">
        <h1 class="text-xl font-extrabold text-gray-900 dark:text-white">Messages</h1>

        <div class="grid grid-cols-2 gap-2.5">
            @foreach ([['Total',$s['total'],'gray'],['Unread',$s['unread'],'indigo'],['Broadcasts',$s['broadcasts'],'gray'],['Direct',$s['direct'],'gray']] as [$lbl,$val,$tone])
                <div class="rounded-2xl p-3 border {{ $tone==='indigo' ? 'bg-indigo-50 dark:bg-indigo-500/10 border-indigo-100 dark:border-indigo-500/20' : 'bg-white dark:bg-[#1d1e2a] border-gray-100 dark:border-white/[0.05]' }} shadow-sm">
                    <p class="text-2xl font-extrabold {{ $tone==='indigo' ? 'text-indigo-600 dark:text-indigo-300' : 'text-gray-900 dark:text-white' }}">{{ $val }}</p>
                    <p class="text-[11px] text-gray-400">{{ $lbl }}</p>
                </div>
            @endforeach
        </div>

        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Team</p>
            <div class="space-y-1.5">
                @foreach ($this->members as $m)
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold" style="background:linear-gradient(135deg,var(--primary),var(--primary-2))">
                            {{ \Illuminate\Support\Str::of($m->name)->substr(0,2)->upper() }}
                        </span>
                        <span class="text-sm text-gray-700 dark:text-gray-200 truncate">{{ $m->name }}{{ $m->id === $me ? ' (you)' : '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </aside>

    {{-- ════ MAIN: list + compose ════ --}}
    <section class="main-body flex-1 min-w-0 p-5">
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Team conversation</p>
            <button wire:click="$set('composing', true)"
                    class="fx inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow-sm" style="background:#1f2330">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New message
            </button>
        </div>

        {{-- compose --}}
        @if ($composing)
            <form wire:submit="send" class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] p-4 mb-4 shadow-sm space-y-3">
                <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-gray-500">To</label>
                    <select wire:model="recipient" class="text-sm px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                        <option value="team">Whole team</option>
                        @foreach ($this->members as $m)
                            @if ($m->id !== $me)<option value="{{ $m->id }}">{{ $m->name }}</option>@endif
                        @endforeach
                    </select>
                </div>
                <textarea wire:model="body" rows="3" placeholder="Write a message…"
                          class="w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                @error('body')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('composing', false)" class="fx px-3 py-2 rounded-xl text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[0.05]">Cancel</button>
                    <button type="submit" class="fx px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background:var(--primary)">Send</button>
                </div>
            </form>
        @endif

        <div class="space-y-2.5 fx-stagger">
            @forelse ($this->messages as $msg)
                @php $mine = $msg->sender_id === $me; @endphp
                <div wire:click="markRead({{ $msg->id }})"
                     class="fx rounded-2xl border p-4 shadow-sm cursor-default {{ $mine ? 'bg-indigo-50/60 dark:bg-indigo-500/[0.06] border-indigo-100 dark:border-indigo-500/20 ml-8' : 'bg-white dark:bg-[#1d1e2a] border-gray-100 dark:border-white/[0.05] mr-8' }}">
                    <div class="flex items-center gap-2.5 mb-1.5">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold shrink-0" style="background:linear-gradient(135deg,var(--primary),var(--primary-2))">
                            {{ \Illuminate\Support\Str::of($msg->sender->name ?? '?')->substr(0,2)->upper() }}
                        </span>
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $msg->sender->name ?? 'Unknown' }}{{ $mine ? ' (you)' : '' }}</span>
                        <span class="text-[11px] text-gray-400">{{ is_null($msg->recipient_id) ? 'to team' : 'to '.($msg->recipient->name ?? '—') }}</span>
                        <span class="text-[11px] text-gray-400 ml-auto">{{ $msg->created_at->diffForHumans() }}</span>
                        <button wire:click.stop="deleteMessage({{ $msg->id }})" data-confirm="Delete this message?"
                                class="shrink-0 p-1 rounded-lg text-gray-300 dark:text-gray-600 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Delete message">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-line pl-9">{{ $msg->body }}</p>
                </div>
            @empty
                <div class="rounded-2xl border border-gray-100 dark:border-white/[0.05] p-12 text-center text-sm text-gray-400">No messages yet — start the conversation.</div>
            @endforelse
        </div>
    </section>
</div>
