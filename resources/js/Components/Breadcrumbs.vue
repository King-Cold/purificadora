<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    items: {
        type: Array,
        required: true,
        // Each item: { label: String, href?: String, icon?: String }
    },
    showHome: {
        type: Boolean,
        default: true,
    },
    homeHref: {
        type: String,
        default: null,
    },
});

const defaultHomeUrl = computed(() => {
    if (props.homeHref) return props.homeHref;
    try {
        return route('dashboard');
    } catch (e) {
        return '/';
    }
});
</script>

<template>
    <nav aria-label="Migas de pan" class="flex items-center flex-wrap gap-1.5 text-xs mb-2 select-none">
        <!-- Ícono de Inicio (Dashboard) -->
        <template v-if="showHome">
            <Link
                :href="defaultHomeUrl"
                class="inline-flex items-center justify-center h-6 w-6 rounded-md bg-white border border-slate-200 text-slate-500 hover:text-blue-600 hover:border-blue-300 hover:bg-blue-50/50 shadow-2xs transition-colors focus:outline-none"
                title="Ir al Inicio"
            >
                <i class="fa-solid fa-house text-[10px]"></i>
            </Link>

            <i class="fa-solid fa-chevron-right text-[8px] text-slate-300 shrink-0"></i>
        </template>

        <!-- Elementos del Breadcrumb -->
        <template v-for="(item, index) in items" :key="index">
            <!-- Si tiene enlace y NO es el último elemento -->
            <Link
                v-if="item.href && index < items.length - 1"
                :href="item.href"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-slate-600 hover:text-blue-600 font-medium hover:bg-white hover:border-slate-200 border border-transparent shadow-2xs transition-all focus:outline-none"
            >
                <i v-if="item.icon" :class="item.icon" class="text-[11px] text-slate-400"></i>
                <span>{{ item.label }}</span>
            </Link>

            <!-- Si es el último elemento (Página actual) -->
            <span
                v-else
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white border border-slate-200 text-slate-900 font-bold shadow-2xs"
            >
                <i v-if="item.icon" :class="item.icon" class="text-[11px] text-blue-600"></i>
                <span class="max-w-[200px] sm:max-w-xs truncate">{{ item.label }}</span>
            </span>

            <!-- Separador chevron entre elementos excepto el último -->
            <i
                v-if="index < items.length - 1"
                class="fa-solid fa-chevron-right text-[8px] text-slate-300 shrink-0"
            ></i>
        </template>
    </nav>
</template>
