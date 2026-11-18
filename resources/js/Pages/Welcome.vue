<script setup>
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Clock,
    Crosshair,
    Droplets,
    MapPin,
    Menu,
    MessageCircle,
    Navigation,
    Phone,
    RefreshCw,
    ShoppingBag,
    Smartphone,
    Star,
    Tag,
    Truck,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
    canRegister: {
        type: Boolean,
        default: true,
    },
    schedule: {
        type: Object,
        default: () => ({
            opening_time: '08:00',
            break_start_time: '13:00',
            break_end_time: '15:00',
            closing_time: '19:00',
            order_cutoff_time: '18:00',
            days_applied: 'Lunes a Domingo',
        }),
    },
    scheduleStatus: {
        type: Object,
        default: () => ({
            can_receive: true,
            reason: 'allowed',
            message: 'Recepción de pedidos disponible.',
        }),
    },
    presentations: {
        type: Array,
        default: () => [],
    },
});

const format12h = (timeStr) => {
    if (!timeStr) return '';
    const [hStr, mStr] = timeStr.split(':');
    let h = parseInt(hStr, 10);
    const m = mStr || '00';
    if (isNaN(h)) return timeStr;
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    if (h === 0) h = 12;
    return `${h}:${m} ${ampm}`;
};

const formattedScheduleDisplay = computed(() => {
    const s = props.schedule || {};
    const open = format12h(s.opening_time || '08:00');
    const close = format12h(s.closing_time || '19:00');
    let days = s.days_applied || 'Lunes a Domingo';
    days = days.replace(/\s*\(todos los días\)/i, '').trim();

    return {
        opening: open,
        closing: close,
        hours: `De ${open} a ${close}`,
        days,
        fullText: `${days} de ${open} a ${close}`,
    };
});

const isStoreOpen = computed(() => {
    if (props.scheduleStatus?.is_store_open !== undefined) {
        return Boolean(props.scheduleStatus.is_store_open);
    }
    const reason = props.scheduleStatus?.reason;
    if (reason === 'orders_closed_for_today') {
        return true;
    }
    return Boolean(props.scheduleStatus?.can_receive);
});

const storeOpenBadge = computed(() => {
    if (isStoreOpen.value) {
        return {
            label: 'Abierto ahora',
            shortLabel: 'Abierto',
            dotClass: 'bg-[#26845B] animate-pulse',
            pillClass: 'bg-[#E8F5EE] text-[#26845B] border-[#BBE4CE]',
        };
    }

    if (props.scheduleStatus?.reason === 'on_break') {
        return {
            label: 'Cerrado · En descanso',
            shortLabel: 'Cerrado',
            dotClass: 'bg-[#D99A27]',
            pillClass: 'bg-[#FEF6E7] text-[#9A630C] border-[#F5DFB5]',
        };
    }

    return {
        label: 'Cerrado ahora',
        shortLabel: 'Cerrado',
        dotClass: 'bg-[#E82147]',
        pillClass: 'bg-[#FDECF3] text-[#B91C3C] border-[#F6C8DB]',
    };
});

const mobileMenuOpen = ref(false);
const activeSection = ref('inicio');

const navItems = [
    { id: 'inicio', label: 'Inicio' },
    { id: 'catalogo', label: 'Precios' },
    { id: 'como-funciona', label: 'Cómo funciona' },
    { id: 'comentarios', label: 'Opiniones' },
    { id: 'sucursal', label: 'Sucursal' },
    { id: 'preguntas', label: 'Preguntas' },
    { id: 'contacto', label: 'Contacto' },
];

let isManualScrolling = false;
let scrollTimeout = null;

const scrollToSection = (e, id) => {
    if (e) e.preventDefault();
    mobileMenuOpen.value = false;

    if (id === 'inicio') {
        activeSection.value = 'inicio';
        isManualScrolling = true;
        if (scrollTimeout) clearTimeout(scrollTimeout);

        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        });

        scrollTimeout = setTimeout(() => {
            isManualScrolling = false;
        }, 800);
        return;
    }

    const targetId = id === 'precios' ? 'catalogo' : id;
    const el = document.getElementById(targetId) || document.getElementById(id);
    if (!el) return;

    activeSection.value = id;
    isManualScrolling = true;
    if (scrollTimeout) clearTimeout(scrollTimeout);

    nextTick(() => {
        const headerOffset = 68;
        const scrollY = window.pageYOffset || window.scrollY || document.documentElement.scrollTop || 0;
        const rect = el.getBoundingClientRect();
        const targetPosition = Math.max(0, Math.round(rect.top + scrollY - headerOffset));

        window.scrollTo({
            top: targetPosition,
            behavior: 'smooth',
        });

        scrollTimeout = setTimeout(() => {
            isManualScrolling = false;
        }, 800);
    });
};

