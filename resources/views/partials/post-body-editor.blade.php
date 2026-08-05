{{--
    Post body editor — Quill 2 (battle-tested rich text editor) + our Media
    lightbox. Quill owns the toolbar, selection and formatting entirely, so
    select-text → toolbar-button toggling works identically in every browser.

    Toolbar: H1–H4 · B I U S · lists · quote · link · 🖼 media (lightbox) · video · clean
    The body is stored as HTML. Legacy bodies (plain text / old grid JSON)
    are converted on open.
--}}
<div x-data="postQuill($wire, @js($mediaAssets ?? []))" class="pbe relative rounded-xl border border-gray-200 dark:border-white/[0.08] overflow-hidden">

    {{-- Quill mounts here (it injects its own toolbar above the editor) --}}
    <div x-ref="editor" class="bg-white dark:bg-[#181924]"></div>

    {{-- ── Media lightbox — pick an asset from the site's Media library ── --}}
    <div x-show="picker" x-cloak class="absolute inset-0 z-20 flex flex-col bg-white/95 dark:bg-[#14151f]/95 backdrop-blur-sm">
        <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-200 dark:border-white/[0.08]">
            <p class="text-sm font-bold text-gray-800 dark:text-gray-100">Insert from Media</p>
            <input x-model="pickerSearch" type="text" placeholder="Search media…"
                   class="flex-1 max-w-xs text-xs rounded-lg bg-gray-50 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] px-3 py-1.5 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
            <span class="flex-1"></span>
            <a href="{{ url($site->name.'/media') }}" target="_blank" class="text-[11px] font-semibold text-indigo-500 hover:underline">Open Media library ↗</a>
            <button type="button" @click="picker = false" class="px-2 py-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm" title="Close">✕</button>
        </div>
        <div class="flex-1 overflow-y-auto p-4">
            <template x-if="!filteredAssets().length">
                <p class="text-xs text-gray-400 text-center py-10">
                    No media found — upload images or videos in the Media library first.
                </p>
            </template>
            <div class="grid grid-cols-4 sm:grid-cols-5 gap-3">
                <template x-for="a in filteredAssets()" :key="a.id">
                    <button type="button" @click="insertAsset(a)"
                            class="group rounded-xl overflow-hidden border border-gray-200 dark:border-white/[0.08] hover:border-indigo-400 hover:shadow-md transition-all text-left">
                        <div class="aspect-square bg-gray-100 dark:bg-white/[0.04] grid place-items-center overflow-hidden">
                            <img x-show="a.type === 'image'" :src="a.url" :alt="a.name" loading="lazy"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform">
                            <span x-show="a.type !== 'image'" class="text-2xl">🎬</span>
                        </div>
                        <p class="px-2 py-1.5 text-[10px] font-medium text-gray-500 dark:text-gray-400 truncate" x-text="a.name"></p>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <style>
        /* Editor sizing + house typography inside Quill */
        .pbe .ql-container { min-height: 300px; max-height: 48vh; overflow-y: auto; font-size: 15px; font-family: inherit; border: 0; }
        .pbe .ql-toolbar { border: 0; border-bottom: 1px solid #e5e7eb; background: #f9fafb; }
        .pbe .ql-editor { min-height: 300px; line-height: 1.7; }
        .pbe .ql-editor h1 { font-size: 1.8rem; font-weight: 800; }
        .pbe .ql-editor h2 { font-size: 1.45rem; font-weight: 700; }
        .pbe .ql-editor h3 { font-size: 1.2rem; font-weight: 700; }
        .pbe .ql-editor h4 { font-size: 1.05rem; font-weight: 600; }
        .pbe .ql-editor img { max-width: 100%; border-radius: 12px; }
        .pbe .ql-editor iframe.ql-video { width: 100%; aspect-ratio: 16/9; border-radius: 12px; }
        /* Dark mode */
        .dark .pbe .ql-toolbar { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.08); }
        .dark .pbe .ql-editor { color: #e5e7eb; }
        .dark .pbe .ql-editor.ql-blank::before { color: #6b7280; }
        .dark .pbe .ql-toolbar .ql-stroke { stroke: #9aa1b4; }
        .dark .pbe .ql-toolbar .ql-fill { fill: #9aa1b4; }
        .dark .pbe .ql-toolbar .ql-picker-label { color: #9aa1b4; }
        .pbe .ql-toolbar button:hover .ql-stroke, .pbe .ql-toolbar button.ql-active .ql-stroke { stroke: #4f46e5; }
        .pbe .ql-toolbar button:hover .ql-fill, .pbe .ql-toolbar button.ql-active .ql-fill { fill: #4f46e5; }

        /* ── Injected table toolbar buttons ── */
        .pbe .pbe-tbtn { width: auto !important; min-width: 26px; padding: 0 5px !important; font-size: 12px; font-weight: 700;
                         color: #5b6172; border-radius: 6px; }
        .pbe .pbe-tbtn:hover { color: #4f46e5; background: #eef0f6; }
        .dark .pbe .pbe-tbtn { color: #9aa1b4; }
        .dark .pbe .pbe-tbtn:hover { background: rgba(255,255,255,.08); }

        /* ── Formatted tables (the SAME classes render on the published post) ── */
        .pbe .ql-editor table { width: 100%; border-collapse: collapse; margin: .8em 0; table-layout: fixed; }
        .pbe .ql-editor td { border: 1px solid #e2e5ee; padding: 9px 12px; vertical-align: top; }
        .dark .pbe .ql-editor td { border-color: rgba(255,255,255,.12); }
        /* alternate ROW background */
        .pbe .ql-editor table.pbe-t-rows tr:nth-child(even) td { background: #f4f6fb; }
        .dark .pbe .ql-editor table.pbe-t-rows tr:nth-child(even) td { background: rgba(255,255,255,.04); }
        /* alternate COLUMN background */
        .pbe .ql-editor table.pbe-t-cols td:nth-child(even) { background: #f4f6fb; }
        .dark .pbe .ql-editor table.pbe-t-cols td:nth-child(even) { background: rgba(255,255,255,.04); }
        /* bold header / footer rows */
        .pbe .ql-editor table.pbe-t-head tr:first-child td { font-weight: 700; background: #eef1f8; border-bottom: 2px solid #cfd6e6; }
        .dark .pbe .ql-editor table.pbe-t-head tr:first-child td { background: rgba(255,255,255,.07); border-bottom-color: rgba(255,255,255,.2); }
        .pbe .ql-editor table.pbe-t-foot tr:last-child td { font-weight: 700; background: #eef1f8; border-top: 2px solid #cfd6e6; }
        .dark .pbe .ql-editor table.pbe-t-foot tr:last-child td { background: rgba(255,255,255,.07); border-top-color: rgba(255,255,255,.2); }
    </style>
</div>
