<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verificar Correo Electrónico - Puritronic" />

        <div class="text-center mb-6">
            <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-blue-50 text-[#0084ff] flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-envelope-circle-check"></i>
            </div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">
                Verifica tu Correo
            </h1>
            <p class="mt-2 text-xs sm:text-sm text-slate-500 leading-relaxed">
                ¡Gracias por registrarte en Puritronic! Antes de comenzar, por favor verifica tu correo electrónico haciendo clic en el enlace que te acabamos de enviar.
            </p>
        </div>

        <div
            class="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs sm:text-sm font-medium text-emerald-800 flex items-center gap-2.5"
            v-if="verificationLinkSent"
            role="status"
        >
            <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i>
            <span>Se ha enviado un nuevo enlace de verificación a tu correo electrónico.</span>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <PrimaryButton
                    class="w-full justify-center py-3 text-sm font-bold shadow-md shadow-blue-900/10"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-notch fa-spin"></i>
                        <span>Reenviando correo...</span>
                    </span>
                    <span v-else class="flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                        <span>Reenviar correo de verificación</span>
                    </span>
                </PrimaryButton>
            </div>

            <div class="text-center pt-2">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-xs font-semibold text-rose-600 hover:text-rose-700 transition-colors cursor-pointer"
                >
                    <i class="fa-solid fa-arrow-right-from-bracket text-[10px] mr-1"></i>
                    Cerrar sesión
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
