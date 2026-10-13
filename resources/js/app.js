import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

// A picture that cannot be loaded (deleted upload, old "recently viewed" entry, bad link)
// shows a neutral placeholder instead of broken-image text. Runs once per picture.
window.addEventListener('error', (event) => {
    const img = event.target;
    if (!(img instanceof HTMLImageElement) || img.dataset.fallbackDone || img.hasAttribute('data-no-fallback')) return;
    // A smaller copy that could not be made: try the normal picture once before giving up.
    if (img.hasAttribute('srcset') && !img.dataset.retried) {
        img.dataset.retried = '1';
        const original = img.getAttribute('src');
        img.removeAttribute('srcset');
        img.removeAttribute('sizes');
        img.src = '';
        img.src = original;
        return;
    }
    img.dataset.fallbackDone = '1';
    img.removeAttribute('srcset');
    img.src = '/images/placeholder.svg';
}, true);

const meta = (name) => document.head.querySelector(`meta[name="${name}"]`)?.getAttribute('content');
const appName = meta('application-name') || import.meta.env.VITE_APP_NAME || 'Vireluxe';
const brandColor = '#c99a1f';

/*
 * v-reveal: the cards of a grid rise in one after another the first time the grid scrolls into view.
 * Grids already on screen at load stay as they are, and nothing happens with "reduce motion" on.
 */
const reveal = {
    mounted(el) {
        try {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;
            // The first report says whether the grid is already on screen (then it stays as it is);
            // otherwise it is hidden until it scrolls into view. No layout reads, so no forced reflow.
            let first = true;
            const observer = new IntersectionObserver((entries) => {
                const visible = entries.some((entry) => entry.isIntersecting);
                if (first) {
                    first = false;
                    if (visible) {
                        observer.disconnect();
                        return;
                    }
                    [...el.children].forEach((child, i) => child.style.setProperty('--i', Math.min(i, 5)));
                    el.classList.add('reveal-init');
                    return;
                }
                if (!visible) return;
                observer.disconnect();
                el.classList.replace('reveal-init', 'reveal-in');
            }, { rootMargin: '0px 0px -8% 0px' });
            observer.observe(el);
            el._revealObserver = observer;
        } catch { /* the grid simply shows as normal */ }
    },
    unmounted(el) {
        el._revealObserver?.disconnect();
    },
};

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('reveal', reveal)
            .mount(el);

        // The first-look banner in the page shell (see app.blade.php) has done its job.
        window.requestAnimationFrame(() => window.requestAnimationFrame(() => document.getElementById('boot')?.remove()));
    },
    progress: {
        color: brandColor,
    },
});
