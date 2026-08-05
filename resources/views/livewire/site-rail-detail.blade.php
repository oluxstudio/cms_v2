@php $item = $this->item; $levelTint = ['success'=>'#16a34a','error'=>'#dc2626','warning'=>'#d97706','info'=>'#2563eb']; @endphp
<div class="max-w-2xl mx-auto p-6">

    @if (! $item)
        <div class="rounded-2xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a] p-10 text-center text-sm text-gray-400">
            Select an item from the right panel to view it here.
        </div>
    @elseif ($type === 'alert')
        <span class="text-[11px] font-semibold uppercase tracking-wide" style="color:{{ $levelTint[$item->level] ?? '#2563eb' }}">{{ str_replace('_',' ',$item->type) }}</span>
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $item->title }}</h1>
        <p class="text-xs text-gray-400 mt-1">{{ $item->created_at->format('M j, Y · g:i A') }}</p>
        @if ($item->body)<p class="text-sm text-gray-600 dark:text-gray-300 mt-4 leading-relaxed">{{ $item->body }}</p>@endif
        @if ($item->link)<a href="{{ $item->link }}" class="inline-block mt-4 text-sm font-semibold text-indigo-600 hover:underline">Go to related item →</a>@endif

    @elseif ($type === 'message')
        <div class="flex items-center gap-3 mb-4">
            <span class="w-12 h-12 rounded-full flex items-center justify-center text-sm font-bold text-white" style="background:linear-gradient(135deg,var(--primary),var(--primary-2))">
                {{ \Illuminate\Support\Str::of($item->sender->name ?? '?')->substr(0,2)->upper() }}
            </span>
            <div>
                <p class="text-base font-bold text-gray-900 dark:text-white">{{ $item->sender->name ?? 'Unknown' }}</p>
                <p class="text-xs text-gray-400">{{ $item->recipient_id ? 'to '.($item->recipient->name ?? 'you') : 'to the team' }} · {{ $item->created_at->diffForHumans() }}</p>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a] p-5 text-sm text-gray-700 dark:text-gray-200 leading-relaxed whitespace-pre-line">{{ $item->body }}</div>

    @elseif ($type === 'todo')
        @php $done = $item->items->where('done',true)->count(); $tot = $item->items->count(); $pct = $tot ? round($done/$tot*100) : ($item->status==='done'?100:0); @endphp
        <span class="text-[11px] font-semibold uppercase tracking-wide {{ $item->status==='done' ? 'text-emerald-600' : 'text-amber-600' }}">{{ $item->status }}</span>
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1 {{ $item->status==='done' ? 'line-through text-gray-400' : '' }}">{{ $item->title }}</h1>
        <p class="text-xs text-gray-400 mt-1">
            by {{ $item->creator->name ?? '—' }}{{ $item->assignee ? ' · assigned to '.$item->assignee->name : '' }}{{ $item->due_at ? ' · due '.$item->due_at->format('M j') : '' }}
        </p>
        @if ($item->description)<p class="text-sm text-gray-600 dark:text-gray-300 mt-4">{{ $item->description }}</p>@endif

        @if ($tot)
            <div class="mt-5">
                <div class="flex items-center justify-between text-xs text-gray-400 mb-1.5"><span>Checklist</span><span>{{ $done }}/{{ $tot }} · {{ $pct }}%</span></div>
                <div class="h-2 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden"><div class="h-full rounded-full bg-emerald-500" style="width:{{ $pct }}%"></div></div>
                <div class="mt-3 space-y-2">
                    @foreach ($item->items as $sub)
                        <div class="flex items-center gap-2.5 text-sm">
                            <span class="w-4 h-4 rounded border flex items-center justify-center shrink-0 {{ $sub->done ? 'bg-emerald-500 border-emerald-500' : 'border-gray-300 dark:border-gray-600' }}">
                                @if ($sub->done)<svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>@endif
                            </span>
                            <span class="text-gray-700 dark:text-gray-200 {{ $sub->done ? 'line-through text-gray-400' : '' }}">{{ $sub->label }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 mt-3">Tick items off in the Todos panel on the right.</p>
            </div>
        @endif
    @endif
</div>
