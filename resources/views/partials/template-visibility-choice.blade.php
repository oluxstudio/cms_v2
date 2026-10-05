{{-- Public / Private choice for a new template (all Add template modes). --}}
<div>
    <span class="bkf-label">Who can use it</span>
    <div class="grid grid-cols-2 gap-2">
        @foreach (['public' => ['Public', 'In the store once published'], 'private' => ['Private', 'Only accounts you assign']] as $vk => [$vl, $vd])
            <label class="flex items-start gap-2 rounded-xl border px-3 py-2.5 cursor-pointer {{ $newVisibility === $vk ? '' : 'border-gray-200 dark:border-white/[0.1]' }}"
                   @if ($newVisibility === $vk) style="border-color:var(--primary);background:color-mix(in srgb, var(--primary) 8%, transparent)" @endif>
                <input type="radio" wire:model.live="newVisibility" value="{{ $vk }}" class="mt-0.5 accent-[var(--primary)]">
                <span><span class="block text-[13px] font-bold text-gray-900 dark:text-white">{{ $vl }}</span><span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $vd }}</span></span>
            </label>
        @endforeach
    </div>
</div>
