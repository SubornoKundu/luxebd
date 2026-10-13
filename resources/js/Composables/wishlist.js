import { computed, toValue } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { routes } from '@/routes';

/**
 * Heart button state shared by the product card and the product page.
 * The server sends the ids of the signed-in customer's wishlist with every page
 * (`wishlistIds`), so the heart turns red as soon as a product is in it.
 */
export function useWishlist(productId) {
    const page = usePage();

    const isAuthed = computed(() => Boolean(page.props.auth?.user));
    const liked = computed(() => (page.props.wishlistIds ?? []).includes(Number(toValue(productId))));

    function toggle() {
        if (!isAuthed.value) {
            router.visit(routes.login());
            return;
        }

        const id = toValue(productId);
        const options = { preserveScroll: true, preserveState: true };

        if (liked.value) {
            router.delete(routes.wishlistDestroy(id), options);
        } else {
            router.post(routes.wishlistStore(), { product_id: id }, options);
        }
    }

    return { liked, toggle };
}
