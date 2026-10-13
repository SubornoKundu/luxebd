<script setup>
import { srcset } from '@/thumb';
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Check, Heart, ShoppingBag, SlidersHorizontal } from '@lucide/vue';
import { formatPrice } from '@/currency';
import { routes } from '@/routes';
import { addToCart as addToCartRequest } from '@/Composables/cart';
import { useWishlist } from '@/Composables/wishlist';

const props = defineProps({
    product: { type: Object, required: true },
});

const page = usePage();

const hasDiscount = computed(
    () => props.product.discount_price && Number(props.product.discount_price) < Number(props.product.price),
);

const percentOff = computed(() => (hasDiscount.value
    ? Math.round((1 - Number(props.product.discount_price) / Number(props.product.price)) * 100)
    : 0));

const outOfStock = computed(() => Number(props.product.stock) <= 0);
const hasOptions = computed(() => Number(props.product.variants_count ?? 0) > 0);
const lowStock = computed(() => !outOfStock.value
    && Number(props.product.stock) <= Number(page.props.shop?.lowStockThreshold ?? 5));

// Brief tick on the bag button so the tap is visibly acknowledged.
const added = ref(false);
let addedTimer;

function addToCart() {
    addToCartRequest({ product_id: props.product.id, quantity: 1 });
    added.value = true;
    clearTimeout(addedTimer);
    addedTimer = setTimeout(() => (added.value = false), 1400);
}

const { liked, toggle: toggleWishlist } = useWishlist(() => props.product.id);
</script>

<template>
    <div class="group relative flex flex-col border border-gray-200 bg-white transition duration-300 hover:border-gold-500 hover:shadow-[0_10px_30px_-12px_rgba(20,17,12,0.4)]">
        <Link :href="routes.productShow(product.slug)" class="card-shine relative block aspect-square overflow-hidden bg-gray-100">
            <img decoding="async"
                :src="product.image"
                :srcset="srcset(product.image, [360, 480, 720])"
                sizes="(min-width: 1024px) 260px, 50vw"
                :alt="product.name"
                class="h-full w-full object-cover transition duration-700 ease-out group-hover:scale-105"
                loading="lazy"
            >
            <span
                v-if="hasDiscount"
                class="absolute top-0 left-0 bg-gold-500 px-2.5 py-1 text-xs font-bold text-green-950"
            >
                -{{ percentOff }}%
            </span>
            <span
                v-if="lowStock"
                class="absolute bottom-0 left-0 bg-green-950/90 px-2.5 py-1 text-[11px] font-semibold text-gold-300"
            >
                Only {{ product.stock }} left
            </span>
            <span
                v-if="outOfStock"
                class="absolute inset-0 flex items-center justify-center bg-white/75 text-sm font-semibold text-gray-700 backdrop-blur-[1px]"
            >
                Out of stock
            </span>
        </Link>

        <button
            type="button"
            class="absolute top-2 right-2 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 shadow-sm transition hover:bg-white hover:text-rose-600 sm:h-8 sm:w-8"
            :class="liked ? 'text-rose-600' : 'text-gray-600'"
            :title="liked ? 'Remove from wishlist' : 'Add to wishlist'"
            :aria-label="liked ? 'Remove from wishlist' : 'Add to wishlist'"
            :aria-pressed="liked"
            @click="toggleWishlist"
        >
            <Heart :key="String(liked)" class="h-4.5 w-4.5" :class="liked ? 'pop-in' : ''" :fill="liked ? 'currentColor' : 'none'" />
        </button>

        <div class="flex flex-1 flex-col gap-1 p-3 sm:p-4">
            <Link v-if="product.brand?.slug" :href="routes.productsIndex({ brand: product.brand.slug })" class="-my-1 w-fit max-w-full truncate py-1 text-xs font-semibold text-gold-600 hover:underline">{{ product.brand.name }}</Link>
            <p v-else-if="product.brand?.name" class="truncate text-xs font-semibold text-gold-600">{{ product.brand.name }}</p>
            <Link :href="routes.productShow(product.slug)" class="font-display line-clamp-2 text-lg leading-snug font-medium break-words text-green-900 hover:text-green-600">
                {{ product.name }}
            </Link>
            <p v-if="product.category?.name" class="text-xs text-gray-500">{{ product.category.name }}</p>

            <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                <div class="flex min-w-0 flex-wrap items-baseline gap-x-2">
                    <span class="text-base font-bold text-gray-900">
                        {{ hasOptions ? 'From ' : '' }}{{ formatPrice(hasDiscount ? product.discount_price : product.price) }}
                    </span>
                    <span v-if="hasDiscount" class="text-xs text-gray-400 line-through">
                        {{ formatPrice(product.price) }}
                    </span>
                </div>

                <Link
                    v-if="hasOptions && !outOfStock"
                    :href="routes.productShow(product.slug)"
                    class="flex h-9 w-9 shrink-0 items-center justify-center bg-green-900 text-white transition hover:bg-green-600 sm:h-8 sm:w-8"
                    title="Choose a size"
                    aria-label="Choose a size"
                >
                    <SlidersHorizontal class="h-4 w-4" />
                </Link>
                <button
                    v-else
                    type="button"
                    :disabled="outOfStock"
                    class="flex h-9 w-9 shrink-0 items-center justify-center bg-green-900 text-white transition hover:bg-green-600 disabled:cursor-not-allowed disabled:bg-gray-300 sm:h-8 sm:w-8"
                    title="Add to cart"
                    aria-label="Add to cart"
                    @click="addToCart"
                >
                    <Check v-if="added" class="pop-in h-4 w-4 text-gold-300" />
                    <ShoppingBag v-else class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>
