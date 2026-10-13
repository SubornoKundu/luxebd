<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Heart, ShoppingCart, X } from '@lucide/vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import { formatPrice } from '@/currency';
import { routes } from '@/routes';

defineProps({
    wishlists: { type: Array, required: true },
});

function remove(productId) {
    router.delete(routes.wishlistDestroy(productId), { preserveScroll: true });
}

function addToCart(product) {
    // Products with sizes / colours need an option picked on their own page.
    if (product.variants_count > 0) {
        router.visit(routes.productShow(product.slug));
        return;
    }

    router.post(routes.cartStore(), { product_id: product.id, quantity: 1 }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Your Wishlist" />

    <MainLayout>
        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
            <h1 class="mb-5 text-xl font-bold text-gray-900 sm:mb-6 sm:text-2xl">Your Wishlist</h1>

            <div v-if="!wishlists.length" class="rounded-2xl border border-dashed border-gray-300 py-20 text-center">
                <Heart class="mx-auto h-10 w-10 text-gray-300" />
                <p class="mt-3 text-gray-500">Nothing here yet.</p>
                <Link :href="routes.productsIndex()" class="mt-3 inline-block rounded-full bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-700">
                    Browse Products
                </Link>
            </div>

            <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                <div v-for="entry in wishlists" :key="entry.id" class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white">
                    <button
                        type="button"
                        class="absolute top-2 right-2 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-gray-600 shadow hover:bg-white hover:text-rose-600 sm:top-3 sm:right-3"
                        title="Remove from wishlist"
                        aria-label="Remove from wishlist"
                        @click="remove(entry.product.id)"
                    >
                        <X class="h-4 w-4" />
                    </button>

                    <Link :href="routes.productShow(entry.product.slug)" class="block aspect-square overflow-hidden bg-gray-100">
                        <img loading="lazy" decoding="async" :src="entry.product.image" :alt="entry.product.name" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                    </Link>

                    <div class="p-3 sm:p-4">
                        <p v-if="entry.product.brand" class="text-xs font-medium text-gray-400 uppercase">{{ entry.product.brand.name }}</p>
                        <Link :href="routes.productShow(entry.product.slug)" class="line-clamp-2 text-sm font-semibold break-words text-gray-900 hover:text-green-700">
                            {{ entry.product.name }}
                        </Link>

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="min-w-0 text-sm font-bold text-gray-900 sm:text-base">{{ formatPrice(entry.product.final_price) }}</span>
                            <button
                                type="button"
                                :disabled="entry.product.stock <= 0"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-600 text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                                title="Add to cart"
                                :aria-label="entry.product.variants_count > 0 ? 'Choose options' : 'Add to cart'"
                                @click="addToCart(entry.product)"
                            >
                                <ShoppingCart class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
