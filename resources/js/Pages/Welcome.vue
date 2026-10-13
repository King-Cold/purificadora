<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
    canRegister: {
        type: Boolean,
        default: true,
    },
});

const mobileMenuOpen = ref(false);
const activeSection = ref('inicio');

const navItems = [
    { id: 'inicio', label: 'Inicio' },
    { id: 'beneficios', label: 'Beneficios' },
    { id: 'como-funciona', label: '¿Cómo funciona?' },
    { id: 'comentarios', label: 'Comentarios' },
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
                const sectionIds = ['inicio', 'beneficios', 'como-funciona', 'comentarios', 'preguntas', 'contacto'];

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

const faqs = [
    {
        q: '¿Cómo realizo un pedido por primera vez?',
        a: 'Es muy sencillo: solo debes hacer clic en "Pedir agua ahora" o "Crear cuenta", indicar tu dirección de entrega en Hocabá y la cantidad de garrafones que necesitas. Te confirmaremos de inmediato vía WhatsApp y en la app.',
    },
    {
        q: '¿Cuáles son los horarios de entrega a domicilio?',
        a: 'Entregamos de Lunes a Sábado de 7:00 AM a 7:00 PM, y Domingos de 8:00 AM a 3:00 PM.',
    },
    {
        q: '¿Aceptan garrafones de otras marcas al momento del cambio?',
        a: 'Sí, aceptamos garrafones comerciales estándar en buen estado físico (limpios, sin perforaciones o rajaduras). Cada garrafón pasa por nuestro proceso de lavado a presión antes de ser llenado.',
    },
    {
        q: '¿Qué formas de pago puedo utilizar?',
        a: 'Aceptamos pago en efectivo contra entrega (nuestros repartidores cuentan con cambio), transferencias electrónicas y pagos directos desde la plataforma.',
    },
];

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
</script>

<template>
    <Head title="Puritronic - Agua pura, directo hasta su hogar" />

    <div class="min-h-screen bg-white font-['Poppins',sans-serif] text-slate-800 antialiased selection:bg-[#0084ff] selection:text-white">
        
        <!-- ================= NAVBAR ================= -->
        <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-100 transition-all duration-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-20">
                    
                    <!-- LOGO PURITRONIC -->
                    <a href="#" class="flex items-center group">
                        <img
                            src="/images/logo.png"
                            alt="Puritronic Agua Purificada"
                            class="h-11 sm:h-12 w-auto object-contain transition-transform duration-200 group-hover:scale-[1.03]"
                        />
                    </a>

                    <!-- NAVEGACIÓN DESKTOP (REACTIVA A LA SECCIÓN ACTIVA) -->
                    <nav class="hidden md:flex items-center space-x-8">
                        <a
                            v-for="item in navItems"
                            :key="item.id"
                            :href="`#${item.id}`"
                            @click="scrollToSection($event, item.id)"
                            :class="[
                                'text-sm transition-all duration-200 py-1 relative',
                                activeSection === item.id
                                    ? 'font-semibold text-[#0084ff] after:absolute after:bottom-0 after:left-0 after:w-full after:h-0.5 after:bg-[#0084ff] after:rounded-full'
                                    : 'font-medium text-slate-600 hover:text-[#0084ff]'
                            ]"
                        >
                            {{ item.label }}
                        </a>
                    </nav>

                    <!-- BOTONES DE ACCIÓN (AUTH) -->
                    <div class="hidden sm:flex items-center gap-3">
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="px-5 py-2.5 text-xs sm:text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-full hover:bg-slate-50 hover:border-slate-300 hover:text-[#0084ff] transition-all shadow-sm"
                        >
                            Iniciar Sesión
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="px-6 py-2.5 text-xs sm:text-sm font-semibold text-white bg-[#0084ff] hover:bg-[#0070dc] rounded-full shadow-md shadow-blue-500/20 hover:shadow-lg hover:shadow-blue-500/30 hover:-translate-y-0.5 transition-all"
                        >
                            Crear Cuenta
                        </Link>
                    </div>

                    <!-- BOTÓN MÓVIL HAMBURGUESA -->
                    <div class="flex sm:hidden">
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

                <!-- MENU MÓVIL DESPLEGABLE (REACTIVO A LA SECCIÓN ACTIVA) -->
                <div v-show="mobileMenuOpen" class="sm:hidden border-t border-slate-100 py-4 space-y-2 bg-white">
                    <a
                        v-for="item in navItems"
                        :key="item.id"
                        :href="`#${item.id}`"
                        @click="scrollToSection($event, item.id)"
                        :class="[
                            'block px-3 py-2 text-sm rounded-lg transition-colors',
                            activeSection === item.id
                                ? 'font-semibold text-[#0084ff] bg-blue-50/70'
                                : 'font-medium text-slate-600 hover:text-[#0084ff] hover:bg-slate-50'
                        ]"
                    >
                        {{ item.label }}
                    </a>
                    <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="w-full text-center py-2.5 text-sm font-semibold text-slate-700 border border-slate-200 rounded-full hover:bg-slate-50"
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
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl font-bold uppercase tracking-wider text-white text-sm bg-[#00d084] hover:bg-[#00b875] shadow-lg shadow-[#00d084]/25 hover:shadow-xl hover:shadow-[#00d084]/35 hover:-translate-y-0.5 transition-all group"
                            >
                                <span>PEDIR AGUA AHORA</span>
                                <i class="fa-solid fa-chevron-right text-xs group-hover:translate-x-1 transition-transform"></i>
                            </Link>

                            <a
                                href="https://wa.me/529991234567?text=Hola,%20quisiera%20pedir%20un%20garrafón%20de%20agua%20a%20domicilio"
                                target="_blank"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-4 rounded-2xl font-semibold text-slate-700 hover:text-emerald-700 bg-white hover:bg-emerald-50/50 border border-slate-200 hover:border-emerald-300 shadow-xs transition-all text-sm"
                            >
                                <i class="fa-brands fa-whatsapp text-lg text-emerald-600"></i>
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

        <!-- ================= BENEFICIOS / CARDS SECTION ================= -->
        <section id="beneficios" class="scroll-mt-20 py-20 lg:py-24 bg-slate-50/60 border-t border-slate-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Títulos -->
                <div class="text-center max-w-2xl mx-auto mb-16 space-y-3">
                    <span class="inline-block text-xs font-bold uppercase tracking-wider text-[#0084ff] bg-blue-50 px-3 py-1 rounded-full">
                        Beneficios Exclusivos
                    </span>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-[#0e1c2e] tracking-tight">
                        Todo lo que necesitas, <span class="text-[#0084ff]">en un solo lugar.</span>
                    </h2>
                    <p class="text-sm sm:text-base text-slate-500 font-normal">
                        Disfruta de una forma más sencilla de solicitar y administrar tus pedidos de agua.
                    </p>
                </div>

                <!-- Grid de 4 Tarjetas con Hover Interactivo en Iconos -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    
                    <!-- Card 1: Haz tu pedido -->
                    <div class="bg-white rounded-3xl p-8 text-center shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border border-slate-100 flex flex-col items-center group">
                        <div class="w-16 h-16 rounded-2xl bg-blue-50 text-[#0084ff] flex items-center justify-center text-2xl mb-6 group-hover:scale-110 group-hover:bg-[#0084ff] group-hover:text-white transition-all duration-300 shadow-xs">
                            <i class="fa-solid fa-cart-shopping"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Haz tu pedido</h3>
                        <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
                            Solicita la cantidad de garrafones que necesitas fácilmente en pocos clics.
                        </p>
                    </div>

                    <!-- Card 2: Sigue tu pedido -->
                    <div class="bg-white rounded-3xl p-8 text-center shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border border-slate-100 flex flex-col items-center group">
                        <div class="w-16 h-16 rounded-2xl bg-cyan-50 text-cyan-500 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 group-hover:bg-cyan-500 group-hover:text-white transition-all duration-300 shadow-xs">
                            <i class="fa-solid fa-magnifying-glass-location"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Sigue tu pedido</h3>
                        <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
                            Consulta el estado de tu pedido en tiempo real y conoce cuándo llegará a tu puerta.
                        </p>
                    </div>

                    <!-- Card 3: Consulta tu historial -->
                    <div class="bg-white rounded-3xl p-8 text-center shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border border-slate-100 flex flex-col items-center group">
                        <div class="w-16 h-16 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 group-hover:bg-purple-600 group-hover:text-white transition-all duration-300 shadow-xs">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Consulta tu historial</h3>
                        <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
                            Revisa todos tus pedidos realizados anteriormente y vuelve a pedir con un solo toque.
                        </p>
                    </div>

                    <!-- Card 4: Controla tus pagos -->
                    <div class="bg-white rounded-3xl p-8 text-center shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border border-slate-100 flex flex-col items-center group">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 group-hover:bg-emerald-600 group-hover:text-white transition-all duration-300 shadow-xs">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Controla tus pagos</h3>
                        <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
                            Consulta tus pagos y notas de entrega de manera transparente y sencilla.
                        </p>
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
                        <div class="w-11 h-11 rounded-full bg-[#00d084] text-white font-extrabold text-sm flex items-center justify-center mb-6 shadow-md shadow-emerald-500/25">
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

        <!-- ================= PREGUNTAS FRECUENTES (FAQ) ================= -->
        <section id="preguntas" class="scroll-mt-20 py-20 lg:py-24 bg-white border-t border-slate-100">
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
                                <span>Hocabá, Yucatán</span>
                            </li>
                            <!-- Horarios -->
                            <li class="flex items-center gap-3">
                                <div class="w-6 flex justify-center text-slate-400">
                                    <i class="fa-regular fa-clock"></i>
                                </div>
                                <span>Lun – Sáb: 7:00 AM – 7:00 PM | Dom: 8:00 AM – 3:00 PM</span>
                            </li>
                            <!-- Servicio a domicilio -->
                            <li class="flex items-center gap-3">
                                <div class="w-6 flex justify-center text-slate-400">
                                    <i class="fa-solid fa-phone"></i>
                                </div>
                                <span>Servicio a domicilio disponible</span>
                            </li>
                        </ul>
                    </div>

                </div>

                <!-- Copyright final -->
                <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                    <p>© {{ new Date().getFullYear() }} Puritronic. Todos los derechos reservados.</p>
                    <div class="flex items-center gap-6">
                        <a href="#inicio" @click="scrollToSection($event, 'inicio')" class="hover:text-slate-300 transition-colors">Volver arriba</a>
                        <Link :href="route('login')" class="hover:text-slate-300 transition-colors">Acceso Clientes</Link>
                    </div>
                </div>
            </div>
        </footer>

    </div>
</template>
