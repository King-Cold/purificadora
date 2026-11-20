<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ShieldCheck, Eye, EyeOff, CheckCircle2 } from 'lucide-vue-next';

const props = defineProps({
    variant: {
        type: String,
        default: 'default', // 'default' | 'client'
    },
});

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const showCurrent = ref(false);
const showNew = ref(false);
const showConfirm = ref(false);

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
        <header class="flex items-start gap-3">
            <div
                v-if="variant === 'client'"
                class="h-10 w-10 rounded-xl bg-violet-100 text-[#6D28D9] flex items-center justify-center shrink-0"
            >
                <ShieldCheck class="w-5 h-5" aria-hidden="true" />
            </div>
            <div>
                <h2 class="text-base sm:text-lg font-extrabold text-[#1E1545]">
                    Actualizar contraseña
                </h2>
                <p class="mt-0.5 text-xs sm:text-sm text-slate-500 leading-relaxed">
                    Usa una contraseña segura de al menos 8 caracteres para proteger tu cuenta y tus pedidos.
                </p>
            </div>
        </header>

        <form @submit.prevent="updatePassword" class="mt-5 space-y-4">
            <div>
                <InputLabel for="current_password" value="Contraseña actual *" />

                <div class="relative mt-1">
                    <TextInput
                        id="current_password"
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        :type="showCurrent ? 'text' : 'password'"
                        class="block w-full pr-11 min-h-[44px]"
                        autocomplete="current-password"
                        placeholder="Tu contraseña actual"
                    />
                    <button
                        type="button"
                        @click="showCurrent = !showCurrent"
                        :aria-label="showCurrent ? 'Ocultar contraseña actual' : 'Mostrar contraseña actual'"
                        class="absolute inset-y-0 right-0 px-3 flex items-center justify-center text-slate-400 hover:text-[#1E1545] transition cursor-pointer"
                    >
                        <EyeOff v-if="showCurrent" class="w-4 h-4" aria-hidden="true" />
                        <Eye v-else class="w-4 h-4" aria-hidden="true" />
                    </button>
                </div>

                <InputError :message="form.errors.current_password" class="mt-1.5" />
            </div>

            <div>
                <InputLabel for="password" value="Nueva contraseña *" />

                <div class="relative mt-1">
                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        :type="showNew ? 'text' : 'password'"
                        class="block w-full pr-11 min-h-[44px]"
                        autocomplete="new-password"
                        placeholder="Mínimo 8 caracteres"
                    />
                    <button
                        type="button"
                        @click="showNew = !showNew"
                        :aria-label="showNew ? 'Ocultar nueva contraseña' : 'Mostrar nueva contraseña'"
                        class="absolute inset-y-0 right-0 px-3 flex items-center justify-center text-slate-400 hover:text-[#1E1545] transition cursor-pointer"
                    >
                        <EyeOff v-if="showNew" class="w-4 h-4" aria-hidden="true" />
                        <Eye v-else class="w-4 h-4" aria-hidden="true" />
                    </button>
                </div>

                <InputError :message="form.errors.password" class="mt-1.5" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirmar nueva contraseña *" />

                <div class="relative mt-1">
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :type="showConfirm ? 'text' : 'password'"
                        class="block w-full pr-11 min-h-[44px]"
                        autocomplete="new-password"
                        placeholder="Repite la nueva contraseña"
                    />
                    <button
                        type="button"
                        @click="showConfirm = !showConfirm"
                        :aria-label="showConfirm ? 'Ocultar confirmación' : 'Mostrar confirmación'"
                        class="absolute inset-y-0 right-0 px-3 flex items-center justify-center text-slate-400 hover:text-[#1E1545] transition cursor-pointer"
                    >
                        <EyeOff v-if="showConfirm" class="w-4 h-4" aria-hidden="true" />
                        <Eye v-else class="w-4 h-4" aria-hidden="true" />
                    </button>
                </div>

                <InputError :message="form.errors.password_confirmation" class="mt-1.5" />
            </div>

            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-3 border-t border-[#E9E7F0]">
                <div>
                    <Transition
                        enter-active-class="transition ease-in-out duration-200"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out duration-300"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-if="form.recentlySuccessful"
                            class="text-xs sm:text-sm font-bold text-emerald-700 flex items-center gap-1.5"
                        >
                            <CheckCircle2 class="w-4 h-4 text-emerald-600" aria-hidden="true" />
                            <span>Contraseña actualizada</span>
                        </p>
                    </Transition>
                </div>

                <button
                    v-if="variant === 'client'"
                    type="submit"
                    :disabled="form.processing"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 min-h-[44px] rounded-xl text-sm font-extrabold text-white bg-[#1E1545] hover:bg-[#2E2363] shadow-sm transition cursor-pointer disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9] focus-visible:ring-offset-2"
                >
                    <span>{{ form.processing ? 'Actualizando...' : 'Actualizar contraseña' }}</span>
                </button>

                <PrimaryButton v-else :disabled="form.processing" class="min-h-[44px]">
                    <span v-if="form.processing">Actualizando...</span>
                    <span v-else>Actualizar contraseña</span>
                </PrimaryButton>
            </div>
        </form>
    </section>
</template>
