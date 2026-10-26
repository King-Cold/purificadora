<script setup>
defineProps({
    value: {
        type: String,
    },
});
</script>

<template>
    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
