<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/AdminPageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { confirmAction } from '@/utils/swal';
import {
    Star,
    Search,
    Globe,
    EyeOff,
    Trash2,
    MessageSquareQuote,
    MapPin,
    Clock,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    reviews: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            featured: 0,
            average_rating: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            rating: 'all',
            landing: 'all',
        }),
    },
});

const search = ref(props.filters.search || '');
const rating = ref(props.filters.rating || 'all');
const landing = ref(props.filters.landing || 'all');
const processingIds = ref({});

let searchTimer = null;

const applyFilters = () => {
    router.get(
        route('admin.reviews.index'),
        {
            search: search.value || undefined,
            rating: rating.value !== 'all' ? rating.value : undefined,
            landing: landing.value !== 'all' ? landing.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const onSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 350);
};

const resetFilters = () => {
    search.value = '';
    rating.value = 'all';
    landing.value = 'all';
    applyFilters();
};

const hasActiveFilters = () => {
    return Boolean(search.value || (rating.value && rating.value !== 'all') || (landing.value && landing.value !== 'all'));
};

const toggleLanding = (review) => {
    if (processingIds.value[review.id]) return;
    processingIds.value[review.id] = true;

    router.patch(
        route('admin.reviews.toggle-landing', review.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processingIds.value[review.id] = false;
            },
        }
    );
};

const deleteReview = async (review) => {
    const confirmed = await confirmAction({
        title: '¿Eliminar comentario?',
        text: `Se eliminará permanentemente la opinión de ${review.customer?.nickname || review.customer?.name || 'este cliente'}.`,
        confirmText: 'Sí, eliminar',
        cancelText: 'Cancelar',
        icon: 'warning',
        danger: true,
    });

    if (!confirmed) return;

    router.delete(route('admin.reviews.destroy', review.id), {
        preserveScroll: true,
    });
};

const cleanPaginationLabel = (label) => {
    return String(label)
        .replace(/&laquo;\s*Previous/i, 'Anterior')
        .replace(/Next\s*&raquo;/i, 'Siguiente')
        .replace(/&laquo;/g, '‹')
        .replace(/&raquo;/g, '›');
};
</script>

