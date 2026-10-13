import { ref } from 'vue';

const KEY = 'vireluxe.recentlyViewed';
const MAX = 12;

/** Newest first. Only the few fields a product card needs are kept. */
function read() {
    try {
        const list = JSON.parse(window.localStorage.getItem(KEY) || '[]');
        return Array.isArray(list) ? list : [];
    } catch {
        return [];
    }
}

function write(list) {
    try {
        window.localStorage.setItem(KEY, JSON.stringify(list));
    } catch {
        // Storage can be blocked (private mode); the list simply isn't kept.
    }
}

export const recentlyViewed = ref([]);

/**
 * The browser only remembers WHICH products were viewed. Their current name, picture, price and
 * stock are asked from the server each time, so nothing old (a replaced picture, an old price, a
 * product that was hidden or deleted) is ever shown. If the server cannot be reached the saved
 * copy is shown instead; a missing picture then falls back to a placeholder.
 */
export async function loadRecentlyViewed() {
    const saved = read();

    if (!saved.length) {
        recentlyViewed.value = [];
        return;
    }

    try {
        const response = await fetch(`/recently-viewed?${new URLSearchParams({ ids: saved.map((p) => p.id).join(',') })}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('recently viewed failed');
        const data = await response.json();

        if (Array.isArray(data)) {
            // The product page may have remembered one more product while we were asking: keep it.
            const asked = new Set(saved.map((p) => p.id));
            const fresh = new Map(data.map((p) => [p.id, p]));
            const next = read()
                .map((p) => fresh.get(p.id) ?? (asked.has(p.id) ? null : p))
                .filter(Boolean);

            write(next);
            recentlyViewed.value = next;
            return;
        }
    } catch {
        // fall through to the saved copy
    }

    recentlyViewed.value = saved;
}

export function rememberProduct(product) {
    if (!product?.id) return;

    const entry = {
        id: product.id,
        slug: product.slug,
        name: product.name,
        image: product.image,
        price: product.price,
        discount_price: product.discount_price,
        stock: product.stock,
        variants_count: product.variants?.length ?? product.variants_count ?? 0,
        brand: product.brand ? { name: product.brand.name } : null,
    };

    const next = [entry, ...read().filter((p) => p.id !== entry.id)].slice(0, MAX);

    write(next);
}
