<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message" class="flex items-start gap-1.5 mt-1.5 text-xs text-rose-600 font-medium" role="alert">
        <i class="fa-solid fa-circle-exclamation text-[11px] mt-0.5 shrink-0" aria-hidden="true"></i>
        <span>{{ message }}</span>
    </div>
</template>
