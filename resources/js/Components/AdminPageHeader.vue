<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Home } from 'lucide-vue-next';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
        // Each item: { label: String, href?: String }
    },
    icon: {
        type: String,
        default: '',
    },
    iconComponent: {
        type: [Object, Function],
        default: null,
    },
    accent: {
        type: String,
        default: 'purple',
        validator: (val) => ['blue', 'purple', 'green', 'magenta', 'amber'].includes(val),
    },
    badgeText: {
        type: String,
        default: '',
    },
    monoTitle: {
        type: Boolean,
        default: false,
    },
    showHome: {
        type: Boolean,
        default: true,
    },
});

const homeUrl = computed(() => {
    try {
        return route('dashboard');
    } catch {
        return '/';
    }
});

const accentStyles = computed(() => {
    const map = {
        blue: {
            iconBox: 'bg-[#EAF4FB] text-[#0071BA] border-[#C8E3F6]',
            badge: 'bg-[#EAF4FB] text-[#0071BA] border-[#C8E3F6]',
        },
        purple: {
            iconBox: 'bg-[#EEEAF7] text-[#1E1545] border-[#D8CEEE]',
            badge: 'bg-[#EEEAF7] text-[#1E1545] border-[#D8CEEE]',
        },
        green: {
            iconBox: 'bg-[#E8F5EE] text-[#26845B] border-[#BBE4CE]',
            badge: 'bg-[#E8F5EE] text-[#26845B] border-[#BBE4CE]',
        },
        magenta: {
            iconBox: 'bg-[#FDECF3] text-[#D9266F] border-[#F6C8DB]',
            badge: 'bg-[#FDECF3] text-[#D9266F] border-[#F6C8DB]',
        },
        amber: {
            iconBox: 'bg-[#FEF6E7] text-[#B87A14] border-[#F5DFB5]',
            badge: 'bg-[#FEF6E7] text-[#B87A14] border-[#F5DFB5]',
        },
    };
    return map[props.accent] || map.purple;
});
</script>

<template>
    <div class="space-y-3 font-sans antialiased">
        <!-- Breadcrumbs minimalistas -->
        <nav
            v-if="showHome || breadcrumbs.length > 0"
            aria-label="Migas de pan"
            class="flex items-center flex-wrap gap-1.5 text-xs select-none"
        >
            <template v-if="showHome">
                <Link
                    :href="homeUrl"
                    class="inline-flex items-center justify-center h-6 w-6 rounded-lg bg-[#F5F4F8] border border-[#E7E4ED] text-[#777386] hover:text-[#1E1545] hover:bg-[#EEEAF7] hover:border-[#D8CEEE] transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E1545]/30"
                    title="Ir al Dashboard"
                >
                    <Home class="w-3.5 h-3.5" />
                </Link>

                <ChevronRight
                    v-if="breadcrumbs.length > 0"
                    class="w-3.5 h-3.5 text-[#C8C2D6] shrink-0"
                />
            </template>

            <template v-for="(item, index) in breadcrumbs" :key="`${item.label}-${index}`">
                <Link
                    v-if="item.href && index < breadcrumbs.length - 1"
                    :href="item.href"
                    class="inline-flex items-center px-2 py-0.5 rounded-lg text-[#777386] hover:text-[#1E1545] hover:bg-[#F5F4F8] font-medium transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E1545]/30"
                >
                    <span class="max-w-[160px] sm:max-w-xs truncate">{{ item.label }}</span>
                </Link>

                <span
                    v-else
                    class="inline-flex items-center px-2.5 py-0.5 rounded-lg bg-[#F5F4F8] border border-[#E7E4ED] text-[#211B35] font-semibold"
                >
                    <span class="max-w-[180px] sm:max-w-xs truncate">{{ item.label }}</span>
                </span>

                <ChevronRight
                    v-if="index < breadcrumbs.length - 1"
                    class="w-3.5 h-3.5 text-[#C8C2D6] shrink-0"
                />
            </template>
        </nav>

        <!-- Identidad + Acciones -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3.5 min-w-0">
                <div
                    v-if="iconComponent || icon || $slots.icon"
                    class="w-11 h-11 rounded-2xl border flex items-center justify-center shrink-0 shadow-[0_2px_6px_rgba(33,27,53,0.04)]"
                    :class="accentStyles.iconBox"
                >
                    <slot name="icon">
                        <component :is="iconComponent" v-if="iconComponent" class="w-5 h-5" />
                        <i v-else :class="[icon, 'text-base leading-none']"></i>
                    </slot>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1
                            class="text-xl sm:text-[22px] font-bold text-[#211B35] tracking-tight leading-snug"
                            :class="{ 'font-mono': monoTitle }"
                        >
                            {{ title }}
                        </h1>

                        <span
                            v-if="badgeText"
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border tabular-nums"
                            :class="accentStyles.badge"
                        >
                            {{ badgeText }}
                        </span>

                        <slot name="badges" />
                    </div>

                    <p v-if="subtitle" class="text-xs sm:text-[13px] text-[#777386] mt-0.5 leading-relaxed">
                        <slot name="subtitle">{{ subtitle }}</slot>
                    </p>
                    <div v-else-if="$slots.subtitle" class="text-xs sm:text-[13px] text-[#777386] mt-0.5 leading-relaxed">
                        <slot name="subtitle" />
                    </div>
                </div>
            </div>

            <div
                v-if="$slots.actions"
                class="flex flex-wrap items-center gap-2.5 shrink-0 md:justify-end"
            >
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
