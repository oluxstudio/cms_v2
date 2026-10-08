{{-- Landing testimonial carousel (lazy island). Arrows, dots, keyboard, autoplay
     every 5 s — paused on hover/focus and when the visitor prefers less motion. --}}
<div>
    <div class="carousel" role="region" aria-roledescription="carousel" aria-label="Customer testimonials"
         x-data="{
            i: 0, n: {{ count($slides) }}, timer: null,
            reduce: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            go(k) { this.i = (k + this.n) % this.n; this.restart(); },
            restart() { clearInterval(this.timer); if (! this.reduce && this.n > 1) this.timer = setInterval(() => this.go(this.i + 1), 5000); },
            pause() { clearInterval(this.timer); },
         }"
         x-init="restart()" @mouseenter="pause()" @mouseleave="restart()" @focusin="pause()" @focusout="restart()"
         @keydown.arrow-left.prevent="go(i - 1)" @keydown.arrow-right.prevent="go(i + 1)">
        <div class="car-view">
            <div class="car-track" :style="`transform: translateX(-${i * 100}%)`">
                @foreach ($slides as $k => $tm)
                    <div class="slide" role="group" aria-roledescription="slide" aria-label="{{ $k + 1 }} of {{ count($slides) }}"
                         :aria-hidden="i !== {{ $k }} ? 'true' : 'false'">
                        <div class="box">
                            @if (! empty($tm['rating']))<p class="stars" aria-label="{{ $tm['rating'] }} out of 5 stars">{{ str_repeat('★', $tm['rating']) }}</p>@endif
                            <q>{{ $tm['quote'] }}</q>
                            <span class="who"><span class="ava" aria-hidden="true">{{ $tm['initials'] }}</span><span><b>{{ $tm['name'] }}</b>@if (! empty($tm['role']))<small>{{ $tm['role'] }}</small>@endif</span></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @if (count($slides) > 1)
            <button type="button" class="car-btn car-prev" @click="go(i - 1)" aria-label="Previous testimonial">‹</button>
            <button type="button" class="car-btn car-next" @click="go(i + 1)" aria-label="Next testimonial">›</button>
            <div class="dots">
                @foreach ($slides as $k => $tm)
                    <button type="button" @click="go({{ $k }})" :class="i === {{ $k }} ? 'on' : ''" aria-label="Show testimonial {{ $k + 1 }}"></button>
                @endforeach
            </div>
        @endif
    </div>
    @if ($illustrative)
        <p style="text-align:center;font-size:16px;color:var(--muted);margin-top:14px">* Illustrative examples of how teams use Olux.</p>
    @endif
</div>
