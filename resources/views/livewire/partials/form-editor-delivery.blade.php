{{-- Delivery channel toggles. Uses fbDelivery + $channels. --}}
            {{-- ── Delivery channels ─────────────────────────────── --}}
            <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-5 space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Delivery</h3>
                    <p class="mt-1 text-xs text-gray-400">How you're told about a submission. More channels coming soon.</p>
                </div>

                @foreach ($channels as $key => $channel)
                    <div class="rounded-xl border border-gray-100 dark:border-white/[0.06] p-4 space-y-3
                                {{ $channel['implemented'] ? '' : 'opacity-60' }}">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $channel['label'] }}</span>
                                    @unless ($channel['implemented'])
                                        <span class="text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded
                                                     bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">Coming soon</span>
                                    @endunless
                                </div>
                                <p class="mt-0.5 text-xs text-gray-400">{{ $channel['description'] }}</p>
                            </div>
                            @if ($channel['implemented'])
                                <x-field.toggle model="fbDelivery.channels.{{ $key }}.enabled" live />
                            @else
                                <span class="inline-flex h-6 w-11 shrink-0 items-center rounded-full bg-gray-200 dark:bg-white/10">
                                    <span class="inline-block h-4 w-4 translate-x-1 transform rounded-full bg-white/70"></span>
                                </span>
                            @endif
                        </div>

                        {{-- Email sub-options --}}
                        @if ($key === 'email' && ($fbDelivery['channels']['email']['enabled'] ?? false))
                            <div class="pt-1 space-y-2 border-t border-gray-100 dark:border-white/[0.06]">
                                <x-field.check model="fbDelivery.channels.email.notify_visitor" text="Send a receipt to the visitor" />
                                <x-field.check model="fbDelivery.channels.email.notify_admin" text="Alert an admin of new submissions" />
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Admin address (optional)</label>
                                    <x-field.text model="fbDelivery.channels.email.admin_address" type="email"
                                                  placeholder="Defaults to the site owner" />
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

