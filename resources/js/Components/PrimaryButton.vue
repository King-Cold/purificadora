<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-900 px-4 py-2.5 sm:py-2 text-xs sm:text-sm font-bold text-white shadow-xs transition-all duration-150 hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-900/30 focus:ring-offset-2 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 cursor-pointer"
    >
        <slot />
    </button>
</template>
