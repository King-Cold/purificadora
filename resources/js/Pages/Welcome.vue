<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

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
    { id: 'catalogo', label: 'Catálogo' },
    { id: 'como-funciona', label: '¿Cómo funciona?' },
    { id: 'comentarios', label: 'Comentarios' },
    { id: 'sucursal', label: 'Sucursal' },
    { id: 'preguntas', label: 'Preguntas' },
    { id: 'contacto', label: 'Contacto' },
];

let isManualScrolling = false;
let scrollTimeout = null;

const scrollToSection = (e, id) => {
    if (e) e.preventDefault();
    mobileMenuOpen.value = false;
    const el = document.getElementById(id);
    if (el) {
        // Marcamos la sección inmediatamente y bloqueamos el listener de scroll durante la animación
        activeSection.value = id;
        isManualScrolling = true;

        if (scrollTimeout) clearTimeout(scrollTimeout);

        const headerOffset = 80;
        const bodyRect = document.body.getBoundingClientRect().top;
        const elementRect = el.getBoundingClientRect().top;
        const elementPosition = elementRect - bodyRect;
        const offsetPosition = elementPosition - headerOffset;

        window.scrollTo({
            top: offsetPosition,
            behavior: 'smooth',
        });

        // Liberamos el bloqueo una vez terminada la animación de scroll suave
        scrollTimeout = setTimeout(() => {
            isManualScrolling = false;
        }, 800);
    }
};

