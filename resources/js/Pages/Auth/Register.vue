<script setup>
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, ArrowRight, Eye, EyeOff, Home, Lock, Mail, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    fixed_bottles_count: 0,
    exchange_bottles_count: 0,
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);
const showBottlesModal = ref(false);

const totalBottles = computed(() => {
    const fixed = Number.isFinite(Number(form.fixed_bottles_count)) ? Math.max(0, parseInt(form.fixed_bottles_count, 10)) : 0;
    const exchange = Number.isFinite(Number(form.exchange_bottles_count)) ? Math.max(0, parseInt(form.exchange_bottles_count, 10)) : 0;
    return fixed + exchange;
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Crear cuenta" />

    <div class="min-h-screen w-full bg-[#F5F4F8] font-sans antialiased text-[#211B35] flex flex-col justify-between selection:bg-[#1E1545] selection:text-white">
        <!-- Barra superior -->
        <header class="w-full max-w-5xl mx-auto px-4 sm:px-6 pt-5 pb-3 flex items-center justify-between">
            <Link
                :href="route('auth.portal')"
                class="w-10 h-10 rounded-xl flex items-center justify-center bg-white border border-[#E7E4ED] text-[#777386] hover:text-[#1E1545] hover:border-[#CFC8E0] shadow-[0_1px_2px_rgba(33,27,53,0.04)] transition duration-150"
                title="Volver"
                aria-label="Volver"
            >
                <ArrowLeft class="w-4 h-4" />
            </Link>

            <Link href="/" class="flex items-center">
                <img
                    src="/images/logo.png"
                    alt="Puritronic"
                    class="h-8 sm:h-9 w-auto object-contain"
                />
            </Link>

            <Link
                href="/"
                class="w-10 h-10 rounded-xl flex items-center justify-center bg-white border border-[#E7E4ED] text-[#777386] hover:text-[#1E1545] hover:border-[#CFC8E0] shadow-[0_1px_2px_rgba(33,27,53,0.04)] transition duration-150"
                title="Ir al inicio"
                aria-label="Ir al inicio"
            >
                <Home class="w-4 h-4" />
            </Link>
        </header>

        <!-- Contenedor principal -->
        <main class="flex-1 w-full max-w-5xl mx-auto px-4 sm:px-6 py-4 sm:py-6 flex items-center justify-center">
            <div class="w-full bg-white border border-[#E7E4ED] rounded-3xl shadow-[0_10px_36px_rgba(30,21,69,0.06)] overflow-hidden grid grid-cols-1 lg:grid-cols-12">
                <!-- Columna ilustración -->
                <div class="lg:col-span-5 bg-[#F5F4F8]/80 border-b lg:border-b-0 lg:border-r border-[#E7E4ED] p-6 sm:p-8 flex items-center justify-center">
                    <div class="w-32 sm:w-44 lg:w-full max-w-[290px] mx-auto">
                        <img
                            src="/images/register.svg"
                            alt="Crear cuenta"
                            class="w-full h-auto object-contain mx-auto"
                        />
                    </div>
                </div>

                <!-- Columna formulario -->
                <div class="lg:col-span-7 p-6 sm:p-8 lg:p-10 flex flex-col justify-center">
                    <div class="w-full max-w-md mx-auto space-y-5">
                        <div class="space-y-1 text-center lg:text-left">
                            <h1 class="text-2xl sm:text-[26px] font-bold text-[#211B35] tracking-tight">
                                Crear cuenta
                            </h1>
                            <p class="text-xs sm:text-sm text-[#777386]">
                                Completa tus datos para comenzar a realizar pedidos.
                            </p>
                        </div>

                        <form @submit.prevent="submit" class="space-y-3.5">
                            <!-- Nombre Completo -->
                            <div class="space-y-1.5">
                                <label for="name" class="block text-xs font-medium text-[#211B35]">
                                    Nombre completo
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#777386]">
                                        <User class="w-4 h-4" />
                                    </div>
                                    <input
                                        id="name"
                                        type="text"
                                        v-model="form.name"
                                        required
                                        autofocus
                                        autocomplete="name"
                                        placeholder="Tu nombre y apellido"
                                        class="w-full pl-10 pr-4 min-h-[42px] rounded-xl border border-[#E7E4ED] bg-white focus:border-[#1E1545] focus:ring-2 focus:ring-[#1E1545]/10 text-sm text-[#211B35] placeholder-[#9B96AA] transition duration-150 outline-none"
                                    />
                                </div>
                                <InputError :message="form.errors.name" />
                            </div>

                            <!-- Correo Electrónico -->
                            <div class="space-y-1.5">
                                <label for="email" class="block text-xs font-medium text-[#211B35]">
                                    Correo electrónico
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#777386]">
                                        <Mail class="w-4 h-4" />
                                    </div>
                                    <input
                                        id="email"
                                        type="email"
                                        v-model="form.email"
                                        required
                                        autocomplete="username"
                                        placeholder="ejemplo@correo.com"
                                        class="w-full pl-10 pr-4 min-h-[42px] rounded-xl border border-[#E7E4ED] bg-white focus:border-[#1E1545] focus:ring-2 focus:ring-[#1E1545]/10 text-sm text-[#211B35] placeholder-[#9B96AA] transition duration-150 outline-none"
                                    />
                                </div>
                                <InputError :message="form.errors.email" />
                            </div>

                            <!-- Contraseña y Confirmación en 2 columnas en tablet/desktop -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="space-y-1.5">
                                    <label for="password" class="block text-xs font-medium text-[#211B35]">
                                        Contraseña
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#777386]">
                                            <Lock class="w-4 h-4" />
                                        </div>
                                        <input
                                            id="password"
                                            :type="showPassword ? 'text' : 'password'"
                                            v-model="form.password"
                                            required
                                            autocomplete="new-password"
                                            placeholder="Mín. 8 caracteres"
                                            class="w-full pl-10 pr-9 min-h-[42px] rounded-xl border border-[#E7E4ED] bg-white focus:border-[#1E1545] focus:ring-2 focus:ring-[#1E1545]/10 text-sm text-[#211B35] placeholder-[#9B96AA] transition duration-150 outline-none"
                                        />
                                        <button
                                            type="button"
                                            @click="showPassword = !showPassword"
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#777386] hover:text-[#211B35] focus:outline-none cursor-pointer"
                                            aria-label="Mostrar u ocultar contraseña"
                                        >
                                            <EyeOff v-if="showPassword" class="w-4 h-4" />
                                            <Eye v-else class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <InputError :message="form.errors.password" />
                                </div>

                                <div class="space-y-1.5">
                                    <label for="password_confirmation" class="block text-xs font-medium text-[#211B35]">
                                        Confirmar contraseña
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#777386]">
                                            <Lock class="w-4 h-4" />
                                        </div>
                                        <input
                                            id="password_confirmation"
                                            :type="showConfirmPassword ? 'text' : 'password'"
                                            v-model="form.password_confirmation"
                                            required
                                            autocomplete="new-password"
                                            placeholder="Repítela aquí"
                                            class="w-full pl-10 pr-9 min-h-[42px] rounded-xl border border-[#E7E4ED] bg-white focus:border-[#1E1545] focus:ring-2 focus:ring-[#1E1545]/10 text-sm text-[#211B35] placeholder-[#9B96AA] transition duration-150 outline-none"
                                        />
                                        <button
                                            type="button"
                                            @click="showConfirmPassword = !showConfirmPassword"
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#777386] hover:text-[#211B35] focus:outline-none cursor-pointer"
                                            aria-label="Mostrar u ocultar confirmación"
                                        >
                                            <EyeOff v-if="showConfirmPassword" class="w-4 h-4" />
                                            <Eye v-else class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <InputError :message="form.errors.password_confirmation" />
                                </div>
                            </div>

                            <!-- Botellones de 20L Iniciales (Opcional) -->
                            <div class="p-3.5 bg-[#F5F4F8] border border-[#E7E4ED] rounded-2xl space-y-2.5">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-semibold text-[#211B35] inline-flex items-center gap-1.5">
                                            <i class="fa-solid fa-bottle-water text-[#0071BA]"></i>
                                            Tus botellones de 20 L
                                        </span>
                                        <button
                                            type="button"
                                            @click="showBottlesModal = true"
                                            class="w-5 h-5 rounded-full inline-flex items-center justify-center text-[#0071BA] hover:bg-[#EAF4FB] transition cursor-pointer"
                                            title="¿Qué son botellones fijos y de cambio?"
                                            aria-label="Ver explicación de botellones fijos y de cambio"
                                        >
                                            <AlertCircle class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                    <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-[#EAF4FB] border border-[#C8E3F6] text-[#0071BA] tabular-nums">
                                        Total: {{ totalBottles }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-2.5">
                                    <div>
                                        <label for="reg_fixed_bottles" class="block text-[11px] font-medium text-[#777386] mb-1">
                                            Fijos (Puritronic)
                                        </label>
                                        <input
                                            id="reg_fixed_bottles"
                                            type="number"
                                            min="0"
                                            max="500"
                                            v-model.number="form.fixed_bottles_count"
                                            class="w-full min-h-[38px] px-3 text-sm font-semibold text-[#211B35] tabular-nums rounded-xl border border-[#E7E4ED] bg-white focus:border-[#1E1545] focus:ring-2 focus:ring-[#1E1545]/10 outline-none transition"
                                        />
                                        <InputError class="mt-1" :message="form.errors.fixed_bottles_count" />
                                    </div>
                                    <div>
                                        <label for="reg_exchange_bottles" class="block text-[11px] font-medium text-[#777386] mb-1">
                                            De cambio
                                        </label>
                                        <input
                                            id="reg_exchange_bottles"
                                            type="number"
                                            min="0"
                                            max="500"
                                            v-model.number="form.exchange_bottles_count"
                                            class="w-full min-h-[38px] px-3 text-sm font-semibold text-[#211B35] tabular-nums rounded-xl border border-[#E7E4ED] bg-white focus:border-[#1E1545] focus:ring-2 focus:ring-[#1E1545]/10 outline-none transition"
                                        />
                                        <InputError class="mt-1" :message="form.errors.exchange_bottles_count" />
                                    </div>
                                </div>
                            </div>

                            <!-- Botón de Envío -->
                            <div class="pt-1">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="w-full min-h-[46px] px-6 rounded-xl bg-[#1E1545] hover:bg-[#2B1F60] text-white font-semibold text-sm shadow-[0_2px_8px_rgba(30,21,69,0.16)] transition duration-150 disabled:opacity-55 disabled:pointer-events-none flex items-center justify-center gap-2 cursor-pointer"
                                >
                                    <span>{{ form.processing ? 'Creando cuenta...' : 'Crear cuenta' }}</span>
                                    <ArrowRight class="w-4 h-4" />
                                </button>
                            </div>
                        </form>

                        <!-- Enlace a Login -->
                        <div class="pt-4 border-t border-[#E7E4ED] text-center">
                            <p class="text-xs text-[#777386]">
                                ¿Ya tienes una cuenta registrada?
                                <Link
                                    :href="route('login')"
                                    class="font-semibold text-[#0071BA] hover:text-[#005994] ml-1 transition-colors"
                                >
                                    Inicia sesión aquí
                                </Link>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="w-full py-3.5 text-center text-xs text-[#777386]">
            Puritronic &copy; {{ new Date().getFullYear() }}
        </footer>

        <!-- Modal Informativo: Botellones Fijos vs Botellones de Cambio -->
        <Modal :show="showBottlesModal" @close="showBottlesModal = false" max-width="md">
            <div class="p-5 sm:p-6 space-y-4">
                <div class="flex items-start justify-between gap-3 border-b border-[#E7E4ED] pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-xl bg-[#EAF4FB] text-[#0071BA] flex items-center justify-center shrink-0">
                            <AlertCircle class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-[#211B35] leading-tight">
                                Tipos de botellones de 20 L
                            </h3>
                            <p class="text-xs text-[#777386]">
                                ¿Cómo funcionan los envases fijos y de cambio?
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="showBottlesModal = false"
                        class="w-8 h-8 rounded-lg text-[#777386] hover:text-[#211B35] hover:bg-[#F5F4F8] flex items-center justify-center transition cursor-pointer"
                        aria-label="Cerrar ventana"
                    >
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>

                <div class="space-y-3">
                    <div class="p-3.5 rounded-xl bg-[#EEEAF7]/55 border border-[#D8CEEE] space-y-1.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-[#1E1545] font-semibold text-xs sm:text-sm">
                                <i class="fa-solid fa-tag text-xs text-[#1E1545]"></i>
                                <span>Botellones Fijos</span>
                            </div>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-[#1E1545] bg-white border border-[#D8CEEE] px-2 py-0.5 rounded-full">
                                Con tu nombre
                            </span>
                        </div>
                        <p class="text-xs text-[#777386] leading-relaxed">
                            Son envases rotulados o marcados con tu nombre. Al pedir a domicilio, el repartidor recoge tus botellones vacíos, los llevamos a planta para lavado y llenado, y te regresamos <strong>exactamente tus mismos envases</strong>.
                        </p>
                    </div>

                    <div class="p-3.5 rounded-xl bg-[#EAF4FB]/70 border border-[#C8E3F6] space-y-1.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-[#0071BA] font-semibold text-xs sm:text-sm">
                                <i class="fa-solid fa-arrow-right-arrow-left text-xs text-[#0071BA]"></i>
                                <span>Botellones de Cambio</span>
                            </div>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-[#0071BA] bg-white border border-[#C8E3F6] px-2 py-0.5 rounded-full">
                                Estándar 20 L
                            </span>
                        </div>
                        <p class="text-xs text-[#777386] leading-relaxed">
                            Son envases comerciales estándar de 20 litros. Al momento de la entrega, entregas tus botellones vacíos al repartidor y recibes otros botellones llenos y sellados <strong>al instante</strong>.
                        </p>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-[#F5F4F8] border border-[#E7E4ED] text-[11px] text-[#777386] flex items-start gap-2 leading-relaxed">
                    <i class="fa-solid fa-circle-info text-[#0071BA] text-xs mt-0.5 shrink-0"></i>
                    <span>
                        <strong>Nota:</strong> Si no estás seguro de cuántos envases tienes, puedes dejarlo en 0 o poner una cantidad aproximada y ajustarlo después en tu perfil.
                    </span>
                </div>

                <div class="pt-1 flex justify-end">
                    <button
                        type="button"
                        @click="showBottlesModal = false"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-[#1E1545] hover:bg-[#2B1F60] text-white font-semibold text-xs transition cursor-pointer"
                    >
                        Entendido
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
