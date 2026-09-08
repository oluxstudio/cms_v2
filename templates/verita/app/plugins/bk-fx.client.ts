/**
 * GENERATED from App\Support\Fx by nuxt:preview-build — do not edit here.
 * The effects engine (enter/leave animations, click FX, parallax) is defined
 * ONCE in app/Support/Fx.php; editing it updates every surface on rebuild.
 */
export default defineNuxtPlugin(() => {
  if (typeof window === 'undefined') return
  const style = document.createElement('style');
  style.textContent = FX_CSS;
  document.head.appendChild(style);
(function () {
    if (window.__bkFx) { window.__bkFxScan && window.__bkFxScan(); return; }
    window.__bkFx = 1;
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var el = entry.target;
            if (entry.isIntersecting) {
                el.classList.add('fx-in');
                el.classList.remove('fx-out');
            } else if (el.classList.contains('fx-in')) {
                if (el.dataset.fxLeave) el.classList.add('fx-out');
                // Reset so the enter animation replays next time.
                if (el.dataset.fxEnter) el.classList.remove('fx-in');
            }
        });
    }, { threshold: 0.12 });

    var scan = function () {
        document.querySelectorAll('[data-fx-enter],[data-fx-leave],[data-fx-click],[data-fx-parallax]').forEach(function (el) {
            if (el.__bkFx) return;
            el.__bkFx = 1;
            if (el.dataset.fxDuration) el.style.setProperty('--fx-dur', (parseInt(el.dataset.fxDuration, 10) || 600) + 'ms');
            if (el.dataset.fxDelay) el.style.setProperty('--fx-delay', (parseInt(el.dataset.fxDelay, 10) || 0) + 'ms');
            if (el.dataset.fxEnter || el.dataset.fxLeave) io.observe(el);
            if (el.dataset.fxClick) el.addEventListener('click', function () {
                var cls = 'fx-click-' + el.dataset.fxClick;
                el.classList.remove(cls); void el.offsetWidth; el.classList.add(cls);
            });
        });
    };
    window.__bkFxScan = scan;

    var parallax = function () {
        document.querySelectorAll('[data-fx-parallax]').forEach(function (el) {
            if (el.closest('.bkw-artboard')) return; // canvas: parallax off (drag/hit-testing)
            var speed = (parseFloat(el.dataset.fxParallax) || 0) / 100;
            if (! speed) return;
            var r = el.getBoundingClientRect();
            var offset = (r.top + r.height / 2 - window.innerHeight / 2) * speed;
            el.style.transform = 'translateY(' + Math.round(offset * 10) / 10 + 'px)';
        });
    };
    var ticking = false;
    window.addEventListener('scroll', function () {
        if (ticking) return; ticking = true;
        requestAnimationFrame(function () { parallax(); ticking = false; });
    }, { passive: true });

    scan(); parallax();
    new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
})();
})

const FX_CSS = `[data-fx-enter]{opacity:0;transition:opacity var(--fx-dur,.6s) ease var(--fx-delay,0s),transform var(--fx-dur,.6s) cubic-bezier(.22,.8,.36,1) var(--fx-delay,0s),filter var(--fx-dur,.6s) ease var(--fx-delay,0s)}
[data-fx-enter="slide-up"]{transform:translateY(32px)}
[data-fx-enter="slide-down"]{transform:translateY(-32px)}
[data-fx-enter="slide-left"]{transform:translateX(32px)}
[data-fx-enter="slide-right"]{transform:translateX(-32px)}
[data-fx-enter="zoom-in"]{transform:scale(.9)}
[data-fx-enter="blur-in"]{filter:blur(10px)}
[data-fx-enter].fx-in{opacity:1;transform:none;filter:none}
[data-fx-leave].fx-out{opacity:0;transition:opacity var(--fx-dur,.6s) ease,transform var(--fx-dur,.6s) ease,filter var(--fx-dur,.6s) ease}
[data-fx-leave="slide-up"].fx-out{transform:translateY(-32px)}
[data-fx-leave="slide-down"].fx-out{transform:translateY(32px)}
[data-fx-leave="slide-left"].fx-out{transform:translateX(-32px)}
[data-fx-leave="slide-right"].fx-out{transform:translateX(32px)}
[data-fx-leave="zoom-out"].fx-out{transform:scale(.9)}
[data-fx-leave="blur-out"].fx-out{filter:blur(10px)}
@keyframes fxPulse{0%{transform:scale(1)}50%{transform:scale(1.06)}100%{transform:scale(1)}}
@keyframes fxBounce{0%,100%{transform:translateY(0)}30%{transform:translateY(-14px)}60%{transform:translateY(0)}80%{transform:translateY(-6px)}}
@keyframes fxShake{0%,100%{transform:translateX(0)}20%{transform:translateX(-8px)}40%{transform:translateX(8px)}60%{transform:translateX(-5px)}80%{transform:translateX(5px)}}
@keyframes fxFlip{0%{transform:perspective(600px) rotateY(0)}100%{transform:perspective(600px) rotateY(360deg)}}
@keyframes fxPop{0%{transform:scale(1)}40%{transform:scale(.94)}70%{transform:scale(1.04)}100%{transform:scale(1)}}
.fx-click-pulse{animation:fxPulse .45s ease}
.fx-click-bounce{animation:fxBounce .6s ease}
.fx-click-shake{animation:fxShake .5s ease}
.fx-click-flip{animation:fxFlip .7s ease}
.fx-click-pop{animation:fxPop .4s ease}
[data-fx-click]{cursor:pointer}
@media (prefers-reduced-motion: reduce){[data-fx-enter]{opacity:1;transform:none;filter:none;transition:none}[data-fx-parallax]{transform:none!important}}`

