{{--
    Ambient app background: 5 soft elements — unique color + unique size per
    roll — scattered across the frame. At the start of EVERY interval each
    element is dealt a new random position, size AND color, and softly
    transitions into that new state (easing lives in app.css .bg-ambient).
--}}
@php
    $palette = ['#dd6119', '#b79df0', '#f291bb', '#1164a8', '#160b3d', '#416d08', '#803249', '#f7bb09', '#8f78f8', '#f2f2f2', '#9e0c0c', '#2a6464', '#93949e', '#211d15'];
    $sizes = [3, 10, 19, 26, 38, 52]; // rem — all distinct

    // Initial deal: shuffle both decks so no two elements share a color or size.
    $colors = $palette;
    shuffle($colors);
    $colors = array_slice($colors, 0, 4);
    $sizeDeal = $sizes;
    shuffle($sizeDeal);
@endphp

<div class="bg-ambient" aria-hidden="true" data-colors='@json($palette)' data-sizes='@json($sizes)'>
    @foreach ($colors as $i => $color)
        <span style="top:{{ rand(5, 95) }}%;left:{{ rand(5, 95) }}%;width:{{ $sizeDeal[$i] }}rem;height:{{ $sizeDeal[$i] }}rem;background:{{ $color }};"></span>
    @endforeach
</div>

<script>
    (function () {
        // One timer re-deals every ambient layer on the page, however many
        // are rendered (layout + page surfaces).
        if (window.__oluxAmbientRoam) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        var shuffle = function (a) {
            for (var i = a.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1)), t = a[i]; a[i] = a[j]; a[j] = t;
            }
            return a;
        };

        // Two alternating phases so the morph always happens IN PLACE:
        //   move  — glide to a new position, keeping current color & size
        //   morph — stay put, softly transition to a new color & size
        var moving = true;
        var tick = function () {
            document.querySelectorAll('.bg-ambient').forEach(function (layer) {
                if (moving) {
                    layer.querySelectorAll('span').forEach(function (b) {
                        b.style.top = (5 + Math.random() * 90) + '%';
                        b.style.left = (5 + Math.random() * 90) + '%';
                    });
                    return;
                }

                var colors, sizes;
                try {
                    colors = shuffle(JSON.parse(layer.dataset.colors || '[]').slice());
                    sizes = shuffle(JSON.parse(layer.dataset.sizes || '[]').slice());
                } catch (e) { return; }

                // Fresh shuffled decks per roll → still never a duplicate
                // color or size among the visible elements.
                layer.querySelectorAll('span').forEach(function (b, i) {
                    if (sizes[i] !== undefined) {
                        b.style.width = sizes[i] + 'rem';
                        b.style.height = sizes[i] + 'rem';
                    }
                    if (colors[i] !== undefined) {
                        b.style.background = colors[i];
                    }
                });
            });
            moving = ! moving;
        };

        window.__oluxAmbientRoam = setInterval(tick, 9000); // each 8s CSS ease finishes before the next phase
    })();
</script>
