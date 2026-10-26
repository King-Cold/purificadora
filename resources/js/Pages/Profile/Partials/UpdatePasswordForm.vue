<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-base sm:text-lg font-bold text-slate-900">
                Actualizar contraseña
            </h2>

            <p class="mt-1 text-xs sm:text-sm text-slate-500">
                Asegúrate de que tu cuenta utilice una contraseña segura para proteger tus accesos y pedidos.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="mt-6 space-y-5">
            <div>
                <InputLabel for="current_password" value="Contraseña actual *" />

                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="mt-1 block w-full text-sm"
                    autocomplete="current-password"
                    placeholder="Tu contraseña actual"
                />

                <InputError
                    :message="form.errors.current_password"
                    class="mt-1.5"
                />
            </div>

            <div>
                <InputLabel for="password" value="Nueva contraseña *" />

                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full text-sm"
                    autocomplete="new-password"
                    placeholder="Mínimo 8 caracteres"
                />

                <InputError :message="form.errors.password" class="mt-1.5" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Confirmar nueva contraseña *"
                />

                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full text-sm"
                    autocomplete="new-password"
                    placeholder="Repite la nueva contraseña"
                />

                <InputError
                    :message="form.errors.password_confirmation"
                    class="mt-1.5"
                />
            </div>

            <div class="flex items-center gap-4 pt-2">
                <PrimaryButton :disabled="form.processing" class="min-h-[44px]">
                    <span v-if="form.processing">Actualizando...</span>
                    <span v-else>Actualizar contraseña</span>
                </PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out duration-200"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out duration-300"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-xs sm:text-sm font-semibold text-emerald-600 flex items-center gap-1.5"
                    >
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Contraseña actualizada correctamente.</span>
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
