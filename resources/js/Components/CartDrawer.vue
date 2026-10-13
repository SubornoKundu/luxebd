<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Minus, Plus, ShoppingBag, Trash2, Truck, X } from '@lucide/vue';
import { formatPrice } from '@/currency';
import { routes } from '@/routes';
import { cartDrawerOpen, closeCartDrawer } from '@/Composables/cart';

const page = usePage();
const items = computed(() => page.props.cartDrawer?.items ?? []);
const subtotal = computed(() => page.props.cartDrawer?.subtotal ?? 0);
const count = computed(() => page.props.cartCount ?? 0);
const isAuthed = computed(() => Boolean(page.props.auth?.user));
const freeOver = computed(() => Number(page.props.shop?.freeShippingOver ?? 0));

const remaining = computed(() => Math.max(0, freeOver.value - subtotal.value));
const progress = computed(() => (freeOver.value > 0 ? Math.min(100, (subtotal.value / freeOver.value) * 100) : 0));

const busy = ref(false);
const closeButton = ref(null);

function change(item, quantity) {
    if (busy.value || quantity < 1 || quantity > item.stock) return;
    busy.value = true;
    router.patch(routes.cartUpdate(item.id), { quantity }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (busy.value = false),
    });
}

function remove(item) {
    if (busy.value) return;
    busy.value = true;
    router.delete(routes.cartDestroy(item.id), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (busy.value = false),
    });
}

function onKey(event) {
    if (event.key === 'Escape') closeCartDrawer();
}

// Lock page scroll behind the drawer, close on Escape, and close after navigating.
watch(cartDrawerOpen, async (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
        window.addEventListener('keydown', onKey);
        await nextTick();
        closeButton.value?.focus();
    } else {
        window.removeEventListener('keydown', onKey);
    }
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKey);
});

// Going to another page (e.g. "Checkout") closes the drawer; updating a quantity does not.
watch(() => page.url, () => closeCartDrawer());
</script>

