<form method="POST" action="{{ route('memberships.public.login', $site->name) }}" class="mt-5 space-y-3">
    @csrf
    <label class="block text-[13px] font-semibold text-gray-700 dark:text-gray-200" for="mbr-email">Email</label>
    <input id="mbr-email" type="email" name="email" required value="{{ old('email') }}" autocomplete="email"
           class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#12131b] px-4 py-2.5 text-sm">
    @error('email')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
    <button class="w-full px-5 py-3 rounded-xl text-sm font-semibold bg-gray-900 text-white dark:bg-white dark:text-gray-900">Email me a sign-in link</button>
</form>
