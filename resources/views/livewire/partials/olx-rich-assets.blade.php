{{-- Mini rich-text editor (olxRich) script + styles — used by livewire.partials.rich-text.
     Include once per page that renders rich-text fields. --}}
    @assets
    <script>
        // Mini rich-text editor behaviour (used by partials/rich-text.blade.php).
        // Zero-dependency: contenteditable + execCommand, syncing innerHTML to
        // the Livewire path with a light sanitizer.
        window.olxRich = (path) => ({
            clean(html) {
                return html
                    {{-- no \1 backreferences here: Livewire injects @assets via preg_replace,
                         which expands \1 in this text to the matched </head>. --}}
                    .replace(/<script[\s\S]*?<\/script>/gi, '')
                    .replace(/<style[\s\S]*?<\/style>/gi, '')
                    .replace(/<\/(?:script|style)>/gi, '')
                    .replace(/\son\w+="[^"]*"/gi, '')
                    .replace(/\sstyle="[^"]*"/gi, '');
            },
            push(el) { this.$wire.set(path, this.clean(el.innerHTML), false); },
            cmd(name) { document.execCommand(name, false); this.push(this.$el.querySelector('.olx-rt-area')); },
            block(tag) {
                document.execCommand('formatBlock', false, tag);
                this.push(this.$el.querySelector('.olx-rt-area'));
            },
            link() {
                const url = prompt('Link URL (e.g. /contact or https://…)');
                if (url) document.execCommand('createLink', false, url);
            },
            highlight() {
                // Wrap the selection in the template's accent span (<span class="hl">).
                const sel = window.getSelection();
                if (!sel || sel.isCollapsed) return;
                const span = document.createElement('span');
                span.className = 'hl';
                try { sel.getRangeAt(0).surroundContents(span); } catch (e) { /* partial-node selection */ }
                this.push(this.$el.querySelector('.olx-rt-area'));
            },
            pastePlain(e) {
                const text = e.clipboardData?.getData('text/plain') ?? '';
                document.execCommand('insertText', false, text);
            },
        });
    </script>
    @endassets
    <style>
        .olx-in { width:100%; margin-top:2px; padding:.4rem .55rem; font-size:12px; border-radius:8px;
                  background:rgba(0,0,0,.02); border:1px solid rgba(0,0,0,.1); color:inherit; }
        .dark .olx-in { background:rgba(255,255,255,.04); border-color:rgba(255,255,255,.1); }
        /* Side panels (collection entry editor) read a size up. */
        #olx-drawer .olx-in, #olx-drawer .olx-rt-area { font-size:14px; }
        #olx-drawer .olx-in { padding:.55rem .7rem; }
        .olx-rt { margin-top:2px; border:1px solid rgba(0,0,0,.1); border-radius:8px; overflow:hidden; background:rgba(0,0,0,.02); }
        .dark .olx-rt { border-color:rgba(255,255,255,.1); background:rgba(255,255,255,.04); }
        .olx-rt-bar { display:flex; gap:2px; padding:3px 4px; border-bottom:1px solid rgba(0,0,0,.07); }
        .dark .olx-rt-bar { border-color:rgba(255,255,255,.07); }
        .olx-rt-bar button { min-width:22px; height:20px; border:0; border-radius:5px; background:transparent;
            font:600 11px/1 system-ui; color:inherit; cursor:pointer; }
        .olx-rt-bar button:hover { background:rgba(0,0,0,.08); }
        .dark .olx-rt-bar button:hover { background:rgba(255,255,255,.1); }
        .olx-rt-hl { background:var(--primary); color:#fff; border-radius:3px; padding:0 3px; font-size:9px; }
        /* Rich mode is exactly as tall as the textarea it replaces: rows × 1.5em + the textarea's padding + border. */
        textarea.olx-in { line-height:1.5; }
        .olx-rt-area.olx-rt-rows { height:calc(var(--rt-rows) * 1.5em + .8rem + 2px); min-height:0; max-height:none; line-height:1.5; resize:vertical; }
        #olx-drawer .olx-rt-area { padding:.55rem .7rem; }
        #olx-drawer .olx-rt-area.olx-rt-rows { height:calc(var(--rt-rows) * 1.5em + 1.1rem + 2px); }
        .olx-rt-area { min-height:5.2em; max-height:14em; overflow-y:auto; padding:.4rem .55rem; font-size:12px; outline:none; }
        .olx-rt-area:focus { box-shadow:inset 0 0 0 2px rgba(99,102,241,.35); }
        /* Long-form bodies (posts): taller, with readable block formatting. */
        .olx-rt-area.olx-rt-long { min-height:12em; max-height:26em; font-size:12.5px; line-height:1.55; }
        .olx-rt-long h2 { font-size:1.25em; font-weight:800; margin:.6em 0 .3em; }
        .olx-rt-long h3 { font-size:1.08em; font-weight:700; margin:.5em 0 .25em; }
        .olx-rt-long p { margin:.35em 0; }
        .olx-rt-long ul { list-style:disc; padding-left:1.3em; margin:.35em 0; }
        .olx-rt-long ol { list-style:decimal; padding-left:1.3em; margin:.35em 0; }
        .olx-rt-long blockquote { border-left:3px solid var(--primary); padding-left:.6em; color:#6b7280; margin:.4em 0; }
        .olx-rt-long a { color:var(--primary); text-decoration:underline; }
        .olx-rt-area a { color:#6366f1; text-decoration:underline; }
    </style>
