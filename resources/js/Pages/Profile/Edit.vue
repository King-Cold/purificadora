<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import ClientProfileView from './Partials/ClientProfileView.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
    },
    isClient: {
        type: Boolean,
        default: false,
    },
    clientData: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <Head :title="isClient ? 'Mi Perfil - Puritronic' : 'Perfil de Usuario - Puritronic'" />

    <AuthenticatedLayout>
        <template #header>
            <div v-if="isClient" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-blue-900 text-white flex items-center justify-center text-sm shadow-xs shrink-0">
                        <i class="fa-solid fa-user-gear text-sky-400"></i>
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900">
                            Mi Perfil
                        </h2>
                        <p class="text-xs text-slate-500">
                            Información personal, botellones registrados y seguridad de acceso
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <span
                        v-if="clientData?.customer?.code"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-slate-100 text-slate-700 border border-slate-200 shadow-2xs"
                    >
                        <i class="fa-solid fa-barcode text-slate-500 text-[11px]"></i>
                        <span>{{ clientData.customer.code }}</span>
                    </span>
                </div>
            </div>
            <h2 v-else class="text-xl font-semibold leading-tight text-slate-800">
                Perfil de Usuario
            </h2>
        </template>

        <!-- Vista especializada para el rol Cliente -->
        <ClientProfileView
            v-if="isClient && clientData"
            :client-data="clientData"
            :must-verify-email="mustVerifyEmail"
            :status="status"
        />

        <!-- Vista estándar para perfiles administrativos y operativos -->
        <div v-else class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
