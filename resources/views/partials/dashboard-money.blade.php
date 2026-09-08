{{-- Money owed — collectible invoices. --}}
@if ($site->hasFeature('invoices') && $moneyOwed)
<div class="rounded-3xl p-4 shadow-sm bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05]">
    <div class="flex items-center justify-between mb-2.5">
        <span class="text-sm font-bold text-gray-900 dark:text-white">Money owed</span>
        <span class="text-xs font-extrabold text-gray-900 dark:text-white">£{{ number_format($outstandingCents / 100, 2) }}</span>
    </div>
    <div class="space-y-1.5">
        @foreach ($moneyOwed as $i)
            <a href="{{ url($site->name.'/invoices/'.$i['id']) }}" class="flex items-center gap-2 text-xs rounded-lg px-1 py-1 hover:bg-gray-50 dark:hover:bg-white/[0.04]">
                <span class="font-mono text-gray-400 shrink-0">{{ $i['number'] }}</span>
                <span class="flex-1 truncate text-gray-700 dark:text-gray-200">{{ $i['customer'] }}</span>
                <span class="font-bold {{ $i['overdue'] ? 'text-rose-500' : 'text-gray-700 dark:text-gray-200' }}">£{{ number_format($i['total_cents'] / 100, 2) }}</span>
                @if ($i['due'])<span class="text-[10px] {{ $i['overdue'] ? 'text-rose-400' : 'text-gray-400' }} shrink-0">{{ $i['overdue'] ? 'overdue' : 'due '.$i['due'] }}</span>@endif
            </a>
        @endforeach
    </div>
</div>
@endif
