<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Restablecer Contraseña - Puritronic" />

        <div class="text-center mb-6">
            <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-blue-50 text-[#0084ff] flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">
                Nueva Contraseña
            </h1>
            <p class="mt-1 text-xs sm:text-sm text-slate-500">
                Define una nueva contraseña segura para tu cuenta.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="Correo Electrónico" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1.5 block w-full bg-slate-50 text-slate-500 cursor-not-allowed"
                    v-model="form.email"
                    required
                    readonly
                    autocomplete="username"
                />

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Nueva Contraseña" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1.5 block w-full"
                    v-model="form.password"
                    placeholder="Mínimo 8 caracteres"
                    required
                    autocomplete="new-password"
                    autofocus
                />

                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Confirmar Nueva Contraseña"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1.5 block w-full"
                    v-model="form.password_confirmation"
                    placeholder="Repite la contraseña"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-1.5"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full justify-center py-3 text-sm font-bold shadow-md shadow-blue-900/10"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-notch fa-spin"></i>
                        <span>Actualizando contraseña...</span>
                    </span>
                    <span v-else class="flex items-center gap-2">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Restablecer Contraseña</span>
                    </span>
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
