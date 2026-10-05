{{-- A page / form parked because it belongs to a template that isn't the site's
     current one (never deleted — it comes back when that template is used again).
     Vars: $item (Page|Form with template_keys/template_active), $action (Livewire method), $noun. --}}
@if ($item->template_active === false)
    @php
        $from = collect((array) $item->template_keys)->reject(fn ($k) => $k === \App\Services\TemplateInstaller::OWNER_KEEP)
            ->map(fn ($k) => \App\Services\TemplateInstaller::templateName($k))->implode(', ');
    @endphp
    <span class="inline-flex flex-wrap items-center gap-1.5 mt-1.5" x-on:click.stop>
        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400"
              title="Not on the live site — it belongs to another template. It comes back if you use that template again.">
            Inactive — from {{ $from ?: 'another template' }}
        </span>
        <button type="button" wire:click.stop="{{ $action }}('{{ $item->id }}')"
                data-confirm="Activate this {{ $noun }}? It will stay on the site whichever template you use."
                class="text-[11px] font-bold px-2 py-0.5 rounded-full border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:border-gray-400">
            Activate
        </button>
    </span>
@endif