onMounted(() => {
    let ticking = false;

    const handleScroll = () => {
        if (isManualScrolling) return;

        if (!ticking) {
            window.requestAnimationFrame(() => {
                const scrollY = window.pageYOffset || window.scrollY || document.documentElement.scrollTop || 0;

                if (window.innerHeight + scrollY >= document.documentElement.scrollHeight - 80) {
                    activeSection.value = 'contacto';
                    ticking = false;
                    return;
                }

                const checkPosition = scrollY + 90;
                const sectionIds = ['inicio', 'catalogo', 'como-funciona', 'comentarios', 'sucursal', 'preguntas', 'contacto'];

                for (let i = sectionIds.length - 1; i >= 0; i--) {
                    const el = document.getElementById(sectionIds[i]);
                    if (el) {
                        const elTop = el.getBoundingClientRect().top + scrollY;
                        if (checkPosition >= elTop) {
                            activeSection.value = sectionIds[i];
                            ticking = false;
                            return;
                        }
                    }
                }
                activeSection.value = 'inicio';
                ticking = false;
            });
            ticking = true;
        }
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();

    onUnmounted(() => {
        window.removeEventListener('scroll', handleScroll);
        if (scrollTimeout) clearTimeout(scrollTimeout);
    });
});

const openFaq = ref(0);
const toggleFaq = (index) => {
    openFaq.value = openFaq.value === index ? null : index;
};

const expandedComments = ref({});
const toggleComment = (index) => {
    expandedComments.value[index] = !expandedComments.value[index];
};

const faqs = computed(() => [
    {
        q: '¿Cómo hago mi primer pedido?',
        a: 'Crea tu cuenta, indica tu dirección en Hocabá y los garrafones que necesitas. Te confirmaremos al instante en pantalla y por WhatsApp.',
    },
    {
        q: '¿Cuál es el horario de entrega?',
        a: `Entregamos de ${formattedScheduleDisplay.value.days} de ${formattedScheduleDisplay.value.opening} a ${formattedScheduleDisplay.value.closing}. La recepción de pedidos cierra a las ${props.schedule?.order_cutoff_time ? format12h(props.schedule.order_cutoff_time) : '6:00 PM'}.`,
    },
    {
        q: '¿Aceptan garrafones de otras marcas?',
        a: 'Sí, aceptamos garrafones estándar de 20 litros limpios y en buen estado estructural para la modalidad de cambio.',
    },
    {
        q: '¿Cómo puedo pagar?',
        a: 'Puedes pagar en efectivo al momento de recibir tus garrafones o mediante transferencia bancaria.',
    },
]);

const comments = [
    {
        name: 'María Elena Pech',
        role: 'Clienta en Hocabá',
        avatarText: 'MP',
        avatarBg: 'bg-[#0071BA]',
        rating: 5,
        time: 'Hace 2 días',
        comment: 'Excelente servicio y puntualidad. Antes tenía que esperar a que pasara el camión o salir a buscar garrafón. Ahora pido por el celular y llega directo a mi casa en minutos.',
        verified: true,
    },
    {
        name: 'Carlos Dzib',
        role: 'Negocio de comidas',
        avatarText: 'CD',
        avatarBg: 'bg-[#26845B]',
        rating: 5,
        time: 'Hace 1 semana',
        comment: 'El agua tiene un sabor fresco y ligero, de muy alta pureza. En mi negocio consumimos varios garrafones a la semana y siempre cumplen con los tiempos acordados.',
        verified: true,
    },
    {
        name: 'Rosaura May',
        role: 'Madre de familia',
        avatarText: 'RM',
        avatarBg: 'bg-[#1E1545]',
        rating: 5,
        time: 'Hace 2 semanas',
        comment: 'La atención de los repartidores es muy amable y los garrafones siempre vienen impecablemente sellados y limpios. Recomiendo totalmente a Puritronic.',
        verified: true,
    },
    {
        name: 'José Armando Ku',
        role: 'Tienda de abarrotes',
        avatarText: 'JK',
        avatarBg: 'bg-[#D9266F]',
        rating: 5,
        time: 'Hace 3 semanas',
        comment: 'Excelente control con los garrafones de cambio. Siempre nos surten a tiempo para no quedarnos sin agua en el negocio.',
        verified: true,
    },
    {
        name: 'Lucía Chan',
        role: 'Clienta frecuente',
        avatarText: 'LC',
        avatarBg: 'bg-[#0071BA]',
        rating: 5,
        time: 'Hace 1 mes',
        comment: 'Muy práctico pedir desde el teléfono. Me avisan en cada paso y mis garrafones fijos regresan siempre impecables.',
        verified: true,
    },
];

const commentsTrackRef = ref(null);
const activeCommentIdx = ref(0);

const getCarouselStepWidth = () => {
    const el = commentsTrackRef.value;
    if (!el) return 320;
    const firstCard = el.firstElementChild;
    if (!firstCard) return 320;
    const gap = parseFloat(window.getComputedStyle(el).columnGap || window.getComputedStyle(el).gap || '16') || 16;
    return firstCard.getBoundingClientRect().width + gap;
};

const scrollCommentsBy = (direction) => {
    const el = commentsTrackRef.value;
    if (!el) return;
    const step = getCarouselStepWidth();
    const maxScroll = el.scrollWidth - el.clientWidth;
    if (direction > 0 && el.scrollLeft >= maxScroll - 8) {
        el.scrollTo({ left: 0, behavior: 'smooth' });
        return;
    }
    if (direction < 0 && el.scrollLeft <= 8) {
        el.scrollTo({ left: maxScroll, behavior: 'smooth' });
        return;
    }
    el.scrollBy({ left: direction * step, behavior: 'smooth' });
};

const scrollToComment = (idx) => {
    const el = commentsTrackRef.value;
    if (!el) return;
    const step = getCarouselStepWidth();
    el.scrollTo({ left: Math.min(el.scrollWidth - el.clientWidth, idx * step), behavior: 'smooth' });
};

const onCommentsScroll = () => {
    const el = commentsTrackRef.value;
    if (!el) return;
    const step = getCarouselStepWidth();
    if (step <= 0) return;
    const maxIdx = Math.max(0, comments.length - 1);
    activeCommentIdx.value = Math.min(maxIdx, Math.max(0, Math.round(el.scrollLeft / step)));
};

const formatPrice = (val, fallback = null) => {
    if (val === null || val === undefined || val === '') return fallback;
    const num = Number(val);
    if (isNaN(num) || num <= 0) return null;
    return (num % 1 === 0) ? String(num) : num.toFixed(2);
};

const bottleProducts = computed(() => {
    const db20L = props.presentations?.find((p) => p.capacity === 20 || p.name?.includes('20')) || props.presentations?.[0];

    const is20Available = db20L ? Boolean(db20L.is_available_for_delivery) : true;
    const p20Home = db20L ? formatPrice(db20L.refill_price) : '15';
    const p20Store = db20L ? formatPrice(db20L.store_price) : '10';
    const p20New = db20L ? formatPrice(db20L.new_bottle_price) : '95';

    return [
        {
            id: 'garrafon-20l',
            name: db20L?.name ? (db20L.name.includes('20') ? db20L.name : `${db20L.name} 20L`) : 'Botellón de 20 Litros',
            isAvailableForDelivery: is20Available,
            availabilityLabel: is20Available ? 'A domicilio y en planta' : 'Solo en planta',
            image: db20L?.image_url || '/images/garrafon-20l.png',
            imageAlt: 'Botellón Puritronic de 20 Litros',
            homePrice: p20Home,
            storePrice: p20Store,
            newBottlePrice: p20New,
        },
    ];
});

const primaryProduct = computed(() => bottleProducts.value[0]);

const steps = [
    {
        num: '01',
        title: 'Haz tu pedido',
        desc: 'Indica cuántos garrafones necesitas y elige tu domicilio en segundos.',
        icon: Smartphone,
        tileClass: 'bg-[#EAF4FB] text-[#0071BA]',
        badgeClass: 'text-[#0071BA]',
    },
    {
        num: '02',
        title: 'Confirmación al instante',
        desc: 'Recibe confirmación automática y consulta en tiempo real dónde va tu agua.',
        icon: CheckCircle2,
        tileClass: 'bg-[#E8F5EE] text-[#26845B]',
        badgeClass: 'text-[#26845B]',
    },
    {
        num: '03',
        title: 'Recibe en tu puerta',
        desc: 'Entregamos tu agua purificada en modalidad Fijos o Cambios hasta tu hogar.',
        icon: Truck,
        tileClass: 'bg-[#EEEAF7] text-[#1E1545]',
        badgeClass: 'text-[#1E1545]',
    },
];
</script>

<template>
    <Head title="Puritronic" />

    <div class="min-h-screen bg-[#FFFFFF] font-sans antialiased text-[#211B35] selection:bg-[#1E1545] selection:text-white">
        <!-- ========================================================================= -->
        <!-- 1. BARRA SUPERIOR STICKY                                                  -->
        <!-- ========================================================================= -->
        <header class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-[#E7E4ED]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16 gap-3">
                    <!-- Logo -->
                    <a href="#inicio" @click="scrollToSection($event, 'inicio')" class="flex items-center shrink-0">
                        <img
                            src="/images/logo.png"
                            alt="Puritronic Agua Purificada"
                            class="h-8 sm:h-9 w-auto object-contain"
                        />
                    </a>

                    <!-- Navegación Desktop -->
                    <nav class="hidden lg:flex items-center gap-6">
                        <a
                            v-for="item in navItems"
                            :key="item.id"
                            :href="`#${item.id}`"
                            @click="scrollToSection($event, item.id)"
                            class="text-[13.5px] whitespace-nowrap py-1.5 relative transition-colors duration-150"
                            :class="activeSection === item.id
                                ? 'font-semibold text-[#1E1545] after:absolute after:bottom-0 after:left-0 after:w-full after:h-[2px] after:bg-[#D9266F] after:rounded-full'
                                : 'font-medium text-[#777386] hover:text-[#211B35]'"
                        >
                            {{ item.label }}
                        </a>
                    </nav>

                    <!-- Acciones Desktop -->
                    <div class="hidden lg:flex items-center gap-2.5 shrink-0">
                        <template v-if="$page.props.auth?.user">
                            <Link
                                :href="route('dashboard')"
                                class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-[#1E1545] hover:bg-[#2B1F60] rounded-xl shadow-[0_1px_2px_rgba(30,21,69,0.12)] transition duration-150 min-h-[40px]"
                            >
                                <span>Ir a mi panel</span>
                                <ArrowRight class="w-3.5 h-3.5" />
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                v-if="canLogin"
                                :href="route('login')"
                                class="inline-flex items-center px-3.5 py-2 text-xs font-semibold text-[#211B35] hover:text-[#1E1545] hover:bg-[#F5F4F8] rounded-xl transition duration-150 min-h-[40px]"
                            >
                                Iniciar sesión
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="inline-flex items-center px-4 py-2 text-xs font-semibold text-white bg-[#1E1545] hover:bg-[#2B1F60] rounded-xl shadow-[0_1px_2px_rgba(30,21,69,0.12)] transition duration-150 min-h-[40px]"
                            >
                                Crear cuenta
                            </Link>
                        </template>
                    </div>

                    <!-- Controles Móvil (< lg): solo botón menú limpio -->
                    <div class="flex lg:hidden items-center">
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            type="button"
                            class="w-10 h-10 rounded-xl flex items-center justify-center text-[#211B35] bg-[#F5F4F8] border border-[#E7E4ED] hover:bg-[#EEEAF7] transition focus:outline-none cursor-pointer"
                            aria-label="Menú principal de navegación"
                        >
                            <X v-if="mobileMenuOpen" class="w-5 h-5" />
                            <Menu v-else class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Backdrop móvil -->
            <div
                v-show="mobileMenuOpen"
                @click="mobileMenuOpen = false"
                class="lg:hidden fixed inset-0 top-16 bg-[#1E1545]/25 backdrop-blur-[2px] z-40"
                aria-hidden="true"
            />

            <!-- Menú desplegable móvil -->
            <div
                v-show="mobileMenuOpen"
                class="lg:hidden absolute top-16 left-0 right-0 bg-white border-b border-[#E7E4ED] shadow-xl py-3 px-4 sm:px-6 space-y-1 z-50 max-h-[calc(100vh-4rem)] overflow-y-auto"
            >
                <a
                    v-for="item in navItems"
                    :key="item.id"
                    :href="`#${item.id}`"
                    @click="scrollToSection($event, item.id)"
                    class="block px-3.5 py-2.5 text-sm rounded-xl transition-colors"
                    :class="activeSection === item.id
                        ? 'font-semibold text-[#1E1545] bg-[#EEEAF7]'
                        : 'font-medium text-[#777386] hover:text-[#211B35] hover:bg-[#F5F4F8]'"
                >
                    {{ item.label }}
                </a>

                <div class="pt-3 mt-2 border-t border-[#E7E4ED] grid grid-cols-2 gap-2">
                    <template v-if="$page.props.auth?.user">
                        <Link
                            :href="route('dashboard')"
                            class="col-span-2 text-center py-2.5 text-sm font-semibold text-white bg-[#1E1545] rounded-xl"
                        >
                            Ir a mi panel
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="text-center py-2.5 text-sm font-semibold text-[#211B35] bg-[#F5F4F8] border border-[#E7E4ED] rounded-xl"
                        >
                            Iniciar sesión
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="text-center py-2.5 text-sm font-semibold text-white bg-[#1E1545] rounded-xl"
                        >
                            Crear cuenta
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- ========================================================================= -->
        <!-- 2. HERO MODERNO Y LIMPIO (ESPACIOS AMPLIOS Y MOBILE-FIRST)                -->
        <!-- ========================================================================= -->
        <section id="inicio" class="scroll-mt-16 relative bg-white overflow-hidden border-b border-[#E7E4ED]">
            <!-- Decoración sutil en fondo sin recargar -->
            <div class="absolute -top-24 -right-24 w-72 sm:w-96 h-72 sm:h-96 rounded-full bg-[#EAF4FB]/70 blur-3xl pointer-events-none" aria-hidden="true" />
            <div class="absolute -bottom-24 -left-24 w-64 sm:w-80 h-64 sm:h-80 rounded-full bg-[#EEEAF7]/65 blur-3xl pointer-events-none" aria-hidden="true" />

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 lg:py-16 relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
                    <!-- Columna izquierda: Mensaje principal y acciones -->
                    <div class="lg:col-span-6 space-y-5 text-center lg:text-left">
                        <div class="inline-flex flex-wrap items-center justify-center gap-2 px-3 py-1.5 rounded-full bg-[#F5F4F8] border border-[#E7E4ED] text-xs font-semibold text-[#211B35]">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full" :class="storeOpenBadge.dotClass" />
                                <span :class="isStoreOpen ? 'text-[#26845B]' : 'text-[#B91C3C]'">{{ storeOpenBadge.label }}</span>
                            </span>
                            <span class="text-[#C8C2D6]">·</span>
                            <span class="text-[#0071BA]">{{ formattedScheduleDisplay.opening }} – {{ formattedScheduleDisplay.closing }}</span>
                        </div>

                        <h1 class="text-3xl sm:text-5xl lg:text-[52px] font-bold text-[#211B35] tracking-tight leading-[1.1]">
                            Tu garrafón lleno y fresco,
                            <span class="text-[#0071BA] block sm:inline"> directo hasta tu puerta.</span>
                        </h1>

                        <p class="text-sm sm:text-base text-[#777386] max-w-lg mx-auto lg:mx-0 leading-relaxed">
                            Haz tu pedido desde el celular en segundos, sigue la entrega en tiempo real y olvídate de salir a cargar garrafones.
                        </p>

                        <!-- Botones principales -->
                        <div class="pt-1 flex flex-col sm:flex-row items-stretch sm:items-center justify-center lg:justify-start gap-3">
                            <Link
                                :href="$page.props.auth?.user ? route('dashboard') : route('auth.portal')"
                                class="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl font-semibold text-white text-sm bg-[#1E1545] hover:bg-[#2B1F60] shadow-[0_4px_14px_rgba(30,21,69,0.18)] transition duration-150 min-h-[48px]"
                            >
                                <span>{{ $page.props.auth?.user ? 'Ir a mi panel' : 'Pedir agua ahora' }}</span>
                                <ArrowRight class="w-4 h-4" />
                            </Link>

                            <a
                                href="https://wa.me/529991404397?text=Hola,%20quisiera%20pedir%20un%20garrafón%20de%20agua%20a%20domicilio"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl font-semibold text-[#26845B] bg-[#E8F5EE] hover:bg-[#D8EFE3] border border-[#BBE4CE] transition duration-150 text-sm min-h-[48px]"
                            >
                                <MessageCircle class="w-4 h-4" />
                                <span>Pedir por WhatsApp</span>
                            </a>
                        </div>

                        <!-- Chips compactos de confianza (responsivos en 3 columnas compactas) -->
                        <div class="pt-4 border-t border-[#E7E4ED] grid grid-cols-3 gap-2 sm:gap-3">
                            <div class="p-2.5 sm:p-3 rounded-2xl bg-[#F5F4F8] border border-[#E7E4ED] flex flex-col sm:flex-row items-center gap-1.5 sm:gap-2.5 text-center sm:text-left">
                                <div class="w-8 h-8 rounded-xl bg-[#EAF4FB] text-[#0071BA] flex items-center justify-center shrink-0">
                                    <Truck class="w-4 h-4" />
                                </div>
                                <span class="text-[11px] sm:text-xs font-semibold text-[#211B35] leading-tight">Entrega a domicilio</span>
                            </div>

                            <div class="p-2.5 sm:p-3 rounded-2xl bg-[#F5F4F8] border border-[#E7E4ED] flex flex-col sm:flex-row items-center gap-1.5 sm:gap-2.5 text-center sm:text-left">
                                <div class="w-8 h-8 rounded-xl bg-[#E8F5EE] text-[#26845B] flex items-center justify-center shrink-0">
                                    <Smartphone class="w-4 h-4" />
                                </div>
                                <span class="text-[11px] sm:text-xs font-semibold text-[#211B35] leading-tight">Pedido en segundos</span>
                            </div>

                            <div class="p-2.5 sm:p-3 rounded-2xl bg-[#F5F4F8] border border-[#E7E4ED] flex flex-col sm:flex-row items-center gap-1.5 sm:gap-2.5 text-center sm:text-left">
                                <div class="w-8 h-8 rounded-xl bg-[#EEEAF7] text-[#1E1545] flex items-center justify-center shrink-0">
                                    <Crosshair class="w-4 h-4" />
                                </div>
                                <span class="text-[11px] sm:text-xs font-semibold text-[#211B35] leading-tight">Estado en vivo</span>
                            </div>
                        </div>
                    </div>

                    <!-- Columna derecha: Visual + Tarjeta flotante de precios rápidos -->
                    <div class="lg:col-span-6">
                        <div class="relative bg-[#F5F4F8] border border-[#E7E4ED] rounded-3xl p-5 sm:p-8 flex flex-col items-center justify-center overflow-hidden">
                            <img
                                src="/images/hero-drinking-water.svg"
                                alt="Puritronic - Entrega de garrafones a domicilio"
                                class="w-full max-w-[260px] sm:max-w-[340px] lg:max-w-[390px] h-auto object-contain mx-auto"
                            />

                            <!-- Barra de beneficios de calidad -->
                            <div
                                class="w-full mt-4 bg-white border border-[#E7E4ED] rounded-2xl p-3.5 sm:p-4 shadow-[0_6px_20px_rgba(30,21,69,0.06)] grid grid-cols-3 divide-x divide-[#E7E4ED] text-center"
                            >
                                <div class="px-1.5 sm:px-2">
                                    <span class="block text-xs sm:text-sm font-bold text-[#0071BA]">Pureza 100%</span>
                                    <span class="block text-[10px] sm:text-xs font-medium text-[#777386] mt-0.5">Ósmosis inversa</span>
                                </div>
                                <div class="px-1.5 sm:px-2">
                                    <span class="block text-xs sm:text-sm font-bold text-[#26845B]">Sellado total</span>
                                    <span class="block text-[10px] sm:text-xs font-medium text-[#777386] mt-0.5">Lavado a presión</span>
                                </div>
                                <div class="px-1.5 sm:px-2">
                                    <span class="block text-xs sm:text-sm font-bold text-[#1E1545]">Envío hoy</span>
                                    <span class="block text-[10px] sm:text-xs font-medium text-[#777386] mt-0.5">Rápido a tu casa</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 3. PRESENTACIONES Y PRECIOS                                               -->
        <!-- ========================================================================= -->
        <section id="catalogo" class="scroll-mt-16 py-12 sm:py-16 bg-[#F5F4F8] border-b border-[#E7E4ED]">
            <span id="precios" class="sr-only"></span>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-8 sm:mb-10">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-semibold bg-[#EAF4FB] text-[#0071BA] border border-[#C8E3F6] mb-3">
                        <Droplets class="w-3.5 h-3.5" />
                        Garrafón 20 L
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-bold text-[#211B35] tracking-tight">
                        Precios claros y sin sorpresas
                    </h2>
                    <p class="mt-2 text-sm sm:text-base text-[#777386]">
                        Elige recargar en sucursal o recibir tus garrafones directamente en casa.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 lg:gap-6 max-w-4xl mx-auto items-stretch">
                    <!-- Tarjeta 1: Producto 20 L -->
                    <div
                        v-for="product in bottleProducts"
                        :key="product.id"
                        class="bg-white rounded-3xl border border-[#E7E4ED] shadow-[0_2px_10px_rgba(33,27,53,0.03)] overflow-hidden flex flex-col justify-between"
                    >
                        <div>
                            <div class="bg-[#F5F4F8]/80 border-b border-[#E7E4ED] px-6 py-6 flex flex-col items-center text-center">
                                <span
                                    class="self-end inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border"
                                    :class="product.isAvailableForDelivery
                                        ? 'bg-[#E8F5EE] text-[#26845B] border-[#BBE4CE]'
                                        : 'bg-[#FDECF3] text-[#D9266F] border-[#F6C8DB]'"
                                >
                                    <span
                                        class="w-1.5 h-1.5 rounded-full"
                                        :class="product.isAvailableForDelivery ? 'bg-[#26845B]' : 'bg-[#D9266F]'"
                                    />
                                    {{ product.availabilityLabel }}
                                </span>

                                <div class="mt-2 w-28 h-28 rounded-full bg-white border border-[#E7E4ED] flex items-center justify-center p-3 shadow-[0_4px_14px_rgba(33,27,53,0.05)]">
                                    <img
                                        :src="product.image"
                                        :alt="product.imageAlt"
                                        class="max-h-full max-w-full object-contain"
                                        loading="lazy"
                                    />
                                </div>

                                <h3 class="mt-4 text-lg font-bold text-[#211B35]">
                                    Garrafón de 20 litros
                                </h3>
                                <p class="text-xs text-[#777386] mt-0.5">Precios vigentes en MXN</p>
                            </div>

                            <div class="px-6 py-2 divide-y divide-[#E7E4ED] text-sm">
                                <div v-if="product.isAvailableForDelivery && product.homePrice" class="py-3 flex items-center justify-between">
                                    <span class="text-[#777386] font-medium">Llenado a domicilio</span>
                                    <span class="text-base font-bold text-[#0071BA] tabular-nums">${{ product.homePrice }} MXN</span>
                                </div>

                                <div v-if="product.storePrice" class="py-3 flex items-center justify-between">
                                    <span class="text-[#777386] font-medium">Llenado en planta</span>
                                    <span class="text-base font-bold text-[#26845B] tabular-nums">${{ product.storePrice }} MXN</span>
                                </div>

                                <div v-if="product.newBottlePrice" class="py-3 flex items-center justify-between">
                                    <span class="text-[#777386] font-medium">Envase nuevo</span>
                                    <span class="text-base font-bold text-[#D9266F] tabular-nums">${{ product.newBottlePrice }} MXN</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 pt-3">
                            <Link
                                :href="route('auth.portal')"
                                class="w-full inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl font-semibold text-sm text-white bg-[#1E1545] hover:bg-[#2B1F60] shadow-[0_1px_2px_rgba(30,21,69,0.12)] transition duration-150 min-h-[44px]"
                            >
                                <ShoppingBag class="w-4 h-4" />
                                <span>Pedir a domicilio</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Tarjeta 2: Modalidades -->
                    <div class="bg-white rounded-3xl border border-[#E7E4ED] shadow-[0_2px_10px_rgba(33,27,53,0.03)] p-6 flex flex-col justify-between space-y-5">
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-bold text-[#211B35]">
                                    Modalidades de servicio
                                </h3>
                                <p class="text-xs sm:text-sm text-[#777386] mt-1">
                                    Elige cómo prefieres entregar y recibir tus garrafones de 20 L.
                                </p>
                            </div>

                            <div class="space-y-3">
                                <div class="p-4 rounded-2xl bg-[#EEEAF7]/55 border border-[#D8CEEE] space-y-1.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl bg-[#1E1545] text-white flex items-center justify-center shrink-0">
                                            <Tag class="w-4 h-4" />
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-semibold text-[#211B35]">Fijos · Tus propios envases</h4>
                                        </div>
                                    </div>
                                    <p class="text-xs text-[#777386] leading-relaxed pl-10">
                                        Recogemos tus garrafones rotulados, los lavamos y llenamos en planta y te devolvemos exactamente los mismos.
                                    </p>
                                </div>

                                <div class="p-4 rounded-2xl bg-[#EAF4FB]/70 border border-[#C8E3F6] space-y-1.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl bg-[#0071BA] text-white flex items-center justify-center shrink-0">
                                            <RefreshCw class="w-4 h-4" />
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-semibold text-[#211B35]">Cambios · Al instante</h4>
                                        </div>
                                    </div>
                                    <p class="text-xs text-[#777386] leading-relaxed pl-10">
                                        Entregas tus envases vacíos al repartidor y recibes garrafones llenos y sellados en la misma visita.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-[#E8F5EE]/80 border border-[#BBE4CE] flex items-center justify-between gap-2 text-xs">
                            <span class="inline-flex items-center gap-2 font-semibold text-[#26845B]">
                                <CheckCircle2 class="w-4 h-4 shrink-0" />
                                Mismo precio en ambas modalidades
                            </span>
                            <span class="font-bold text-[#211B35] shrink-0">20 L</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 4. CÓMO FUNCIONA (3 PASOS CLAROS)                                         -->
        <!-- ========================================================================= -->
        <section id="como-funciona" class="scroll-mt-16 py-12 sm:py-16 bg-white border-b border-[#E7E4ED]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-8 sm:mb-10">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-semibold bg-[#E8F5EE] text-[#26845B] border border-[#BBE4CE] mb-3">
                        Rápido y sencillo
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-bold text-[#211B35] tracking-tight">
                        Pide tu agua en tres pasos
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-6 max-w-5xl mx-auto">
                    <div
                        v-for="step in steps"
                        :key="step.num"
                        class="bg-[#F5F4F8]/70 border border-[#E7E4ED] rounded-3xl p-6 flex flex-col justify-between space-y-4 hover:bg-white hover:shadow-[0_6px_22px_rgba(33,27,53,0.05)] transition duration-150"
                    >
                        <div class="flex items-center justify-between">
                            <div class="w-11 h-11 rounded-2xl flex items-center justify-center" :class="step.tileClass">
                                <component :is="step.icon" class="w-5 h-5" />
                            </div>
                            <span class="text-xs font-bold tabular-nums px-2.5 py-1 rounded-full bg-white border border-[#E7E4ED]" :class="step.badgeClass">
                                Paso {{ step.num }}
                            </span>
                        </div>

                        <div>
                            <h3 class="text-base font-bold text-[#211B35]">
                                {{ step.title }}
                            </h3>
                            <p class="text-xs sm:text-sm text-[#777386] mt-1.5 leading-relaxed">
                                {{ step.desc }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 5. OPINIONES DE CLIENTES                                                  -->
        <!-- ========================================================================= -->
        <section id="comentarios" class="scroll-mt-16 py-12 sm:py-16 bg-[#F5F4F8] border-b border-[#E7E4ED]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 max-w-6xl mx-auto mb-6 sm:mb-8">
                    <div class="text-center sm:text-left">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white border border-[#E7E4ED] mb-3 shadow-2xs">
                            <div class="flex text-amber-400 gap-0.5">
                                <Star v-for="n in 5" :key="n" class="w-3.5 h-3.5 fill-amber-400" />
                            </div>
                            <span class="text-xs font-semibold text-[#211B35]">5.0 calificación promedio</span>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-[#211B35] tracking-tight">
                            Lo que dicen nuestros clientes
                        </h2>
                    </div>

                    <!-- Flechas de navegación del carrusel -->
                    <div class="hidden sm:flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            @click="scrollCommentsBy(-1)"
                            class="w-10 h-10 rounded-xl bg-white border border-[#E7E4ED] text-[#211B35] hover:border-[#1E1545] hover:bg-[#EEEAF7]/40 flex items-center justify-center transition cursor-pointer shadow-2xs"
                            aria-label="Comentario anterior"
                            title="Anterior"
                        >
                            <ChevronLeft class="w-4 h-4" />
                        </button>
                        <button
                            type="button"
                            @click="scrollCommentsBy(1)"
                            class="w-10 h-10 rounded-xl bg-white border border-[#E7E4ED] text-[#211B35] hover:border-[#1E1545] hover:bg-[#EEEAF7]/40 flex items-center justify-center transition cursor-pointer shadow-2xs"
                            aria-label="Siguiente comentario"
                            title="Siguiente"
                        >
                            <ChevronRight class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Carrusel horizontal (móvil, tablet y escritorio) -->
                <div class="max-w-6xl mx-auto space-y-5">
                    <div
                        ref="commentsTrackRef"
                        @scroll.passive="onCommentsScroll"
                        class="flex gap-4 lg:gap-5 overflow-x-auto snap-x snap-mandatory scroll-smooth pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    >
                        <div
                            v-for="(item, index) in comments"
                            :key="index"
                            class="w-[85%] sm:w-[calc(50%-0.5rem)] lg:w-[calc(33.333%-0.85rem)] shrink-0 snap-center sm:snap-start bg-white rounded-3xl p-5 sm:p-6 border border-[#E7E4ED] shadow-[0_2px_10px_rgba(33,27,53,0.03)] flex flex-col justify-between"
                        >
                            <div>
                                <div class="flex items-center text-amber-400 gap-1 mb-3">
                                    <Star v-for="s in item.rating" :key="s" class="w-3.5 h-3.5 fill-amber-400" />
                                </div>

                                <p
                                    class="text-[#211B35] text-xs sm:text-sm leading-relaxed mb-2"
                                    :class="{ 'line-clamp-3': !expandedComments[index] }"
                                >
                                    “{{ item.comment }}”
                                </p>

                                <button
                                    v-if="item.comment && item.comment.length > 110"
                                    @click="toggleComment(index)"
                                    type="button"
                                    class="text-xs font-semibold text-[#0071BA] hover:text-[#005994] focus:outline-none mb-3 inline-block cursor-pointer"
                                >
                                    {{ expandedComments[index] ? 'Leer menos' : 'Leer más' }}
                                </button>
                            </div>

                            <div class="pt-4 border-t border-[#E7E4ED] flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div :class="`w-9 h-9 rounded-xl ${item.avatarBg} text-white font-bold text-xs flex items-center justify-center shrink-0`">
                                        {{ item.avatarText }}
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="text-xs sm:text-sm font-semibold text-[#211B35] truncate flex items-center gap-1">
                                            <span>{{ item.name }}</span>
                                            <CheckCircle2 v-if="item.verified" class="w-3.5 h-3.5 text-[#0071BA] shrink-0" />
                                        </h4>
                                        <p class="text-[11px] text-[#777386] truncate">{{ item.role }}</p>
                                    </div>
                                </div>
                                <span class="text-[11px] text-[#777386] shrink-0">{{ item.time }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Indicadores + flechas móviles -->
                    <div class="flex items-center justify-center gap-3">
                        <button
                            type="button"
                            @click="scrollCommentsBy(-1)"
                            class="sm:hidden w-8 h-8 rounded-xl bg-white border border-[#E7E4ED] text-[#211B35] flex items-center justify-center cursor-pointer"
                            aria-label="Comentario anterior"
                        >
                            <ChevronLeft class="w-4 h-4" />
                        </button>

                        <div class="flex items-center gap-1.5">
                            <button
                                v-for="(item, idx) in comments"
                                :key="`dot-${idx}`"
                                type="button"
                                @click="scrollToComment(idx)"
                                :aria-label="`Ver comentario ${idx + 1}`"
                                class="h-2 rounded-full transition-all duration-200 cursor-pointer"
                                :class="activeCommentIdx === idx ? 'w-6 bg-[#1E1545]' : 'w-2 bg-[#CFC8E0] hover:bg-[#A6A3B2]'"
                            />
                        </div>

                        <button
                            type="button"
                            @click="scrollCommentsBy(1)"
                            class="sm:hidden w-8 h-8 rounded-xl bg-white border border-[#E7E4ED] text-[#211B35] flex items-center justify-center cursor-pointer"
                            aria-label="Siguiente comentario"
                        >
                            <ChevronRight class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 6. SUCURSAL EN HOCABÁ                                                     -->
        <!-- ========================================================================= -->
        <section id="sucursal" class="scroll-mt-16 py-12 sm:py-16 bg-white border-b border-[#E7E4ED]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-8 sm:mb-10">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-semibold bg-[#FDECF3] text-[#D9266F] border border-[#F6C8DB] mb-3">
                        <MapPin class="w-3.5 h-3.5" />
                        Sucursal Hocabá
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-bold text-[#211B35] tracking-tight">
                        Visítanos en planta
                    </h2>
                    <p class="mt-2 text-sm sm:text-base text-[#777386]">
                        Recarga tus garrafones o adquiere envases nuevos directamente en sucursal.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 lg:gap-6 max-w-6xl mx-auto items-stretch">
                    <!-- Información -->
                    <div class="bg-[#F5F4F8] rounded-3xl border border-[#E7E4ED] p-6 flex flex-col justify-between space-y-5">
                        <div class="flex items-center justify-between pb-3.5 border-b border-[#E7E4ED]">
                            <span class="text-xs font-semibold text-[#211B35]">Estado actual</span>
                            <span
                                class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border"
                                :class="storeOpenBadge.pillClass"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="storeOpenBadge.dotClass" />
                                {{ storeOpenBadge.label }}
                            </span>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-xl bg-white border border-[#E7E4ED] text-[#D9266F] flex items-center justify-center shrink-0">
                                    <MapPin class="w-4 h-4" />
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-[#211B35]">Dirección</h4>
                                    <p class="text-xs sm:text-sm text-[#777386] mt-0.5">
                                        Calle 20 #102, Centro · Hocabá, Yucatán
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-xl bg-white border border-[#E7E4ED] text-[#0071BA] flex items-center justify-center shrink-0">
                                    <Clock class="w-4 h-4" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="text-xs font-semibold text-[#211B35]">Horario</h4>
                                        <span
                                            class="text-[10px] font-semibold px-2 py-0.5 rounded-full border"
                                            :class="storeOpenBadge.pillClass"
                                        >
                                            {{ storeOpenBadge.shortLabel }}
                                        </span>
                                    </div>
                                    <p class="text-xs sm:text-sm text-[#777386] mt-0.5">
                                        {{ formattedScheduleDisplay.days }} · {{ formattedScheduleDisplay.hours }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-xl bg-white border border-[#E7E4ED] text-[#26845B] flex items-center justify-center shrink-0">
                                    <Phone class="w-4 h-4" />
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-[#211B35]">Teléfono</h4>
                                    <a
                                        href="tel:9991404397"
                                        class="text-xs sm:text-sm font-semibold text-[#0071BA] hover:underline mt-0.5 inline-block tabular-nums"
                                    >
                                        9991 40 43 97
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fotografía real -->
                    <div class="rounded-3xl overflow-hidden border border-[#E7E4ED] bg-[#F5F4F8] relative min-h-[250px] sm:min-h-[280px]">
                        <img
                            src="/images/sucursal-real.jpg"
                            alt="Fachada de Sucursal Puritronic Hocabá"
                            class="w-full h-full object-cover"
                            loading="lazy"
                        />
                        <div class="absolute bottom-3 left-3 right-3 py-2.5 px-3.5 rounded-2xl bg-white/95 backdrop-blur-md border border-[#E7E4ED] text-xs font-semibold text-[#211B35]">
                            Planta Puritronic · Hocabá Centro
                        </div>
                    </div>

                    <!-- Mapa -->
                    <div class="rounded-3xl overflow-hidden border border-[#E7E4ED] bg-[#F5F4F8] flex flex-col justify-end relative min-h-[250px] sm:min-h-[280px] md:col-span-2 lg:col-span-1">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m17!1m12!1m3!1d3729.429274512127!2d-89.249962!3d20.814365!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m2!1m1!2zMjDCsDQ4JzUxLjciTiA4OcKwMTQnNTkuOSJX!5e0!3m2!1ses-419!2smx!4v1792245980366!5m2!1ses-419!2smx"
                            class="absolute inset-0 w-full h-full border-0"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="strict-origin-when-cross-origin"
                            title="Ubicación de Puritronic en Google Maps"
                        ></iframe>

                        <div class="relative z-10 p-3">
                            <a
                                href="https://maps.app.goo.gl/dxB7hkU2R1xbzGr6A"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="w-full bg-white/95 backdrop-blur-md rounded-2xl py-2.5 px-4 shadow-md border border-[#E7E4ED] hover:border-[#1E1545] transition flex items-center justify-between min-h-[44px]"
                            >
                                <span class="inline-flex items-center gap-2 text-xs font-semibold text-[#211B35]">
                                    <Navigation class="w-4 h-4 text-[#0071BA]" />
                                    Cómo llegar en Google Maps
                                </span>
                                <ChevronRight class="w-4 h-4 text-[#777386]" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 7. PREGUNTAS FRECUENTES                                                   -->
        <!-- ========================================================================= -->
        <section id="preguntas" class="scroll-mt-16 py-12 sm:py-16 bg-[#F5F4F8] border-b border-[#E7E4ED]">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-xl mx-auto mb-8 sm:mb-10">
                    <h2 class="text-2xl sm:text-3xl font-bold text-[#211B35] tracking-tight">
                        Preguntas frecuentes
                    </h2>
                    <p class="mt-2 text-sm text-[#777386]">
                        Respuestas rápidas sobre envases, entregas y pagos.
                    </p>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(faq, index) in faqs"
                        :key="index"
                        class="rounded-2xl border transition-all duration-150 overflow-hidden bg-white"
                        :class="openFaq === index ? 'border-[#1E1545] shadow-[0_4px_16px_rgba(30,21,69,0.06)]' : 'border-[#E7E4ED]'"
                    >
                        <button
                            @click="toggleFaq(index)"
                            type="button"
                            class="w-full px-5 py-4 flex items-center justify-between text-left focus:outline-none gap-3 min-h-[52px] cursor-pointer"
                            :aria-expanded="openFaq === index"
                        >
                            <span
                                class="text-xs sm:text-sm font-semibold transition-colors"
                                :class="openFaq === index ? 'text-[#1E1545]' : 'text-[#211B35]'"
                            >
                                {{ faq.q }}
                            </span>

                            <div
                                class="w-7 h-7 rounded-xl flex items-center justify-center transition-transform duration-200 shrink-0"
                                :class="openFaq === index ? 'bg-[#1E1545] text-white rotate-180' : 'bg-[#F5F4F8] text-[#777386]'"
                            >
                                <ChevronDown class="w-4 h-4" />
                            </div>
                        </button>

                        <div
                            v-show="openFaq === index"
                            class="px-5 pb-4 pt-1 text-[#777386] text-xs sm:text-sm leading-relaxed border-t border-[#E7E4ED]"
                        >
                            {{ faq.a }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 8. BLOQUE FINAL DE ACCIÓN                                                 -->
        <!-- ========================================================================= -->
        <section class="py-10 sm:py-14 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="rounded-3xl bg-[#1E1545] p-6 sm:p-10 text-white grid grid-cols-1 lg:grid-cols-12 gap-6 items-center relative overflow-hidden">
                    <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-[#0071BA]/25 pointer-events-none" aria-hidden="true" />

                    <div class="lg:col-span-7 space-y-2 text-center lg:text-left relative z-10">
                        <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">
                            ¿Te hace falta agua hoy?
                        </h2>
                        <p class="text-white/80 text-sm sm:text-base max-w-md mx-auto lg:mx-0">
                            Crea tu cuenta en menos de un minuto o inicia sesión para pedir a domicilio.
                        </p>
                    </div>

                    <div class="lg:col-span-5 flex flex-col sm:flex-row lg:justify-end gap-3 relative z-10">
                        <Link
                            :href="route('register')"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-white text-[#1E1545] hover:bg-[#F5F4F8] font-semibold text-sm transition min-h-[46px]"
                        >
                            <span>Crear cuenta</span>
                            <ArrowRight class="w-4 h-4" />
                        </Link>

                        <Link
                            :href="route('login')"
                            class="inline-flex items-center justify-center px-5 py-3 rounded-xl bg-white/15 hover:bg-white/25 text-white border border-white/20 font-semibold text-sm transition min-h-[46px]"
                        >
                            <span>Iniciar sesión</span>
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 9. FOOTER CORPORATIVO                                                     -->
        <!-- ========================================================================= -->
        <footer id="contacto" class="scroll-mt-16 bg-[#1E1545] text-white pt-12 pb-8 border-t border-white/10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 pb-10 border-b border-white/10">
                    <div class="space-y-3">
                        <img
                            src="/images/logo.png"
                            alt="Puritronic Agua Purificada"
                            class="h-9 w-auto object-contain"
                        />
                        <p class="text-white/70 text-xs sm:text-sm max-w-sm leading-relaxed">
                            Agua purificada de calidad y entrega puntual a domicilio en Hocabá, Yucatán.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <h3 class="text-xs font-semibold text-white uppercase tracking-wider">
                            Secciones
                        </h3>
                        <ul class="grid grid-cols-2 gap-2 text-xs text-white/75">
                            <li><a href="#inicio" @click="scrollToSection($event, 'inicio')" class="hover:text-white transition-colors">Inicio</a></li>
                            <li><a href="#catalogo" @click="scrollToSection($event, 'catalogo')" class="hover:text-white transition-colors">Precios</a></li>
                            <li><a href="#como-funciona" @click="scrollToSection($event, 'como-funciona')" class="hover:text-white transition-colors">Cómo funciona</a></li>
                            <li><a href="#comentarios" @click="scrollToSection($event, 'comentarios')" class="hover:text-white transition-colors">Opiniones</a></li>
                            <li><a href="#sucursal" @click="scrollToSection($event, 'sucursal')" class="hover:text-white transition-colors">Sucursal</a></li>
                            <li><a href="#preguntas" @click="scrollToSection($event, 'preguntas')" class="hover:text-white transition-colors">Preguntas</a></li>
                        </ul>
                    </div>

                    <div class="space-y-3">
                        <h3 class="text-xs font-semibold text-white uppercase tracking-wider">
                            Contacto
                        </h3>
                        <ul class="space-y-2.5 text-xs text-white/75">
                            <li class="flex items-start gap-2.5">
                                <MapPin class="w-4 h-4 text-[#38BDF8] shrink-0 mt-0.5" />
                                <span>Calle 20 #102, Centro · Hocabá</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <Clock class="w-4 h-4 text-[#38BDF8] shrink-0 mt-0.5" />
                                <span class="flex flex-wrap items-center gap-2">
                                    <span>{{ formattedScheduleDisplay.fullText }}</span>
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                        :class="isStoreOpen ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="isStoreOpen ? 'bg-emerald-400' : 'bg-rose-400'" />
                                        {{ storeOpenBadge.shortLabel }}
                                    </span>
                                </span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <Phone class="w-4 h-4 text-[#38BDF8] shrink-0 mt-0.5" />
                                <a href="tel:9991404397" class="hover:text-white font-medium tabular-nums">9991 40 43 97</a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="pt-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-white/60">
                    <p>© {{ new Date().getFullYear() }} Puritronic Hocabá. Todos los derechos reservados.</p>
                    <div class="flex items-center gap-5">
                        <a href="#inicio" @click="scrollToSection($event, 'inicio')" class="hover:text-white transition-colors">Volver arriba</a>
                        <Link :href="$page.props.auth?.user ? route('dashboard') : route('login')" class="hover:text-white transition-colors font-semibold text-white">
                            {{ $page.props.auth?.user ? 'Mi panel' : 'Iniciar sesión' }}
                        </Link>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Botones flotantes laterales (Plataforma arriba + WhatsApp abajo) -->
        <div class="fixed bottom-5 right-4 sm:bottom-6 sm:right-6 z-40 flex flex-col items-end gap-3.5">
            <!-- Pedir agua por la plataforma -->
            <Link
                :href="$page.props.auth?.user ? route('dashboard') : route('auth.portal')"
                :aria-label="$page.props.auth?.user ? 'Ir a mi panel' : 'Pedir agua por la plataforma'"
                :title="$page.props.auth?.user ? 'Ir a mi panel' : 'Pedir agua por la plataforma'"
                class="group inline-flex items-center gap-2.5 cursor-pointer"
            >
                <span class="hidden sm:inline-flex items-center px-3 py-1.5 rounded-xl bg-white border border-[#E7E4ED] shadow-[0_6px_18px_rgba(33,27,53,0.08)] text-xs font-semibold text-[#211B35] opacity-0 translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200 pointer-events-none">
                    {{ $page.props.auth?.user ? 'Ir a mi panel' : 'Pedir en plataforma' }}
                </span>

                <span class="relative flex w-14 h-14 shrink-0 items-center justify-center rounded-full bg-[#1E1545] hover:bg-[#0071BA] text-white shadow-[0_8px_24px_rgba(30,21,69,0.28)] hover:scale-105 transition-all duration-200">
                    <ShoppingBag class="w-6 h-6" />
                </span>
            </Link>

            <!-- Pedir por WhatsApp -->
            <a
                href="https://wa.me/529991404397?text=Hola,%20quisiera%20pedir%20un%20garrafón%20de%20agua%20a%20domicilio"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Pedir por WhatsApp"
                title="Pedir por WhatsApp"
                class="group inline-flex items-center gap-2.5 cursor-pointer"
            >
                <span class="hidden sm:inline-flex items-center px-3 py-1.5 rounded-xl bg-white border border-[#E7E4ED] shadow-[0_6px_18px_rgba(33,27,53,0.08)] text-xs font-semibold text-[#211B35] opacity-0 translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200 pointer-events-none">
                    Pedir por WhatsApp
                </span>

                <span class="relative flex w-14 h-14 shrink-0 items-center justify-center rounded-full bg-[#25D366] hover:bg-[#1EBE5B] text-white shadow-[0_8px_24px_rgba(37,211,102,0.38)] hover:scale-105 transition-all duration-200">
                    <svg class="w-7 h-7 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12.031 2c-5.516 0-9.969 4.453-9.969 9.969 0 1.766.469 3.484 1.344 5l-1.406 5.156 5.281-1.391c1.469.797 3.109 1.219 4.75 1.219 5.516 0 9.969-4.453 9.969-9.969S17.547 2 12.031 2zm5.813 14.125c-.25.703-1.422 1.344-1.969 1.422-.531.078-1.203.109-1.938-.125-.453-.141-1.031-.328-1.766-.656-3.094-1.344-5.109-4.469-5.266-4.672-.156-.203-1.266-1.688-1.266-3.219 0-1.531.797-2.281 1.078-2.594.281-.313.609-.391.813-.391.203 0 .406 0 .578.016.188.016.438-.078.688.531.25.609.859 2.094.938 2.25.078.156.125.344.031.547-.094.203-.156.344-.313.531-.156.188-.328.406-.469.547-.156.156-.328.328-.141.641.188.313.844 1.391 1.813 2.25 1.25 1.109 2.297 1.453 2.609 1.609.313.156.5.125.688-.078.188-.203.797-.938 1.016-1.25.219-.313.438-.266.734-.156.297.109 1.891.891 2.219 1.047.328.156.547.234.625.359.078.125.078.734-.172 1.438z"/>
                    </svg>
                </span>
            </a>
        </div>
    </div>
</template>
