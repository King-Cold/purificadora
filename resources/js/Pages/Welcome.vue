<script setup>
import { Head, Link } from '@inertiajs/vue3';
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
        days: days,
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

    // Si es inicio, desplazar directamente a la parte superior absoluta
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

    // Usar nextTick para calcular la posición con el estado de cierre ya procesado
    nextTick(() => {
        const headerOffset = 64; // Altura exacta del header fijo (h-16)
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

                // Si está cerca del final de la página, marcar contacto
                if (window.innerHeight + scrollY >= document.documentElement.scrollHeight - 80) {
                    activeSection.value = 'contacto';
                    ticking = false;
                    return;
                }

                const checkPosition = scrollY + 80;
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

const openFaq = ref(null);
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
        avatarBg: 'bg-[#0076E8]',
        rating: 5,
        time: 'Hace 2 días',
        comment: 'Excelente servicio y puntualidad. Antes tenía que esperar a que pasara el camión o salir a buscar garrafón. Ahora pido por el celular y llega directo a mi casa en minutos.',
        verified: true,
    },
    {
        name: 'Carlos Dzib',
        role: 'Negocio de comidas',
        avatarText: 'CD',
        avatarBg: 'bg-emerald-600',
        rating: 5,
        time: 'Hace 1 semana',
        comment: 'El agua tiene un sabor fresco y ligero, de muy alta pureza. En mi negocio consumimos varios garrafones a la semana y siempre cumplen con los tiempos acordados.',
        verified: true,
    },
    {
        name: 'Rosaura May',
        role: 'Madre de familia',
        avatarText: 'RM',
        avatarBg: 'bg-teal-600',
        rating: 5,
        time: 'Hace 2 semanas',
        comment: 'La atención de los repartidores es muy amable y los garrafones siempre vienen impecablemente sellados y limpios. Recomiendo totalmente a Puritronic.',
        verified: true,
    },
];

const formatPrice = (val, fallback = null) => {
    if (val === null || val === undefined || val === '') return fallback;
    const num = Number(val);
    if (isNaN(num) || num <= 0) return null;
    return (num % 1 === 0) ? String(num) : num.toFixed(2);
};

const bottleProducts = computed(() => {
    const db20L = props.presentations?.find(p => p.capacity === 20 || p.name?.includes('20')) || props.presentations?.[0];

    const is20Available = db20L ? Boolean(db20L.is_available_for_delivery) : true;
    const p20Home = db20L ? formatPrice(db20L.refill_price) : '15';
    const p20Store = db20L ? formatPrice(db20L.store_price) : '10';
    const p20New = db20L ? formatPrice(db20L.new_bottle_price) : '95';

    return [
        {
            id: 'garrafon-20l',
            name: db20L?.name ? (db20L.name.includes('20') ? db20L.name : `${db20L.name} 20L`) : 'Botellón de 20 Litros',
            isAvailableForDelivery: is20Available,
            availabilityLabel: is20Available ? 'A domicilio y en planta' : 'Disponible en planta',
            image: db20L?.image_url || '/images/garrafon-20l.png',
            imageAlt: 'Botellón Puritronic de 20 Litros',
            homePrice: p20Home,
            storePrice: p20Store,
            newBottlePrice: p20New,
        },
    ];
});
</script>

