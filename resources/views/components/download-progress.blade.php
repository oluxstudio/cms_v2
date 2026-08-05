{{--
    REUSABLE download-with-progress overlay.

    Include once per layout (already in x-layouts.selected), then mark ANY
    link:  <a href="…/pdf" data-download-progress data-filename="INV-0001.pdf"
              data-label="Preparing your invoice…">Download</a>

    Phase 1 (server generating the file): indeterminate sweeping bar.
    Phase 2 (bytes streaming): real percentage from Content-Length.
    Uses the app theme tokens (var(--primary)) so it fits every page.
--}}
<div id="dlp-overlay" class="fixed inset-0 z-[80] hidden place-items-center p-6"
     style="background:rgba(10,10,12,.55); backdrop-filter:blur(4px)">
    <div class="w-full max-w-xs bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.08] shadow-2xl p-5 text-center">
        <p id="dlp-label" class="text-sm font-bold text-gray-900 dark:text-white">Preparing file…</p>
        <div class="mt-3 h-2.5 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.08]">
            <div id="dlp-bar" class="h-full rounded-full transition-[width] duration-200"
                 style="width:8%; background:var(--primary)"></div>
        </div>
        <p id="dlp-pct" class="mt-2 text-[11px] font-semibold text-gray-400">Working…</p>
    </div>
</div>
<style>
    /* indeterminate sweep while the server is still generating */
    #dlp-bar.dlp-indeterminate { width: 35% !important; animation: dlp-sweep 1.1s ease-in-out infinite; }
    @keyframes dlp-sweep { 0% { margin-left: -35%; } 100% { margin-left: 100%; } }
</style>
<script>
(function () {
    'use strict';
    const overlay = document.getElementById('dlp-overlay');
    const bar = document.getElementById('dlp-bar');
    const label = document.getElementById('dlp-label');
    const pct = document.getElementById('dlp-pct');
    if (!overlay || window.__dlpBound) return;
    window.__dlpBound = true;

    const show = (text) => {
        label.textContent = text;
        pct.textContent = 'Working…';
        bar.classList.add('dlp-indeterminate');
        bar.style.width = '35%';
        overlay.classList.remove('hidden');
        overlay.classList.add('grid');
    };
    const progress = (loaded, total) => {
        bar.classList.remove('dlp-indeterminate');
        bar.style.marginLeft = '0';
        if (total > 0) {
            const p = Math.min(100, Math.round(loaded / total * 100));
            bar.style.width = p + '%';
            pct.textContent = 'Downloading… ' + p + '%';
        } else {
            bar.style.width = '90%';
            pct.textContent = 'Downloading… ' + (loaded / 1024 | 0) + ' KB';
        }
    };
    const hide = (ok) => {
        bar.classList.remove('dlp-indeterminate');
        bar.style.marginLeft = '0';
        bar.style.width = '100%';
        pct.textContent = ok ? 'Done ✓' : 'Failed — try again';
        setTimeout(() => { overlay.classList.add('hidden'); overlay.classList.remove('grid'); bar.style.width = '8%'; }, ok ? 550 : 1800);
    };

    // Global downloader — also callable directly: window.downloadWithProgress(url, filename, label)
    window.downloadWithProgress = async function (url, filename, text) {
        show(text || 'Preparing file…');
        try {
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const total = +(res.headers.get('Content-Length') || 0);
            const reader = res.body.getReader();
            const chunks = [];
            let loaded = 0;
            for (;;) {
                const { done, value } = await reader.read();
                if (done) break;
                chunks.push(value);
                loaded += value.length;
                progress(loaded, total);
            }
            const blob = new Blob(chunks, { type: res.headers.get('Content-Type') || 'application/octet-stream' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = filename || (res.headers.get('Content-Disposition') || '').match(/filename="?([^";]+)/)?.[1] || 'download';
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 4000);
            hide(true);
        } catch (e) {
            console.error('[download-progress]', e);
            hide(false);
        }
    };

    // Any link marked data-download-progress gets the behavior.
    document.addEventListener('click', (e) => {
        const a = e.target.closest('[data-download-progress]');
        if (!a) return;
        e.preventDefault();
        window.downloadWithProgress(a.href, a.dataset.filename || '', a.dataset.label || 'Preparing file…');
    });
})();
</script>
