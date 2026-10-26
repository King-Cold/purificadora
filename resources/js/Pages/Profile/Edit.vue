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
        <template #header v-if="!isClient">
            <h2 class="text-xl font-semibold leading-tight text-slate-800">
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