<template>
    <Head title="Puritronic Hocabá - Agua pura, directo hasta su hogar" />

    <div class="min-h-screen bg-white font-['Poppins',sans-serif] text-[#10233F] antialiased selection:bg-[#0076E8] selection:text-white">
        
        <!-- ========================================================================= -->
        <!-- 1. ENCABEZADO (BARRA COMPACTA h-16 / 64px)                                -->
        <!-- ========================================================================= -->
        <header class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-200/80 transition-all duration-200">
            <div class="max-w-7xl 2xl:max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16 gap-4">
                    
                    <!-- LOGO PURITRONIC -->
                    <a href="#inicio" @click="scrollToSection($event, 'inicio')" class="flex items-center shrink-0 group mr-2 sm:mr-4">
                        <img
                            src="/images/logo.png"
                            alt="Puritronic Agua Purificada"
                            class="h-9 sm:h-10 w-auto object-contain transition-transform duration-200 group-hover:scale-[1.02]"
                        />
                    </a>

                    <!-- NAVEGACIÓN DESKTOP: TEXTO AZUL MARINO (#10233F) Y LÍNEA ACTIVA ROJO VINO (#9C3550) DE 2px -->
                    <nav class="hidden lg:flex items-center gap-5 xl:gap-7">
                        <a
                            v-for="item in navItems"
                            :key="item.id"
                            :href="`#${item.id}`"
                            @click="scrollToSection($event, item.id)"
                            :class="[
                                'text-xs xl:text-sm whitespace-nowrap transition-colors duration-150 py-1.5 px-0.5 relative',
                                activeSection === item.id
                                    ? 'font-bold text-[#10233F] after:absolute after:bottom-0 after:left-0 after:w-full after:h-[2px] after:bg-[#9C3550] after:rounded-full'
                                    : 'font-medium text-slate-600 hover:text-[#10233F]'
                            ]"
                        >
                            {{ item.label }}
                        </a>
                    </nav>

                    <!-- ACCIONES DE CUENTA: CREAR CUENTA DESTACADO EN AZUL #0076E8, INICIAR SESIÓN SECUNDARIO -->
                    <div class="hidden lg:flex items-center gap-3 shrink-0 ml-2 sm:ml-4">
                        <template v-if="$page.props.auth?.user">
                            <Link
                                :href="route('dashboard')"
                                class="px-5 py-2 text-xs xl:text-sm font-semibold text-white bg-[#0076E8] hover:bg-[#0060be] rounded-xl shadow-xs hover:shadow transition-all flex items-center gap-2 whitespace-nowrap min-h-[40px]"
                            >
                                <span>Ir a mi Panel</span>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                v-if="canLogin"
                                :href="route('login')"
                                class="px-3.5 py-2 text-xs xl:text-sm font-semibold text-[#10233F] hover:text-[#0076E8] hover:bg-slate-50 rounded-xl transition-colors whitespace-nowrap min-h-[40px] inline-flex items-center"
                            >
                                Iniciar sesión
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="px-5 py-2 text-xs xl:text-sm font-semibold text-white bg-[#0076E8] hover:bg-[#0060be] rounded-xl shadow-xs hover:shadow hover:-translate-y-0.5 transition-all whitespace-nowrap min-h-[40px] inline-flex items-center"
                            >
                                Crear cuenta
                            </Link>
                        </template>
                    </div>

                    <!-- BOTÓN MÓVIL HAMBURGUESA (< lg) -->
                    <div class="flex lg:hidden">
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            type="button"
                            class="w-11 h-11 rounded-xl flex items-center justify-center text-[#10233F] hover:text-black bg-slate-100 hover:bg-slate-200/80 transition-colors focus:outline-none"
                            aria-label="Menú principal de navegación"
                        >
                            <i :class="mobileMenuOpen ? 'fa-solid fa-xmark text-lg' : 'fa-solid fa-bars text-lg'"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- BACKDROP MÓVIL AL ABRIR EL MENÚ -->
            <div
                v-show="mobileMenuOpen"
                @click="mobileMenuOpen = false"
                class="lg:hidden fixed inset-0 top-16 bg-black/20 backdrop-blur-xs z-40 transition-opacity"
                aria-hidden="true"
            ></div>

            <!-- MENÚ MÓVIL DESPLEGABLE COMO OVERLAY FLOTANTE (< lg) -->
            <div
                v-show="mobileMenuOpen"
                class="lg:hidden absolute top-16 left-0 right-0 bg-white border-b border-slate-200/90 shadow-xl py-3 px-4 sm:px-6 space-y-1.5 z-50 max-h-[calc(100vh-5rem)] overflow-y-auto"
            >
                <a
                    v-for="item in navItems"
                    :key="item.id"
                    :href="`#${item.id}`"
                    @click="scrollToSection($event, item.id)"
                    :class="[
                        'block px-3 py-2 text-sm rounded-xl transition-colors',
                        activeSection === item.id
                            ? 'font-bold text-[#10233F] bg-[#EAF5FF] border-l-4 border-[#9C3550]'
                            : 'font-medium text-slate-600 hover:text-[#10233F] hover:bg-slate-50'
                    ]"
                >
                    {{ item.label }}
                </a>
                <div class="pt-3 mt-2 border-t border-slate-100 flex flex-col gap-2">
                    <template v-if="$page.props.auth?.user">
                        <Link
                            :href="route('dashboard')"
                            class="w-full text-center py-2.5 text-sm font-semibold text-white bg-[#0076E8] hover:bg-[#0060be] rounded-xl shadow-xs"
                        >
                            Ir a mi Panel
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="w-full text-center py-2.5 text-sm font-semibold text-[#10233F] bg-slate-50 border border-slate-200/80 rounded-xl hover:bg-slate-100"
                        >
                            Iniciar sesión
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="w-full text-center py-2.5 text-sm font-semibold text-white bg-[#0076E8] hover:bg-[#0060be] rounded-xl shadow-xs"
                        >
                            Crear cuenta
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- ========================================================================= -->
        <!-- 2. PORTADA / HERO (FONDO CELESTE SUAVE #EAF5FF, ESPACIADO COMPACTO)       -->
        <!-- ========================================================================= -->
        <section id="inicio" class="scroll-mt-16 relative py-8 sm:py-12 lg:py-14 bg-[#EAF5FF] border-b border-blue-100/60 overflow-hidden">
            <div class="max-w-7xl 2xl:max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center">
                    
                    <!-- COLUMNA IZQUIERDA EN ESCRITORIO: TÍTULO Y BOTONES (EN MÓVIL VA PRIMERO) -->
                    <div class="lg:col-span-7 space-y-5 text-center lg:text-left order-1">
                        
                        <!-- Indicador de Servicio Activo en Hocabá -->
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/90 border border-blue-200/70 text-[#0076E8] text-xs font-semibold shadow-2xs">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <i class="fa-solid fa-location-dot text-[11px]"></i>
                            <span>Servicio a domicilio en Hocabá</span>
                        </div>

                        <!-- Título Principal en Azul Marino #10233F con acento Azul #0076E8 -->
                        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-[#10233F] tracking-tight leading-[1.14]">
                            Agua pura,<br />
                            <span class="text-[#0076E8]">hasta tu hogar.</span>
                        </h1>

                        <!-- Descripción concisa -->
                        <p class="text-sm sm:text-base text-slate-600 max-w-lg mx-auto lg:mx-0 leading-relaxed font-normal">
                            Pide desde tu celular y recibe agua purificada en Hocabá.
                        </p>

                        <!-- Botones de Acción: "Pedir agua" y "Pedir por WhatsApp" -->
                        <div class="pt-1 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3">
                            <Link
                                :href="route('auth.portal')"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-bold text-white text-xs sm:text-sm bg-[#0076E8] hover:bg-[#0060be] shadow-sm hover:shadow hover:-translate-y-0.5 transition-all group min-h-[44px]"
                            >
                                <span>Pedir agua</span>
                                <i class="fa-solid fa-chevron-right text-xs group-hover:translate-x-1 transition-transform"></i>
                            </Link>

                            <a
                                href="https://wa.me/529991404397?text=Hola,%20quisiera%20pedir%20un%20garrafón%20de%20agua%20a%20domicilio"
                                target="_blank"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-semibold text-[#10233F] hover:text-[#0076E8] bg-white hover:bg-slate-50 border border-slate-200/90 shadow-2xs hover:shadow-xs transition-all text-xs sm:text-sm min-h-[44px]"
                            >
                                <i class="fa-brands fa-whatsapp text-base text-emerald-600"></i>
                                <span>Pedir por WhatsApp</span>
                            </a>
                        </div>

                        <!-- FILA COMPACTA DE BENEFICIOS: ICONO + FRASE ÚNICA -->
                        <div class="pt-5 border-t border-blue-200/60 grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 text-left">
                            
                            <!-- Beneficio 1: Entrega a domicilio -->
                            <div class="bg-white/85 backdrop-blur-xs px-3.5 py-2.5 rounded-xl border border-blue-100 shadow-2xs flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-[#EAF5FF] text-[#0076E8] flex items-center justify-center shrink-0 text-xs">
                                    <i class="fa-solid fa-truck-fast"></i>
                                </div>
                                <span class="text-xs sm:text-sm font-semibold text-[#10233F]">Entrega a domicilio</span>
                            </div>

                            <!-- Beneficio 2: Pedido fácil -->
                            <div class="bg-white/85 backdrop-blur-xs px-3.5 py-2.5 rounded-xl border border-blue-100 shadow-2xs flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-[#EAF5FF] text-[#0076E8] flex items-center justify-center shrink-0 text-xs">
                                    <i class="fa-solid fa-mobile-screen"></i>
                                </div>
                                <span class="text-xs sm:text-sm font-semibold text-[#10233F]">Pedido fácil</span>
                            </div>

                            <!-- Beneficio 3: Sigue tu pedido -->
                            <div class="bg-white/85 backdrop-blur-xs px-3.5 py-2.5 rounded-xl border border-blue-100 shadow-2xs flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-[#EAF5FF] text-[#0076E8] flex items-center justify-center shrink-0 text-xs">
                                    <i class="fa-solid fa-location-crosshairs"></i>
                                </div>
                                <span class="text-xs sm:text-sm font-semibold text-[#10233F]">Sigue tu pedido</span>
                            </div>

                        </div>
                    </div>

                    <!-- COLUMNA DERECHA EN ESCRITORIO: ILUSTRACIÓN DE INICIO (EN MÓVIL VA DESPUÉS DE LOS BOTONES) -->
                    <div class="lg:col-span-5 relative flex items-center justify-center order-2">
                        <div class="w-full max-w-sm sm:max-w-md lg:max-w-none flex justify-center">
                            <img
                                src="/images/hero-drinking-water.svg"
                                alt="Agua pura hasta tu hogar - Puritronic"
                                class="w-full h-auto max-h-[340px] sm:max-h-[380px] lg:max-h-[420px] object-contain drop-shadow-sm mx-auto"
                            />
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 3. PRESENTACIONES Y PRECIOS (FONDO AZUL GRISÁCEO #F2F6FA, COMPACTO)       -->
        <!-- ========================================================================= -->
        <section id="catalogo" class="scroll-mt-16 py-10 sm:py-12 lg:py-14 bg-[#F2F6FA] border-b border-slate-200/80">
            <span id="precios" class="sr-only"></span>
            <div class="max-w-7xl 2xl:max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Encabezado sin etiquetas redundantes -->
                <div class="text-center max-w-2xl mx-auto mb-8 sm:mb-10">
                    <h2 class="text-2xl sm:text-[30px] lg:text-[32px] font-bold text-[#10233F] tracking-tight">
                        Presentaciones y precios
                    </h2>
                    <div class="w-12 h-[3px] bg-[#9C3550] rounded-full mx-auto mt-2.5" aria-hidden="true"></div>
                    <p class="mt-3 text-sm sm:text-base text-slate-600 font-normal leading-relaxed">
                        Consulta nuestros precios en sucursal y a domicilio.
                    </p>
                </div>

                <!-- Grid de Garrafón de 20 Litros junto al Bloque de Modalidades -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 lg:gap-6 max-w-4xl mx-auto items-stretch">
                    
                    <!-- Tarjeta 1: Garrafón de 20 Litros (Tarjeta blanca sobre fondo suave) -->
                    <div
                        v-for="product in bottleProducts"
                        :key="product.id"
                        class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 flex flex-col justify-between shadow-xs hover:border-slate-300 transition-all"
                    >
                        <div>
                            <!-- Imagen centrada y limpia -->
                            <div class="flex items-center justify-center py-2 mb-2">
                                <img
                                    :src="product.image"
                                    :alt="product.imageAlt"
                                    class="h-32 sm:h-36 w-auto object-contain drop-shadow-2xs transition-transform duration-200 hover:scale-105"
                                    loading="lazy"
                                />
                            </div>

                            <!-- Nombre y referencia de moneda MXN una sola vez -->
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                <h3 class="text-base sm:text-lg font-bold text-[#10233F] tracking-tight">
                                    Garrafón de 20 litros
                                </h3>
                                <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full border border-slate-200/70">
                                    Precios en MXN
                                </span>
                            </div>

                            <!-- Lista alineada de precios para lectura inmediata -->
                            <div class="border-t border-slate-100 pt-3 pb-1 space-y-2.5 text-sm">
                                <div
                                    v-if="product.isAvailableForDelivery && product.homePrice"
                                    class="flex items-center justify-between gap-2 text-slate-700"
                                >
                                    <span class="text-slate-600 font-medium">A domicilio</span>
                                    <span class="font-extrabold text-[#10233F] shrink-0 text-base">${{ product.homePrice }}</span>
                                </div>

                                <div
                                    v-if="product.storePrice"
                                    class="flex items-center justify-between gap-2 text-slate-700"
                                >
                                    <span class="text-slate-600 font-medium">Llenado en sucursal</span>
                                    <span class="font-extrabold text-[#10233F] shrink-0 text-base">${{ product.storePrice }}</span>
                                </div>

                                <div
                                    v-if="product.newBottlePrice"
                                    class="flex items-center justify-between gap-2 text-xs text-slate-500 pt-2 border-t border-slate-100"
                                >
                                    <span>Envase nuevo</span>
                                    <span class="font-semibold text-slate-800 shrink-0">${{ product.newBottlePrice }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Acciones -->
                        <div class="pt-4 mt-auto border-t border-slate-100">
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                                <Link
                                    :href="route('auth.portal')"
                                    class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl font-bold text-xs sm:text-sm text-white bg-[#0076E8] hover:bg-[#0060be] shadow-xs hover:shadow transition-all text-center min-h-[42px]"
                                >
                                    <i class="fa-solid fa-cart-shopping text-xs"></i>
                                    <span>Pedir a domicilio</span>
                                </Link>

                                <a
                                    href="#sucursal"
                                    @click="scrollToSection($event, 'sucursal')"
                                    class="inline-flex items-center justify-center gap-1.5 py-2 px-3 text-xs sm:text-sm font-semibold text-slate-600 hover:text-[#0076E8] transition-colors text-center"
                                >
                                    <i class="fa-solid fa-location-dot text-xs text-slate-400"></i>
                                    <span>Ver sucursal</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta 2: Modalidades de servicio -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 flex flex-col justify-between shadow-xs">
                        <div class="space-y-3.5">
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-[#10233F]">
                                    Modalidades de servicio
                                </h3>
                                <p class="text-xs text-slate-600 mt-1">
                                    Dos modalidades para tus garrafones de 20 litros.
                                </p>
                            </div>

                            <div class="space-y-3 text-xs">
                                <!-- Modalidad Fijos -->
                                <div class="p-3.5 bg-slate-50/80 rounded-xl border border-slate-200/70 space-y-1">
                                    <div class="flex items-center gap-2 font-bold text-[#10233F]">
                                        <div class="w-6 h-6 rounded-md bg-blue-100/70 text-[#0076E8] flex items-center justify-center text-xs shrink-0">
                                            <i class="fa-solid fa-tag"></i>
                                        </div>
                                        <span class="text-xs sm:text-sm">Fijos · Tus propios envases</span>
                                    </div>
                                    <p class="text-slate-600 leading-relaxed pl-8">
                                        Recogemos tus garrafones, los llenamos y te devolvemos los mismos.
                                    </p>
                                </div>

                                <!-- Modalidad Cambios -->
                                <div class="p-3.5 bg-slate-50/80 rounded-xl border border-slate-200/70 space-y-1">
                                    <div class="flex items-center gap-2 font-bold text-[#10233F]">
                                        <div class="w-6 h-6 rounded-md bg-emerald-100/70 text-emerald-600 flex items-center justify-center text-xs shrink-0">
                                            <i class="fa-solid fa-arrows-rotate"></i>
                                        </div>
                                        <span class="text-xs sm:text-sm">Cambios · Intercambio en tu domicilio</span>
                                    </div>
                                    <p class="text-slate-600 leading-relaxed pl-8">
                                        Entregas un garrafón vacío y recibes uno lleno en la misma visita.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3.5 mt-auto border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <span class="flex items-center gap-1.5 font-medium text-slate-600">
                                <i class="fa-solid fa-circle-check text-emerald-600 text-xs"></i>
                                Mismo precio de llenado en ambas modalidades
                            </span>
                            <span class="font-bold text-[#10233F]">20 Litros</span>
                        </div>
                    </div>

                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 4. CÓMO FUNCIONA (FONDO MENTA SUAVE #E8F7F2, BLOQUE COMPACTO)             -->
        <!-- ========================================================================= -->
        <section id="como-funciona" class="scroll-mt-16 py-10 sm:py-12 lg:py-14 bg-[#E8F7F2] border-b border-emerald-100/60">
            <div class="max-w-7xl 2xl:max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Encabezado sin introducción redundante -->
                <div class="text-center max-w-2xl mx-auto mb-8 sm:mb-10">
                    <h2 class="text-2xl sm:text-[30px] lg:text-[32px] font-bold text-[#10233F] tracking-tight">
                        Pide en tres pasos
                    </h2>
                    <div class="w-12 h-[3px] bg-[#9C3550] rounded-full mx-auto mt-2.5" aria-hidden="true"></div>
                </div>

                <!-- TRES PASOS REUNIDOS EN BLOQUE COMPACTO -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-5 max-w-4xl mx-auto items-stretch">
                    
                    <!-- PASO 1 -->
                    <div class="bg-white rounded-2xl p-5 border border-emerald-100/80 shadow-xs flex flex-col items-center text-center">
                        <div class="w-12 h-12 rounded-xl bg-[#EAF5FF] text-[#0076E8] flex items-center justify-center text-xl mb-3 shadow-2xs">
                            <i class="fa-solid fa-mobile-screen"></i>
                        </div>
                        <span class="text-[11px] font-bold text-[#0076E8] uppercase tracking-wider mb-1">Paso 1</span>
                        <h3 class="text-base font-bold text-[#10233F] mb-1.5">Haz tu pedido</h3>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed font-normal">
                            Indica cuántos garrafones necesitas.
                        </p>
                    </div>

                    <!-- PASO 2 -->
                    <div class="bg-white rounded-2xl p-5 border border-emerald-100/80 shadow-xs flex flex-col items-center text-center">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl mb-3 shadow-2xs">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <span class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider mb-1">Paso 2</span>
                        <h3 class="text-base font-bold text-[#10233F] mb-1.5">Confirmación automática</h3>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed font-normal">
                            Recibe la confirmación y consulta el estado de tu pedido.
                        </p>
                    </div>

                    <!-- PASO 3 -->
                    <div class="bg-white rounded-2xl p-5 border border-emerald-100/80 shadow-xs flex flex-col items-center text-center">
                        <div class="w-12 h-12 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl mb-3 shadow-2xs">
                            <i class="fa-solid fa-truck-fast"></i>
                        </div>
                        <span class="text-[11px] font-bold text-teal-600 uppercase tracking-wider mb-1">Paso 3</span>
                        <h3 class="text-base font-bold text-[#10233F] mb-1.5">Recibe tu agua</h3>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed font-normal">
                            Realizamos la entrega según tu modalidad de servicio.
                        </p>
                    </div>

                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 5. COMENTARIOS (FONDO AZUL GRISÁCEO #F2F6FA, TARJETAS BLANCAS)           -->
        <!-- ========================================================================= -->
        <section id="comentarios" class="scroll-mt-16 py-10 sm:py-12 lg:py-14 bg-[#F2F6FA] border-b border-slate-200/80">
            <div class="max-w-7xl 2xl:max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Encabezado con Calificación -->
                <div class="text-center max-w-2xl mx-auto mb-8 sm:mb-10">
                    <h2 class="text-2xl sm:text-[30px] lg:text-[32px] font-bold text-[#10233F] tracking-tight">
                        Nuestros clientes opinan
                    </h2>
                    <div class="w-12 h-[3px] bg-[#9C3550] rounded-full mx-auto mt-2.5" aria-hidden="true"></div>

                    <!-- Calificación destacada compacta -->
                    <div class="inline-flex items-center gap-2 mt-4 px-3 py-1 bg-white rounded-full border border-slate-200/80 shadow-2xs">
                        <div class="flex text-amber-400 text-xs gap-0.5">
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                        </div>
                        <span class="text-xs font-bold text-[#10233F]">5.0 de 5 estrellas</span>
                    </div>
                </div>

                <!-- Grid de Reseñas en Tarjetas Blancas -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-5 max-w-5xl mx-auto items-stretch">
                    <div
                        v-for="(item, index) in comments"
                        :key="index"
                        class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs flex flex-col justify-between"
                    >
                        <div>
                            <!-- Estrellas destacadas -->
                            <div class="flex items-center text-amber-400 text-xs gap-1 mb-2.5">
                                <i v-for="s in item.rating" :key="s" class="fa-solid fa-star"></i>
                            </div>

                            <!-- Comentario con extracto de 3 líneas y Leer más -->
                            <p
                                :class="[
                                    'text-slate-700 text-xs sm:text-sm leading-relaxed mb-1.5 font-normal',
                                    !expandedComments[index] ? 'line-clamp-3' : ''
                                ]"
                            >
                                “{{ item.comment }}”
                            </p>
                            <button
                                v-if="item.comment && item.comment.length > 110"
                                @click="toggleComment(index)"
                                type="button"
                                class="text-[11px] font-semibold text-[#0076E8] hover:text-[#005bb5] focus:outline-none mb-3 inline-block"
                            >
                                {{ expandedComments[index] ? 'Leer menos' : 'Leer más' }}
                            </button>
                        </div>

                        <!-- Autor destacado y fecha legible -->
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div :class="`w-8 h-8 rounded-full ${item.avatarBg} text-white font-bold text-xs flex items-center justify-center shrink-0`">
                                    {{ item.avatarText }}
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-xs sm:text-sm font-bold text-[#10233F] truncate flex items-center gap-1">
                                        <span>{{ item.name }}</span>
                                        <i v-if="item.verified" class="fa-solid fa-circle-check text-[#0076E8] text-[10px]" title="Cliente verificado"></i>
                                    </h4>
                                    <p class="text-[11px] text-slate-500 truncate">{{ item.role }}</p>
                                </div>
                            </div>
                            
                            <!-- Fecha legible -->
                            <span class="text-[11px] text-slate-400 font-normal shrink-0">{{ item.time }}</span>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 6. SUCURSAL (VISÍTANOS EN HOCABÁ)                                         -->
        <!-- ========================================================================= -->
        <section id="sucursal" class="scroll-mt-16 py-10 sm:py-12 lg:py-14 bg-white border-b border-slate-200/80">
            <div class="max-w-7xl 2xl:max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Encabezado centrado con línea rojo vino #9C3550 -->
                <div class="text-center max-w-2xl mx-auto mb-8 sm:mb-10">
                    <h2 class="text-2xl sm:text-[30px] lg:text-[32px] font-bold text-[#10233F] tracking-tight">
                        Visítanos en Hocabá
                    </h2>
                    <div class="w-12 h-[3px] bg-[#9C3550] rounded-full mx-auto mt-2.5" aria-hidden="true"></div>
                    <p class="mt-3 text-sm sm:text-base text-slate-600 font-normal leading-relaxed">
                        Recarga tus garrafones o compra tus envases en sucursal.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 lg:gap-6 items-stretch max-w-6xl mx-auto">
                    
                    <!-- BLOQUE 1: DATOS CON ETIQUETAS DIRECCIÓN, HORARIO, TELÉFONO -->
                    <div class="bg-slate-50/90 rounded-2xl border border-slate-200/80 p-5 sm:p-6 flex flex-col justify-between space-y-4">
                        
                        <div class="space-y-4">
                            <!-- Estado de Apertura -->
                            <div class="flex items-center justify-between pb-3 border-b border-slate-200/70">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="w-2.5 h-2.5 rounded-full"
                                        :class="isStoreOpen ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"
                                    ></span>
                                    <span class="text-xs font-bold text-[#10233F]">Atención en planta</span>
                                </div>
                                <span
                                    class="text-[11px] font-bold px-2.5 py-0.5 rounded-full"
                                    :class="isStoreOpen ? 'text-emerald-700 bg-emerald-50 border border-emerald-200/60' : 'text-slate-600 bg-slate-100 border border-slate-200/60'"
                                >
                                    {{ isStoreOpen ? 'Abierto hoy' : 'Cerrado' }}
                                </span>
                            </div>

                            <!-- Etiqueta: Dirección -->
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-50 text-[#0076E8] flex items-center justify-center shrink-0 text-xs mt-0.5">
                                    <i class="fa-solid fa-location-dot"></i>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-xs font-bold text-[#10233F]">Dirección</h4>
                                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed mt-0.5">
                                        Calle 20 #102, Centro · Hocabá, Yucatán
                                    </p>
                                </div>
                            </div>

                            <!-- Etiqueta: Horario -->
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-50 text-[#0076E8] flex items-center justify-center shrink-0 text-xs mt-0.5">
                                    <i class="fa-regular fa-clock"></i>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-xs font-bold text-[#10233F]">Horario</h4>
                                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed mt-0.5">
                                        {{ formattedScheduleDisplay.days }}<br />
                                        {{ formattedScheduleDisplay.hours }}
                                    </p>
                                </div>
                            </div>

                            <!-- Etiqueta: Teléfono -->
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-50 text-[#0076E8] flex items-center justify-center shrink-0 text-xs mt-0.5">
                                    <i class="fa-solid fa-phone"></i>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-xs font-bold text-[#10233F]">Teléfono</h4>
                                    <a
                                        href="tel:9991404397"
                                        class="text-xs sm:text-sm text-[#0076E8] font-bold hover:underline leading-relaxed mt-0.5 inline-block"
                                    >
                                        9991 40 43 97
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- BLOQUE 2: FOTOGRAFÍA REAL DE LA FACHADA -->
                    <div class="rounded-2xl overflow-hidden shadow-xs border border-slate-200/80 bg-slate-100 relative min-h-[260px] sm:min-h-[280px]">
                        <img
                            src="/images/sucursal-real.jpg"
                            alt="Fachada de Sucursal Puritronic Hocabá"
                            class="w-full h-full object-cover transition-transform duration-500 ease-out hover:scale-105"
                            loading="lazy"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent pointer-events-none"></div>
                        <div class="absolute bottom-3 left-3 right-3 py-2 px-3 rounded-xl bg-white/95 backdrop-blur-md border border-white/60 shadow-xs text-xs font-bold text-[#10233F]">
                            Fachada de la sucursal
                        </div>
                    </div>

                    <!-- BLOQUE 3: MAPA CON BOTÓN «Cómo llegar» -->
                    <div class="rounded-2xl overflow-hidden border border-slate-200/80 shadow-xs bg-slate-100 flex flex-col justify-end relative min-h-[260px] sm:min-h-[280px] md:col-span-2 lg:col-span-1">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m17!1m12!1m3!1d3729.429274512127!2d-89.249962!3d20.814365!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m2!1m1!2zMjDCsDQ4JzUxLjciTiA4OcKwMTQnNTkuOSJX!5e0!3m2!1ses-419!2smx!4v1792245980366!5m2!1ses-419!2smx"
                            class="absolute inset-0 w-full h-full border-0"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="strict-origin-when-cross-origin"
                            title="Ubicación de Puritronic en Google Maps"
                        ></iframe>

                        <!-- Botón del mapa: «Cómo llegar» -->
                        <div class="relative z-10 p-3">
                            <a
                                href="https://maps.app.goo.gl/dxB7hkU2R1xbzGr6A"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="w-full bg-white/95 backdrop-blur-md rounded-xl py-2.5 px-3.5 shadow-md border border-slate-200/90 hover:border-[#0076E8] hover:shadow-lg transition-all flex items-center justify-between group/map cursor-pointer min-h-[42px]"
                            >
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="#4285F4"/>
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 2.22 1.25 4.9 2.76 7.42L12 9V2z" fill="#EA4335"/>
                                        <path d="M12 9l4.24 7.42C17.75 13.9 19 11.22 19 9c0-3.87-3.13-7-7-7v7z" fill="#FBBC05"/>
                                        <path d="M7.76 16.42L12 22l4.24-5.58C14.88 18.2 13.48 20 12 20s-2.88-1.8-4.24-3.58z" fill="#34A853"/>
                                        <circle cx="12" cy="9" r="2.8" fill="#FFFFFF"/>
                                    </svg>
                                    <span class="text-xs font-bold text-[#10233F] group-hover/map:text-[#0076E8] transition-colors">
                                        Cómo llegar
                                    </span>
                                </div>
                                <i class="fa-solid fa-chevron-right text-xs text-[#0076E8] group-hover/map:translate-x-1 transition-transform"></i>
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 7. PREGUNTAS FRECUENTES (RESUELVE TUS DUDAS)                              -->
        <!-- ========================================================================= -->
        <section id="preguntas" class="scroll-mt-16 py-10 sm:py-12 lg:py-14 bg-slate-50/60 border-b border-slate-200/80">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Encabezado con línea rojo vino #9C3550 -->
                <div class="text-center max-w-2xl mx-auto mb-8 sm:mb-10">
                    <h2 class="text-2xl sm:text-[30px] lg:text-[32px] font-bold text-[#10233F] tracking-tight">
                        Resuelve tus dudas
                    </h2>
                    <div class="w-12 h-[3px] bg-[#9C3550] rounded-full mx-auto mt-2.5" aria-hidden="true"></div>
                </div>

                <!-- Acordeón compacto con fondo celeste suave #EAF5FF en la pregunta abierta -->
                <div class="space-y-3">
                    <div
                        v-for="(faq, index) in faqs"
                        :key="index"
                        :class="[
                            'rounded-2xl border transition-all duration-200 overflow-hidden',
                            openFaq === index
                                ? 'bg-[#EAF5FF] border-blue-200 shadow-2xs'
                                : 'bg-white border-slate-200/80 hover:border-slate-300 shadow-2xs'
                        ]"
                    >
                        <button
                            @click="toggleFaq(index)"
                            type="button"
                            class="w-full px-5 py-4 flex items-center justify-between text-left focus:outline-none gap-3 min-h-[44px]"
                            :aria-expanded="openFaq === index"
                        >
                            <span :class="['text-xs sm:text-sm font-semibold transition-colors duration-150', openFaq === index ? 'text-[#0076E8]' : 'text-[#10233F]']">
                                {{ faq.q }}
                            </span>
                            
                            <!-- Control visible para expandir o cerrar (+ / - o chevron con estado visual claro) -->
                            <div :class="[
                                'w-7 h-7 rounded-full flex items-center justify-center text-xs transition-transform duration-200 shrink-0',
                                openFaq === index
                                    ? 'bg-[#0076E8] text-white rotate-180 shadow-2xs'
                                    : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
                            ]">
                                <i class="fa-solid fa-chevron-down text-[10px]"></i>
                            </div>
                        </button>
                        
                        <div
                            v-show="openFaq === index"
                            class="px-5 pb-4 pt-0 text-slate-600 text-xs sm:text-sm border-t border-blue-100/60 leading-relaxed font-normal"
                        >
                            {{ faq.a }}
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 8. CIERRE (FONDO AZUL SÓLIDO #0076E8)                                     -->
        <!-- ========================================================================= -->
        <section class="py-8 sm:py-10 bg-white">
            <div class="max-w-7xl 2xl:max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="relative overflow-hidden rounded-2xl bg-[#0076E8] p-6 sm:p-8 lg:p-10 shadow-md text-white">
                    <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                        
                        <!-- Columna izquierda: Información y llamado -->
                        <div class="lg:col-span-7 space-y-2 text-center lg:text-left">
                            <h2 class="text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight">
                                ¿Necesitas agua?
                            </h2>
                            <p class="text-white/90 text-sm sm:text-base font-normal leading-relaxed max-w-md mx-auto lg:mx-0">
                                Crea tu cuenta y haz tu pedido.
                            </p>
                        </div>

                        <!-- Columna derecha: Botones de acción -->
                        <div class="lg:col-span-5 flex flex-col sm:flex-row lg:flex-col gap-2.5 lg:items-end justify-center">
                            <Link
                                :href="route('register')"
                                class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-white text-[#10233F] hover:bg-slate-100 font-bold transition-all shadow-xs group text-xs sm:text-sm w-full sm:w-auto lg:w-56 min-h-[44px]"
                            >
                                <span>Crear mi cuenta</span>
                                <i class="fa-solid fa-chevron-right text-xs text-[#0076E8] group-hover:translate-x-1 transition-transform"></i>
                            </Link>

                            <Link
                                :href="route('login')"
                                class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-[#10233F] hover:bg-[#162C5B] text-white font-semibold transition-all shadow-xs text-xs sm:text-sm w-full sm:w-auto lg:w-56 min-h-[44px]"
                            >
                                <span>Iniciar sesión</span>
                            </Link>
                        </div>

                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 9. PIE DE PÁGINA (FONDO AZUL MARINO #10233F)                              -->
        <!-- ========================================================================= -->
        <footer id="contacto" class="scroll-mt-16 bg-[#10233F] text-white pt-12 pb-8 border-t border-slate-800">
            <div class="max-w-7xl 2xl:max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 pb-10 border-b border-white/10">
                    
                    <!-- Grupo 1: Identidad y propuesta -->
                    <div class="space-y-3">
                        <img
                            src="/images/logo.png"
                            alt="Puritronic Agua Purificada"
                            class="h-9 sm:h-10 w-auto object-contain"
                        />
                        <p class="text-slate-300 text-xs sm:text-sm leading-relaxed max-w-sm">
                            Agua purificada a domicilio en Hocabá.
                        </p>
                    </div>

                    <!-- Grupo 2: Navegación -->
                    <div class="space-y-3">
                        <h3 class="text-xs font-bold text-white tracking-wider uppercase">
                            Navegación
                        </h3>
                        <ul class="grid grid-cols-2 gap-2 text-xs text-slate-300">
                            <li><a href="#inicio" @click="scrollToSection($event, 'inicio')" class="hover:text-white transition-colors">Inicio</a></li>
                            <li><a href="#catalogo" @click="scrollToSection($event, 'catalogo')" class="hover:text-white transition-colors">Precios</a></li>
                            <li><a href="#como-funciona" @click="scrollToSection($event, 'como-funciona')" class="hover:text-white transition-colors">Cómo funciona</a></li>
                            <li><a href="#comentarios" @click="scrollToSection($event, 'comentarios')" class="hover:text-white transition-colors">Opiniones</a></li>
                            <li><a href="#sucursal" @click="scrollToSection($event, 'sucursal')" class="hover:text-white transition-colors">Sucursal</a></li>
                            <li><a href="#preguntas" @click="scrollToSection($event, 'preguntas')" class="hover:text-white transition-colors">Preguntas</a></li>
                        </ul>
                    </div>

                    <!-- Grupo 3: Contacto -->
                    <div class="space-y-3">
                        <h3 class="text-xs font-bold text-white tracking-wider uppercase">
                            Contacto
                        </h3>
                        <ul class="space-y-2 text-xs text-slate-300">
                            <li class="flex items-start gap-2.5">
                                <i class="fa-solid fa-location-dot text-[#0076E8] mt-0.5 text-xs"></i>
                                <span>Calle 20 #102, Centro · Hocabá</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <i class="fa-regular fa-clock text-[#0076E8] mt-0.5 text-xs"></i>
                                <span>{{ formattedScheduleDisplay.fullText }}</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <i class="fa-solid fa-phone text-[#0076E8] mt-0.5 text-xs"></i>
                                <span>Tel. <a href="tel:9991404397" class="hover:underline text-white font-medium">9991 40 43 97</a></span>
                            </li>
                        </ul>
                    </div>

                </div>

                <!-- Copyright y acciones finales -->
                <div class="pt-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
                    <p>© {{ new Date().getFullYear() }} Puritronic Hocabá. Todos los derechos reservados.</p>
                    <div class="flex items-center gap-5">
                        <a href="#inicio" @click="scrollToSection($event, 'inicio')" class="hover:text-white transition-colors">Volver arriba</a>
                        <a href="#sucursal" @click="scrollToSection($event, 'sucursal')" class="hover:text-white transition-colors">Sucursal</a>
                        <Link :href="$page.props.auth?.user ? route('dashboard') : route('login')" class="hover:text-white transition-colors font-semibold">
                            {{ $page.props.auth?.user ? 'Mi Panel' : 'Iniciar sesión' }}
                        </Link>
                    </div>
                </div>

            </div>
        </footer>

    </div>
</template>
