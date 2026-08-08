<div class="h-full flex flex-col" x-data="{ tab: @entangle('tab'), device: 'desktop', polux: false }">

    {{-- ══════════ Top bar (Blockframe style): Build | Layout | Preview · devices · undo/redo · export ══════════ --}}
    <div class="shrink-0 flex flex-wrap items-center gap-3 px-4 py-2 min-h-[52px] bg-[#14161f] border-b border-[#333849]">

        {{-- View tabs --}}
        <div class="flex items-center gap-0.5 p-[3px] rounded-[9px] bg-[#1c1f2b] border border-[#333849]">
            @foreach(['build' => 'Build', 'layouts' => 'Layout', 'components' => 'Components', 'preview' => 'Preview'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-[#4c7dff] text-white' : 'text-[#9aa0b4] hover:text-white'"
                        class="px-3.5 py-1.5 rounded-md text-[12.5px] font-semibold transition-colors">{{ $label }}</button>
            @endforeach
        </div>

        {{-- Page picker + quiet links to management screens --}}
        @if($editingLayout)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-[#4c7dff] text-white">▣ {{ $editingLayout->name }}</span>
            <button wire:click="stopEditingLayout" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-[#9aa0b4] hover:text-white border border-[#333849]">Done</button>
        @else
            <select wire:change="selectPage($event.target.value)" title="Page"
                    class="text-xs font-semibold rounded-lg bg-[#1c1f2b] border-[#333849] text-[#e8eaf2] pl-2.5 pr-7 py-1.5">
                @forelse($pages as $p)
                    <option value="{{ $p->id }}" @selected($page && $page->id === $p->id)>{{ $p->name }}</option>
                @empty
                    <option value="">No pages yet</option>
                @endforelse
            </select>
            <button type="button" @click="tab = 'pages'" :class="tab === 'pages' ? 'text-white' : ''"
                    class="text-[11px] font-semibold text-[#9aa0b4] hover:text-white">Pages</button>
            <button type="button" @click="tab = 'theme'" :class="tab === 'theme' ? 'text-white' : ''"
                    class="text-[11px] font-semibold text-[#9aa0b4] hover:text-white">Theme</button>
            <button type="button" @click="tab = 'templates'" :class="tab === 'templates' ? 'text-white' : ''"
                    class="text-[11px] font-semibold text-[#9aa0b4] hover:text-white">Templates</button>
        @endif

        <div class="ml-auto flex items-center gap-2">
            {{-- Devices --}}
            <div class="flex items-center gap-0.5 p-[3px] rounded-[9px] bg-[#1c1f2b] border border-[#333849]">
                @foreach(['desktop' => ['Desktop', 'M3 4h18v13H3z M8 21h8M12 17v4'], 'tablet' => ['Tablet', 'M5 2h14v20H5z M11 18h2'], 'mobile' => ['Mobile', 'M7 2h10v20H7z M11 18h2']] as $dev => [$lbl, $path])
                    <button type="button" @click="device = '{{ $dev }}'" title="{{ $lbl }}"
                            :class="device === '{{ $dev }}' ? 'bg-[#262a3a] text-[#e8eaf2]' : 'text-[#9aa0b4] hover:text-[#e8eaf2]'"
                            class="flex items-center gap-1.5 px-2.5 py-[5px] rounded-md text-xs font-semibold transition-colors">
                        <svg class="w-[15px] h-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="{{ $path }}"/></svg>
                        {{ $lbl }}
                    </button>
                @endforeach
            </div>

            {{-- Undo / Redo --}}
            <div x-data @keydown.window.ctrl.z.prevent="$event.shiftKey ? $wire.redo() : $wire.undo()" class="flex items-center gap-2">
                <button wire:click="undo" @disabled(! $canUndo)
                        class="px-3 py-[7px] rounded-lg bg-[#262a3a] border border-[#333849] text-xs font-semibold text-[#e8eaf2] hover:border-[#4c7dff] disabled:opacity-40 transition-colors"
                        title="{{ $canUndo ? 'Undo: '.$canUndo : 'Nothing to undo' }} (Ctrl+Z)">↩ Undo</button>
                <button wire:click="redo" @disabled(! $canRedo)
                        class="px-3 py-[7px] rounded-lg bg-[#262a3a] border border-[#333849] text-xs font-semibold text-[#e8eaf2] hover:border-[#4c7dff] disabled:opacity-40 transition-colors"
                        title="{{ $canRedo ? 'Redo: '.$canRedo : 'Nothing to redo' }} (Ctrl+Shift+Z)">↪ Redo</button>
            </div>

            {{-- Export Site: split button — PUBLISH (default) pushes the standalone
                 Nuxt 4 app to olux-studio/templates; Export downloads it as a zip. --}}
            <div class="relative flex" x-data="{ exportOpen: false }" @click.outside="exportOpen = false">
                <button wire:click="publishSite" wire:loading.attr="disabled" wire:target="publishSite"
                        class="px-3.5 py-[7px] rounded-l-lg bg-[#4c7dff] hover:bg-[#3a68e8] disabled:opacity-60 text-white text-xs font-bold transition-colors"
                        title="Publish: generate the standalone Nuxt 4 app and push it to olux-studio/templates">
                    <span wire:loading.remove wire:target="publishSite">Export Site</span>
                    <span wire:loading wire:target="publishSite">Publishing…</span>
                </button>
                <button type="button" @click="exportOpen = !exportOpen"
                        class="px-1.5 py-[7px] rounded-r-lg bg-[#3a68e8] hover:bg-[#2f57c9] text-white text-xs font-bold border-l border-white/20"
                        title="More export options">▾</button>
                <div x-show="exportOpen" x-cloak
                     class="absolute right-0 top-full mt-1 w-56 rounded-xl border border-[#333849] bg-[#20232f] shadow-2xl z-50 p-1">
                    <button wire:click="publishSite" @click="exportOpen = false"
                            class="w-full text-left px-3 py-2 rounded-lg text-xs text-white hover:bg-white/10">
                        <span class="font-bold">Publish</span>
                        <span class="block text-[10px] text-gray-400">Push the Nuxt 4 app to olux-studio/templates</span>
                    </button>
                    <button wire:click="exportSiteZip" @click="exportOpen = false"
                            class="w-full text-left px-3 py-2 rounded-lg text-xs text-white hover:bg-white/10">
                        <span class="font-bold">Export site</span>
                        <span class="block text-[10px] text-gray-400">Download the Nuxt 4 app as a zip</span>
                    </button>
                </div>
            </div>

            {{-- Polux --}}
            <button @click="polux = !polux"
                    :class="polux ? 'bg-white text-gray-900 border-transparent' : 'border-[#333849] text-[#9aa0b4] hover:text-white'"
                    class="flex items-center gap-1.5 px-2.5 py-[7px] rounded-lg text-xs font-semibold border transition-colors" title="AI assistant">
                <span class="w-4 h-4 rounded bg-[#4c7dff] text-white text-[10px] font-black flex items-center justify-center">P</span>
                Polux
            </button>
        </div>
    </div>

    {{-- ══════════ Bodies per tab ══════════ --}}
    <div class="flex-1 overflow-hidden flex">

        {{-- ── PAGES: light CRUD + selected page details ── --}}
        <template x-if="tab === 'pages'"><div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-2xl mx-auto space-y-5">
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Pages</h2>
                    <p class="text-[11px] text-gray-400 mt-0.5">Every page is a block canvas. Pick one to build, or add a new one.</p>
                </div>
                <div class="space-y-1.5">
                    @foreach($pages as $p)
                        <div class="flex items-center gap-2 rounded-xl border px-3 py-2 transition-colors
                            {{ $page && $page->id === $p->id ? 'bg-indigo-50 dark:bg-indigo-500/10 border-indigo-200 dark:border-indigo-500/30' : 'bg-white dark:bg-white/[0.02] border-gray-200 dark:border-white/[0.06]' }}">
                            <button wire:click="selectPage('{{ $p->id }}')" class="flex-1 text-left min-w-0">
                                <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $p->name }}</span>
                                <span class="block text-[11px] text-gray-400 font-mono">{{ $p->url }}</span>
                            </button>
                            <button wire:click="selectPage('{{ $p->id }}')" @click="tab = 'build'"
                                    class="shrink-0 px-2.5 py-1 rounded-lg text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">Build →</button>
                            <span class="shrink-0 w-1.5 h-1.5 rounded-full {{ $p->is_published ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600' }}" title="{{ $p->is_published ? 'Published' : 'Draft' }}"></span>
                            <button wire:click="deletePage('{{ $p->id }}')" data-confirm="Delete this page and all its blocks?"
                                    class="shrink-0 p-1 rounded-lg text-gray-300 dark:text-gray-600 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Delete page">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex-1"><x-field.text model="newPageName" placeholder="New page name…" wire:keydown.enter="addPage" /></div>
                    <button wire:click="addPage" class="px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Add page</button>
                </div>

                @if($page)
                    <div class="bg-white dark:bg-[#1e1f2b] rounded-2xl border border-gray-200 dark:border-white/[0.06] p-5 space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">“{{ $page->name }}” details</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-field.text label="Name" model="pageName" />
                            <x-field.text label="URL" model="pageUrl" mono />
                            <x-field.wrapper class="sm:col-span-2" label="Layout — the page skeleton this page renders inside">
                                <select x-on:change="$wire.setPageBlockLayout($event.target.value === '' ? null : parseInt($event.target.value))" class="bkf-input">
                                    @foreach($blockLayouts as $bl)
                                        <option value="{{ $bl->is_system ? '' : $bl->id }}" @selected($pageLayout && $pageLayout->id === $bl->id)>
                                            {{ $bl->name }}{{ $bl->is_system ? ' (default)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </x-field.wrapper>
                        </div>
                        <x-field.textarea label="Page script" model="pageScript" rows="4" class="bkf-mono"
                                          placeholder="// JavaScript that runs when this page loads on the live site"
                                          hint="Runs on the live site and in exports — never inside the editor." />
                        <div class="flex items-center justify-between">
                            <x-field.check model="pagePublished" text="Published" />
                            <button wire:click="savePage" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">Save page</button>
                        </div>
                        <p class="text-[11px] text-gray-400">SEO keywords &amp; custom attributes live on the <a href="{{ url($site->name.'/pages') }}" class="text-indigo-500 hover:underline">Pages</a> screen.</p>
                    </div>
                @endif
            </div>
        </div></template>

        {{-- ── LAYOUT VIEW: reusable structural templates around ONE content section ── --}}
        <template x-if="tab === 'layouts'"><div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-2xl mx-auto space-y-4">
                <div class="bg-white dark:bg-[#1e1f2b] rounded-2xl border border-gray-200 dark:border-white/[0.06] p-5">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Create a layout</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5 mb-3">
                        A layout is a reusable structural template: blocks arranged around one
                        <strong>Content section</strong> where each page's own blocks render.
                        You design it with the same canvas pages use. <strong>Blank</strong> is the
                        built-in default — just the content section, nothing around it.
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex-1 min-w-[160px]"><x-field.text model="newLayoutName" placeholder="Layout name…" wire:keydown.enter="createBlockLayout" /></div>
                        <button wire:click="createBlockLayout" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Create &amp; design</button>
                    </div>
                </div>

                <div class="space-y-2">
                    @foreach($blockLayouts as $bl)
                        <div class="bg-white dark:bg-[#1e1f2b] rounded-2xl border border-gray-200 dark:border-white/[0.06] p-4">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $bl->name }}</p>
                                @if($bl->is_system)
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide bg-gray-100 dark:bg-white/[0.08] text-gray-500 dark:text-gray-300" title="Built in — cannot be edited or deleted">system</span>
                                @endif
                                <span class="text-[11px] text-gray-400">{{ $bl->pages_count }} {{ Str::plural('page', $bl->pages_count) }}</span>
                                <span class="text-[11px] text-gray-400">· {{ max(0, $bl->blocks()->count() - 2) }} layout {{ Str::plural('block', max(0, $bl->blocks()->count() - 2)) }}</span>
                                @unless($bl->is_system)
                                    <button wire:click="editLayout('{{ $bl->id }}')"
                                            class="ml-auto px-2.5 py-1 rounded-lg text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30 hover:bg-indigo-50 dark:hover:bg-indigo-500/10"
                                            title="Open this layout in the canvas — full editor, same blocks as pages">
                                        Edit layout
                                    </button>
                                    <button wire:click="deleteBlockLayout('{{ $bl->id }}')"
                                            data-confirm="Delete “{{ $bl->name }}”? {{ $bl->pages_count ? $bl->pages_count.' page(s) will fall back to Blank — their content is kept.' : '' }}"
                                            class="p-1 rounded-lg text-gray-300 dark:text-gray-600 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Delete layout">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                @endunless
                            </div>
                            @if($bl->is_system)
                                <div class="mt-2.5 rounded-lg border border-dashed border-gray-200 dark:border-white/[0.08] py-3 text-center">
                                    <p class="text-[10px] text-gray-400">blank — the content section and nothing else; pages provide all blocks</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div></template>

        {{-- ── COMPONENTS: user-built reusable blocks made of default blocks ── --}}
        <template x-if="tab === 'components'"><div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-2xl mx-auto space-y-4">
                <div class="bg-white dark:bg-[#1e1f2b] rounded-2xl border border-gray-200 dark:border-white/[0.06] p-5">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Create a component</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5 mb-3">
                        A component is <strong>your own block</strong>, built from the default blocks —
                        a hero section, a pricing row, a footer… Design it once on its own canvas and it
                        joins the block palette: drop it into any page, as many times as you like.
                        Placed copies are independent — editing the component changes future stamps, not past ones.
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex-1 min-w-[160px]"><x-field.text model="newComponentName" placeholder="Component name (e.g. Hero section)…" wire:keydown.enter="createComponent" /></div>
                        <button wire:click="createComponent" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Create &amp; design</button>
                    </div>
                </div>

                <div class="space-y-2">
                    @forelse($components as $comp)
                        <div class="bg-white dark:bg-[#1e1f2b] rounded-2xl border border-gray-200 dark:border-white/[0.06] p-4">
                            <div class="flex items-center gap-2">
                                <span class="text-[15px]">⚙</span>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $comp->name }}</p>
                                <span class="text-[11px] text-gray-400">{{ max(0, $comp->blocks_count - 1) }} {{ Str::plural('block', max(0, $comp->blocks_count - 1)) }}</span>
                                <button wire:click="editLayout('{{ $comp->id }}')"
                                        class="ml-auto px-2.5 py-1 rounded-lg text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30 hover:bg-indigo-50 dark:hover:bg-indigo-500/10"
                                        title="Open this component in the canvas — same editor pages use">
                                    Edit component
                                </button>
                                <button wire:click="deleteComponent('{{ $comp->id }}')"
                                        data-confirm="Delete “{{ $comp->name }}”? Copies already placed on pages are kept."
                                        class="p-1 rounded-lg text-gray-300 dark:text-gray-600 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Delete component">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 text-center py-4">No components yet — build your first one above.</p>
                    @endforelse
                </div>
            </div>
        </div></template>

        {{-- ── THEME: site-wide look ── --}}
        <template x-if="tab === 'theme'"><div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-2xl mx-auto bg-white dark:bg-[#1e1f2b] rounded-2xl border border-gray-200 dark:border-white/[0.06] p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Site theme</h3>
                        <p class="text-[11px] text-gray-400">Fonts &amp; colours applied across every page — blocks inherit these tokens.</p>
                    </div>
                    <button wire:click="saveTheme" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">Save theme</button>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div class="col-span-2 sm:col-span-1">
                        <x-field.select label="Font" model="theme.font" :empty="null"
                                        :options="['Inter', 'Poppins', 'Roboto', 'Montserrat', 'Georgia', 'system-ui']" />
                    </div>
                    @foreach(['accent' => 'Accent', 'navy' => 'Header / footer', 'text' => 'Text', 'surface' => 'Surface'] as $k => $lbl)
                        <x-field.color :label="$lbl" model="theme.{{ $k }}" :value="(string) ($theme[$k] ?? '')" placeholder="#hex" />
                    @endforeach
                    <x-field.select label="Corner radius" model="theme.radius" :empty="null"
                                    :options="['0px' => 'Square', '8px' => 'Soft', '12px' => 'Rounded', '20px' => 'Pill']" />
                    <x-field.select label="Base size" model="theme.base_size" :empty="null"
                                    :options="['15px' => 'Compact', '16px' => 'Default', '18px' => 'Large']" />
                </div>

                {{-- ── Theme variables: define once, use in any block as $name ── --}}
                <div class="pt-4 border-t border-gray-100 dark:border-white/[0.05]">
                    <div class="flex items-center justify-between mb-1">
                        <h4 class="text-xs font-bold text-gray-900 dark:text-white">Theme variables</h4>
                        <button wire:click="addThemeVariable" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">＋ Add variable</button>
                    </div>
                    <p class="text-[11px] text-gray-400 mb-3">
                        Colours, sizes, fonts — anything CSS. Reference one in any block field as
                        <code class="font-mono text-indigo-500">$name</code>: e.g. background
                        <code class="font-mono text-indigo-500">$primary</code>, width
                        <code class="font-mono text-indigo-500">$content-width</code>. Change it here → every block using it updates.
                    </p>
                    <div class="space-y-1.5">
                        @foreach((array) ($theme['variables'] ?? []) as $i => $var)
                            <div class="flex items-center gap-1.5" wire:key="themevar-{{ $i }}">
                                <span class="text-xs text-gray-400 font-mono">$</span>
                                <div class="w-36"><x-field.text model="theme.variables.{{ $i }}.name" placeholder="primary" mono /></div>
                                <div class="flex-1 min-w-0">
                                    <x-field.color model="theme.variables.{{ $i }}.value" :value="(string) ($var['value'] ?? '')"
                                                   placeholder="#4f46e5 · 64px · 'Poppins', sans-serif" />
                                </div>
                                <div class="shrink-0 w-20">
                                    <x-field.select model="theme.variables.{{ $i }}.type" empty="auto"
                                                    :options="['color' => 'color', 'size' => 'size', 'font' => 'font', 'other' => 'any']"
                                                    title="Where this variable is offered — auto-detected from the value on save" />
                                </div>
                                {{-- swatch preview now lives inside the colour field itself --}}
                                <button wire:click="removeThemeVariable({{ $i }})" data-confirm="Remove this theme variable? Blocks using it keep the raw $name text." class="shrink-0 w-6 h-6 rounded text-gray-300 hover:text-rose-500" title="Remove">✕</button>
                            </div>
                        @endforeach
                        @if(empty($theme['variables']))
                            <p class="text-[11px] text-gray-400 italic">No variables yet — add one, save the theme, then use it in blocks as $name.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div></template>

        {{-- ── TEMPLATES: saved builds (content + layout + theme), capped by plan ── --}}
        <template x-if="tab === 'templates'"><div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-2xl mx-auto space-y-4">
                <div class="bg-white dark:bg-[#1e1f2b] rounded-2xl border border-gray-200 dark:border-white/[0.06] p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">My templates</h3>
                        <span class="text-[11px] font-semibold {{ $builderTemplates->count() >= $templateLimit ? 'text-amber-500' : 'text-gray-400' }}">
                            {{ $builderTemplates->count() }} of {{ $templateLimit }} slot{{ $templateLimit === 1 ? '' : 's' }} used
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-0.5 mb-3">
                        A template is your whole build — the page's blocks, its layout and the theme — saved together.
                        Apply one anytime and keep modifying it to your taste. Your plan includes
                        {{ $templateLimit === 1 ? 'one free template' : $templateLimit.' templates' }}.
                    </p>
                    <div class="flex flex-wrap items-center gap-2" x-data="{ name: '' }">
                        <div class="flex-1 min-w-[160px]">
                            <x-field.text placeholder="Template name…" x-model="name"
                                          @keydown.enter="if (name.trim()) { $wire.saveTemplate(name); name = '' }" />
                        </div>
                        <button @click="if (name.trim()) { $wire.saveTemplate(name); name = '' }"
                                @if($builderTemplates->count() >= $templateLimit) disabled title="Template limit reached — update an existing one or upgrade" @endif
                                class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-semibold">
                            Save current build
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    @forelse($builderTemplates as $tpl)
                        <div class="bg-white dark:bg-[#1e1f2b] rounded-2xl border border-gray-200 dark:border-white/[0.06] p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $tpl->name }}</p>
                                @if($tpl->is_default)
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">default · free</span>
                                @endif
                                <span class="text-[11px] text-gray-400">
                                    {{ count($tpl->payload['content'] ?? []) }} content block{{ count($tpl->payload['content'] ?? []) === 1 ? '' : 's' }}
                                    · {{ ($tpl->payload['layout_name'] ?? null) ? 'layout “'.$tpl->payload['layout_name'].'”' : 'Blank layout' }}
                                    · saved {{ $tpl->updated_at->diffForHumans() }}
                                </span>
                                <span class="ml-auto flex items-center gap-1.5">
                                    <button wire:click="applyTemplate('{{ $tpl->id }}')" @click="tab = 'build'"
                                            data-confirm="Load “{{ $tpl->name }}” into “{{ $page?->name }}”? The page's current blocks are replaced (Ctrl+Z undoes the content)."
                                            class="px-2.5 py-1 rounded-lg text-[11px] font-semibold text-white bg-indigo-600 hover:bg-indigo-700">Apply &amp; edit</button>
                                    <button wire:click="updateTemplate('{{ $tpl->id }}')"
                                            data-confirm="Overwrite “{{ $tpl->name }}” with the current build?"
                                            class="px-2.5 py-1 rounded-lg text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">Update</button>
                                    @unless($tpl->is_default)
                                        <button wire:click="setDefaultTemplate('{{ $tpl->id }}')" title="Make this the default template"
                                                class="px-2.5 py-1 rounded-lg text-[11px] font-semibold text-gray-500 border border-gray-200 dark:border-white/[0.08] hover:bg-gray-50 dark:hover:bg-white/[0.05]">Set default</button>
                                    @endunless
                                    <button wire:click="deleteTemplate('{{ $tpl->id }}')" data-confirm="Delete template “{{ $tpl->name }}”? Pages already built from it keep their blocks."
                                            class="p-1 rounded-lg text-gray-300 dark:text-gray-600 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Delete template">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 text-center py-6">No templates yet — build something, then hit <strong>Save current build</strong>.</p>
                    @endforelse
                </div>
            </div>
        </div></template>

        {{-- ── PREVIEW: the real rendered page, sized by the device toggle ── --}}
        <template x-if="tab === 'preview'"><div class="flex-1 overflow-auto bkw-stage">
            @if($previewUrl)
                <div class="mx-auto h-full transition-all duration-300"
                     :style="'width:' + (device === 'mobile' ? '390px' : (device === 'tablet' ? '768px' : '100%'))">
                    <div class="flex justify-between px-2 pt-2 mb-1.5 font-mono text-[10px] tracking-[.12em] uppercase text-gray-400">
                        <span>Preview · {{ $page?->name ?? '' }}</span>
                        <span x-text="device === 'mobile' ? '390px' : (device === 'tablet' ? '768px' : '100%')"></span>
                    </div>
                    <div class="bkw-artboard bg-white rounded-md shadow-2xl" style="height:calc(100% - 22px)" wire:key="pv-{{ $page?->id ?? 0 }}">
                        <iframe src="{{ $previewUrl }}" title="Page preview" class="w-full h-full min-h-[640px]" style="border:0"></iframe>
                    </div>
                </div>
            @else
                <div class="h-full flex items-center justify-center text-sm text-gray-400">Renderer not built — run <code class="mx-1 font-mono text-xs">nuxt:preview-build</code>.</div>
            @endif
        </div></template>

        {{-- ── BUILD: blocks left · live canvas center · inspector right · Polux drawer ── --}}
        <div x-show="tab === 'build'" class="flex-1 overflow-hidden flex">

            {{-- Left rail: palette + layers --}}
            <aside class="w-72 shrink-0 flex flex-col border-r border-gray-200 dark:border-white/[0.06]">
                {{-- Palette: drag a card onto the canvas — or click to insert. --}}
                <div class="flex-[3] min-h-0 overflow-y-auto p-3">
                    @foreach($palette as $groupName => $types)
                        <h2 class="px-1 pt-2 pb-1.5 first:pt-0 font-mono text-[9.5px] font-semibold uppercase tracking-[.14em] text-gray-400">{{ $groupName }}</h2>
                        <div class="grid grid-cols-2 gap-1.5">
                            @foreach($types as $key => $def)
                                @continue(! $def || ($key === 'content_slot'))
                                {{-- Slot/Prop placeholders only make sense while designing a component --}}
                                @continue(in_array($key, ['slot', 'prop'], true) && ! $editingLayout?->isComponent())
                                <div draggable="true" data-bkw-palette="{{ $key }}" wire:click="insertBlock('{{ $key }}')"
                                     class="bkw-pblock group rounded-lg border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-white/[0.04] p-2 cursor-grab active:cursor-grabbing select-none hover:border-indigo-400 hover:-translate-y-px transition-all"
                                     title="{{ $def['description'] ?? '' }}">
                                    <div class="h-7 rounded bg-indigo-50 dark:bg-indigo-500/10 grid place-items-center text-[15px] text-indigo-500">{{ $def['icon'] ?? '·' }}</div>
                                    <p class="mt-1.5 text-[11px] font-semibold leading-tight text-gray-800 dark:text-gray-100">{{ $def['name'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                    <h2 class="px-1 pt-4 pb-1.5 font-mono text-[9.5px] font-semibold uppercase tracking-[.14em] text-gray-400">My components</h2>
                    @if($components->isEmpty())
                        <p class="text-[10px] text-gray-400 border border-dashed border-gray-200 dark:border-white/[0.1] rounded-lg p-2.5 leading-relaxed">
                            Build your own blocks from the default ones in the <strong>Components</strong> tab — they appear here to drop into any page.
                        </p>
                    @else
                        <div class="grid grid-cols-2 gap-1.5">
                            @foreach($components as $comp)
                                <div draggable="true" data-bkw-component="{{ $comp->id }}" wire:click="insertComponent('{{ $comp->id }}')"
                                     class="bkw-pblock group rounded-lg border border-emerald-200 dark:border-emerald-400/30 bg-white dark:bg-white/[0.04] p-2 cursor-grab active:cursor-grabbing select-none hover:border-emerald-400 hover:-translate-y-px transition-all"
                                     title="{{ $comp->name }} — your component; drag onto the canvas or click to insert">
                                    <div class="h-7 rounded bg-emerald-50 dark:bg-emerald-400/10 grid place-items-center text-[13px] text-emerald-500">⚙</div>
                                    <p class="mt-1.5 text-[11px] font-semibold leading-tight text-gray-800 dark:text-gray-100 truncate">{{ $comp->name }}</p>
                                    <p class="font-mono text-[9px] text-gray-400">component</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <h2 class="px-1 pt-4 pb-1.5 font-mono text-[9.5px] font-semibold uppercase tracking-[.14em] text-gray-400">Reusable blocks</h2>
                    @if($reusables->isEmpty())
                        <p class="text-[10px] text-gray-400 border border-dashed border-gray-200 dark:border-white/[0.1] rounded-lg p-2.5 leading-relaxed">
                            Select any block on the canvas and press <strong>Save as reusable</strong> — it appears here to drag back in anywhere.
                        </p>
                    @else
                        <div class="grid grid-cols-2 gap-1.5">
                            @foreach($reusables as $r)
                                <div draggable="true" data-bkw-reuse="{{ $r->id }}" wire:click="insertReusableAt('{{ $r->id }}')"
                                     class="bkw-pblock group relative rounded-lg border border-amber-200 dark:border-amber-400/30 bg-white dark:bg-white/[0.04] p-2 cursor-grab active:cursor-grabbing select-none hover:border-amber-400 hover:-translate-y-px transition-all"
                                     title="Reusable {{ $r->root_type }} — drag onto the canvas or click to insert">
                                    <div class="h-7 rounded bg-amber-50 dark:bg-amber-400/10 grid place-items-center text-[13px] text-amber-500">⟳</div>
                                    <p class="mt-1.5 text-[11px] font-semibold leading-tight text-gray-800 dark:text-gray-100 truncate">{{ $r->name }}</p>
                                    <p class="font-mono text-[9px] text-gray-400">{{ $r->root_type }}</p>
                                    <button wire:click.stop="deleteReusable('{{ $r->id }}')" title="Remove from palette"
                                            class="absolute top-1 right-1 hidden group-hover:block text-[10px] text-gray-400 hover:text-rose-500 px-1">✕</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <p class="text-[10px] text-gray-400 mt-3 px-1 leading-relaxed">Drag a block onto the canvas, or click to insert into the selection.</p>
                </div>

                {{-- Layers --}}
                <div class="flex-[2] min-h-0 overflow-y-auto p-3 border-t border-gray-200 dark:border-white/[0.06]">
                    <h2 class="px-1.5 pb-2 text-[10px] font-bold uppercase tracking-wider text-gray-400">Layers</h2>
                    @if($tree)
                        <div wire:key="layers-{{ $page->id }}">
                            @include('partials.blockkit-node', ['node' => $tree, 'selectedId' => $selectedId, 'catalogue' => $catalogue, 'depth' => 0])
                        </div>
                    @else
                        <p class="text-xs text-gray-400 px-1.5">No page selected.</p>
                    @endif
                </div>
            </aside>

            {{-- Canvas: visual block pieces (drag & drop) ⇄ the real rendered page.
                 WHITE surface in both Build and Layout views — the canvas IS the page. --}}
            <main class="flex-1 overflow-hidden bg-white flex flex-col"
                  x-data="{ canvas: 'blocks' }">
                <div class="shrink-0 px-4 pt-3 flex items-center gap-2">
                    @unless($editingLayout)
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Layout</label>
                        <select x-on:change="$wire.setPageBlockLayout($event.target.value === '' ? null : parseInt($event.target.value))"
                                class="text-sm rounded-xl bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 px-3 py-1.5"
                                title="The layout this page renders inside — its blocks wrap your content">
                            @foreach($blockLayouts as $bl)
                                <option value="{{ $bl->is_system ? '' : $bl->id }}" @selected($page && $pageLayout && $pageLayout->id === $bl->id)>{{ $bl->name }}{{ $bl->is_system ? ' (default)' : '' }}</option>
                            @endforeach
                        </select>
                    @endunless
                    <p class="text-[10px] text-gray-400">
                        {{ $editingLayout ? 'Design the layout — blocks here render on every page using it.' : 'Drag pieces to arrange; click one to edit it.' }}
                    </p>
                </div>

                {{-- WYSIWYG canvas: dark blueprint stage · white artboard = the real page --}}
                <div class="flex-1 overflow-auto bkw-stage"
                     @click.self="$wire.deselect()">
                    @if($tree)
                        <div class="mx-auto">
                            <div class="flex justify-between px-2 pt-2 mb-1.5 font-mono text-[10px] tracking-[.12em] uppercase text-gray-400">
                                <span>{{ $editingLayout ? 'Layout · '.$editingLayout->name : 'Page · '.($page?->name ?? '') }}</span>
                                <span x-text="device === 'mobile' ? '390px' : (device === 'tablet' ? '768px' : '100%')"></span>
                            </div>
                            <div class="bkw-artboard mx-auto transition-all duration-300 bg-white min-h-[640px] text-gray-900"
                                 x-bind:style="'{{ \App\Support\ThemeTokens::rootCss((array) ($theme['variables'] ?? [])) }}--accent:{{ $theme['accent'] ?? '#6366f1' }};--surface:{{ $theme['surface'] ?? '#f8fafc' }};width:' + (device === 'mobile' ? '390px' : (device === 'tablet' ? '768px' : '100%'))"
                                 wire:key="bkw-{{ $editingLayout ? 'lay-'.$editingLayout->id : 'page-'.($page?->id ?? 0) }}">
                                @include('partials.blockkit-wysiwyg-node', ['node' => $tree, 'selectedId' => $selectedId, 'catalogue' => $catalogue, 'depth' => 0])
                            </div>
                        </div>
                        {{-- FOUNDATION breadcrumb: the selected block's ancestor chain —
                             tap any chip to select that wrapper. Works identically on
                             Build / Layout / Component canvases, and on touch screens
                             where hover and precise re-clicking don't exist. --}}
                        @if($selectedPath)
                            <div class="bkw-crumbs" role="navigation" aria-label="Selected block path">
                                @foreach($selectedPath as $crumb)
                                    <button type="button" wire:click="select('{{ $crumb['id'] }}')"
                                            class="bkw-crumb {{ $loop->last ? 'bkw-crumb-on' : '' }}"
                                            title="Select {{ $crumb['label'] }}">
                                        {{ $crumb['label'] }}<span class="bkw-crumb-type">{{ strtolower($crumb['label']) === $crumb['type'] ? '' : $crumb['type'] }}</span>
                                    </button>
                                    @unless($loop->last)<span class="bkw-crumb-sep">›</span>@endunless
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div class="h-full flex items-center justify-center text-sm text-gray-400">No page selected.</div>
                    @endif
                </div>

            </main>

            {{-- Inspector --}}
            <aside class="w-80 shrink-0 overflow-y-auto border-l border-gray-200 dark:border-white/[0.06] p-4">
                @if($selected)
                    <div class="flex items-center gap-2 mb-3">
                        <div class="min-w-0 flex-1">
                            <input wire:model="labelForm" type="text"
                                   class="w-full text-sm font-bold rounded-lg bg-gray-50 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] px-2.5 py-1.5 text-gray-900 dark:text-white">
                            <p class="text-[10px] text-gray-400 mt-0.5 font-mono">{{ $selected->type }}</p>
                        </div>
                        <button wire:click="toggleLock"
                                class="shrink-0 w-8 h-8 rounded-lg border text-sm {{ $selected->isLocked() ? 'bg-amber-100 dark:bg-amber-400/15 border-amber-300 dark:border-amber-400/40' : 'border-gray-200 dark:border-white/[0.08] text-gray-400 hover:text-amber-500' }}"
                                title="{{ $selected->isLocked() ? 'Pinned — the AI cannot modify this block. Click to unpin.' : 'Pin this block — the AI will not touch it.' }}">📌</button>
                    </div>

                    {{-- Kind-aware variable lists: colour vars on colour fields, size vars on size fields. --}}
                    @php
                        $allVars   = (array) ($theme['variables'] ?? []);
                        $colorVars = \App\Support\ThemeTokens::ofType($allVars, 'color');
                        $sizeVars  = \App\Support\ThemeTokens::ofType($allVars, 'size');
                        $fontVars  = \App\Support\ThemeTokens::ofType($allVars, 'font');
                        $varsFor   = fn (string $key) => match (true) {
                            in_array($key, ['background', 'overlay', 'color', 'gradient'], true) => $colorVars,
                            // Size-typed fields suggest ONLY size variables (foundational):
                            // widths/heights incl. the min/max constraints, spacing, bg metrics.
                            in_array($key, ['width', 'height', 'size', 'gap', 'bg_size', 'bg_position',
                                'min_width', 'max_width', 'min_height', 'max_height',
                                'margin', 'padding', 'inset', 'basis'], true) => $sizeVars,
                            $key === 'font' => $fontVars,
                            default => $allVars,
                        };
                        // Suggestions for the filterable value fields: auto (sizing)
                        // + the matching theme variables — user still types anything.
                        $isSizeKey = fn (string $key) => str_contains($key, 'width') || str_contains($key, 'height')
                            || in_array($key, ['size', 'basis', 'margin', 'padding', 'inset', 'gap'], true);
                        $valOpts = function (string $key) use ($varsFor, $isSizeKey) {
                            // '0' as an array key would become int 0 — use 0px.
                            $opts = $isSizeKey($key) ? ['auto' => '', '0px' => '', '100%' => ''] : [];
                            // CSS keyword presets — pickable from the same filterable field.
                            if ($key === 'bg_size') {
                                $opts = ['cover' => '', 'contain' => '', 'auto' => '', '100% 100%' => ''] + $opts;
                            }
                            if ($key === 'bg_position') {
                                $opts = array_fill_keys(['center', 'top', 'left', 'bottom', 'right',
                                    'top left', 'top right', 'center left', 'center right',
                                    'bottom left', 'bottom center', 'bottom right'], '') + $opts;
                            }
                            foreach ($varsFor($key) as $var) $opts['$'.$var['name']] = $var['value'];
                            return $opts;
                        };
                    @endphp
                    @if($sizeVars)
                        <datalist id="bkThemeVars">
                            @foreach($sizeVars as $var)<option value="${{ $var['name'] }}">{{ $var['value'] }}</option>@endforeach
                        </datalist>
                    @endif
                    {{-- FOUNDATION: removal never depends on hover or stacking —
                         the selected block can ALWAYS be deleted/duplicated here. --}}
                    <div class="flex items-center gap-1.5 mb-2">
                        <button wire:click="duplicateBlock('{{ $selected->id }}')"
                                class="flex-1 px-2 py-1.5 rounded-lg text-[11px] font-semibold text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-white/[0.08] hover:border-indigo-400">⧉ Duplicate</button>
                        <button wire:click="deleteBlock('{{ $selected->id }}')" data-confirm="Delete this block?"
                                class="flex-1 px-2 py-1.5 rounded-lg text-[11px] font-semibold text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-500/30 hover:bg-rose-50 dark:hover:bg-rose-500/10">✕ Delete block</button>
                    </div>

                    {{-- ── Device sections (mobile-first): the tab you edit is the frame
                         you preview — one shared state drives inspector AND canvas. ── --}}
                    <div class="flex items-center gap-0.5 p-0.5 mb-2 rounded-xl bg-gray-100 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.06]">
                        @foreach(['mobile' => ['Mobile', 'base'], 'tablet' => ['Tablet', '≥820px'], 'desktop' => ['Desktop', '≥1100px']] as $dev => [$devLabel, $devHint])
                            <button type="button" @click="device = '{{ $dev }}'"
                                    :class="device === '{{ $dev }}' ? 'bg-white dark:bg-white/[0.12] text-indigo-600 dark:text-indigo-300 shadow-sm' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'"
                                    class="flex-1 px-1 py-1 rounded-lg text-[10.5px] font-semibold transition-colors leading-tight">
                                {{ $devLabel }}<span class="block text-[8.5px] font-normal opacity-70">{{ $devHint }}</span>
                            </button>
                        @endforeach
                    </div>
                    <p class="text-[10px] text-gray-400 mb-2" x-show="device !== 'mobile'" x-cloak>
                        Mobile-first: fields marked <span class="font-mono text-indigo-500">◱</span> hold this device's override — empty inherits from the smaller device.
                    </p>

                    {{-- Auto-apply: every change (select, blur, picker) saves the block —
                         the canvas is always the saved truth; Save stays as a manual fallback. --}}
                    <div class="space-y-3" x-data x-on:change.debounce.400ms="$wire.saveInspector()">
                        @php
                            // Minimalist inspector: props grouped into small collapsible
                            // sections — only the first is open, so one screen stays calm.
                            $groupOf = fn (string $key) => match (true) {
                                str_starts_with($key, 'bg_') || in_array($key, ['background', 'gradient', 'overlay', 'overlay_opacity'], true) => 'Background',
                                in_array($key, ['display', 'direction', 'flex_wrap', 'justify_content', 'align_items', 'align_content', 'gap', 'columns', 'max_width', 'padding'], true) => 'Layout',
                                in_array($key, ['width', 'height', 'min_width', 'min_height', 'max_height', 'margin', 'position', 'inset', 'z_index', 'overflow', 'flex_child', 'basis'], true) => 'Size & Position',
                                in_array($key, ['color', 'font', 'size', 'text_gradient', 'align'], true) => 'Text',
                                in_array($key, ['blur', 'backdrop_blur', 'brightness', 'contrast', 'saturate', 'grayscale'], true) => 'Filters',
                                in_array($key, ['opacity', 'radius', 'bordered', 'shadow', 'variant'], true) => 'Effects',
                                default => 'Content',
                            };
                            $propGroups = [];
                            foreach ($schema as $gk => $gr) $propGroups[$groupOf($gk)][$gk] = $gr;
                            // FOUNDATIONAL consistency: every block presents the SAME grouped
                            // sheet. Shared style-schema fields fill each group for keys the
                            // block's own props don't cover (props win when both exist), so a
                            // flex shows Width/Height/Position/… exactly like a container.
                            $styleOrder = ['width', 'height', 'position', 'inset', 'margin', 'z_index', 'overflow',
                                           'min_width', 'max_width', 'min_height', 'max_height', 'flex_child',
                                           'padding', 'background', 'color', 'radius', 'opacity'];
                            $styleInject = [];
                            foreach ($styleOrder as $sk) {
                                if (isset($styleSchema[$sk]) && ! array_key_exists($sk, $schema)) {
                                    $styleInject[$groupOf($sk)][$sk] = $styleSchema[$sk];
                                }
                            }
                            foreach (array_keys($styleInject) as $g) $propGroups[$g] = $propGroups[$g] ?? [];
                            // Effects always exists: fx animations + custom script live there.
                            $propGroups['Effects'] = $propGroups['Effects'] ?? [];
                            $groupOrder = ['Content', 'Layout', 'Size & Position', 'Background', 'Text', 'Filters', 'Effects'];
                            $propGroups = array_replace(array_intersect_key(array_flip($groupOrder), $propGroups), $propGroups);
                        @endphp
                        @foreach($propGroups as $groupName => $groupSchema)
                        <details class="bkf-group" wire:key="grp-{{ $selected->id }}-{{ $groupName }}"
                                 x-data="{ o: {{ $loop->first ? 'true' : 'false' }} }" x-bind:open="o" x-on:toggle="o = $event.target.open">
                            <summary>{{ $groupName }}</summary>
                            <div class="bkf-group-body">
                        @foreach($groupSchema as $key => $rule)
                            @php
                                // show_if: a prop appears only when its controlling prop
                                // holds one of the listed values (e.g. flex props ⇐ display=flex).
                                $hidden = false;
                                foreach ((array) ($rule['show_if'] ?? []) as $ctrl => $vals) {
                                    $vals = (array) $vals;
                                    // '*' = show when the controlling prop has ANY non-empty value
                                    // (e.g. overlay_opacity only matters once an overlay colour is set).
                                    $ok = in_array('*', $vals, true)
                                        ? (($propsForm[$ctrl] ?? '') !== '' && ($propsForm[$ctrl] ?? null) !== null)
                                        : in_array($propsForm[$ctrl] ?? '', $vals, true);
                                    if (! $ok) { $hidden = true; break; }
                                }
                            @endphp
                            {{-- Overlay companions render INSIDE the Overlay panel below. --}}
                            @continue($hidden || in_array($key, ['overlay_opacity', 'overlay_gradient', 'overlay_gradient_opacity'], true))
                            <div wire:key="prop-{{ $selected->id }}-{{ $key }}">
                                <label class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1">
                                    {{ \Illuminate\Support\Str::headline($key) }}@if($rule['required'] ?? false)<em class="text-rose-500"> *</em>@endif
                                </label>
                                @if($rule['type'] === 'responsive_enum')
                                    {{-- One field per device section — shows the ACTIVE device's value. --}}
                                    @foreach(['mobile' => 'base', 'tablet' => 'md', 'lg' => 'lg'] as $dev => $bp)
                                        @php $dev = $bp === 'lg' ? 'desktop' : $dev; @endphp
                                        <div x-show="device === '{{ $dev }}'" x-cloak="{{ $dev !== 'mobile' ? 'true' : '' }}">
                                            <x-field.select model="propsForm.{{ $key }}.{{ $bp }}" :options="$rule['values']"
                                                            :empty="$bp === 'base' ? '—' : 'inherit ↑ (from smaller device)'" />
                                        </div>
                                    @endforeach
                                    <p class="text-[10px] text-indigo-400 mt-0.5">◱ responsive — set per device via the tabs above</p>
                                @elseif($rule['type'] === 'enum')
                                    @if(count($rule['values']) <= 4 && isset($rule['default']))
                                        {{-- Short always-on enums read best as a segmented radio. --}}
                                        <x-field.radio model="propsForm.{{ $key }}" :options="$rule['values']" name="rg-{{ $selected->id }}-{{ $key }}" />
                                    @else
                                        <x-field.select model="propsForm.{{ $key }}" :options="$rule['values']" />
                                    @endif
                                @elseif($rule['type'] === 'bool')
                                    <x-field.check model="propsForm.{{ $key }}" />
                                @elseif($rule['type'] === 'int' && str_contains($key, 'opacity'))
                                    {{-- Opacity reads best as a slider (0–100%). --}}
                                    <x-field.range model="propsForm.{{ $key }}" :value="$propsForm[$key] ?? ''" />
                                @elseif(in_array($key, ['blur', 'backdrop_blur', 'brightness', 'contrast', 'saturate', 'grayscale'], true))
                                    {{-- CSS filters as sliders — blurs in px, the rest in % (100 = normal). --}}
                                    <x-field.range model="propsForm.{{ $key }}" :value="$propsForm[$key] ?? ''"
                                                   :max="in_array($key, ['blur', 'backdrop_blur'], true) ? 40 : ($key === 'grayscale' ? 100 : 200)"
                                                   :unit="in_array($key, ['blur', 'backdrop_blur'], true) ? 'px' : '%'"
                                                   :hint="$key === 'backdrop_blur' ? 'Frosts what shows through the block (backdrop-filter).' : null" />
                                @elseif($rule['type'] === 'int')
                                    <x-field.text type="number" model="propsForm.{{ $key }}" />
                                @elseif($rule['type'] === 'columns')
                                    {{-- Columns per device section (mobile-first). --}}
                                    @foreach(['mobile' => 'base', 'tablet' => 'md', 'desktop' => 'lg'] as $dev => $bp)
                                        <div x-show="device === '{{ $dev }}'" x-cloak="{{ $dev !== 'mobile' ? 'true' : '' }}">
                                            <x-field.text type="number" min="1" max="6" model="propsForm.{{ $key }}.{{ $bp }}"
                                                          :placeholder="$bp === 'base' ? 'columns' : 'inherit ↑'" />
                                        </div>
                                    @endforeach
                                    <p class="text-[10px] text-indigo-400 mt-0.5">◱ responsive — set per device via the tabs above</p>
                                @elseif($rule['type'] === 'options')
                                    <x-field.textarea model="propsForm.{{ $key }}" rows="3" placeholder="One option per line" />
                                @elseif($rule['type'] === 'action')
                                    <div class="space-y-1.5 rounded-lg border border-gray-200 dark:border-white/[0.08] p-2">
                                        <x-field.select model="propsForm.{{ $key }}.type" :options="['link', 'submit', 'open_modal', 'custom_event']" :empty="null" />
                                        <x-field.text model="propsForm.{{ $key }}.url" placeholder="URL (for link)" />
                                    </div>
                                @elseif($rule["type"] === "sides")
                                    @if($key === 'margin')
                                        <button type="button" wire:click="centerBlock('props')"
                                                class="mb-1 px-2 py-1 rounded-lg text-[10.5px] font-semibold text-indigo-600 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30 hover:bg-indigo-50 dark:hover:bg-indigo-500/10"
                                                title="margin-left/right: auto — centers the block in its parent (needs a width smaller than the parent)">↔ Center in parent</button>
                                    @endif
                                    <div class="grid grid-cols-4 gap-1.5">
                                        @foreach(["top", "right", "bottom", "left"] as $side)
                                            <label class="text-[10px] text-gray-400 capitalize">{{ $side }}
                                                <x-field.value model="propsForm.{{ $key }}.{{ $side }}" :options="$valOpts($key)" placeholder="0" />
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif($rule["type"] === "object" && $key === "media")
                                    <div class="space-y-1.5 rounded-lg border border-gray-200 dark:border-white/[0.08] p-2">
                                        <x-field.text model="propsForm.{{ $key }}.asset_id" placeholder="@media/filename or URL" mono />
                                        <x-field.select model="propsForm.{{ $key }}.ratio" :options="['1:1', '4:3', '16:9']" empty="ratio —" />
                                    </div>
                                @elseif($rule["type"] === "object")
                                    <p class="text-[10px] text-gray-400">Structured value — ask Polux to change it.</p>
                                @elseif($key === 'content')
                                    <x-field.textarea model="propsForm.{{ $key }}" rows="4"
                                                      title="Inline HTML allowed: b strong i em u s span br a mark small sub sup code — click the block on the canvas to edit visually" />
                                @elseif($key === 'asset_id')
                                    <x-field.asset model="propsForm.{{ $key }}" propKey="{{ $key }}"
                                                   :assets="$mediaImages" :videos="$mediaVideos"
                                                   :kind="($propsForm['kind'] ?? 'image') === 'video' ? 'video' : 'image'" />
                                @elseif($key === 'bg_image')
                                    {{-- Background image: icon opens the media modal; ref/URL typed directly. --}}
                                    <x-field.asset model="propsForm.{{ $key }}" propKey="{{ $key }}" :assets="$mediaImages" />
                                @elseif($key === 'overlay')
                                    {{-- Overlay: collapsed swatch → expands to solid colour + opacity
                                         AND gradient + opacity (two tint layers above the background). --}}
                                    @php
                                        $ovSet = ($propsForm['overlay'] ?? '') !== '' || ($propsForm['overlay_gradient'] ?? '') !== '';
                                        $ovChip = ($propsForm['overlay_gradient'] ?? '') !== ''
                                            ? 'background-image:'.$propsForm['overlay_gradient']
                                            : (($propsForm['overlay'] ?? '') !== ''
                                                ? 'background:'.$propsForm['overlay']
                                                : 'background:repeating-conic-gradient(#e5e7eb 0% 25%, #fff 0% 50%) 0 0/10px 10px');
                                    @endphp
                                    <div x-data="{ o: {{ $ovSet ? 'true' : 'false' }} }">
                                        <button type="button" class="bkf-input bkf-grad-swatch" @click="o = !o" title="Click to edit the overlay tints">
                                            <span class="bkf-grad-chip" style="{{ $ovChip }}"></span>
                                            <span class="bkf-mono" style="flex:1;min-width:0;text-align:left;font-size:11px;opacity:.8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                                {{ $ovSet ? trim(collect([$propsForm['overlay'] ?? '', $propsForm['overlay_gradient'] ?? ''])->filter()->implode(' + ')) : 'none — click to add' }}
                                            </span>
                                            <span x-text="o ? '▴' : '▾'" style="opacity:.5;font-size:10px"></span>
                                        </button>
                                        <div x-show="o" x-cloak class="bkf-panel">
                                            <x-field.color label="Solid colour" model="propsForm.overlay" :vars="$colorVars" :value="(string) ($propsForm['overlay'] ?? '')" />
                                            @if(($propsForm['overlay'] ?? '') !== '')
                                                <x-field.range label="Colour opacity" model="propsForm.overlay_opacity" :value="$propsForm['overlay_opacity'] ?? ''" />
                                            @endif
                                            <x-field.gradient label="Gradient" model="propsForm.overlay_gradient" :value="(string) ($propsForm['overlay_gradient'] ?? '')" />
                                            @if(($propsForm['overlay_gradient'] ?? '') !== '')
                                                <x-field.range label="Gradient opacity" model="propsForm.overlay_gradient_opacity" :value="$propsForm['overlay_gradient_opacity'] ?? ''" />
                                            @endif
                                        </div>
                                    </div>
                                @elseif(in_array($key, ['gradient', 'text_gradient'], true))
                                    {{-- Gradient previewed in the field — click to expand the builder. --}}
                                    <x-field.gradient model="propsForm.{{ $key }}" :value="(string) ($propsForm[$key] ?? '')"
                                                      :hint="$key === 'text_gradient' ? 'Painted through the letters (background-clip: text).' : null" />
                                @elseif(in_array($key, ['background', 'overlay', 'color'], true))
                                    {{-- Colour property: picker swatch + theme variables (--name) + free value. --}}
                                    <x-field.color model="propsForm.{{ $key }}" :vars="$colorVars" :value="(string) ($propsForm[$key] ?? '')" />
                                @elseif(in_array($key, ['width', 'height', 'size', 'font', 'bg_size', 'bg_position'], true))
                                    {{-- Filterable value field: auto + theme variables + free input. --}}
                                    <x-field.value model="propsForm.{{ $key }}" :options="$valOpts($key)"
                                                   :placeholder="match (true) {
                                                       $key === 'bg_size' => 'cover · contain · auto · 320px 240px',
                                                       $key === 'bg_position' => 'center · top left · 50% 50%',
                                                       $isSizeKey($key) => 'auto · 24px · 50% · $variable',
                                                       default => '#hex · $variable · value',
                                                   }" />
                                    @if(in_array($key, ['bg_size', 'bg_position'], true))
                                        {{-- Always-visible one-click presets (the datalist only shows on focus). --}}
                                        <div class="flex flex-wrap gap-1 mt-1.5">
                                            @foreach($key === 'bg_size'
                                                ? ['cover', 'contain', 'auto']
                                                : ['center', 'top', 'left', 'bottom', 'right', 'top left', 'top right', 'center left', 'center right', 'bottom left', 'bottom center', 'bottom right'] as $preset)
                                                <button type="button"
                                                        x-on:click="$wire.set('propsForm.{{ $key }}', '{{ $preset }}').then(() => $wire.saveInspector())"
                                                        class="px-2 py-0.5 rounded-md text-[10.5px] font-semibold border transition-colors
                                                            {{ ($propsForm[$key] ?? '') === $preset
                                                                ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-300'
                                                                : 'border-gray-200 dark:border-white/[0.08] text-gray-500 hover:border-indigo-300 hover:text-indigo-500' }}">
                                                    {{ $preset }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <x-field.text model="propsForm.{{ $key }}" />
                                @endif
                            </div>
                        @endforeach
                        @if($groupName === 'Effects')
                            {{-- FOUNDATION effects on EVERY block: viewport enter/leave
                                 animations, click animation, parallax + custom script. --}}
                            <div wire:key="fx-{{ $selected->id }}-enter">
                                <x-field.select label="Animate on enter" model="styleForm.fx_enter"
                                                :options="['fade-in', 'slide-up', 'slide-down', 'slide-left', 'slide-right', 'zoom-in', 'blur-in']" />
                            </div>
                            <div wire:key="fx-{{ $selected->id }}-leave">
                                <x-field.select label="Animate on leave" model="styleForm.fx_leave"
                                                :options="['fade-out', 'slide-up', 'slide-down', 'slide-left', 'slide-right', 'zoom-out', 'blur-out']"
                                                hint="Plays when the block scrolls out of view; the enter animation replays on return." />
                            </div>
                            <div class="grid grid-cols-2 gap-1.5" wire:key="fx-{{ $selected->id }}-timing">
                                <x-field.text label="Duration (ms)" type="number" min="0" step="50" model="styleForm.fx_duration" placeholder="600" />
                                <x-field.text label="Delay (ms)" type="number" min="0" step="50" model="styleForm.fx_delay" placeholder="0" />
                            </div>
                            <div wire:key="fx-{{ $selected->id }}-click">
                                <x-field.select label="On click" model="styleForm.fx_click"
                                                :options="['pulse', 'bounce', 'shake', 'flip', 'pop']" />
                            </div>
                            <div wire:key="fx-{{ $selected->id }}-parallax">
                                <x-field.range label="Parallax speed" model="styleForm.fx_parallax" :value="$styleForm['fx_parallax'] ?? ''"
                                               :max="100" min="-100" unit="%"
                                               hint="Moves with scroll — negative values drift opposite. Off on the canvas." />
                            </div>
                            <div wire:key="fx-{{ $selected->id }}-script">
                                <x-field.textarea label="Custom script" model="scriptForm" rows="4" class="bkf-mono"
                                                  placeholder="el.addEventListener('click', () => { … })"
                                                  hint="JavaScript with `el` bound to this block — runs on the live site and exports, never inside the editor." />
                            </div>
                        @endif
                        {{-- FOUNDATIONAL consistency: shared style fields complete the
                             group so every block shows the same property sheet. --}}
                        @foreach($styleInject[$groupName] ?? [] as $key => $rule)
                            <div wire:key="prop-{{ $selected->id }}-style-{{ $key }}">
                                <label class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1">{{ \Illuminate\Support\Str::headline($key) }}</label>
                                @if($rule['type'] === 'enum')
                                    <x-field.select model="styleForm.{{ $key }}" :options="$rule['values']" />
                                @elseif($rule['type'] === 'size')
                                    <x-field.value model="styleForm.{{ $key }}" :options="$valOpts($key)"
                                                   placeholder="auto · 24px · 50% · $variable"
                                                   title="Relative to the block's parent frame — %, px, rem, vh, or a $theme-variable" />
                                @elseif($rule['type'] === 'sides')
                                    @if($key === 'margin')
                                        <button type="button" wire:click="centerBlock('style')"
                                                class="mb-1 px-2 py-1 rounded-lg text-[10.5px] font-semibold text-indigo-600 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30 hover:bg-indigo-50 dark:hover:bg-indigo-500/10"
                                                title="margin-left/right: auto — centers the block in its parent (needs a width smaller than the parent)">↔ Center in parent</button>
                                    @endif
                                    <div class="grid grid-cols-4 gap-1.5">
                                        @foreach(['top', 'right', 'bottom', 'left'] as $side)
                                            <label class="text-[10px] text-gray-400 capitalize">{{ $side }}
                                                <x-field.value model="styleForm.{{ $key }}.{{ $side }}" :options="$valOpts($key)" placeholder="0"
                                                               title="auto · 0 · 8pt · 1rem · 5% · 2vh — auto centers (margin)" />
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif($key === 'flex_child')
                                    {{-- How this block behaves inside a flex parent. --}}
                                    <div class="grid grid-cols-4 gap-1.5">
                                        <label class="text-[10px] text-gray-400">Grow<x-field.text type="number" min="0" model="styleForm.{{ $key }}.grow" /></label>
                                        <label class="text-[10px] text-gray-400">Shrink<x-field.text type="number" min="0" model="styleForm.{{ $key }}.shrink" /></label>
                                        <label class="text-[10px] text-gray-400 col-span-2">Basis<x-field.value model="styleForm.{{ $key }}.basis" :options="$valOpts('basis')" placeholder="auto" /></label>
                                    </div>
                                @elseif(str_contains($key, 'opacity'))
                                    <x-field.range model="styleForm.{{ $key }}" :value="$styleForm[$key] ?? ''" />
                                @elseif($rule['type'] === 'int')
                                    <x-field.text type="number" model="styleForm.{{ $key }}" />
                                @elseif(in_array($key, ['background', 'color'], true))
                                    <x-field.color model="styleForm.{{ $key }}" :vars="$colorVars" :value="(string) ($styleForm[$key] ?? '')" />
                                @else
                                    <x-field.value model="styleForm.{{ $key }}" :options="$valOpts($key)" placeholder="token · hex · $variable" />
                                @endif
                            </div>
                        @endforeach
                            </div>
                        </details>
                        @endforeach

                        @if($selected->type === 'component_ref')
                            <div class="rounded-lg border border-emerald-300 dark:border-emerald-400/40 bg-emerald-50/60 dark:bg-emerald-400/[0.07] p-3 space-y-2.5">
                                <p class="text-[11px] font-bold text-emerald-700 dark:text-emerald-300">
                                    ⚙ Live instance of “{{ $refComponent?->name ?? 'deleted component' }}”
                                </p>
                                <p class="text-[10px] text-emerald-600/80 dark:text-emerald-300/70 leading-relaxed -mt-1.5">
                                    Editing the component updates every copy. Props below are this instance's values.
                                </p>
                                @foreach($refProps as $prop)
                                    <div>
                                        <label class="block text-[11px] font-semibold text-emerald-700 dark:text-emerald-300 mb-1 capitalize">{{ $prop['name'] }}</label>
                                        <input type="text" wire:model="propsForm.overrides.{{ $prop['name'] }}" placeholder="{{ $prop['default'] ?: '—' }}"
                                               class="w-full text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-emerald-200 dark:border-emerald-400/30 px-2.5 py-1.5 text-gray-800 dark:text-gray-100">
                                    </div>
                                @endforeach
                                <div class="flex gap-1.5 pt-0.5">
                                    @if($refComponent)
                                        <button wire:click="editLayout('{{ $refComponent->id }}')"
                                                class="flex-1 px-2 py-1.5 rounded-lg text-[11px] font-semibold text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-400/40 hover:bg-emerald-100 dark:hover:bg-emerald-400/10">Edit component</button>
                                    @endif
                                    <button wire:click="detachComponent('{{ $selected->id }}')"
                                            data-confirm="Detach this copy? It becomes independent — component edits will no longer affect it."
                                            class="flex-1 px-2 py-1.5 rounded-lg text-[11px] font-semibold text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-white/[0.15] hover:bg-gray-100 dark:hover:bg-white/[0.06]">Detach copy</button>
                                </div>
                            </div>
                        @endif

                        @if($editingLayout?->isComponent() && in_array($selected->type, ['header', 'content', 'button', 'tile', 'input', 'textarea', 'select', 'checkbox', 'media'], true))
                            <div class="rounded-lg border border-emerald-200 dark:border-emerald-400/30 bg-emerald-50/50 dark:bg-emerald-400/[0.06] p-2.5">
                                <label class="block text-[11px] font-semibold text-emerald-700 dark:text-emerald-300 mb-1">Expose as prop</label>
                                <input wire:model="propNameForm" type="text" placeholder="e.g. title, image, cta"
                                       class="w-full text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-emerald-200 dark:border-emerald-400/30 px-2.5 py-1.5 text-gray-800 dark:text-gray-100">
                                <p class="text-[10px] text-emerald-600/80 dark:text-emerald-300/70 mt-1 leading-relaxed">
                                    Named props are asked for when the component is placed on a page —
                                    this block's current content is the default. Leave empty for fixed content.
                                </p>
                            </div>
                        @endif

                        @if(empty($theme['variables']))
                            <p class="text-[10.5px] text-gray-400 rounded-lg border border-dashed border-gray-200 dark:border-white/[0.08] px-2.5 py-2">
                                💡 Define <button type="button" @click="tab = 'theme'" class="font-semibold text-indigo-500 hover:underline">Theme variables</button>
                                (colours, sizes, fonts) and a <span class="font-mono">$</span> picker appears on these fields — set once, reuse in every block.
                            </p>
                        @endif

                        <button wire:click="saveInspector" wire:loading.attr="disabled"
                                class="w-full px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-70 text-white text-xs font-semibold">
                            <span wire:loading.remove wire:target="saveInspector">Save block</span>
                            <span wire:loading wire:target="saveInspector">Saving…</span>
                        </button>

                        @unless($selected->type === 'content_slot')
                            <button x-data @click="const n = prompt('Name this reusable block:', @js($labelForm ?: $selected->type)); if (n) $wire.saveReusable(n)"
                                    class="w-full px-3 py-2 rounded-xl border border-amber-300 dark:border-amber-400/40 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-400/10 text-xs font-semibold"
                                    title="Save this block (and everything inside it) to the palette to reuse anywhere">
                                ⟳ Save as reusable
                            </button>
                        @endunless
                    </div>
                @else
                    <div class="text-center pt-16 px-3">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Nothing selected</p>
                        <p class="text-xs text-gray-400 mt-1.5 leading-relaxed">Pick a block in the layers panel to edit it — or hit <strong>Add block</strong> to start building. Drag the ⠿ handle to rearrange; 📌 pins a block so Polux leaves it alone.</p>
                    </div>
                @endif
            </aside>

            {{-- Polux drawer --}}
            <aside x-show="polux" x-cloak class="w-80 shrink-0 border-l border-gray-200 dark:border-white/[0.06] bg-white dark:bg-[#181a24]">
                <livewire:block-assistant :site-id="$site->id" :page-id="$pageId" :key="'polux-'.$site->id" />
            </aside>
        </div>
    </div>

    {{-- Export HTML modal --}}
    @if($exportHtml)
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/60" wire:click.self="closeExport">
            <div class="w-[min(760px,92vw)] max-h-[82vh] flex flex-col rounded-2xl bg-white dark:bg-[#1e1f2b] border border-gray-200 dark:border-white/[0.1] overflow-hidden shadow-2xl">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-white/[0.08]">
                    <p class="text-sm font-bold text-gray-900 dark:text-white">Exported page HTML</p>
                    <button wire:click="closeExport" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[0.06]">Close</button>
                </div>
                <textarea readonly spellcheck="false" id="bkw-export-code"
                          class="flex-1 min-h-[340px] p-4 font-mono text-[11.5px] leading-relaxed bg-gray-50 dark:bg-[#14151d] text-gray-700 dark:text-gray-300 border-0 resize-none focus:ring-0">{{ $exportHtml }}</textarea>
                <div class="flex justify-end gap-2 px-4 py-3 border-t border-gray-200 dark:border-white/[0.08]">
                    <button x-data @click="const t = document.getElementById('bkw-export-code'); t.select(); navigator.clipboard?.writeText(t.value); $el.textContent = 'Copied ✓'; setTimeout(() => $el.textContent = 'Copy to clipboard', 1500)"
                            class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">Copy to clipboard</button>
                    <button x-data @click="const b = new Blob([document.getElementById('bkw-export-code').value], {type: 'text/html'}); const a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = @js(\Illuminate\Support\Str::slug($page?->name ?? 'page').'.html'); a.click()"
                            class="px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/[0.1] text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06]">Download .html</button>
                </div>
            </div>
        </div>
    @endif
    {{-- ── Component prop fill-in: shown when placing a component that exposes props ── --}}
    @if($pendingStamp)
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/50" wire:key="stamp-modal">
            <div class="w-[min(440px,92vw)] rounded-2xl bg-white dark:bg-[#1e1f2b] border border-gray-200 dark:border-white/[0.1] shadow-2xl p-5">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Place “{{ $pendingStamp['name'] }}”</h3>
                <p class="text-[11px] text-gray-400 mt-0.5 mb-3">Fill the component's props for this copy — defaults are prefilled; change what you like.</p>
                <div class="space-y-2.5 max-h-[50vh] overflow-y-auto">
                    @foreach($pendingStamp['props'] as $prop)
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1 capitalize">{{ $prop['name'] }}</label>
                            <input type="text" wire:model="pendingStamp.values.{{ $prop['name'] }}" placeholder="{{ $prop['default'] ?: '—' }}"
                                   class="w-full text-sm rounded-lg bg-gray-50 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] px-2.5 py-1.5 text-gray-800 dark:text-gray-100">
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button wire:click="cancelStamp" class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-gray-200 dark:border-white/[0.1] text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.05]">Cancel</button>
                    <button wire:click="confirmStamp" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">Place component</button>
                </div>
            </div>
        </div>
    @endif

{{-- Rich-text LINK PICKER: link selected text to a page, a media file, or any URL. --}}
<div id="bkwLinkDlg" class="bkw-linkdlg" style="display:none">
    <p class="bkw-linkdlg-title">Link to…</p>
    <div class="bkw-linkdlg-cols">
        <div>
            <p class="bkw-linkdlg-h">Pages</p>
            @foreach($pages as $lp)
                <button type="button" data-bkw-linkurl="{{ $lp->url }}">{{ $lp->name }} <span>{{ $lp->url }}</span></button>
            @endforeach
        </div>
        <div>
            <p class="bkw-linkdlg-h">Media library</p>
            @forelse($linkMedia as $lm)
                <button type="button" data-bkw-linkurl="{{ $lm->publicUrl() }}">
                    @if($lm->file_type === 'image')<img src="{{ $lm->publicUrl() }}" alt="">@endif
                    {{ \Illuminate\Support\Str::limit($lm->name, 24) }}
                </button>
            @empty
                <p class="bkw-linkdlg-empty">No media yet — upload in the Media screen.</p>
            @endforelse
        </div>
    </div>
    <div class="bkw-linkdlg-row">
        <input id="bkwLinkUrl" type="text" placeholder="https://… · /page · mailto:…">
        <button type="button" id="bkwLinkApply">Link</button>
        <button type="button" id="bkwLinkCancel">Cancel</button>
    </div>
</div>

</div>



@script
<script>
    /* ── Native HTML5 drag & drop for the WYSIWYG canvas ─────────────────────
       Palette cards carry data-bkw-palette="<type>"; canvas nodes carry
       data-bkw-item/data-id; containers expose data-bkw-list/data-parent-id.
       A blue insert bar previews the drop index; drop calls the ONE mutation
       service via $wire (insertBlockAt for new, moveBlock for moves).      */
    let bkwDrag = null;      // {kind:'new', type} | {kind:'move', id}
    let bkwBar = null;

    const bkwRemoveBar = () => { if (bkwBar) { bkwBar.remove(); bkwBar = null; } };
    const bkwKids = (list) => [...list.children].filter((c) => c.matches?.('[data-bkw-item], .bkw-node'));
    const bkwIndexFor = (list, e) => {
        const kids = bkwKids(list);
        const horizontal = getComputedStyle(list).display === 'flex' && getComputedStyle(list).flexDirection === 'row';
        for (let i = 0; i < kids.length; i++) {
            const r = kids[i].getBoundingClientRect();
            if (horizontal ? e.clientX < r.left + r.width / 2 : e.clientY < r.top + r.height / 2) return i;
        }
        return kids.length;
    };
    const bkwShowBar = (list, e) => {
        bkwRemoveBar();
        const kids = bkwKids(list);
        bkwBar = document.createElement('div');
        bkwBar.className = 'bkw-insert-bar';
        const idx = bkwIndexFor(list, e);
        if (!kids.length || idx >= kids.length) list.appendChild(bkwBar);
        else list.insertBefore(bkwBar, kids[idx]);
    };

    document.addEventListener('dragstart', (e) => {
        const pal = e.target.closest?.('[data-bkw-palette]');
        if (pal) { bkwDrag = { kind: 'new', type: pal.dataset.bkwPalette }; e.dataTransfer.effectAllowed = 'copy'; return; }
        const reuse = e.target.closest?.('[data-bkw-reuse]');
        if (reuse) { bkwDrag = { kind: 'reuse', id: parseInt(reuse.dataset.bkwReuse) }; e.dataTransfer.effectAllowed = 'copy'; return; }
        const comp = e.target.closest?.('[data-bkw-component]');
        if (comp) { bkwDrag = { kind: 'component', id: parseInt(comp.dataset.bkwComponent) }; e.dataTransfer.effectAllowed = 'copy'; return; }
        const item = e.target.closest?.('[data-bkw-item]');
        if (item) {
            bkwDrag = { kind: 'move', id: item.dataset.id };
            e.dataTransfer.effectAllowed = 'move';
            e.stopPropagation();
            requestAnimationFrame(() => item.classList.add('bkw-dragging'));
        }
    });
    document.addEventListener('dragend', () => {
        bkwDrag = null; bkwRemoveBar();
        document.querySelectorAll('.bkw-dragging').forEach((n) => n.classList.remove('bkw-dragging'));
        document.querySelectorAll('.bkw-dragover').forEach((n) => n.classList.remove('bkw-dragover'));
    });
    /* Geometry-based targeting: browsers hit-test by PAINT order, so a
       container behind other blocks (z-index:-1 backdrops…) could never
       receive a drop. We instead pick the DEEPEST container list whose box
       contains the pointer — every container is a drop target, like a real
       builder. Ties (overlapping siblings) go to the later/topmost one. */
    const bkwListAt = (x, y, e) => {
        const direct = e?.target?.closest?.('[data-bkw-list]');
        const inCanvas = !!e?.target?.closest?.('.bkw-artboard');
        if (direct && !inCanvas) return direct; // layers panel etc: trust the DOM
        // Walk the full paint stack at the point (includes covered elements):
        // deepest-nested candidate wins; ties go to the visually topmost one.
        const stack = document.elementsFromPoint(x, y);
        let best = null, bestDepth = -1;
        for (const el of stack) {
            const l = el.matches?.('[data-bkw-list]') ? el : el.closest?.('[data-bkw-list]');
            if (!l || !l.closest('.bkw-artboard') || l === best) continue;
            if (bkwDrag?.kind === 'move' && l.closest(`[data-bkw-item][data-id="${bkwDrag.id}"]`)) continue;
            let d = 0; for (let p = l.parentElement; p; p = p.parentElement) if (p.matches?.('[data-bkw-list]')) d++;
            if (d > bestDepth) { best = l; bestDepth = d; } // strict: earlier stack (topmost) wins ties
        }
        return best ?? direct;
    };

    document.addEventListener('dragover', (e) => {
        if (!bkwDrag) return;
        const list = bkwListAt(e.clientX, e.clientY, e);
        if (!list) return;
        // never drop a node into itself
        if (bkwDrag.kind === 'move' && list.closest(`[data-bkw-item][data-id="${bkwDrag.id}"]`)) return;
        e.preventDefault(); e.stopPropagation();
        document.querySelectorAll('.bkw-dragover').forEach((n) => n.classList.remove('bkw-dragover'));
        list.classList.add('bkw-dragover');
        bkwShowBar(list, e);
    });
    document.addEventListener('drop', (e) => {
        if (!bkwDrag) return;
        const list = bkwListAt(e.clientX, e.clientY, e);
        if (!list) return;
        if (bkwDrag.kind === 'move' && list.closest(`[data-bkw-item][data-id="${bkwDrag.id}"]`)) return;
        e.preventDefault(); e.stopPropagation();
        let idx = bkwIndexFor(list, e);
        const parent = list.dataset.parentId;
        bkwRemoveBar();
        list.classList.remove('bkw-dragover');
        if (bkwDrag.kind === 'new') { $wire.insertBlockAt(bkwDrag.type, parent, idx); }
        else if (bkwDrag.kind === 'reuse') { $wire.insertReusableAt(bkwDrag.id, parent, idx); }
        else if (bkwDrag.kind === 'component') { $wire.requestComponentInsert(bkwDrag.id, parent, idx); }
        else {
            // same-list move: the service positions AFTER removal, so shift down
            const moving = document.querySelector(`[data-bkw-item][data-id="${bkwDrag.id}"]`);
            if (moving && moving.parentElement === list && bkwKids(list).indexOf(moving) < idx) idx--;
            $wire.moveBlock(bkwDrag.id, parent, idx);
        }
        bkwDrag = null;
    });

    const initBkSort = () => {
        document.querySelectorAll('[data-bk-list]').forEach((el) => {
            if (el._bkSortable) return;
            el._bkSortable = window.Sortable.create(el, {
                group: 'bk',
                animation: 150,
                handle: '[data-bk-handle]',
                draggable: '[data-bk-item]',
                forceFallback: true,
                fallbackOnBody: true,
                swapThreshold: 0.6,
                ghostClass: 'bk-drag-ghost',
                onEnd: (evt) => {
                    const id = evt.item?.dataset?.id;
                    const parent = evt.to?.dataset?.parentId;
                    if (id && parent) $wire.moveBlock(id, parent, evt.newIndex);
                },
            });
        });
    };
    document.addEventListener('keydown', (e) => {
        if ((e.key !== 'Delete' && e.key !== 'Backspace') || e.target?.matches?.('input, textarea, select, [contenteditable]')) return;
        const sel = $wire.get('selectedId');
        if (sel) { e.preventDefault(); $wire.deleteBlock(sel); }
    });

    /* Backdrop anchors (⚓) must escape their container: a z-index:-1 parent
       creates a stacking context that traps ALL descendants behind the page —
       no CSS can lift them out. So we re-home each anchor onto the artboard
       itself and pin it over its container's top-left corner. */
    const bkwPlaceAnchors = () => {
        const ab = document.querySelector('.bkw-artboard');
        if (!ab) return;
        document.querySelectorAll('.bkw-anchor').forEach((a) => {
            const t = document.querySelector(`[data-bkw-item][data-id="${a.dataset.parentId}"]`);
            if (!t) { a.remove(); return; }
            if (a.parentElement !== ab) ab.appendChild(a);
            const tr = t.getBoundingClientRect(), abr = ab.getBoundingClientRect();
            a.style.left = Math.max(2, tr.x - abr.x + 4) + 'px';
            a.style.top = Math.max(2, tr.y - abr.y + 4) + 'px';
        });
    };
    bkwPlaceAnchors();
    Livewire.hook('morph.updated', () => queueMicrotask(bkwPlaceAnchors));
    /* FOUNDATION: selection, delete and duplicate are DELEGATED and
       STACK-AWARE. Per-element Livewire bindings die when a subtree is
       re-parented by a re-render, and native hit-testing cannot reach blocks
       behind a backdrop (z-index:-1) — so neither is trusted. One document
       listener resolves the target by paint-stack geometry every time. */
    /* Click ownership follows the EYE: walking the paint stack top→down,
       the first element that actually paints pixels (text, image, background,
       control) claims the click for its block. Transparent wrappers and drop
       lists pass through — so a block visible behind them is selectable, while
       genuinely covered pixels select the block you see on top. */
    const bkwPaints = (el) => {
        if (el.matches?.('img, video, svg, iframe, button, input, select, textarea, hr')) return true;
        const cs = getComputedStyle(el);
        if (cs.backgroundColor !== 'rgba(0, 0, 0, 0)' || cs.backgroundImage !== 'none') return true;
        for (const n of el.childNodes) if (n.nodeType === 3 && n.textContent.trim()) return true;
        return false;
    };
    const bkwItemAt = (x, y) => {
        let painted = null, deepest = null, deepestDepth = -1;
        for (const el of document.elementsFromPoint(x, y)) {
            if (!el.closest?.('.bkw-artboard')) continue;
            const it = el.matches?.('[data-bkw-item]') ? el : el.closest?.('[data-bkw-item]');
            if (!it) continue;
            if (!painted && bkwPaints(el)) painted = it; // topmost PAINTED pixel
            let d = 0; for (let p = it.parentElement; p; p = p.parentElement) if (p.matches?.('[data-bkw-item]')) d++;
            if (d > deepestDepth) { deepest = it; deepestDepth = d; }
        }
        // Paint-ownership resolves true OVERLAPS (a backdrop behind content):
        // the block you see wins. But when the painted block is an ANCESTOR
        // whose background merely shows through transparent children (a flex
        // inside a coloured container), pointing there means the deepest
        // block — wrappers stay directly selectable.
        if (painted && deepest && painted !== deepest && painted.contains(deepest)) return deepest;
        return painted ?? deepest;
    };
    document.addEventListener('click', (e) => {
        const a = e.target.closest?.('.bkw-anchor');
        if (a?.dataset?.parentId) { e.stopPropagation(); $wire.select(a.dataset.parentId); return; }
        const del = e.target.closest?.('[data-bkw-del]');
        if (del) {
            e.stopPropagation();
            // Foundation: removal always confirms through the shared modal.
            const doDelete = () => $wire.deleteBlock(del.dataset.bkwDel);
            window.bkConfirm
                ? window.bkConfirm('Delete this block?', { danger: true, okLabel: 'Delete' }).then((ok) => { if (ok) doDelete(); })
                : doDelete();
            return;
        }
        const dup = e.target.closest?.('[data-bkw-dup]');
        if (dup) { e.stopPropagation(); $wire.duplicateBlock(dup.dataset.bkwDup); return; }
        // Canvas click → stack-aware select (covered/nested blocks included).
        if (e.target.closest?.('.bkw-artboard') && ! e.target.closest?.('button, input, select, textarea, a')) {
            // Clicks inside the FOCUSED inline text editor place the caret —
            // never re-select or cycle while the user is actually typing.
            const ed = e.target.closest?.('[contenteditable="true"]');
            if (ed && (ed === document.activeElement || ed.contains(document.activeElement))) return;
            // FOUNDATION — click selects EXACTLY the highlighted block: the
            // hover preview and the click resolve the same target, so what
            // lights up is what you get, first click, every time. Clicking a
            // block that is ALREADY selected steps through the hit stack
            // (deepest → shallowest, wrapping), keeping every transparent
            // wrapper reachable — and on touch (no hover), repeated taps do
            // the same walk.
            let item = (bkwHot?.isConnected && bkwHot.closest('.bkw-artboard'))
                ? bkwHot
                : bkwItemAt(e.clientX, e.clientY);
            const selNow = document.querySelector('.bkw-artboard .bkw-selected[data-id]');
            if (item && selNow && item.dataset.id === selNow.dataset.id) {
                const chain = [];
                for (const el of document.elementsFromPoint(e.clientX, e.clientY)) {
                    if (!el.closest?.('.bkw-artboard')) continue;
                    const it = el.matches?.('[data-bkw-item]') ? el : el.closest?.('[data-bkw-item]');
                    if (it?.dataset?.id && !chain.includes(it)) chain.push(it);
                }
                chain.sort((a, b) => a.contains(b) ? 1 : b.contains(a) ? -1 : 0); // deepest first
                const at = chain.findIndex((n) => n.dataset.id === item.dataset.id);
                if (at !== -1 && chain.length > 1) item = chain[(at + 1) % chain.length];
            }
            if (item?.dataset?.id) {
                e.stopPropagation();
                $wire.select(item.dataset.id);
                // Text blocks edit in place the moment they are selected.
                const t = item.querySelector?.(':scope > [data-bkw-text]') || item.querySelector?.('[data-bkw-text]');
                if (t && t.dataset.bkwText === item.dataset.id) { rtPendingId = item.dataset.id; rtStart(t); }
                else rtPendingId = null;
            }
        }
    }, true); // CAPTURE: supersedes any legacy per-node binding
    window.addEventListener('resize', bkwPlaceAnchors);

    /* Hover preview: moving the mouse highlights the block a click would
       select (same paint-stack rule), so padded wrappers show themselves
       before you commit. rAF-throttled; inert while editing text. */
    let bkwHot = null, bkwHotAt = 0;
    document.addEventListener('mousemove', (e) => {
        const now = performance.now();
        if (now - bkwHotAt < 40) return; // cheap throttle, no rAF (works unfocused)
        bkwHotAt = now;
        const inCanvas = e.target?.closest?.('.bkw-artboard') && ! e.target.closest?.('[contenteditable="true"]');
        const it = inCanvas ? bkwItemAt(e.clientX, e.clientY) : null;
        if (it === bkwHot) return;
        bkwHot?.classList.remove('bkw-hot');
        bkwHot = it;
        it?.classList.add('bkw-hot');
        // Mirror the highlight into the LAYERS panel — hovering the canvas
        // shows exactly which layer row the block is.
        document.querySelectorAll('.bkw-hot-layer').forEach((n) => n.classList.remove('bkw-hot-layer'));
        if (it?.dataset?.id) {
            const row = document.querySelector(`[data-bk-item][data-id="${it.dataset.id}"]`);
            if (row) { row.classList.add('bkw-hot-layer'); row.scrollIntoView({ block: 'nearest' }); }
        }
    }, true);

    /* ── Inline rich-text editing (CMS) ─────────────────────────────────
       Double-click a header/content block → its text becomes contenteditable
       with a floating toolbar (B I U S Link Clear). Blur or Ctrl+Enter saves
       (sanitized server-side to the inline allow-list); Esc cancels. */
    let rtEl = null, rtOriginal = '', rtSavedRange = null;
    const rtApplyLink = (url) => {
        const dlg = document.getElementById('bkwLinkDlg');
        dlg.style.display = 'none';
        if (!url || !rtEl) return;
        rtEl.focus();
        if (rtSavedRange) { const sel = document.getSelection(); sel.removeAllRanges(); sel.addRange(rtSavedRange); }
        document.execCommand('createLink', false, url);
    };
    document.addEventListener('click', (e) => {
        const dlg = document.getElementById('bkwLinkDlg');
        if (!dlg || dlg.style.display === 'none') return;
        const pick = e.target.closest?.('[data-bkw-linkurl]');
        if (pick) { e.stopPropagation(); rtApplyLink(pick.dataset.bkwLinkurl); return; }
        if (e.target.id === 'bkwLinkApply') { e.stopPropagation(); rtApplyLink(document.getElementById('bkwLinkUrl').value.trim()); return; }
        if (e.target.id === 'bkwLinkCancel') { e.stopPropagation(); dlg.style.display = 'none'; }
    }, true);
    const rtBar = document.createElement('div');
    rtBar.className = 'bkw-rtbar';
    rtBar.innerHTML = [
        ['bold', '<b>B</b>'], ['italic', '<i>I</i>'], ['underline', '<u>U</u>'],
        ['strikeThrough', '<s>S</s>'], ['span', '&lt;span&gt;'], ['br', '↵br'],
        ['link', '🔗'], ['removeFormat', '⌫'],
    ].map(([cmd, label]) => `<button type="button" data-rt="${cmd}" tabindex="-1">${label}</button>`).join('')
      + `<input type="text" id="bkwRtClass" placeholder="class…" tabindex="-1" title="Type CSS class(es) and press Enter — applies to the inline tag around the selection, or wraps it in a span">`;
    rtBar.style.display = 'none';
    document.body.appendChild(rtBar);
    rtBar.addEventListener('mousedown', (e) => {
        e.preventDefault(); // keep the text selection alive
        const cmd = e.target.closest('[data-rt]')?.dataset?.rt;
        if (!cmd || !rtEl) return;
        if (cmd === 'span') {
            // Wrap the selection in a plain <span> (target for classes/styles).
            const sel = document.getSelection();
            if (sel && sel.rangeCount && !sel.isCollapsed) {
                const range = sel.getRangeAt(0);
                const span = document.createElement('span');
                try { range.surroundContents(span); }
                catch { span.appendChild(range.extractContents()); range.insertNode(span); }
                sel.removeAllRanges(); const nr = document.createRange(); nr.selectNodeContents(span); sel.addRange(nr);
            }
            rtEl.focus(); return;
        }
        if (cmd === 'br') {
            document.execCommand('insertHTML', false, '<br>');
            rtEl.focus(); return;
        }
        if (cmd === 'link') {
            // Save the selection, then open the page/media/url picker.
            const sel = document.getSelection();
            rtSavedRange = sel && sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
            const dlg = document.getElementById('bkwLinkDlg');
            const br = rtBar.getBoundingClientRect();
            dlg.style.left = Math.max(8, Math.min(br.x, innerWidth - 440)) + 'px';
            dlg.style.top = (br.y + 30) + 'px';
            dlg.style.display = 'block';
        } else {
            document.execCommand(cmd, false);
        }
        rtEl.focus();
    });
    rtBar.addEventListener('keydown', (e) => {
        if (e.target.id !== 'bkwRtClass' || e.key !== 'Enter') return;
        e.preventDefault();
        const cls = e.target.value.trim().replace(/[^-_a-zA-Z0-9 ]+/g, '');
        if (!cls || !rtEl) return;
        const sel = document.getSelection();
        let target = null;
        if (sel && sel.rangeCount) {
            let n = sel.getRangeAt(0).commonAncestorContainer;
            if (n.nodeType === 3) n = n.parentElement;
            // closest allowed inline tag INSIDE the edited block
            if (n && n !== rtEl && rtEl.contains(n) && n.matches('b,strong,i,em,u,s,span,a,mark,small,sub,sup,code')) target = n;
        }
        if (!target && sel && sel.rangeCount && !sel.isCollapsed) {
            const range = sel.getRangeAt(0);
            target = document.createElement('span');
            try { range.surroundContents(target); }
            catch { target.appendChild(range.extractContents()); range.insertNode(target); }
        }
        if (target) { target.className = cls; e.target.value = ''; }
        rtEl.focus();
    });
    const rtPlace = () => {
        if (!rtEl) return;
        const r = rtEl.getBoundingClientRect();
        rtBar.style.left = Math.max(6, r.x) + 'px';
        rtBar.style.top = Math.max(6, r.y - 34) + 'px';
    };
    const rtStop = (save) => {
        if (!rtEl) return;
        const el = rtEl, html = el.innerHTML;
        rtEl = null;
        rtBar.style.display = 'none';
        el.removeAttribute('contenteditable');
        el.classList.remove('bkw-editing');
        if (save && html !== rtOriginal) $wire.updateContent(el.dataset.bkwText, html);
        else if (!save) el.innerHTML = rtOriginal;
    };
    const rtStart = (t, selectAll = false) => {
        if (!t || rtEl === t) return;
        rtStop(true);
        rtEl = t; rtOriginal = t.innerHTML;
        t.setAttribute('contenteditable', 'true');
        t.classList.add('bkw-editing');
        t.focus();
        if (selectAll) document.getSelection()?.selectAllChildren(t);
        rtBar.style.display = 'flex';
        rtPlace();
    };
    // SELECTING a text block puts it straight into edit mode — but select()
    // triggers a Livewire re-render that can replace the node, so we remember
    // the block id and re-attach after every morph until deselected.
    let rtPendingId = null;
    const rtAttachPending = () => {
        if (!rtPendingId) return;
        const t = document.querySelector(`[data-bkw-text="${rtPendingId}"]`);
        // Livewire morphs PATCH the same node (attributes stripped) — so the
        // check is the attribute, never node identity.
        if (t && !t.isContentEditable) { rtEl = null; rtStart(t); }
    };
    Livewire.hook('morph.updated', () => setTimeout(rtAttachPending, 40));
    document.addEventListener('dblclick', (e) => {
        const t = e.target.closest?.('[data-bkw-text]');
        if (!t) return;
        e.preventDefault(); e.stopPropagation();
        rtPendingId = t.dataset.bkwText;
        rtStart(t, true);
    }, true);
    document.addEventListener('focusout', (e) => {
        const dlg = document.getElementById('bkwLinkDlg');
        if (rtEl && e.target === rtEl && !rtBar.contains(e.relatedTarget) && !(dlg && dlg.contains(e.relatedTarget))) rtStop(true);
        // leaving the class input back to nowhere should not kill the session
    });
    document.addEventListener('keydown', (e) => {
        if (!rtEl) return;
        if (e.key === 'Escape') { e.preventDefault(); rtPendingId = null; rtStop(false); }
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); rtPendingId = null; rtStop(true); return; }
        // Enter = line break inside the block (real <br>), never a new <div>.
        if (e.key === 'Enter') { e.preventDefault(); document.execCommand('insertHTML', false, '<br>'); }
    }, true);
    window.addEventListener('scroll', rtPlace, true);

    initBkSort();
    Livewire.hook('morph.updated', () => queueMicrotask(initBkSort));

    // Delete / Backspace removes the selected block (unless typing in a field).
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Delete' && e.key !== 'Backspace') return;
        if (e.target.matches('input, textarea, select, [contenteditable]')) return;
        const id = $wire.selectedId;
        if (id) { e.preventDefault(); $wire.deleteBlock(id); }
    });

    $wire.on('bk-confirm-delete', async ({ blockId }) => {
        // Foundation: confirmations use the shared modal, never native confirm().
        const ok = window.bkConfirm
            ? await window.bkConfirm('This block contains other blocks — delete it and everything inside?', { danger: true, okLabel: 'Delete' })
            : confirm('This block contains other blocks — delete it and everything inside?');
        if (ok) $wire.deleteBlock(blockId, true);
    });
</script>
@endscript

@assets
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
{{-- FOUNDATION effects engine: enter/leave animations + click FX live on the
     canvas exactly like the real page (parallax is canvas-suppressed). --}}
<style>{!! \App\Support\Fx::css() !!}</style>
<script>{!! \App\Support\Fx::js() !!}</script>
<style>
.bk-drag-ghost { opacity: .4; background: rgba(99,102,241,.12); outline: 2px dashed #6366f1; outline-offset: -2px; border-radius: 8px; }

/* ── WYSIWYG canvas (blueprint stage + white artboard) ─────────────────── */
.bkw-stage {
    background-color: #ffffff;
    background-image: linear-gradient(rgba(76,125,255,.06) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(76,125,255,.06) 1px, transparent 1px);
    background-size: 24px 24px;
}
.bkw-artboard { overflow: hidden; position: relative; isolation: isolate; border: 2px solid #d3d9e8; box-shadow: none !important; border-radius: 0; }
/* CSS-faithful: wrappers are STATIC like real elements — a user position wins
   via inline style; relative kicks in only while the hover/selected UI shows. */
.bkw-node { min-height: 8px; }
.bkw-node:hover, .bkw-node.bkw-selected, .bkw-instance, .bkw-slot { position: relative; }
.bkw-container { border: 1.5px dashed #7a9bff; background: transparent; display: flex; flex-direction: column; }
/* The drop zone FILLS the container — a 50vh container is droppable across
   all 50vh, not just the strip its content happens to occupy. */
/* The drop-list wrapper is SIZE-TRANSPARENT: it fills the container in
   every display mode so percentage chains behave as if children were direct
   descendants — exactly like the rendered page (which has no wrapper). */
.bkw-container > [data-bkw-list] { flex: 1 1 auto; height: 100%; }
/* Nested targeting: only the INNERMOST hovered block lights up — its
   ancestors stay quiet, so any block at any depth is precisely pickable. */
.bkw-node:hover:not(:has(.bkw-node:hover)) { outline: 1px solid rgba(47,107,255,.35); }
/* Hover preview (paint-stack aware, set by JS): the block a click WOULD
   select — padded wrappers reveal themselves before you commit. */
.bkw-node.bkw-hot { outline: 1.5px dashed #818cf8; outline-offset: -1px; position: relative; }
.bkw-node.bkw-hot > .bkw-tag { opacity: 1; }
/* The matching row in the layers panel lights up with the canvas hover. */
.bkw-hot-layer > div:first-child, .bkw-hot-layer > button:first-child { background: rgba(99,102,241,.12); border-radius: 7px; }
.bkw-hot-layer { outline: 1px dashed rgba(99,102,241,.5); outline-offset: -1px; border-radius: 7px; }
.bkw-node.bkw-selected { outline: 2px solid #2f6bff !important; }
/* Breadcrumb: the selected block's ancestor chain — sticky over the canvas,
   tap a chip to select that wrapper (the touch-screen selection path). */
.bkw-crumbs { position: sticky; bottom: 10px; z-index: 2147483100; display: flex; flex-wrap: wrap; align-items: center; gap: 3px;
    width: fit-content; max-width: calc(100% - 24px); margin: 10px auto 0; padding: 5px 9px; border-radius: 12px;
    background: rgba(17,19,28,.88); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,.1); box-shadow: 0 10px 30px -10px rgba(0,0,0,.5); }
.bkw-crumb { border: 0; background: transparent; color: #aab3c7; font-size: 11px; font-weight: 600; padding: 3px 7px; border-radius: 8px; cursor: pointer; }
.bkw-crumb:hover { background: rgba(255,255,255,.1); color: #fff; }
.bkw-crumb-on { background: #4f46e5; color: #fff; }
.bkw-crumb-on:hover { background: #4338ca; color: #fff; }
.bkw-crumb-type { margin-left: 4px; font-size: 9px; font-weight: 500; opacity: .55; font-family: ui-monospace, monospace; }
.bkw-crumb-sep { color: #5a6378; font-size: 10px; }
/* FOUNDATION: the selected block is LIFTED above every backdrop/stacking
   trick while selected — so it is always hoverable, draggable and actionable.
   Editor-only; saved styles and the rendered site are untouched. */
.bkw-node.bkw-selected { z-index: 2147483000 !important; }
/* …and every ancestor of the selection lifts too — otherwise a backdrop
   parent (z-index:-1 stacking context) would keep trapping its children. */
.bkw-node:has(.bkw-selected) { z-index: 2147482999 !important; position: relative; }
.bkw-node.bkw-selected.bkw-container { border-color: #2f6bff; }
.bkw-ro { opacity: .55; pointer-events: none; }
.bkw-ro .bkw-tag { display: none !important; }
.bkw-tag {
    position: absolute; top: -1px; left: -1px; transform: translateY(-100%);
    background: #8fa8e8; color: #fff; font-family: ui-monospace, monospace;
    font-size: 9px; font-weight: 600; letter-spacing: .06em; padding: 2px 6px;
    border-radius: 4px 4px 0 0; display: none; z-index: 20; white-space: nowrap; pointer-events: none;
}
.bkw-node:hover:not(:has(.bkw-node:hover)) > .bkw-tag { display: block; }
.bkw-node.bkw-selected > .bkw-tag { display: block; background: #2f6bff; }
.bkw-anchor {
    position: absolute; top: 4px; left: 4px; z-index: 60; pointer-events: auto;
    display: inline-flex; align-items: center; gap: 4px; cursor: pointer;
    background: #4338ca; color: #fff; font-family: ui-monospace, monospace;
    font-size: 9.5px; font-weight: 700; letter-spacing: .05em; padding: 3px 8px;
    border-radius: 6px; box-shadow: 0 2px 8px rgba(30,30,90,.35);
}
.bkw-anchor.bkw-dragover { outline: 2px solid #22c55e; background: #16a34a; }
.bkw-chip {
    position: absolute; top: 6px; right: 6px; background: #14161f; color: #ffb454;
    font-family: ui-monospace, monospace; font-size: 9px; padding: 3px 7px;
    border-radius: 5px; letter-spacing: .08em; z-index: 3;
}
.bkw-actions {
    position: absolute; top: -1px; right: -1px; transform: translateY(-100%);
    display: none; gap: 2px; z-index: 21;
}
.bkw-node:hover:not(:has(.bkw-node:hover)) > .bkw-actions, .bkw-node.bkw-selected > .bkw-actions { display: inline-flex; }
.bkw-actions button {
    width: 20px; height: 18px; border: 0; border-radius: 4px 4px 0 0; cursor: pointer;
    background: #2f6bff; color: #fff; font-size: 10px; line-height: 1;
}
.bkw-actions button:hover { background: #1e50d6; }
.bkw-actions button.bkw-del:hover { background: #e2445c; }
.bkw-instance { border: 1.5px solid #34d39955; }
.bkw-instance:hover { outline-color: rgba(16,185,129,.4); }
.bkw-instance.bkw-selected { outline-color: #10b981 !important; }
.bkw-fill { border-style: dashed; border-color: #34d399; }
/* The slot area + prop content of a live instance stay INTERACTIVE even when
   surrounding component-owned blocks are read-only (pointer-events:none). */
.bkw-fill, .bkw-fill * { pointer-events: auto; }
.bkw-prop-live { pointer-events: auto !important; cursor: pointer; }
.bkw-prop-live:hover { outline: 1px dashed #d97706; outline-offset: 1px; }
.bkw-editing { outline: 2px solid #10b981 !important; outline-offset: 2px; cursor: text; min-width: 40px; }
.bkw-rtbar { position: fixed; z-index: 2147483200; display: flex; gap: 2px; padding: 3px;
  background: #14161f; border-radius: 8px; box-shadow: 0 6px 24px rgba(0,0,0,.35); }
.bkw-rtbar button { min-width: 26px; height: 24px; border: 0; border-radius: 5px; background: transparent;
  color: #e8eaf2; font-size: 12px; cursor: pointer; }
.bkw-rtbar button:hover { background: rgba(255,255,255,.14); }
.bkw-rtbar #bkwRtClass { width: 84px; height: 24px; border: 0; border-radius: 5px; margin-left: 2px;
  background: rgba(255,255,255,.1); color: #e8eaf2; font-size: 11px; padding: 0 7px; outline: none; }
.bkw-rtbar #bkwRtClass::placeholder { color: #9aa0b4; }
.bkw-linkdlg { position: fixed; z-index: 2147483300; width: 430px; background: #fff; border: 1px solid #dfe3ee;
  border-radius: 12px; box-shadow: 0 18px 60px rgba(15,20,60,.25); padding: 12px; font-size: 12px; color: #1a1c22; }
.bkw-linkdlg-title { font-weight: 700; margin-bottom: 8px; }
.bkw-linkdlg-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; max-height: 220px; overflow-y: auto; }
.bkw-linkdlg-h { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: #9aa0b4; margin-bottom: 4px; }
.bkw-linkdlg button[data-bkw-linkurl] { display: flex; align-items: center; gap: 6px; width: 100%; text-align: left;
  padding: 5px 7px; border: 0; background: transparent; border-radius: 7px; cursor: pointer; font-size: 12px; }
.bkw-linkdlg button[data-bkw-linkurl]:hover { background: #eef2ff; }
.bkw-linkdlg button[data-bkw-linkurl] span { color: #9aa0b4; font-family: ui-monospace, monospace; font-size: 10px; }
.bkw-linkdlg button[data-bkw-linkurl] img { width: 26px; height: 20px; object-fit: cover; border-radius: 4px; flex: none; }
.bkw-linkdlg-row { display: flex; gap: 6px; margin-top: 10px; }
.bkw-linkdlg-row input { flex: 1; border: 1px solid #dfe3ee; border-radius: 8px; padding: 6px 9px; font-size: 12px; }
.bkw-linkdlg-row button { border: 0; border-radius: 8px; padding: 6px 12px; font-weight: 600; cursor: pointer; }
#bkwLinkApply { background: #4f46e5; color: #fff; }
#bkwLinkCancel { background: #eef0f6; color: #3a3f4e; }
.bkw-linkdlg-empty { color: #9aa0b4; font-size: 11px; }
.bkw-dragging { opacity: .35; }
.bkw-dragover { outline: 2px solid #2f6bff; outline-offset: -1px; background: rgba(47,107,255,.06); }
.bkw-insert-bar { position: relative; height: 0; pointer-events: none; z-index: 30; }
.bkw-insert-bar::after {
    content: ""; position: absolute; left: 0; right: 0; top: -2px; height: 4px;
    border-radius: 2px; background: #2f6bff; box-shadow: 0 0 0 2px rgba(47,107,255,.25);
}
</style>
@endassets