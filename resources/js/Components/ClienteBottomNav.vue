<script setup>
import { Link } from '@inertiajs/vue3';
import { Home, Droplets, ClipboardList, User } from 'lucide-vue-next';

const isCurrent = (tab) => {
    try {
        if (tab === 'home') {
            return route().current('cliente.dashboard') || route().current('dashboard');
        }
        if (tab === 'order') {
            return route().current('cliente.orders.create');
        }
        if (tab === 'orders') {
            return route().current('cliente.orders.index') || route().current('cliente.orders.show');
        }
        if (tab === 'account') {
            return (
                route().current('profile.edit') ||
                route().current('cliente.profile.*') ||
                route().current('cliente.addresses.*')
            );
        }
    } catch (e) {
        return false;
    }
    return false;
};
</script>

<template>
    <nav
        aria-label="Navegación rápida del cliente"
        class="fixed bottom-0 inset-x-0 z-40 lg:hidden bg-white/95 backdrop-blur-md border-t border-[#E9E7F0] shadow-[0_-6px_24px_-6px_rgba(30,21,69,0.12)] pb-[env(safe-area-inset-bottom)]"
    >
        <div class="grid grid-cols-4 h-16 max-w-lg mx-auto px-2">
            <!-- 1. Inicio -->
            <Link
                :href="route('cliente.dashboard')"
                :aria-current="isCurrent('home') ? 'page' : undefined"
                class="flex flex-col items-center justify-center gap-1 min-h-[48px] rounded-xl transition-colors select-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                :class="isCurrent('home') ? 'text-[#1E1545] font-extrabold' : 'text-slate-500 hover:text-[#1E1545] font-medium'"
            >
                <div
                    class="h-7 w-11 rounded-full flex items-center justify-center transition-colors"
                    :class="isCurrent('home') ? 'bg-[#ECE8F8] text-[#1E1545]' : ''"
                >
                    <Home class="w-[18px] h-[18px]" aria-hidden="true" />
                </div>
                <span class="text-[11px] leading-none">Inicio</span>
            </Link>

            <!-- 2. Pedir -->
            <Link
                :href="route('cliente.orders.create')"
                :aria-current="isCurrent('order') ? 'page' : undefined"
                class="flex flex-col items-center justify-center gap-1 min-h-[48px] rounded-xl transition-colors select-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                :class="isCurrent('order') ? 'text-[#6D28D9] font-extrabold' : 'text-slate-600 hover:text-[#1E1545] font-semibold'"
            >
                <div
                    class="h-7 w-12 rounded-full flex items-center justify-center shadow-sm transition-transform active:scale-95"
                    :class="isCurrent('order')
                        ? 'bg-[#6D28D9] text-white'
                        : 'bg-[#1E1545] text-white'"
                >
                    <Droplets class="w-4 h-4" aria-hidden="true" />
                </div>
                <span class="text-[11px] leading-none">Pedir</span>
            </Link>

            <!-- 3. Pedidos -->
            <Link
                :href="route('cliente.orders.index')"
                :aria-current="isCurrent('orders') ? 'page' : undefined"
                class="flex flex-col items-center justify-center gap-1 min-h-[48px] rounded-xl transition-colors select-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                :class="isCurrent('orders') ? 'text-[#1E1545] font-extrabold' : 'text-slate-500 hover:text-[#1E1545] font-medium'"
            >
                <div
                    class="h-7 w-11 rounded-full flex items-center justify-center transition-colors"
                    :class="isCurrent('orders') ? 'bg-[#ECE8F8] text-[#1E1545]' : ''"
                >
                    <ClipboardList class="w-[18px] h-[18px]" aria-hidden="true" />
                </div>
                <span class="text-[11px] leading-none">Pedidos</span>
            </Link>

            <!-- 4. Cuenta -->
            <Link
                :href="route('profile.edit')"
                :aria-current="isCurrent('account') ? 'page' : undefined"
                class="flex flex-col items-center justify-center gap-1 min-h-[48px] rounded-xl transition-colors select-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                :class="isCurrent('account') ? 'text-[#1E1545] font-extrabold' : 'text-slate-500 hover:text-[#1E1545] font-medium'"
            >
                <div
                    class="h-7 w-11 rounded-full flex items-center justify-center transition-colors"
                    :class="isCurrent('account') ? 'bg-[#ECE8F8] text-[#1E1545]' : ''"
                >
                    <User class="w-[18px] h-[18px]" aria-hidden="true" />
                </div>
                <span class="text-[11px] leading-none">Cuenta</span>
            </Link>
        </div>
    </nav>
</template>
