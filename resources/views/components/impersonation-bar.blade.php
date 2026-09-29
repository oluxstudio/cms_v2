{{-- Shown on every page while a super admin is viewing as a client. Can't be dismissed. --}}
@if (auth()->check() && \App\Services\Impersonation::active())
    <div role="status" class="fixed left-3 z-[80] max-w-[calc(100vw-1.5rem)] flex flex-wrap items-center gap-x-3 gap-y-1 pl-4 pr-2 py-2 rounded-2xl text-[13px] font-semibold text-white shadow-xl ring-4 ring-white/40"
         style="background:#be123c; bottom: calc(env(safe-area-inset-bottom, 0px) + 12px)">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
        <span>Viewing as <b>{{ auth()->user()->name }}</b> ({{ auth()->user()->email }}) · {{ \App\Services\Impersonation::minutesLeft() }} min left · changes are real</span>
        <form method="POST" action="{{ route('impersonate.stop') }}">
            @csrf
            <button type="submit" class="px-3 py-1 rounded-full bg-white text-[12.5px] font-bold" style="color:#be123c">Stop viewing</button>
        </form>
    </div>
@endif