<template>
    <div class="fixed inset-0 z-[70] overflow-hidden" :class="cartDrawerOpen ? 'visible' : 'invisible'" :aria-hidden="!cartDrawerOpen">
        <!-- Backdrop -->
        <div
            class="absolute inset-0 bg-gray-900/50 transition-opacity duration-300"
            :class="cartDrawerOpen ? 'opacity-100' : 'opacity-0'"
            @click="closeCartDrawer"
        />

        <!-- Panel -->
        <aside
            class="absolute inset-y-0 right-0 flex h-dvh w-full max-w-md flex-col bg-white shadow-2xl transition-transform duration-300 ease-out"
            :class="cartDrawerOpen ? 'translate-x-0' : 'translate-x-full'"
            role="dialog"
            aria-modal="true"
            aria-label="Shopping cart"
        >
            <header class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 sm:px-5">
                <h2 class="flex items-center gap-2 text-lg font-bold text-gray-900">
                    <ShoppingBag class="h-5 w-5 text-green-600" /> Your cart
                    <span v-if="count" class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">{{ count }}</span>
                </h2>
                <button
                    ref="closeButton"
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100"
                    aria-label="Close cart"
                    @click="closeCartDrawer"
                >
                    <X class="h-5 w-5" />
                </button>
            </header>

            <!-- Free delivery progress -->
            <div v-if="items.length && freeOver > 0" class="border-b border-gray-100 bg-green-50/60 px-4 py-3 sm:px-5">
                <p class="flex items-center gap-2 text-sm text-gray-700">
                    <Truck class="h-4 w-4 shrink-0 text-green-600" />
                    <span v-if="remaining > 0">Add <strong>{{ formatPrice(remaining) }}</strong> more for free delivery</span>
                    <span v-else class="font-semibold text-green-700">You've got free delivery!</span>
                </p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-green-100">
                    <div class="h-full rounded-full bg-gold-500 transition-all duration-500" :style="{ width: progress + '%' }" />
                </div>
            </div>

            <!-- Lines -->
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 sm:px-5">
                <ul v-if="items.length" class="divide-y divide-gray-100">
                    <li v-for="item in items" :key="item.id" class="flex gap-3 py-4">
                        <Link :href="routes.productShow(item.slug)" class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-gray-100" @click="closeCartDrawer">
                            <img loading="lazy" decoding="async" :src="item.image" :alt="item.name" class="h-full w-full object-cover">
                        </Link>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <div class="flex items-start justify-between gap-2">
                                <Link :href="routes.productShow(item.slug)" class="line-clamp-2 text-sm font-semibold break-words text-gray-900 hover:text-green-700" @click="closeCartDrawer">{{ item.name }}</Link>
                                <button
                                    type="button"
                                    class="-mt-1 -mr-2 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-400 hover:bg-rose-50 hover:text-rose-600"
                                    :aria-label="`Remove ${item.name}`"
                                    :disabled="busy"
                                    @click="remove(item)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                            <p v-if="item.variant_label" class="mt-0.5 text-xs text-gray-500">{{ item.variant_label }}</p>
                            <p class="mt-1 flex flex-wrap items-baseline gap-x-2 text-sm">
                                <span class="font-semibold text-gray-900">{{ formatPrice(item.unit_price) }}</span>
                                <span v-if="item.original_price" class="text-xs text-gray-400 line-through">{{ formatPrice(item.original_price) }}</span>
                            </p>
                            <div class="mt-auto flex items-center justify-between gap-2 pt-2">
                                <div class="inline-flex items-center rounded-full border border-gray-200">
                                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full text-gray-600 hover:bg-gray-100 disabled:opacity-40" aria-label="Decrease quantity" :disabled="busy || item.quantity <= 1" @click="change(item, item.quantity - 1)">
                                        <Minus class="h-4 w-4" />
                                    </button>
                                    <span class="min-w-8 text-center text-sm font-semibold tabular-nums" aria-live="polite">{{ item.quantity }}</span>
                                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full text-gray-600 hover:bg-gray-100 disabled:opacity-40" aria-label="Increase quantity" :disabled="busy || item.quantity >= item.stock" @click="change(item, item.quantity + 1)">
                                        <Plus class="h-4 w-4" />
                                    </button>
                                </div>
                                <span class="text-sm font-bold whitespace-nowrap text-gray-900">{{ formatPrice(item.line_total) }}</span>
                            </div>
                            <p v-if="item.quantity >= item.stock" class="mt-1 text-xs text-amber-600">Max available: {{ item.stock }}</p>
                        </div>
                    </li>
                </ul>

                <div v-else class="flex h-full flex-col items-center justify-center py-16 text-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-400"><ShoppingBag class="h-8 w-8" /></span>
                    <p class="mt-4 font-semibold text-gray-900">{{ isAuthed ? 'Your cart is empty' : 'Sign in to start shopping' }}</p>
                    <p class="mt-1 max-w-xs text-sm text-gray-500">{{ isAuthed ? 'Add something you like and it will show up here.' : 'Log in to add products to your cart and check out.' }}</p>
                    <Link :href="isAuthed ? routes.productsIndex() : routes.login()" class="mt-5 rounded-full bg-green-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-green-700" @click="closeCartDrawer">
                        {{ isAuthed ? 'Browse products' : 'Log in' }}
                    </Link>
                </div>
            </div>

            <!-- Footer -->
            <footer v-if="items.length" class="space-y-3 border-t border-gray-200 bg-white px-4 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-5">
                <div class="flex items-center justify-between text-base">
                    <span class="font-medium text-gray-600">Subtotal</span>
                    <span class="text-lg font-bold text-gray-900">{{ formatPrice(subtotal) }}</span>
                </div>
                <p class="text-xs text-gray-400">Delivery and coupons are applied at checkout.</p>
                <div class="grid grid-cols-2 gap-2">
                    <Link :href="routes.cartIndex()" class="rounded-full border border-gray-300 px-4 py-3 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="closeCartDrawer">View cart</Link>
                    <Link :href="routes.checkoutIndex()" class="rounded-full bg-green-600 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-green-700" @click="closeCartDrawer">Checkout</Link>
                </div>
            </footer>
        </aside>
    </div>
</template>