<template>
    <Head title="Comentarios" />

    <AuthenticatedLayout :wide="true">
        <template #header>
            <AdminPageHeader
                title="Comentarios de Clientes"
                subtitle="Revisa las calificaciones de los pedidos y selecciona cuáles se muestran en el landing page."
                icon="fa-solid fa-star"
                accent="purple"
                :breadcrumbs="[
                    { label: 'Administración', href: route('dashboard') },
                    { label: 'Comentarios' },
                ]"
            />
        </template>

        <div class="py-6 sm:py-8 bg-[#F5F4F8] min-h-[calc(100vh-8rem)]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-5">

                <!-- 1. MÉTRICAS RÁPIDAS -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-4 sm:p-5 flex items-center justify-between gap-3">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block">
                                Total de comentarios
                            </span>
                            <span class="text-2xl font-black text-[#1E1545] tabular-nums mt-1 block">
                                {{ stats.total }}
                            </span>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-[#EEEAF7] text-[#1E1545] flex items-center justify-center shrink-0">
                            <MessageSquareQuote class="w-5 h-5" aria-hidden="true" />
                        </div>
                    </div>

                    <div class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-4 sm:p-5 flex items-center justify-between gap-3">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block">
                                Calificación promedio
                            </span>
                            <span class="text-2xl font-black text-[#1E1545] tabular-nums mt-1 flex items-center gap-1.5">
                                <span>{{ Number(stats.average_rating || 0).toFixed(1) }}</span>
                                <Star class="w-5 h-5 fill-amber-400 text-amber-400" aria-hidden="true" />
                            </span>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                            <Star class="w-5 h-5 fill-amber-400 text-amber-500" aria-hidden="true" />
                        </div>
                    </div>

                    <div class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-4 sm:p-5 flex items-center justify-between gap-3">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block">
                                En landing page
                            </span>
                            <span class="text-2xl font-black text-[#26845B] tabular-nums mt-1 block">
                                {{ stats.featured }}
                            </span>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-[#E8F2EC] text-[#26845B] flex items-center justify-center shrink-0">
                            <Globe class="w-5 h-5" aria-hidden="true" />
                        </div>
                    </div>
                </div>

                <!-- 2. BARRA DE FILTROS -->
                <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-[#E9E7F0] space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                        <div class="lg:col-span-5">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-wider block mb-1">
                                Buscar
                            </label>
                            <div class="relative">
                                <Search class="w-4 h-4 text-slate-400 absolute left-3 top-2.5 pointer-events-none" aria-hidden="true" />
                                <input
                                    v-model="search"
                                    @input="onSearchInput"
                                    type="text"
                                    placeholder="Buscar por cliente, folio o comentario..."
                                    class="w-full pl-9 pr-3 py-2 rounded-xl border-[#E9E7F0] text-sm focus:border-[#1E1545] focus:ring-[#1E1545] placeholder:text-slate-400"
                                />
                            </div>
                        </div>

                        <div class="lg:col-span-3">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-wider block mb-1">
                                Estrellas
                            </label>
                            <select
                                v-model="rating"
                                @change="applyFilters"
                                class="w-full py-2 px-3 rounded-xl border-[#E9E7F0] text-sm font-medium text-[#1E1545] focus:border-[#1E1545] focus:ring-[#1E1545]"
                            >
                                <option value="all">Todas las calificaciones</option>
                                <option value="5">5 estrellas</option>
                                <option value="4">4 estrellas</option>
                                <option value="3">3 estrellas</option>
                                <option value="2">2 estrellas</option>
                                <option value="1">1 estrella</option>
                            </select>
                        </div>

                        <div class="lg:col-span-3">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-wider block mb-1">
                                Visibilidad en portada
                            </label>
                            <select
                                v-model="landing"
                                @change="applyFilters"
                                class="w-full py-2 px-3 rounded-xl border-[#E9E7F0] text-sm font-medium text-[#1E1545] focus:border-[#1E1545] focus:ring-[#1E1545]"
                            >
                                <option value="all">Todos</option>
                                <option value="featured">Visibles en landing page</option>
                                <option value="hidden">Ocultos del landing page</option>
                            </select>
                        </div>

                        <div class="lg:col-span-1 flex justify-end">
                            <button
                                v-if="hasActiveFilters()"
                                @click="resetFilters"
                                type="button"
                                class="w-full py-2 px-3 rounded-xl text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition inline-flex items-center justify-center gap-1 cursor-pointer"
                            >
                                <X class="w-3.5 h-3.5" aria-hidden="true" />
                                <span>Limpiar</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 3. LISTADO DE COMENTARIOS -->
                <div v-if="reviews.data.length === 0" class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-10 text-center space-y-2">
                    <div class="w-12 h-12 rounded-2xl bg-[#EEEAF7] text-[#1E1545] mx-auto flex items-center justify-center">
                        <MessageSquareQuote class="w-6 h-6" aria-hidden="true" />
                    </div>
                    <h2 class="text-base font-extrabold text-[#1E1545]">
                        No hay comentarios registrados
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto">
                        Cuando tus clientes califiquen sus pedidos con estrellas y dejen su opinión, aparecerán aquí para que decidas cuáles destacar en el landing page.
                    </p>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <article
                        v-for="review in reviews.data"
                        :key="review.id"
                        class="rounded-2xl bg-white border shadow-sm p-5 flex flex-col justify-between gap-4 transition"
                        :class="review.show_on_landing ? 'border-emerald-300 ring-1 ring-emerald-500/15' : 'border-[#E9E7F0]'"
                    >
                        <div class="space-y-3">
                            <!-- Cabecera: estrellas + estado en landing -->
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-1">
                                    <Star
                                        v-for="s in 5"
                                        :key="s"
                                        class="w-4 h-4"
                                        :class="s <= review.rating ? 'fill-amber-400 text-amber-400' : 'text-slate-200'"
                                    />
                                    <span class="ml-1.5 text-xs font-extrabold text-[#1E1545] tabular-nums">
                                        {{ review.rating }}/5
                                    </span>
                                </div>

                                <span
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold"
                                    :class="review.show_on_landing
                                        ? 'bg-[#E8F2EC] text-[#26845B]'
                                        : 'bg-[#F4F3F8] text-slate-600'"
                                >
                                    <Globe v-if="review.show_on_landing" class="w-3 h-3" aria-hidden="true" />
                                    <EyeOff v-else class="w-3 h-3" aria-hidden="true" />
                                    <span>{{ review.show_on_landing ? 'Visible en landing page' : 'Solo interno' }}</span>
                                </span>
                            </div>

                            <!-- Comentario real -->
                            <p class="text-sm text-[#1E1545] leading-relaxed break-words bg-[#F5F4F8]/70 border border-[#E9E7F0] rounded-xl p-3.5">
                                “{{ review.comment }}”
                            </p>
                        </div>

                        <!-- Metadatos del cliente / pedido y acciones -->
                        <div class="space-y-3 pt-3 border-t border-[#E9E7F0]">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div class="min-w-0">
                                    <p class="font-extrabold text-[#1E1545] truncate">
                                        {{ review.customer?.nickname || review.customer?.name || 'Cliente' }}
                                        <span v-if="review.customer?.nickname && review.customer?.name && review.customer.nickname !== review.customer.name" class="font-medium text-slate-500">
                                            ({{ review.customer.name }})
                                        </span>
                                    </p>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500 mt-0.5">
                                        <Link
                                            v-if="review.order?.id"
                                            :href="route('admin.orders.show', review.order.id)"
                                            class="font-mono font-bold text-[#0071BA] hover:underline"
                                        >
                                            {{ review.order.folio }}
                                        </Link>
                                        <span v-if="review.order?.zone_label" class="inline-flex items-center gap-1">
                                            <MapPin class="w-3 h-3 text-emerald-600" aria-hidden="true" />
                                            {{ review.order.zone_label }}
                                        </span>
                                        <span class="inline-flex items-center gap-1 tabular-nums">
                                            <Clock class="w-3 h-3 text-slate-400" aria-hidden="true" />
                                            {{ review.created_at }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-2 pt-1">
                                <button
                                    type="button"
                                    @click="toggleLanding(review)"
                                    :disabled="Boolean(processingIds[review.id])"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-[42px] rounded-xl text-xs font-extrabold transition cursor-pointer disabled:opacity-50"
                                    :class="review.show_on_landing
                                        ? 'bg-slate-100 hover:bg-slate-200 text-[#1E1545] border border-[#E9E7F0]'
                                        : 'bg-[#26845B] hover:bg-[#1F6D4B] text-white shadow-sm'"
                                >
                                    <EyeOff v-if="review.show_on_landing" class="w-4 h-4" aria-hidden="true" />
                                    <Globe v-else class="w-4 h-4" aria-hidden="true" />
                                    <span>{{ review.show_on_landing ? 'Quitar del landing page' : 'Mostrar en landing page' }}</span>
                                </button>

                                <button
                                    type="button"
                                    @click="deleteReview(review)"
                                    class="h-[42px] w-[42px] rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 flex items-center justify-center transition cursor-pointer shrink-0"
                                    title="Eliminar comentario"
                                    aria-label="Eliminar comentario"
                                >
                                    <Trash2 class="w-4 h-4" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <!-- Paginación -->
                <nav
                    v-if="reviews.links && reviews.links.length > 3"
                    aria-label="Paginación de comentarios"
                    class="flex flex-wrap items-center justify-center gap-1.5 pt-2"
                >
                    <template v-for="(link, idx) in reviews.links" :key="idx">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="px-3.5 py-2 rounded-xl text-xs font-bold border transition"
                            :class="link.active
                                ? 'bg-[#1E1545] text-white border-[#1E1545]'
                                : 'bg-white text-[#1E1545] border-[#E9E7F0] hover:bg-[#F5F4F8]'"
                        >
                            {{ cleanPaginationLabel(link.label) }}
                        </Link>
                        <span
                            v-else
                            class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-400 bg-white/60 border border-[#E9E7F0]"
                        >
                            {{ cleanPaginationLabel(link.label) }}
                        </span>
                    </template>
                </nav>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