onMounted(() => {
    let ticking = false;

    const handleScroll = () => {
        // Si el desplazamiento fue iniciado por un clic en el menú, no interferir con la selección
        if (isManualScrolling) return;

        if (!ticking) {
            window.requestAnimationFrame(() => {
                // Si el usuario está casi al final del documento, activar la sección 'contacto'
                if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 100) {
                    activeSection.value = 'contacto';
                    ticking = false;
                    return;
                }

                const scrollPosition = window.scrollY + 140;
                const sectionIds = ['inicio', 'catalogo', 'como-funciona', 'comentarios', 'sucursal', 'preguntas', 'contacto'];

                for (let i = sectionIds.length - 1; i >= 0; i--) {
                    const el = document.getElementById(sectionIds[i]);
                    if (el && scrollPosition >= el.offsetTop) {
                        activeSection.value = sectionIds[i];
                        ticking = false;
                        return;
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

const faqs = computed(() => [
    {
        q: '¿Cómo realizo un pedido por primera vez?',
        a: 'Es muy sencillo: solo debes hacer clic en "Pedir agua ahora" o "Crear cuenta", indicar tu dirección de entrega en Hocabá y la cantidad de garrafones que necesitas. Te confirmaremos de inmediato vía WhatsApp y en la app.',
    },
    {
        q: '¿Cuáles son los horarios de atención y entrega a domicilio?',
        a: `Atendemos de ${formattedScheduleDisplay.value.days} de ${formattedScheduleDisplay.value.opening} a ${formattedScheduleDisplay.value.closing}.`,
    },
    {
        q: '¿Aceptan garrafones de otras marcas al momento del cambio?',
        a: 'Sí, aceptamos garrafones comerciales estándar en buen estado físico (limpios, sin perforaciones o rajaduras). Cada garrafón pasa por nuestro proceso de lavado a presión antes de ser llenado.',
    },
    {
        q: '¿Qué formas de pago puedo utilizar?',
        a: 'Aceptamos pago en efectivo contra entrega (nuestros repartidores cuentan con cambio), transferencias electrónicas y pagos directos desde la plataforma.',
    },
]);

// Comentarios de clientes en Hocabá
const comments = [
    {
        name: 'María Elena Pech',
        role: 'Clienta en Hocabá',
        avatarText: 'MP',
        avatarBg: 'bg-blue-600',
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
        avatarBg: 'bg-cyan-600',
        rating: 5,
        time: 'Hace 2 semanas',
        comment: 'La atención de los repartidores es muy amable y los garrafones siempre vienen impecablemente sellados y limpios. Recomiendo totalmente a Puritronic.',
        verified: true,
    },
];

// Catálogo oficial de presentaciones de garrafones y tarifas reactivas
const bottleProducts = computed(() => {
    const db20L = props.presentations?.find(p => p.capacity === 20 || p.name?.includes('20'));
    const db10L = props.presentations?.find(p => p.capacity === 10 || p.name?.includes('10'));

    const is20Available = db20L ? Boolean(db20L.is_available_for_delivery) : true;
    const is10Available = db10L ? Boolean(db10L.is_available_for_delivery) : false;

    const p20Home = db20L?.refill_price !== null && db20L?.refill_price !== undefined ? String(db20L.refill_price) : '15';
    const p20Store = db20L?.store_price !== null && db20L?.store_price !== undefined ? String(db20L.store_price) : '10';
    const p20New = db20L?.new_bottle_price !== null && db20L?.new_bottle_price !== undefined ? String(db20L.new_bottle_price) : '95';

    const p10Home = db10L?.refill_price !== null && db10L?.refill_price !== undefined ? String(db10L.refill_price) : '8';
    const p10Store = db10L?.store_price !== null && db10L?.store_price !== undefined ? String(db10L.store_price) : '5';
    const p10New = db10L?.new_bottle_price !== null && db10L?.new_bottle_price !== undefined ? String(db10L.new_bottle_price) : '65';

    return [
        {
            id: 'garrafon-20l',
            name: db20L?.name || 'Garrafón 20 Litros',
            capacityBadge: db20L?.formatted_capacity || '20 Litros',
            tag: 'Más Solicitado · A Domicilio',
            tagClass: 'bg-[#AF143C] text-white shadow-md shadow-red-900/20 ring-2 ring-white',
            featured: true,
            isAvailableForDelivery: is20Available,
            image: db20L?.image_url || '/images/garrafon-20l.png',
            imageAlt: 'Garrafón Puritronic de 20 Litros',
            tagline: 'Tamaño familiar de máxima capacidad',
            description: 'La presentación tradicional y preferida por familias y negocios en Hocabá. Máxima pureza y frescura para tu día a día.',
            homePrice: p20Home,
            homePriceLabel: 'A Domicilio',
            homePriceSub: 'Fijo o cambio',
            storePrice: p20Store,
            storePriceLabel: 'En Sucursal',
            storePriceSub: 'Relleno en planta',
            newBottlePrice: p20New,
            features: [
                `Entrega directa a la puerta de tu hogar por solo $${p20Home}`,
                `Rellénalo en nuestra sucursal por únicamente $${p20Store}`,
                'Tarifa fija a domicilio: botellón de cambio o fijo',
                'Lavado y sanitizado a presión grado alimenticio',
                'Purificación por ósmosis inversa y luz ultravioleta (UV)',
                'Tapa hermética nueva y sello de garantía en cada entrega',
            ],
            btnOrderText: 'Pedir 20L a Domicilio',
            btnStoreText: 'Rellenar en Sucursal',
        },
        {
            id: 'garrafon-10l',
            name: db10L?.name || 'Garrafón 10 Litros',
            capacityBadge: db10L?.formatted_capacity || '10 Litros',
            tag: 'Exclusivo en Sucursal',
            tagClass: 'bg-emerald-700 text-white shadow-md shadow-emerald-900/20 ring-2 ring-white',
            featured: false,
            isAvailableForDelivery: is10Available,
            image: db10L?.image_url || '/images/garrafon-10l.png',
            imageAlt: 'Garrafón Puritronic de 10 Litros',
            tagline: 'Ligero, ergonómico y exclusivo de planta',
            description: 'La opción ideal si buscas comodidad y menor peso para recarga personal. Rellenado y venta directa en nuestra sucursal.',
            homePrice: p10Home,
            homePriceLabel: 'A Domicilio',
            homePriceSub: 'Fijo o cambio',
            storePrice: p10Store,
            storePriceLabel: 'En Sucursal',
            storePriceSub: 'Relleno en planta',
            newBottlePrice: p10New,
            features: [
                `Rellénalo en nuestra sucursal por únicamente $${p10Store}`,
                'Venta de envase nuevo y recargas directo en planta',
                'Diseño compacto con asa ergonómica de fácil agarre',
                'Lavado y sanitizado a presión grado alimenticio',
                'Purificación por ósmosis inversa y luz ultravioleta (UV)',
                'Tapa hermética y máxima higiene garantizada',
            ],
            btnOrderText: 'Pedir 10L a Domicilio',
            btnStoreText: 'Rellenar en Sucursal',
        },
    ];
});
</script>

<template>
    <Head title="Puritronic - Agua pura, directo hasta su hogar" />

    <div class="min-h-screen bg-white font-['Poppins',sans-serif] text-slate-800 antialiased selection:bg-[#0084ff] selection:text-white">
        
        <!-- ================= NAVBAR ================= -->
        <header class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-100 transition-all duration-200">
            <div class="max-w-7xl 2xl:max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-20 gap-4">
                    
                    <!-- LOGO PURITRONIC -->
                    <a href="#" class="flex items-center shrink-0 group mr-2 sm:mr-4">
                        <img
                            src="/images/logo.png"
                            alt="Puritronic Agua Purificada"
                            class="h-10 sm:h-11 md:h-12 w-auto object-contain transition-transform duration-200 group-hover:scale-[1.02]"
                        />
                    </a>

                    <!-- NAVEGACIÓN DESKTOP (REACTIVA A LA SECCIÓN ACTIVA CON LÍNEA ROJA DE LA MARCA) -->
                    <nav class="hidden lg:flex items-center gap-4 xl:gap-6 2xl:gap-8">
                        <a
                            v-for="item in navItems"
                            :key="item.id"
                            :href="`#${item.id}`"
                            @click="scrollToSection($event, item.id)"
                            :class="[
                                'text-xs xl:text-sm whitespace-nowrap transition-all duration-200 py-1.5 px-0.5 relative',
                                activeSection === item.id
                                    ? 'font-bold text-[#0E1E42] after:absolute after:bottom-0 after:left-0 after:w-full after:h-[2.5px] after:bg-[#AF143C] after:rounded-full'
                                    : 'font-medium text-slate-600 hover:text-[#0E1E42]'
                            ]"
                        >
                            {{ item.label }}
                        </a>
                    </nav>

                    <!-- BOTONES DE ACCIÓN (AUTH) -->
                    <div class="hidden lg:flex items-center gap-2.5 xl:gap-3 shrink-0 ml-2 sm:ml-4">
                        <template v-if="$page.props.auth?.user">
                            <Link
                                :href="route('dashboard')"
                                class="px-5 xl:px-6 py-2.5 text-xs xl:text-sm font-semibold text-white bg-[#0084ff] hover:bg-[#0070dc] rounded-full shadow-md shadow-blue-500/20 hover:shadow-lg hover:shadow-blue-500/30 hover:-translate-y-0.5 transition-all flex items-center gap-2 whitespace-nowrap"
                            >
                                <span>Ir a mi Panel</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                v-if="canLogin"
                                :href="route('login')"
                                class="px-4 xl:px-5 py-2.5 text-xs xl:text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-full hover:bg-slate-50 hover:border-slate-300 hover:text-[#0084ff] transition-all shadow-xs whitespace-nowrap"
                            >
                                Iniciar Sesión
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="px-5 xl:px-6 py-2.5 text-xs xl:text-sm font-semibold text-white bg-[#0084ff] hover:bg-[#0070dc] rounded-full shadow-md shadow-blue-500/20 hover:shadow-lg hover:shadow-blue-500/30 hover:-translate-y-0.5 transition-all whitespace-nowrap"
                            >
                                Crear Cuenta
                            </Link>
                        </template>
                    </div>

                    <!-- BOTÓN MÓVIL HAMBURGUESA (< lg) -->
                    <div class="flex lg:hidden">
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            type="button"
                            class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-600 hover:text-slate-900 bg-slate-50 border border-slate-200/80 hover:bg-slate-100 transition-colors focus:outline-none"
                            aria-label="Menú principal"
                        >
                            <i :class="mobileMenuOpen ? 'fa-solid fa-xmark text-lg' : 'fa-solid fa-bars text-lg'"></i>
                        </button>
                    </div>
                </div>

                <!-- MENU MÓVIL DESPLEGABLE (< lg) -->
                <div v-show="mobileMenuOpen" class="lg:hidden border-t border-slate-100 py-4 space-y-2 bg-white">
                    <a
                        v-for="item in navItems"
                        :key="item.id"
                        :href="`#${item.id}`"
                        @click="scrollToSection($event, item.id)"
                        :class="[
                            'block px-3 py-2 text-sm rounded-lg transition-colors',
                            activeSection === item.id
                                ? 'font-bold text-[#AF143C] bg-red-50/70 border-l-2 border-[#AF143C]'
                                : 'font-medium text-slate-600 hover:text-[#0E1E42] hover:bg-slate-50'
                        ]"
                    >
                        {{ item.label }}
                    </a>
                    <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
                        <template v-if="$page.props.auth?.user">
                            <Link
                                :href="route('dashboard')"
                                class="w-full text-center py-2.5 text-sm font-semibold text-white bg-[#0084ff] rounded-full shadow-md shadow-blue-500/20"
                            >
                                Ir a mi Panel
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                v-if="canLogin"
                                :href="route('login')"
                                class="w-full text-center py-2.5 text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-full hover:bg-slate-50 hover:text-[#0084ff]"
                            >
                                Iniciar Sesión
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="w-full text-center py-2.5 text-sm font-semibold text-white bg-[#0084ff] rounded-full shadow-md shadow-blue-500/20"
                            >
                                Crear Cuenta
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </header>

        <!-- ================= HERO SECTION ================= -->
        <section id="inicio" class="scroll-mt-20 relative pt-10 pb-16 lg:pt-16 lg:pb-24 overflow-hidden bg-gradient-to-b from-blue-50/30 via-white to-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                    
                    <!-- COLUMNA IZQUIERDA: TEXTO Y CTA -->
                    <div class="lg:col-span-6 space-y-6 text-center lg:text-left">
                        
                        <!-- Badge Servicio a domicilio -->
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-50 border border-blue-100 text-[#0084ff] text-xs font-semibold shadow-xs">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <i class="fa-solid fa-location-dot text-xs"></i>
                            <span>Servicio a domicilio en Hocabá</span>
                        </div>

                        <!-- Título Principal -->
                        <h1 class="text-4xl sm:text-5xl lg:text-[3.5rem] font-extrabold text-[#0e1c2e] tracking-tight leading-[1.12]">
                            Agua pura,<br />
                            <span class="bg-gradient-to-r from-[#0084ff] to-[#00b4d8] bg-clip-text text-transparent">
                                directo hasta su hogar.
                            </span>
                        </h1>

                        <!-- Descripción -->
                        <p class="text-base sm:text-lg text-slate-500 max-w-lg mx-auto lg:mx-0 leading-relaxed font-normal">
                            Solicita tus garrafones de agua de manera rápida, sencilla y sin complicaciones desde tu celular.
                        </p>

                        <!-- Botón CTA Primario -->
                        <div class="pt-2 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                            <Link
                                :href="route('auth.portal')"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl font-bold uppercase tracking-wider text-white text-sm bg-[#0084ff] hover:bg-[#0070dc] shadow-lg shadow-blue-500/25 hover:shadow-xl hover:shadow-blue-500/35 hover:-translate-y-0.5 transition-all group"
                            >
                                <span>PEDIR AGUA AHORA</span>
                                <i class="fa-solid fa-chevron-right text-xs group-hover:translate-x-1 transition-transform"></i>
                            </Link>

                            <a
                                href="https://wa.me/529991404397?text=Hola,%20quisiera%20pedir%20un%20garrafón%20de%20agua%20a%20domicilio"
                                target="_blank"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-6 py-4 rounded-2xl font-semibold text-slate-700 hover:text-emerald-700 bg-white hover:bg-emerald-50/50 border border-slate-200 hover:border-emerald-300 shadow-xs hover:shadow-md hover:-translate-y-0.5 transition-all text-sm group"
                            >
                                <i class="fa-brands fa-whatsapp text-lg text-emerald-600 group-hover:scale-110 transition-transform"></i>
                                <span>Pedir por WhatsApp</span>
                            </a>
                        </div>

                        <!-- Mini Features / Garantías del Hero -->
                        <div class="pt-8 border-t border-slate-100 grid grid-cols-3 gap-3 sm:gap-4 text-left">
                            
                            <!-- Entrega a domicilio -->
                            <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-2.5 sm:gap-3 p-2 rounded-xl hover:bg-slate-50/80 transition-colors">
                                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-blue-50 text-[#0084ff] flex items-center justify-center flex-shrink-0 text-base shadow-xs">
                                    <i class="fa-solid fa-truck-fast"></i>
                                </div>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 leading-tight">Entrega</h4>
                                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium">A domicilio</p>
                                </div>
                            </div>

                            <!-- Fácil desde tu celular -->
                            <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-2.5 sm:gap-3 p-2 rounded-xl hover:bg-slate-50/80 transition-colors">
                                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-blue-50 text-[#0084ff] flex items-center justify-center flex-shrink-0 text-base shadow-xs">
                                    <i class="fa-solid fa-mobile-screen"></i>
                                </div>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 leading-tight">Fácil</h4>
                                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium">Desde tu celular</p>
                                </div>
                            </div>

                            <!-- Seguimiento de tu pedido -->
                            <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-2.5 sm:gap-3 p-2 rounded-xl hover:bg-slate-50/80 transition-colors">
                                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-blue-50 text-[#0084ff] flex items-center justify-center flex-shrink-0 text-base shadow-xs">
                                    <i class="fa-solid fa-location-crosshairs"></i>
                                </div>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 leading-tight">Seguimiento</h4>
                                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium">De tu pedido</p>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- COLUMNA DERECHA: ILUSTRACIÓN VECTORIAL LIMPIA -->
                    <div class="lg:col-span-6 relative flex items-center justify-center">
                        <div class="w-full max-w-lg lg:max-w-xl relative flex justify-center">
                            <img
                                src="/images/hero-drinking-water.svg"
                                alt="Persona tomando agua purificada fresca Puritronic"
                                class="w-full h-auto max-h-[480px] object-contain mx-auto hover:scale-[1.01] transition-transform duration-300"
                            />
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ================= CATÁLOGO Y PRECIOS ================= -->
        <section id="catalogo" class="scroll-mt-20 py-20 lg:py-24 bg-slate-50/60 border-t border-slate-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Títulos -->
                <div class="text-center max-w-2xl mx-auto mb-16 space-y-3">
                    <span class="inline-block text-xs font-bold uppercase tracking-wider text-[#0084ff] bg-blue-50 px-3 py-1 rounded-full">
                        Catálogo de Servicios
                    </span>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-[#0e1c2e] tracking-tight">
                        Precios claros, <span class="text-[#0084ff]">sin sorpresas.</span>
                    </h2>
                    <p class="text-sm sm:text-base text-slate-500 font-normal">
                        Elige la opción que mejor se adapte a tus necesidades. Máxima pureza e higiene en cada servicio.
                    </p>
                </div>

                <!-- Grid de 2 Presentaciones Oficiales con Uniformidad de Diseño -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-10 max-w-5xl mx-auto items-stretch">
                    
                    <div
                        v-for="product in bottleProducts"
                        :key="product.id"
                        :class="[
                            'rounded-3xl p-7 sm:p-9 flex flex-col justify-between transition-all duration-300 relative group bg-white',
                            product.featured
                                ? 'border-2 border-[#0084ff] shadow-xl shadow-blue-500/10 lg:-translate-y-2'
                                : 'border border-slate-200/90 shadow-xs hover:shadow-xl hover:border-slate-300'
                        ]"
                    >
                        <!-- Badge destacado para la tarjeta principal -->
                        <div
                            v-if="product.tag"
                            :class="[
                                'absolute -top-3.5 left-1/2 -translate-x-1/2 text-[11px] font-bold uppercase tracking-wider px-4 py-1 rounded-full',
                                product.tagClass
                            ]"
                        >
                            {{ product.tag }}
                        </div>

                        <div>
                            <!-- Showcase Visual del Producto con Pedestal Suave -->
                            <div class="bg-gradient-to-b from-blue-50/70 via-slate-50/40 to-white rounded-2xl p-6 sm:p-8 mb-6 border border-blue-100/50 flex flex-col items-center justify-center relative overflow-hidden group-hover:border-blue-200 transition-colors">
                                <img
                                    :src="product.image"
                                    :alt="product.imageAlt"
                                    class="h-52 sm:h-60 w-auto object-contain mx-auto drop-shadow-md group-hover:scale-105 transition-transform duration-300"
                                    loading="lazy"
                                />
                            </div>

                            <!-- Encabezado del Producto: Nombre y Capacidad -->
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <h3 class="text-2xl sm:text-3xl font-extrabold text-[#0e1c2e] tracking-tight">
                                    {{ product.name }}
                                </h3>
                                <span class="text-xs font-bold px-3 py-1 rounded-full bg-blue-50 text-[#0084ff] border border-blue-200/60 shrink-0">
                                    {{ product.capacityBadge }}
                                </span>
                            </div>

                            <!-- Subtítulo / Tagline -->
                            <p class="text-xs font-semibold text-[#AF143C] uppercase tracking-wider mb-2">
                                {{ product.tagline }}
                            </p>

                            <!-- Descripción breve -->
                            <p class="text-xs sm:text-sm text-slate-500 leading-relaxed mb-6">
                                {{ product.description }}
                            </p>

                            <!-- Cuadro Comparativo de Precios Oficiales (A Domicilio únicamente si está disponible) -->
                            <div v-if="product.isAvailableForDelivery" class="grid grid-cols-2 gap-3 sm:gap-4 mb-4">
                                <!-- A Domicilio -->
                                <div class="p-4 rounded-2xl bg-blue-50/60 border border-blue-200/80 text-center relative">
                                    <span class="inline-flex items-center justify-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#0084ff] mb-1">
                                        <i class="fa-solid fa-truck-fast"></i>
                                        <span>A Domicilio</span>
                                    </span>
                                    <div class="flex items-baseline justify-center gap-1 my-1">
                                        <span class="text-3xl sm:text-4xl font-black text-[#0e1c2e] tracking-tight">
                                            ${{ product.homePrice }}
                                        </span>
                                        <span class="text-xs font-bold text-slate-500">MXN</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 font-medium">
                                        Cambio o fijo
                                    </p>
                                </div>

                                <!-- En Sucursal -->
                                <div class="p-4 rounded-2xl bg-slate-50/90 border border-slate-200/80 text-center relative">
                                    <span class="inline-flex items-center justify-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1">
                                        <i class="fa-solid fa-store text-emerald-600"></i>
                                        <span>En Sucursal</span>
                                    </span>
                                    <div class="flex items-baseline justify-center gap-1 my-1">
                                        <span class="text-3xl sm:text-4xl font-black text-[#0e1c2e] tracking-tight">
                                            ${{ product.storePrice }}
                                        </span>
                                        <span class="text-xs font-bold text-slate-500">MXN</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 font-medium">
                                        Relleno en planta
                                    </p>
                                </div>
                            </div>

                            <!-- Para presentaciones exclusivas de sucursal (sin servicio a domicilio) -->
                            <div v-else class="mb-4">
                                <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/90 border border-slate-200/90 flex items-center justify-between gap-4">
                                    <div>
                                        <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-emerald-700 mb-1">
                                            <i class="fa-solid fa-store"></i>
                                            <span>Relleno en Sucursal</span>
                                        </span>
                                        <p class="text-xs text-slate-500 font-medium">
                                            Recarga y venta directa en planta
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <div class="flex items-baseline justify-end gap-1">
                                            <span class="text-3xl sm:text-4xl font-black text-[#0e1c2e] tracking-tight">
                                                ${{ product.storePrice }}
                                            </span>
                                            <span class="text-xs font-bold text-slate-500">MXN</span>
                                        </div>
                                        <span class="inline-block text-[10px] font-bold text-emerald-700 uppercase tracking-wider bg-emerald-100/70 px-2.5 py-0.5 rounded-full mt-0.5">
                                            Exclusivo Sucursal
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Nota informativa destacada: Sin importar fijo o cambio o exclusivo en planta -->
                            <div class="flex items-center gap-2.5 p-3 rounded-xl bg-slate-100/70 border border-slate-200/60 text-slate-600 text-[11px] sm:text-xs mb-6">
                                <i class="fa-solid fa-circle-check text-[#0084ff] text-sm shrink-0"></i>
                                <span v-if="product.isAvailableForDelivery"><strong>Tarifa transparente:</strong> cuesta exactamente ${{ product.homePrice }} llevarlo a tu casa, sin importar si es de cambio o fijo.</span>
                                <span v-else><strong>Servicio en planta:</strong> presentación exclusiva para rellenado o compra en nuestra sucursal (no disponible para entrega a domicilio).</span>
                            </div>

                            <!-- Línea divisoria y Lista de características -->
                            <div class="border-t border-slate-100 pt-5 mb-6">
                                <p class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3.5">
                                    ¿Qué incluye este producto?
                                </p>
                                <ul class="space-y-2.5">
                                    <li
                                        v-for="(feature, fIndex) in product.features"
                                        :key="fIndex"
                                        class="flex items-start gap-2.5 text-xs sm:text-sm text-slate-600"
                                    >
                                        <i class="fa-solid fa-circle-check text-[#0084ff] text-sm mt-0.5 shrink-0"></i>
                                        <span>{{ feature }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Botones de Acción: Pedir a Domicilio y Ver Sucursal -->
                        <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row gap-3">
                            <Link
                                v-if="product.isAvailableForDelivery"
                                :href="route('auth.portal')"
                                class="flex-1 inline-flex items-center justify-center gap-2 py-3 px-5 rounded-2xl font-bold text-xs sm:text-sm text-white bg-[#0084ff] hover:bg-[#0070dc] shadow-md shadow-blue-500/25 hover:shadow-lg hover:shadow-blue-500/35 hover:-translate-y-0.5 transition-all text-center"
                            >
                                <i class="fa-solid fa-cart-shopping"></i>
                                <span>{{ product.btnOrderText }}</span>
                            </Link>

                            <a
                                href="#sucursal"
                                @click="scrollToSection($event, 'sucursal')"
                                class="inline-flex items-center justify-center gap-2 py-3 px-5 rounded-2xl font-bold text-xs sm:text-sm transition-all text-center"
                                :class="product.isAvailableForDelivery ? 'text-slate-700 bg-slate-100 hover:bg-slate-200' : 'flex-1 text-white bg-slate-900 hover:bg-slate-800 shadow-md'"
                            >
                                <i class="fa-solid fa-location-dot"></i>
                                <span>{{ product.btnStoreText }}</span>
                            </a>
                        </div>
                    </div>

                </div>

            </div>
        </section>

        <!-- ================= CÓMO FUNCIONA / PROCESO ================= -->
        <section id="como-funciona" class="scroll-mt-20 py-20 lg:py-24 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Títulos -->
                <div class="text-center max-w-2xl mx-auto mb-20 space-y-3">
                    <span class="inline-block text-xs font-bold uppercase tracking-wider text-[#0084ff] bg-blue-50 px-3 py-1 rounded-full">
                        Paso a Paso
                    </span>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-[#0e1c2e] tracking-tight">
                        Pedir agua nunca <span class="text-[#0084ff]">fue tan sencillo.</span>
                    </h2>
                    <p class="text-sm sm:text-base text-slate-500 font-normal">
                        Sigue estos tres sencillos pasos para recibir agua purificada y fresca en tu hogar.
                    </p>
                </div>

                <!-- Pasos 01, 02, 03 con conector dashed minimalista -->
                <div class="relative grid grid-cols-1 md:grid-cols-3 gap-12 lg:gap-8 items-start">
                    
                    <!-- Línea dashed conectora en desktop -->
                    <div class="hidden md:block absolute top-[94px] left-[20%] right-[20%] h-0.5 border-t-2 border-dashed border-slate-200 z-0">
                        <i class="fa-solid fa-chevron-right text-slate-400 text-xs absolute -right-1 -top-2 bg-white pl-1"></i>
                    </div>

                    <!-- PASO 1 -->
                    <div class="relative z-10 flex flex-col items-center text-center p-6 rounded-3xl hover:bg-slate-50/60 transition-colors">
                        <div class="w-11 h-11 rounded-full bg-[#0052cc] text-white font-extrabold text-sm flex items-center justify-center mb-6 shadow-md shadow-blue-500/25">
                            01
                        </div>
                        <div class="w-20 h-20 rounded-2xl bg-blue-50 text-[#0084ff] flex items-center justify-center text-3xl mb-6 shadow-xs">
                            <i class="fa-solid fa-mobile-screen"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Haz tu pedido</h3>
                        <p class="text-xs sm:text-sm text-slate-500 max-w-xs leading-relaxed">
                            Solicita la cantidad de garrafones que necesitas fácilmente en pocos clics.
                        </p>
                    </div>

                    <!-- PASO 2 -->
                    <div class="relative z-10 flex flex-col items-center text-center p-6 rounded-3xl hover:bg-slate-50/60 transition-colors">
                        <div class="w-11 h-11 rounded-full bg-[#0084ff] text-white font-extrabold text-sm flex items-center justify-center mb-6 shadow-md shadow-blue-400/25">
                            02
                        </div>
                        <div class="w-20 h-20 rounded-2xl bg-blue-50 text-[#0084ff] flex items-center justify-center text-3xl mb-6 shadow-xs">
                            <i class="fa-solid fa-truck-fast"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Confirmamos tu pedido</h3>
                        <p class="text-xs sm:text-sm text-slate-500 max-w-xs leading-relaxed">
                            Recibirás información en vivo sobre el estado y preparación de tu solicitud.
                        </p>
                    </div>

                    <!-- PASO 3 -->
                    <div class="relative z-10 flex flex-col items-center text-center p-6 rounded-3xl hover:bg-slate-50/60 transition-colors">
                        <div class="w-11 h-11 rounded-full bg-emerald-500 text-white font-extrabold text-sm flex items-center justify-center mb-6 shadow-md shadow-emerald-500/25">
                            03
                        </div>
                        <div class="w-20 h-20 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl mb-6 shadow-xs">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Recibe tu agua</h3>
                        <p class="text-xs sm:text-sm text-slate-500 max-w-xs leading-relaxed">
                            Nuestro repartidor llegará directamente hasta la puerta de tu hogar en Hocabá.
                        </p>
                    </div>

                </div>
            </div>
        </section>

        <!-- ================= SECCIÓN DE COMENTARIOS / OPINIONES ================= -->
        <section id="comentarios" class="scroll-mt-20 py-20 lg:py-24 bg-slate-50/60 border-t border-slate-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Títulos -->
                <div class="text-center max-w-2xl mx-auto mb-16 space-y-3">
                    <span class="inline-block text-xs font-bold uppercase tracking-wider text-[#0084ff] bg-blue-50 px-3 py-1 rounded-full">
                        Comentarios
                    </span>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-[#0e1c2e] tracking-tight">
                        Lo que dicen <span class="text-[#0084ff]">nuestros clientes</span>
                    </h2>
                    <p class="text-sm sm:text-base text-slate-500 font-normal">
                        La satisfacción de las familias y negocios de Hocabá que disfrutan de Puritronic todos los días.
                    </p>

                    <!-- Calificación general -->
                    <div class="inline-flex items-center gap-2 pt-2 px-4 py-1.5 bg-white rounded-full border border-slate-200/80 shadow-xs">
                        <div class="flex text-amber-400 text-xs gap-1">
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                        </div>
                        <span class="text-xs font-bold text-slate-700">5.0 de 5 estrellas</span>
                    </div>
                </div>

                <!-- Grid de Comentarios -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div
                        v-for="(item, index) in comments"
                        :key="index"
                        class="bg-white rounded-3xl p-8 border border-slate-100 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group"
                    >
                        <div>
                            <!-- Estrellas y Fecha -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex text-amber-400 text-xs gap-1">
                                    <i v-for="s in item.rating" :key="s" class="fa-solid fa-star"></i>
                                </div>
                                <span class="text-[11px] font-medium text-slate-400">{{ item.time }}</span>
                            </div>

                            <!-- Texto del Comentario -->
                            <p class="text-slate-600 text-xs sm:text-sm leading-relaxed mb-6 font-normal">
                                “{{ item.comment }}”
                            </p>
                        </div>

                        <!-- Información del Usuario -->
                        <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                            <div :class="`w-10 h-10 rounded-full ${item.avatarBg} text-white font-bold text-xs flex items-center justify-center flex-shrink-0 shadow-xs`">
                                {{ item.avatarText }}
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-xs sm:text-sm font-bold text-slate-900 truncate flex items-center gap-1.5">
                                    <span>{{ item.name }}</span>
                                    <i v-if="item.verified" class="fa-solid fa-circle-check text-blue-500 text-xs" title="Cliente verificado"></i>
                                </h4>
                                <p class="text-[11px] text-slate-500 truncate">{{ item.role }}</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ================= SECCIÓN NUESTRA SUCURSAL ================= -->
        <section id="sucursal" class="scroll-mt-20 py-16 sm:py-20 lg:py-24 bg-white border-t border-slate-100">
            <div class="max-w-7xl 2xl:max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8 lg:gap-6 items-stretch">
                    
                    <!-- COLUMNA 1: INFORMACIÓN DE SUCURSAL -->
                    <div class="md:col-span-2 lg:col-span-5 flex flex-col justify-between space-y-6 text-center lg:text-left">
                        <div class="space-y-4">
                            <!-- Badge -->
                            <span class="inline-block text-xs font-bold uppercase tracking-wider text-[#0084ff] bg-blue-50 border border-blue-100 px-3.5 py-1.5 rounded-full">
                                NUESTRA SUCURSAL
                            </span>

                            <!-- Título principal -->
                            <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-[#0e1c2e] leading-tight">
                                Visítanos en<br class="hidden sm:inline">
                                <span class="text-[#0084ff]"> Hocabá, Yucatán</span>
                            </h2>

                            <!-- Descripción -->
                            <p class="text-xs sm:text-sm text-slate-500 font-normal leading-relaxed max-w-lg mx-auto lg:mx-0">
                                También puedes visitarnos en nuestra sucursal para atención personalizada, recargas y más servicios de agua purificada.
                            </p>
                        </div>

                        <!-- Grid 2x2 de datos -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 pt-2 max-w-md mx-auto lg:max-w-none lg:mx-0 text-left">
                            <!-- Dirección -->
                            <div class="flex items-start gap-3.5 p-3.5 sm:p-0 rounded-2xl bg-slate-50/70 sm:bg-transparent border border-slate-100 sm:border-0">
                                <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 text-base shadow-xs">
                                    <i class="fa-solid fa-location-dot"></i>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-xs sm:text-sm font-bold text-slate-900 leading-snug">
                                        Dirección
                                    </h3>
                                    <p class="text-[11px] sm:text-xs text-slate-500 font-normal leading-relaxed mt-0.5">
                                        Calle 20 #102, Centro<br>
                                        Hocabá, Yucatán
                                    </p>
                                </div>
                            </div>

                            <!-- Horario de atención Dinámico -->
                            <div class="flex items-start gap-3.5 p-3.5 sm:p-0 rounded-2xl bg-slate-50/70 sm:bg-transparent border border-slate-100 sm:border-0">
                                <div class="w-11 h-11 rounded-2xl bg-blue-50 text-[#0084ff] flex items-center justify-center flex-shrink-0 text-base shadow-xs">
                                    <i class="fa-regular fa-clock"></i>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-xs sm:text-sm font-bold text-slate-900 leading-snug">
                                        Horario de atención
                                    </h3>
                                    <p class="text-[11px] sm:text-xs text-slate-500 font-normal leading-relaxed mt-0.5">
                                        {{ formattedScheduleDisplay.days }}<br>
                                        {{ formattedScheduleDisplay.hours }}
                                    </p>
                                </div>
                            </div>

                            <!-- Teléfono -->
                            <div class="flex items-start gap-3.5 p-3.5 sm:p-0 rounded-2xl bg-slate-50/70 sm:bg-transparent border border-slate-100 sm:border-0">
                                <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 text-base shadow-xs">
                                    <i class="fa-solid fa-phone"></i>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-xs sm:text-sm font-bold text-slate-900 leading-snug">
                                        Teléfono
                                    </h3>
                                    <a
                                        href="tel:9991404397"
                                        class="text-[11px] sm:text-xs text-slate-500 hover:text-[#0084ff] font-medium leading-relaxed mt-0.5 inline-block transition-colors"
                                    >
                                        9991 40 43 97
                                    </a>
                                </div>
                            </div>

                            <!-- También atendemos -->
                            <div class="flex items-start gap-3.5 p-3.5 sm:p-0 rounded-2xl bg-slate-50/70 sm:bg-transparent border border-slate-100 sm:border-0">
                                <div class="w-11 h-11 rounded-2xl bg-blue-50 text-[#0084ff] flex items-center justify-center flex-shrink-0 text-base shadow-xs">
                                    <i class="fa-regular fa-star"></i>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-xs sm:text-sm font-bold text-slate-900 leading-snug">
                                        También atendemos
                                    </h3>
                                    <p class="text-[11px] sm:text-xs text-slate-500 font-normal leading-relaxed mt-0.5">
                                        Pedidos a domicilio
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMNA 2: FOTOGRAFÍA DE LA SUCURSAL -->
                    <div class="md:col-span-1 lg:col-span-4 min-h-[300px] sm:min-h-[360px] flex">
                        <div class="relative w-full rounded-3xl overflow-hidden shadow-lg border border-slate-100 group min-h-[300px]">
                            <img
                                src="/images/sucursal-real.jpg"
                                alt="Sucursal Puritronic Hocabá, Yucatán"
                                class="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-105"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/50 via-transparent to-transparent pointer-events-none"></div>
                            
                            <!-- Badge dinámico sobre la foto -->
                            <div class="absolute bottom-4 left-4 right-4 py-2.5 px-4 rounded-2xl bg-white/95 backdrop-blur-md border border-white/60 shadow-md flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="w-2.5 h-2.5 rounded-full"
                                        :class="isStoreOpen ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"
                                    ></span>
                                    <span class="text-xs font-bold text-slate-800">Planta & Sucursal Hocabá</span>
                                </div>
                                <span
                                    class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full"
                                    :class="isStoreOpen ? 'text-emerald-700 bg-emerald-50 border border-emerald-200/60' : 'text-slate-600 bg-slate-100 border border-slate-200/60'"
                                >
                                    {{ isStoreOpen ? 'Abierto' : 'Cerrado' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMNA 3: GOOGLE MAPS IFRAME REAL Y BOTÓN CÓMO LLEGAR -->
                    <div class="md:col-span-1 lg:col-span-3 min-h-[300px] sm:min-h-[360px] flex">
                        <div class="relative w-full rounded-3xl overflow-hidden border border-slate-200/90 shadow-md bg-slate-100 flex flex-col justify-end min-h-[300px]">
                            
                            <!-- Google Maps Iframe proporcionado por el usuario -->
                            <iframe
                                src="https://www.google.com/maps/embed?pb=!1m17!1m12!1m3!1d3729.429274512127!2d-89.249962!3d20.814365!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m2!1m1!2zMjDCsDQ4JzUxLjciTiA4OcKwMTQnNTkuOSJX!5e0!3m2!1ses-419!2smx!4v1792245980366!5m2!1ses-419!2smx"
                                class="absolute inset-0 w-full h-full border-0"
                                allowfullscreen=""
                                loading="lazy"
                                referrerpolicy="strict-origin-when-cross-origin"
                            ></iframe>

                            <!-- Botón flotante inferior "Cómo llegar" con link directo de Google Maps -->
                            <div class="relative z-10 p-3.5 sm:p-4">
                                <a
                                    href="https://maps.app.goo.gl/dxB7hkU2R1xbzGr6A"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="w-full bg-white/95 backdrop-blur-md rounded-2xl py-3 px-4 shadow-xl border border-slate-200/80 hover:border-blue-300 hover:shadow-2xl transition-all duration-300 flex items-center justify-between group/btn cursor-pointer"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <!-- Logo icono Google Maps -->
                                        <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="#4285F4"/>
                                            <path d="M12 2C8.13 2 5 5.13 5 9c0 2.22 1.25 4.9 2.76 7.42L12 9V2z" fill="#EA4335"/>
                                            <path d="M12 9l4.24 7.42C17.75 13.9 19 11.22 19 9c0-3.87-3.13-7-7-7v7z" fill="#FBBC05"/>
                                            <path d="M7.76 16.42L12 22l4.24-5.58C14.88 18.2 13.48 20 12 20s-2.88-1.8-4.24-3.58z" fill="#34A853"/>
                                            <circle cx="12" cy="9" r="2.8" fill="#FFFFFF"/>
                                        </svg>
                                        <span class="text-xs sm:text-sm font-bold text-slate-800 group-hover/btn:text-[#0084ff] transition-colors">
                                            Cómo llegar
                                        </span>
                                    </div>
                                    <i class="fa-solid fa-chevron-right text-xs text-[#0084ff] group-hover/btn:translate-x-1 transition-transform"></i>
                                </a>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ================= PREGUNTAS FRECUENTES (FAQ) ================= -->
        <section id="preguntas" class="scroll-mt-20 py-20 lg:py-24 bg-slate-50/60 border-t border-slate-100">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Títulos -->
                <div class="text-center mb-16 space-y-3">
                    <span class="inline-block text-xs font-bold uppercase tracking-wider text-[#0084ff] bg-blue-50 px-3 py-1 rounded-full">
                        Dudas Frecuentes
                    </span>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-[#0e1c2e] tracking-tight">
                        Preguntas <span class="text-[#0084ff]">Frecuentes</span>
                    </h2>
                    <p class="text-sm sm:text-base text-slate-500">
                        Resolvemos las preguntas más comunes sobre el servicio, los pedidos y las entregas.
                    </p>
                </div>

                <!-- Lista de Acordeón con Cambio de Color Dinámico al Abrir -->
                <div class="space-y-3.5">
                    <div
                        v-for="(faq, index) in faqs"
                        :key="index"
                        :class="[
                            'rounded-2xl border transition-all duration-300 overflow-hidden',
                            openFaq === index
                                ? 'bg-blue-50/80 border-blue-200 shadow-md shadow-blue-500/5'
                                : 'bg-white border-slate-200/80 hover:border-slate-300 shadow-xs'
                        ]"
                    >
                        <button
                            @click="toggleFaq(index)"
                            type="button"
                            class="w-full px-6 py-5 flex items-center justify-between text-left focus:outline-none gap-4"
                        >
                            <span :class="['text-sm sm:text-base font-semibold transition-colors duration-200', openFaq === index ? 'text-[#0084ff]' : 'text-slate-800']">
                                {{ faq.q }}
                            </span>
                            <div :class="[
                                'w-8 h-8 rounded-full flex items-center justify-center text-xs transition-all duration-300 flex-shrink-0',
                                openFaq === index
                                    ? 'bg-[#0084ff] text-white rotate-180 shadow-xs'
                                    : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
                            ]">
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                        </button>
                        
                        <div
                            v-show="openFaq === index"
                            class="px-6 pb-6 pt-1 text-slate-600 text-xs sm:text-sm border-t border-blue-100/60 leading-relaxed font-normal"
                        >
                            {{ faq.a }}
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ================= BANNER CTA AZUL MINIMALISTA ================= -->
        <section class="py-12 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-3xl sm:rounded-[2.5rem] bg-gradient-to-r from-[#0084ff] via-[#0096f2] to-[#00b4d8] p-8 sm:p-12 lg:p-16 shadow-xl shadow-blue-500/15">
                    
                    <!-- Elementos decorativos de fondo sutiles -->
                    <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>
                    <div class="absolute -left-16 -bottom-16 w-64 h-64 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>

                    <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                        
                        <!-- Columna izquierda: Logo y texto -->
                        <div class="lg:col-span-7 space-y-4 text-white text-center lg:text-left">
                            
                            <!-- Logo -->
                            <div>
                                <img
                                    src="/images/logo.png"
                                    alt="Puritronic"
                                    class="h-10 sm:h-12 w-auto object-contain mx-auto lg:mx-0 drop-shadow-md"
                                />
                            </div>

                            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                                ¿Necesitas agua?
                            </h2>
                            <p class="text-white/90 text-sm sm:text-base max-w-md mx-auto lg:mx-0 font-light">
                                Crea tu cuenta y realiza tu pedido en pocos minutos.
                            </p>
                        </div>

                        <!-- Columna derecha: Botones de acción -->
                        <div class="lg:col-span-5 flex flex-col sm:flex-row lg:flex-col gap-3.5 lg:items-end justify-center">
                            <Link
                                :href="route('register')"
                                class="inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl bg-white text-slate-800 font-bold hover:bg-slate-50 transition-all shadow-md group text-xs sm:text-sm w-full sm:w-auto lg:w-64"
                            >
                                <span>Crear mi cuenta</span>
                                <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:translate-x-1 transition-transform"></i>
                            </Link>

                            <Link
                                :href="route('login')"
                                class="inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl bg-[#0e1c2e] hover:bg-[#1a2d42] text-white font-bold transition-all shadow-md group text-xs sm:text-sm w-full sm:w-auto lg:w-64"
                            >
                                <span>Ya tengo cuenta</span>
                                <i class="fa-solid fa-chevron-right text-xs text-white/50 group-hover:translate-x-1 transition-transform"></i>
                            </Link>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- ================= FOOTER OSCURO MINIMALISTA ================= -->
        <footer id="contacto" class="scroll-mt-20 bg-[#0e1c2e] text-white pt-16 pb-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-12 pb-12 border-b border-white/10">
                    
                    <!-- Columna 1: Marca y descripción -->
                    <div class="space-y-4">
                        <div>
                            <img
                                src="/images/logo.png"
                                alt="Puritronic Agua Purificada"
                                class="h-11 sm:h-12 w-auto object-contain"
                            />
                        </div>
                        <p class="text-slate-400 text-xs sm:text-sm max-w-sm leading-relaxed">
                            Agua purificada de calidad y máxima higiene, directo hasta tu hogar.
                        </p>
                    </div>

                    <!-- Columna 2: Contacto e información local -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-white tracking-wider uppercase">
                            Contacto
                        </h3>
                        <ul class="space-y-3 text-xs sm:text-sm text-slate-300">
                            <!-- Ubicación -->
                            <li class="flex items-center gap-3">
                                <div class="w-6 flex justify-center text-slate-400">
                                    <i class="fa-solid fa-location-dot"></i>
                                </div>
                                <span>Calle 20 #102, Centro · Hocabá, Yucatán</span>
                            </li>
                            <!-- Horarios -->
                            <li class="flex items-center gap-3">
                                <div class="w-6 flex justify-center text-slate-400">
                                    <i class="fa-regular fa-clock"></i>
                                </div>
                                <span class="text-slate-200 font-medium">{{ formattedScheduleDisplay.fullText }}</span>
                            </li>
                            <!-- Servicio a domicilio -->
                            <li class="flex items-center gap-3">
                                <div class="w-6 flex justify-center text-slate-400">
                                    <i class="fa-solid fa-phone"></i>
                                </div>
                                <span>Tel. 9991 40 43 97 · Pedidos a domicilio</span>
                            </li>
                        </ul>
                    </div>

                </div>

                <!-- Copyright final -->
                <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                    <p>© {{ new Date().getFullYear() }} Puritronic. Todos los derechos reservados.</p>
                    <div class="flex items-center gap-6">
                        <a href="#inicio" @click="scrollToSection($event, 'inicio')" class="hover:text-slate-300 transition-colors">Volver arriba</a>
                        <a href="#sucursal" @click="scrollToSection($event, 'sucursal')" class="hover:text-slate-300 transition-colors">Nuestra Sucursal</a>
                        <Link :href="$page.props.auth?.user ? route('dashboard') : route('login')" class="hover:text-slate-300 transition-colors">{{ $page.props.auth?.user ? 'Mi Panel' : 'Acceso Clientes' }}</Link>
                    </div>
                </div>
            </div>
        </footer>

    </div>
</template>
