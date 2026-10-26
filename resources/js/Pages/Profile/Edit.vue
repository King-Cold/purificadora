<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import ClientProfileView from './Partials/ClientProfileView.vue';
import { Head, Link } from '@inertiajs/vue3';

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
    canDeleteAccount: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <Head :title="isClient ? 'Mi Perfil - Puritronic' : 'Perfil de Usuario - Puritronic'" />

    <AuthenticatedLayout>
        <template #header>
            <div v-if="isClient" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('cliente.dashboard')"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition shrink-0 cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-900/20"
                        title="Volver al inicio"
                        aria-label="Volver al inicio"
                    >
                        <i class="fa-solid fa-arrow-left text-sm"></i>
                    </Link>
                    <div>
                        <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900">
                            Mi perfil
                        </h1>
                        <p class="text-xs text-slate-500">
                            Administra tus datos, botellones y seguridad
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <span
                        v-if="clientData?.customer?.code"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-slate-100 text-slate-700 border border-slate-200/80 shadow-2xs"
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

                <div v-if="canDeleteAccount" class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
