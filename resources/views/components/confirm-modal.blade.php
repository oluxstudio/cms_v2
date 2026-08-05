{{--
    FOUNDATION confirm/alert modal — replaces every native confirm()/alert().

    · Buttons carry `data-confirm="message"` (+ their wire:click/@click as
      usual): a capture-phase interceptor pauses the click, shows this modal,
      and re-fires the click only when the user confirms.
    · window.bkConfirm(msg [, {title, okLabel, danger, alert}]) → Promise<boolean>
      for programmatic use (canvas JS, Alpine).
    · window.alert(msg) is overridden to show the modal in info mode.

    Design: dark glass card, centered icon badge → title → muted copy →
    action row (primary + Cancel). Include ONCE per layout.
--}}
<div id="bk-confirm-root">
    <style>
        #bk-confirm-backdrop{position:fixed;inset:0;z-index:2147483200;background:rgba(8,10,16,.62);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);display:none;place-items:center;padding:24px}
        #bk-confirm-backdrop.bkc-open{display:grid}
        .bkc-card{width:min(400px,92vw);border-radius:22px;padding:30px 28px 26px;text-align:center;color:#eef1f7;
            background:linear-gradient(160deg,rgba(38,41,52,.94),rgba(21,23,31,.96));
            border:1px solid rgba(255,255,255,.09);
            box-shadow:0 32px 90px -18px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.06);
            backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
            animation:bkc-pop .16s ease-out}
        @keyframes bkc-pop{from{transform:scale(.94);opacity:0}to{transform:scale(1);opacity:1}}
        .bkc-badge{width:46px;height:46px;border-radius:50%;margin:0 auto 16px;display:grid;place-items:center;background:#e11d48;box-shadow:0 8px 24px -6px rgba(225,29,72,.55)}
        .bkc-badge.bkc-info{background:#4f46e5;box-shadow:0 8px 24px -6px rgba(79,70,229,.55)}
        .bkc-badge svg{width:20px;height:20px;color:#fff}
        #bk-confirm-title{font-size:16px;font-weight:800;letter-spacing:.01em;margin:0 0 8px;color:#fff}
        #bk-confirm-msg{font-size:13px;line-height:1.65;color:#98a0b3;margin:0 auto 22px;max-width:34ch}
        .bkc-actions{display:flex;gap:10px;justify-content:center}
        .bkc-btn{border:0;border-radius:10px;padding:9px 18px;font-size:12.5px;font-weight:700;cursor:pointer;letter-spacing:.01em;transition:filter .12s, background .12s}
        #bk-confirm-ok{background:#e11d48;color:#fff}
        #bk-confirm-ok.bkc-info{background:#4f46e5}
        #bk-confirm-ok:hover{filter:brightness(1.12)}
        #bk-confirm-cancel{background:rgba(255,255,255,.08);color:#e5e9f2;border:1px solid rgba(255,255,255,.12)}
        #bk-confirm-cancel:hover{background:rgba(255,255,255,.14)}
    </style>

    <div id="bk-confirm-backdrop">
        <div class="bkc-card" role="alertdialog" aria-modal="true" aria-labelledby="bk-confirm-title">
            <div class="bkc-badge" id="bk-confirm-badge">
                {{-- trash (danger) --}}
                <svg id="bk-confirm-icon-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                {{-- info (notice / neutral confirm) --}}
                <svg id="bk-confirm-icon-info" style="display:none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 id="bk-confirm-title">Are you sure?</h3>
            <p id="bk-confirm-msg"></p>
            <div class="bkc-actions">
                <button type="button" id="bk-confirm-ok" class="bkc-btn">Continue</button>
                <button type="button" id="bk-confirm-cancel" class="bkc-btn">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    if (window.bkConfirm) return; // one instance per page

    const backdrop = document.getElementById('bk-confirm-backdrop');
    const badge    = document.getElementById('bk-confirm-badge');
    const iconDanger = document.getElementById('bk-confirm-icon-danger');
    const iconInfo   = document.getElementById('bk-confirm-icon-info');
    const titleEl  = document.getElementById('bk-confirm-title');
    const msgEl    = document.getElementById('bk-confirm-msg');
    const okBtn    = document.getElementById('bk-confirm-ok');
    const cancel   = document.getElementById('bk-confirm-cancel');
    let resolver = null;

    const close = (result) => {
        backdrop.classList.remove('bkc-open');
        const r = resolver; resolver = null;
        if (r) r(result);
    };

    window.bkConfirm = (message, opts = {}) => new Promise((resolve) => {
        if (resolver) resolver(false); // a new dialog supersedes a pending one
        resolver = resolve;
        const danger = opts.danger ?? true;
        titleEl.textContent = opts.title ?? (danger ? 'Are you sure?' : 'Please confirm');
        msgEl.textContent = String(message ?? '');
        okBtn.textContent = opts.okLabel ?? 'Continue';
        badge.classList.toggle('bkc-info', ! danger);
        okBtn.classList.toggle('bkc-info', ! danger);
        iconDanger.style.display = danger ? '' : 'none';
        iconInfo.style.display = danger ? 'none' : '';
        cancel.style.display = opts.alert ? 'none' : '';
        backdrop.classList.add('bkc-open');
        okBtn.focus();
    });

    okBtn.addEventListener('click', () => close(true));
    cancel.addEventListener('click', () => close(false));
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(false); });
    document.addEventListener('keydown', (e) => {
        if (! backdrop.classList.contains('bkc-open')) return;
        if (e.key === 'Escape') { e.stopPropagation(); close(false); }
        if (e.key === 'Enter')  { e.stopPropagation(); close(true); }
    }, true);

    // data-confirm interceptor: pause the click, ask, re-fire on confirm.
    document.addEventListener('click', (e) => {
        const t = e.target.closest?.('[data-confirm]');
        if (! t || t.dataset.bkConfirmed) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        const destructive = /delete|remove|discard|revoke|detach|cancel this/i.test(t.dataset.confirm);
        const title = /delete/i.test(t.dataset.confirm) ? 'Delete?' : (destructive ? 'Are you sure?' : 'Please confirm');
        window.bkConfirm(t.dataset.confirm, { danger: destructive, title, okLabel: 'Continue' })
            .then((ok) => {
                if (! ok) return;
                t.dataset.bkConfirmed = '1';
                // Re-fire as a full pointer sequence — wire:click listeners in
                // Alpine-templated panes don't react to a bare .click().
                const r = t.getBoundingClientRect();
                for (const type of ['pointerdown', 'mousedown', 'pointerup', 'mouseup', 'click']) {
                    t.dispatchEvent(new MouseEvent(type, { bubbles: true, cancelable: true, clientX: r.x + r.width / 2, clientY: r.y + r.height / 2 }));
                }
                delete t.dataset.bkConfirmed;
            });
    }, true);

    // Informational alerts go through the same modal (OK only).
    window.alert = (message) => { window.bkConfirm(message, { title: 'Notice', okLabel: 'OK', alert: true, danger: false }); };
})();
</script>
