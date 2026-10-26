<script setup>
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
    <Head title="Crear Cuenta - Puritronic" />

    <div class="min-h-screen w-full bg-white font-['Poppins',sans-serif] text-slate-800 flex flex-col justify-between selection:bg-[#0084ff] selection:text-white">
        
        <!-- Header superior / Barra de navegación -->
        <header class="w-full px-4 py-3 sm:px-8 sm:py-4 flex items-center justify-between">
            <Link
                :href="route('auth.portal')"
                class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center bg-slate-50 border border-slate-200/90 text-slate-600 hover:text-[#0084ff] hover:border-[#0084ff]/40 hover:bg-blue-50/50 shadow-xs transition-all"
                title="Volver"
                aria-label="Volver"
            >
                <i class="fa-solid fa-chevron-left text-xs sm:text-sm"></i>
            </Link>

            <Link href="/" class="flex items-center">
                <img
                    src="/images/logo.png"
                    alt="Puritronic"
                    class="h-7 sm:h-9 w-auto object-contain drop-shadow-xs"
                />
            </Link>

            <Link
                href="/"
                class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center bg-slate-50 border border-slate-200/90 text-slate-600 hover:text-[#0084ff] hover:border-[#0084ff]/40 hover:bg-blue-50/50 shadow-xs transition-all"
                title="Ir al inicio"
                aria-label="Ir al inicio"
            >
                <i class="fa-solid fa-house text-xs sm:text-sm"></i>
            </Link>
        </header>

        <!-- Contenido principal a Pantalla Completa: ilustración reducida en móvil para visibilidad total de los 4 campos -->
        <main class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-8 lg:px-12 py-2 sm:py-4 flex flex-col lg:flex-row items-center justify-center gap-3 sm:gap-6 lg:gap-14">
            
            <!-- Columna Ilustración (Tamaño balanceado y nítido) -->
            <div class="w-full lg:w-1/2 flex justify-center items-center">
                <div class="w-56 sm:w-68 md:w-80 lg:w-full lg:max-w-lg">
                    <img
                        src="/images/register.svg"
                        alt="Crear Cuenta"
                        class="w-full h-auto max-h-[210px] sm:max-h-[280px] lg:max-h-[480px] object-contain drop-shadow-sm transform hover:scale-[1.02] transition-transform duration-300 mx-auto"
                    />
                </div>
            </div>

            <!-- Columna Formulario Registro -->
            <div class="w-full lg:w-1/2 flex justify-center">
                <div class="w-full max-w-sm sm:max-w-md space-y-3 sm:space-y-4">
                    
                    <!-- Encabezado del Formulario -->
                    <div class="space-y-0.5 sm:space-y-1 text-center lg:text-left">
                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-[#0e1c2e] tracking-tight">
                            Crear Cuenta
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 font-normal">
                            Completa tus datos para empezar a pedir agua.
                        </p>
                    </div>

                    <!-- Formulario de Registro compacto -->
                    <form @submit.prevent="submit" class="space-y-2 sm:space-y-3">
                        
                        <!-- Campo: Nombre Completo -->
                        <div class="space-y-0.5">
                            <label for="name" class="block text-xs font-bold text-slate-700">
                                Nombre Completo
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-regular fa-user text-xs sm:text-sm"></i>
                                </div>
                                <input
                                    id="name"
                                    type="text"
                                    v-model="form.name"
                                    required
                                    autofocus
                                    autocomplete="name"
                                    placeholder="Tu nombre y apellido"
                                    class="w-full pl-9 pr-4 py-2 sm:py-2.5 rounded-2xl border border-slate-200 focus:border-[#0084ff] focus:ring-4 focus:ring-blue-500/10 text-xs sm:text-sm text-slate-800 placeholder-slate-400 transition-all outline-none bg-slate-50/50 hover:bg-white"
                                />
                            </div>
                            <InputError class="mt-0.5" :message="form.errors.name" />
                        </div>

                        <!-- Campo: Correo Electrónico -->
                        <div class="space-y-0.5">
                            <label for="email" class="block text-xs font-bold text-slate-700">
                                Correo Electrónico
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-regular fa-envelope text-xs sm:text-sm"></i>
                                </div>
                                <input
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                    required
                                    autocomplete="username"
                                    placeholder="ejemplo@correo.com"
                                    class="w-full pl-9 pr-4 py-2 sm:py-2.5 rounded-2xl border border-slate-200 focus:border-[#0084ff] focus:ring-4 focus:ring-blue-500/10 text-xs sm:text-sm text-slate-800 placeholder-slate-400 transition-all outline-none bg-slate-50/50 hover:bg-white"
                                />
                            </div>
                            <InputError class="mt-0.5" :message="form.errors.email" />
                        </div>

                        <!-- Campo: Contraseña -->
                        <div class="space-y-0.5">
                            <label for="password" class="block text-xs font-bold text-slate-700">
                                Contraseña
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-lock text-xs sm:text-sm"></i>
                                </div>
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    v-model="form.password"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Mínimo 8 caracteres"
                                    class="w-full pl-9 pr-9 py-2 sm:py-2.5 rounded-2xl border border-slate-200 focus:border-[#0084ff] focus:ring-4 focus:ring-blue-500/10 text-xs sm:text-sm text-slate-800 placeholder-slate-400 transition-all outline-none bg-slate-50/50 hover:bg-white"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
                                >
                                    <i :class="showPassword ? 'fa-regular fa-eye-slash text-xs sm:text-sm' : 'fa-regular fa-eye text-xs sm:text-sm'"></i>
                                </button>
                            </div>
                            <InputError class="mt-0.5" :message="form.errors.password" />
                        </div>

                        <!-- Campo: Confirmar Contraseña -->
                        <div class="space-y-0.5">
                            <label for="password_confirmation" class="block text-xs font-bold text-slate-700">
                                Confirmar Contraseña
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-lock-open text-xs sm:text-sm"></i>
                                </div>
                                <input
                                    id="password_confirmation"
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    v-model="form.password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Repite tu contraseña"
                                    class="w-full pl-9 pr-9 py-2 sm:py-2.5 rounded-2xl border border-slate-200 focus:border-[#0084ff] focus:ring-4 focus:ring-blue-500/10 text-xs sm:text-sm text-slate-800 placeholder-slate-400 transition-all outline-none bg-slate-50/50 hover:bg-white"
                                />
                                <button
                                    type="button"
                                    @click="showConfirmPassword = !showConfirmPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
                                >
                                    <i :class="showConfirmPassword ? 'fa-regular fa-eye-slash text-xs sm:text-sm' : 'fa-regular fa-eye text-xs sm:text-sm'"></i>
                                </button>
                            </div>
                            <InputError class="mt-0.5" :message="form.errors.password_confirmation" />
                        </div>

                        <!-- Botellones de 20L Iniciales (Opcional) -->
                        <div class="p-3 bg-blue-50/50 border border-blue-100 rounded-2xl space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                        <i class="fa-solid fa-bottle-water text-[#0084ff]"></i>
                                        Tus Botellones de 20L
                                    </span>
                                    <button
                                        type="button"
                                        @click="showBottlesModal = true"
                                        class="w-5 h-5 rounded-full inline-flex items-center justify-center text-[#0084ff] hover:text-[#0070dc] hover:bg-blue-100/70 transition cursor-pointer"
                                        title="¿Qué son botellones fijos y de cambio?"
                                        aria-label="Ver explicación de botellones fijos y de cambio"
                                    >
                                        <i class="fa-solid fa-circle-exclamation text-xs"></i>
                                    </button>
                                </div>
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">
                                    Total: {{ totalBottles }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 leading-tight">
                                Indica cuántos envases tienes para tus pedidos a domicilio (puedes ajustarlo después).
                            </p>

                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <div>
                                    <label for="reg_fixed_bottles" class="block text-[11px] font-medium text-slate-700">
                                        Fijos (Puritronic)
                                    </label>
                                    <input
                                        id="reg_fixed_bottles"
                                        type="number"
                                        min="0"
                                        max="500"
                                        v-model.number="form.fixed_bottles_count"
                                        class="w-full mt-0.5 px-2.5 py-1.5 text-xs rounded-xl border border-slate-200 focus:border-[#0084ff] focus:ring-2 focus:ring-blue-500/10 text-slate-800 bg-white"
                                    />
                                    <InputError class="mt-0.5" :message="form.errors.fixed_bottles_count" />
                                </div>
                                <div>
                                    <label for="reg_exchange_bottles" class="block text-[11px] font-medium text-slate-700">
                                        De Cambio
                                    </label>
                                    <input
                                        id="reg_exchange_bottles"
                                        type="number"
                                        min="0"
                                        max="500"
                                        v-model.number="form.exchange_bottles_count"
                                        class="w-full mt-0.5 px-2.5 py-1.5 text-xs rounded-xl border border-slate-200 focus:border-[#0084ff] focus:ring-2 focus:ring-blue-500/10 text-slate-800 bg-white"
                                    />
                                    <InputError class="mt-0.5" :message="form.errors.exchange_bottles_count" />
                                </div>
                            </div>
                        </div>

                        <!-- Botón de Envío -->
                        <div class="pt-1">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full py-2.5 sm:py-3 px-6 rounded-2xl bg-gradient-to-r from-[#0084ff] to-[#009bf5] hover:from-[#0070dc] hover:to-[#008fe0] text-white font-bold text-xs sm:text-sm shadow-md shadow-blue-500/20 hover:shadow-lg hover:shadow-blue-500/30 hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-50 disabled:pointer-events-none flex items-center justify-center gap-2"
                            >
                                <span v-if="form.processing">Creando cuenta...</span>
                                <span v-else>CREAR MI CUENTA</span>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Enlace a Login -->
                    <div class="pt-2 border-t border-slate-100 text-center">
                        <p class="text-xs text-slate-500">
                            ¿Ya tienes una cuenta registrada?
                            <Link
                                :href="route('login')"
                                class="font-bold text-[#0084ff] hover:text-[#006bd6] ml-1 transition-colors underline"
                            >
                                Inicia sesión aquí
                            </Link>
                        </p>
                    </div>

                </div>
            </div>

        </main>

        <!-- Footer inferior simple -->
        <footer class="w-full py-2.5 sm:py-3 text-center text-[11px] sm:text-xs text-slate-400">
            Puritronic &copy; {{ new Date().getFullYear() }}
        </footer>

        <!-- Modal Informativo: Botellones Fijos vs Botellones de Cambio -->
        <Modal :show="showBottlesModal" @close="showBottlesModal = false" max-width="md">
            <div class="p-5 sm:p-6 space-y-4">
                <!-- Header del Modal -->
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0084ff] flex items-center justify-center shrink-0 shadow-xs">
                            <i class="fa-solid fa-circle-exclamation text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 leading-tight">
                                Tipos de Botellones de 20L
                            </h3>
                            <p class="text-xs text-slate-500">
                                ¿Cómo funcionan los envases fijos y de cambio?
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="showBottlesModal = false"
                        class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer"
                        aria-label="Cerrar ventana"
                    >
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>

                <!-- Contenido con las 2 modalidades -->
                <div class="space-y-3">
                    <!-- 1. Botellones Fijos -->
                    <div class="p-3.5 rounded-xl bg-blue-50/70 border border-blue-200/80 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-blue-900 font-bold text-xs sm:text-sm">
                                <i class="fa-solid fa-tag text-xs text-[#0084ff]"></i>
                                <span>Botellones Fijos</span>
                            </div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-blue-700 bg-blue-100/80 px-2 py-0.5 rounded-full">
                                Con tu nombre
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Son envases rotulados o marcados con tu nombre. Al pedir a domicilio, el repartidor recoge tus botellones vacíos, los llevamos a planta para su lavado a presión y llenado, y te regresamos <strong>exactamente tus mismos envases</strong>.
                        </p>
                    </div>

                    <!-- 2. Botellones de Cambio -->
                    <div class="p-3.5 rounded-xl bg-sky-50/60 border border-sky-200/80 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-sky-950 font-bold text-xs sm:text-sm">
                                <i class="fa-solid fa-arrow-right-arrow-left text-xs text-sky-600"></i>
                                <span>Botellones De Cambio</span>
                            </div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-sky-700 bg-sky-100/80 px-2 py-0.5 rounded-full">
                                Comerciales estándar
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Son envases comerciales estándar de 20 litros (limpios y en buen estado). Al momento de la entrega, entregas tus botellones vacíos al repartidor y recibes otros botellones limpios, llenos y sellados <strong>al instante</strong>.
                        </p>
                    </div>
                </div>

                <!-- Aclaración útil -->
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70 text-[11px] text-slate-600 flex items-start gap-2 leading-relaxed">
                    <i class="fa-solid fa-circle-info text-[#0084ff] text-xs mt-0.5 shrink-0"></i>
                    <span>
                        <strong>Nota:</strong> Si no estás seguro de cuántos envases tienes en este momento, puedes dejarlo en 0 o indicar una cantidad estimada. Podrás ajustarlo cuando gustes desde tu perfil de cliente.
                    </span>
                </div>

                <!-- Botón de Cierre -->
                <div class="pt-1 flex justify-end">
                    <button
                        type="button"
                        @click="showBottlesModal = false"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-[#0084ff] hover:bg-[#0070dc] text-white font-bold text-xs shadow-xs hover:shadow transition cursor-pointer"
                    >
                        Entendido
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
