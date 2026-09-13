<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get an estimate — {{ ucwords(str_replace('-', ' ', $site->name)) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Standalone page: the admin bundle doesn't boot Alpine, so bring it. --}}
    <style>[x-cloak]{display:none !important}</style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased flex items-start justify-center p-6">
    @php
        $payload = $estimators->map(fn ($e) => [
            'slug' => $e->slug,
            'name' => $e->name,
            'fields' => $e->fields->where('type', '!=', 'fixed')->values()->map(fn ($f) => [
                'key' => $f->key, 'label' => $f->label, 'type' => $f->type, 'unit' => $f->unit,
                'required' => (bool) $f->required, 'options' => array_column($f->options ?? [], 'label'),
            ])->all(),
        ])->values();
    @endphp
    <div class="max-w-md w-full bg-white rounded-3xl border border-gray-100 shadow-sm p-8 my-6"
         x-data='{
            estimators: @json($payload),
            current: 0,
            values: {},
            results: [],
            sending: false, sent: "", error: "",
            name: "", email: "", phone: "", notes: "",
            est() { return this.estimators[this.current] || null },
            pick(i) { this.current = i; this.values = {}; this.results = []; this.sent = ""; this.error = "" },
            async quote() {
                if (! this.est()) return;
                try {
                    const r = await fetch("{{ url('api/sites/'.$site->name.'/quote') }}", {
                        method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json" },
                        body: JSON.stringify({ estimator: this.est().slug, fields: this.values }),
                    });
                    const data = await r.json();
                    this.results = r.ok ? (data.results || []) : [];
                } catch (e) { this.results = [] }
            },
            async request() {
                this.sending = true; this.error = "";
                try {
                    const r = await fetch("{{ url('api/sites/'.$site->name.'/estimator/request') }}", {
                        method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json" },
                        body: JSON.stringify({ estimator: this.est().slug, fields: this.values,
                                               name: this.name, email: this.email, phone: this.phone, notes: this.notes }),
                    });
                    const data = await r.json();
                    if (r.ok) { this.sent = data.reference || "OK" } else { this.error = data.message || "Something went wrong — please check your details." }
                } catch (e) { this.error = "Could not send your request — please try again." }
                this.sending = false;
            },
         }'>

        <div class="text-center mb-6">
            <div class="w-14 h-14 rounded-full bg-lime-100 flex items-center justify-center mx-auto mb-4 text-2xl">🧮</div>
            <h1 class="text-2xl font-extrabold tracking-tight">Get an instant estimate</h1>
            <p class="text-sm text-gray-500 mt-1">Answer a few questions — your estimate updates as you type.</p>
        </div>

        <template x-if="estimators.length === 0">
            <p class="text-sm text-gray-400 text-center py-8">No estimators are available yet — check back soon.</p>
        </template>

        {{-- Estimator picker --}}
        <div class="flex flex-wrap gap-1.5 mb-5" x-show="estimators.length > 1">
            <template x-for="(e, i) in estimators" :key="e.slug">
                <button type="button" @click="pick(i)"
                        class="px-3 py-1.5 rounded-full text-xs font-bold border-2 transition-colors"
                        :class="i === current ? 'border-lime-500 bg-lime-50 text-lime-800' : 'border-gray-200 text-gray-600 hover:border-gray-300'"
                        x-text="e.name"></button>
            </template>
        </div>

        {{-- Visitor fields --}}
        <div class="space-y-3.5" x-show="est()">
            <template x-for="f in (est()?.fields || [])" :key="est().slug + f.key">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                        <span x-text="f.label"></span>
                        <span class="text-gray-400 font-normal normal-case" x-show="f.unit" x-text="'(' + f.unit + ')'"></span>
                        <span class="text-rose-500" x-show="f.required">*</span>
                    </label>
                    <template x-if="f.type === 'select'">
                        <select x-model="values[f.key]" @change="quote()"
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-lime-500 bg-white">
                            <option value="">Choose…</option>
                            <template x-for="o in f.options" :key="o"><option :value="o" x-text="o"></option></template>
                        </select>
                    </template>
                    <template x-if="f.type === 'toggle'">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" x-model="values[f.key]" @change="quote()" class="rounded"> Yes
                        </label>
                    </template>
                    <template x-if="f.type === 'text'">
                        <input type="text" x-model="values[f.key]" @input.debounce.400ms="quote()"
                               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-lime-500">
                    </template>
                    <template x-if="f.type === 'number'">
                        <input type="number" min="0" x-model="values[f.key]" @input.debounce.300ms="quote()"
                               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-lime-500">
                    </template>
                </div>
            </template>
        </div>

        {{-- Live results --}}
        <div class="mt-5 pt-4 border-t border-gray-100 space-y-2" x-show="results.length">
            <template x-for="res in results" :key="res.name">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-600" x-text="res.name"></span>
                    <span class="text-sm font-extrabold px-3 py-1 rounded-full bg-lime-100 text-lime-800" x-text="res.formatted"></span>
                </div>
            </template>
        </div>

        {{-- Request the quote --}}
        <div class="mt-5 pt-4 border-t border-gray-100" x-show="results.length && !sent">
            <p class="text-sm font-bold mb-2.5">Happy with it? Request this quote:</p>
            <div class="space-y-2.5">
                <input type="text" x-model="name" placeholder="Your name *" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-lime-500">
                <input type="email" x-model="email" placeholder="Email *" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-lime-500">
                <input type="tel" x-model="phone" placeholder="Phone (optional)" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-lime-500">
                <textarea x-model="notes" rows="2" placeholder="Anything we should know? (optional)" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-lime-500 resize-none"></textarea>
                <p class="text-xs text-rose-600" x-show="error" x-text="error"></p>
                <button type="button" @click="request()" :disabled="sending || !name || !email"
                        class="w-full py-3.5 bg-lime-600 hover:bg-lime-700 disabled:opacity-50 text-white font-semibold rounded-xl transition-colors shadow-sm"
                        x-text="sending ? 'Sending…' : 'Request this quote'"></button>
            </div>
        </div>

        <div class="mt-5 pt-4 border-t border-gray-100 text-center" x-show="sent" x-cloak>
            <span class="text-3xl">✅</span>
            <p class="text-sm font-bold mt-2">Request sent!</p>
            <p class="text-xs text-gray-500 mt-1">Reference <b x-text="sent"></b> — a copy is on its way to your email, and we'll be in touch shortly.</p>
        </div>

        <p class="text-[11px] text-gray-400 text-center mt-6">{{ ucwords(str_replace('-', ' ', $site->name)) }} · powered by Olux</p>
    </div>
</body>
</html>
