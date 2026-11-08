<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const isCurrent = (name) => {
    try {
        if (name === 'reparto') {
            return route().current('repartidor.reparto') ||
                   route().current('repartidor.dashboard') ||
                   route().current('repartidor.route.*') ||
                   route().current('repartidor.collections.*') ||
                   route().current('repartidor.deliveries.*');
        }
        if (name === 'debt') {
            return route().current('repartidor.debt.*');
        }
        if (name === 'truck') {
            return route().current('repartidor.truck-load.*');
        }
        if (name === 'customers') {
            return route().current('repartidor.customers.*');
        }
        return false;
    } catch (e) {
        return false;
    }
};
</script>

<template>
    <nav
        aria-label="Navegación móvil del repartidor"
        class="fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200/90 shadow-[0_-4px_16px_rgba(0,0,0,0.06)] lg:hidden"
    >
        <div class="grid grid-cols-4 h-16 max-w-lg mx-auto px-1">
            <!-- 1. Reparto (Hub Principal) -->
            <Link
                :href="route('repartidor.reparto')"
                class="flex flex-col items-center justify-center gap-1 min-h-[48px] py-1 px-1 transition-colors select-none"
                :class="isCurrent('reparto') ? 'text-blue-900 font-bold' : 'text-slate-500 hover:text-slate-900 font-medium'"
            >
                <div class="relative flex items-center justify-center h-6 w-6">
                    <i class="fa-solid fa-truck text-base"></i>
                    <span
                        v-if="isCurrent('reparto')"
                        class="absolute -bottom-1 h-1 w-4 bg-blue-900 rounded-full"
                    ></span>
                </div>
                <span class="text-[11px] leading-tight truncate">Reparto</span>
            </Link>

            <!-- 2. Mi Carga -->
            <Link
                :href="route('repartidor.truck-load.index')"
                class="flex flex-col items-center justify-center gap-1 min-h-[48px] py-1 px-1 transition-colors select-none"
                :class="isCurrent('truck') ? 'text-blue-900 font-bold' : 'text-slate-500 hover:text-slate-900 font-medium'"
            >
                <div class="relative flex items-center justify-center h-6 w-6">
                    <i class="fa-solid fa-boxes-stacked text-base"></i>
                    <span
                        v-if="isCurrent('truck')"
                        class="absolute -bottom-1 h-1 w-4 bg-blue-900 rounded-full"
                    ></span>
                </div>
                <span class="text-[11px] leading-tight truncate">Mi Carga</span>
            </Link>

            <!-- 3. Cobros -->
            <Link
                :href="route('repartidor.debt.index')"
                class="flex flex-col items-center justify-center gap-1 min-h-[48px] py-1 px-1 transition-colors select-none"
                :class="isCurrent('debt') ? 'text-blue-900 font-bold' : 'text-slate-500 hover:text-slate-900 font-medium'"
            >
                <div class="relative flex items-center justify-center h-6 w-6">
                    <i class="fa-solid fa-hand-holding-dollar text-base"></i>
                    <span
                        v-if="isCurrent('debt')"
                        class="absolute -bottom-1 h-1 w-4 bg-blue-900 rounded-full"
                    ></span>
                </div>
                <span class="text-[11px] leading-tight truncate">Cobros</span>
            </Link>

            <!-- 4. Clientes -->
            <Link
                :href="route('repartidor.customers.index')"
                class="flex flex-col items-center justify-center gap-1 min-h-[48px] py-1 px-1 transition-colors select-none"
                :class="isCurrent('customers') ? 'text-blue-900 font-bold' : 'text-slate-500 hover:text-slate-900 font-medium'"
            >
                <div class="relative flex items-center justify-center h-6 w-6">
                    <i class="fa-solid fa-users text-base"></i>
                    <span
                        v-if="isCurrent('customers')"
                        class="absolute -bottom-1 h-1 w-4 bg-blue-900 rounded-full"
                    ></span>
                </div>
                <span class="text-[11px] leading-tight truncate">Clientes</span>
            </Link>
        </div>
    </nav>
</template>

