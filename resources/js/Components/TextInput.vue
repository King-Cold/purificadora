<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: [String, Number],
    required: false,
    default: '',
});

const input = ref(null);

onMounted(() => {
    if (input.value && input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        class="w-full rounded-xl border-slate-300 text-slate-800 shadow-2xs text-sm focus:border-blue-900 focus:ring-2 focus:ring-blue-900/15 placeholder:text-slate-400 transition-all duration-150 disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed"
        v-model="model"
        ref="input"
    />
</template>
