@props(['icon' => 'inbox', 'message' => 'Nothing here yet.'])

<div class="flex flex-col items-center justify-center py-16 text-center">
    <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-white/[0.05] flex items-center justify-center mb-4">
        <x-dynamic-component :component="'icons.'.$icon" class="w-6 h-6 text-gray-400 dark:text-gray-500" />
    </div>
    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 max-w-xs">{{ $message }}</p>
</div>
