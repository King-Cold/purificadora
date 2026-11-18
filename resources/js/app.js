import '../css/app.css';
import './bootstrap';
import './echo';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { showFlashNotifications } from './utils/swal';

const rawAppName = import.meta.env.VITE_APP_NAME;
const appName = rawAppName && rawAppName !== 'Laravel' ? rawAppName : 'Puritronic';

router.on('success', (event) => {
    showFlashNotifications(event.detail?.page?.props?.flash);
});

createInertiaApp({
    title: (title) => {
        if (!title) return appName;
        return /puritronic/i.test(title) ? title : `${title} · ${appName}`;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        showFlashNotifications(props.initialPage?.props?.flash);

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
