<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Recuperar Contraseña - Puritronic" />

        <div class="text-center mb-6">
            <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-blue-50 text-[#0084ff] flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-key"></i>
            </div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">
                ¿Olvidaste tu contraseña?
            </h1>
            <p class="mt-2 text-xs sm:text-sm text-slate-500 leading-relaxed">
                Ingresa tu correo electrónico registrado y te enviaremos un enlace para que puedas restablecerla con total seguridad.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs sm:text-sm font-medium text-emerald-800 flex items-center gap-2.5"
            role="status"
        >
            <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i>
            <span>{{ status }}</span>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="Correo Electrónico" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1.5 block w-full"
                    v-model="form.email"
                    placeholder="ejemplo@correo.com"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full justify-center py-3 text-sm font-bold shadow-md shadow-blue-900/10"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-notch fa-spin"></i>
                        <span>Enviando enlace...</span>
                    </span>
                    <span v-else class="flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                        <span>Enviar enlace de recuperación</span>
                    </span>
                </PrimaryButton>
            </div>

            <div class="text-center pt-2">
                <Link
                    :href="route('login')"
                    class="text-xs font-semibold text-slate-600 hover:text-[#0084ff] transition-colors"
                >
                    <i class="fa-solid fa-chevron-left text-[10px] mr-1"></i>
                    Volver al inicio de sesión
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
