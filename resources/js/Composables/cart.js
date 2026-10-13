import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { routes } from '@/routes';

/** Is the slide-out cart open? Shared by the header, product cards and product page. */
export const cartDrawerOpen = ref(false);

export function openCartDrawer() {
    cartDrawerOpen.value = true;
}

export function closeCartDrawer() {
    cartDrawerOpen.value = false;
}

// Every page mounts its own layout (and so its own drawer), which means a component-level
// "close when the URL changes" watcher never fires. Watch navigation here instead: going to
// a different page closes the drawer, while changing a quantity (same page) keeps it open.
if (typeof window !== 'undefined') {
    let lastPath = window.location.pathname;

    router.on('navigate', (event) => {
        const path = String(event.detail.page.url).split('?')[0];

        if (path !== lastPath) {
            closeCartDrawer();
        }

        lastPath = path;
    });
}

/**
 * Add something to the cart and open the drawer when it worked.
 * `payload` = { product_id, variant_id?, quantity? }. With `buyNow` the server
 * redirects to checkout instead, so the drawer stays closed.
 *
 * Guests are not blocked here: the server answers with the login page and
 * remembers where they were, so they come back after signing in.
 */
let adding = false;

export function addToCart(payload, { buyNow = false, onFinish } = {}) {
    // Ignore a second tap while the first request is still running (no double adds).
    if (adding) {
        return;
    }

    adding = true;

    router.post(
        routes.cartStore(),
        { ...payload, buy_now: buyNow ? 1 : undefined },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                if (!buyNow && !page.props.flash?.error && page.props.auth?.user) {
                    openCartDrawer();
                }
            },
            onFinish: () => {
                adding = false;
                onFinish?.();
            },
        },
    );
}
