<div>
@if ($show)
    <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

        <div class="relative w-full max-w-md bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-2xl border border-gray-100 dark:border-white/[0.08] overflow-hidden">
            {{-- Header --}}
            <div class="px-6 pt-6 pb-4" style="background:linear-gradient(120deg,#4f46e5,#7c3aed);">
                <p class="text-[12px] font-semibold uppercase tracking-[.12em] text-white/70">Welcome to Olux</p>
                <h2 class="mt-1 text-2xl font-extrabold text-white">Hi {{ $firstName ?: 'there' }} 👋</h2>
                <p class="mt-1 text-sm text-white/80">Two quick questions so we can tailor your setup — or skip and dive in.</p>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Your role</label>
                    <select wire:model="role" class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                        <option value="">Choose…</option>
                        @foreach ($roles as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">What do you want to do first?</label>
                    <select wire:model="goal" class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                        <option value="">Choose…</option>
                        @foreach ($goals as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <button wire:click="skip" class="text-sm font-medium text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">Skip for now</button>
                    <button wire:click="start" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors shadow-sm">Let's go →</button>
                </div>
            </div>
        </div>
    </div>
@endif
</div>
