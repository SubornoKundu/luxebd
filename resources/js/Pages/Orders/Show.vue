<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Banknote, CheckCircle2, MapPin, Package, Printer, Truck, XCircle } from '@lucide/vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import OrderTracker from '@/Components/OrderTracker.vue';
import { useLivePoll } from '@/Composables/livePoll';
import { formatPrice, paymentMethodLabel } from '@/currency';
import { routes } from '@/routes';

const props = defineProps({
    order: { type: Object, required: true },
    courier: { type: String, default: null },
});

useLivePoll(() => ['pending', 'processing', 'shipped'].includes(props.order.status), ['order', 'courier']);

const cancelled = computed(() => props.order.status === 'cancelled');

function cancelOrder() {
    if (window.confirm('Cancel this order?')) {
        router.post(routes.orderCancel(props.order.id), {}, { preserveScroll: true });
    }
}

function formatDate(value) {
    return new Date(value).toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="`Order ${order.order_number}`" />

    <MainLayout>
        <div class="mx-auto max-w-3xl px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
            <div v-if="Number(order.advance_amount) > 0 && order.status !== 'cancelled' && order.status !== 'completed'" class="mb-4 flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <Banknote class="h-6 w-6 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-800">
                    <p class="font-semibold">{{ formatPrice(order.advance_amount) }} paid by bKash<template v-if="order.payment_status !== 'paid'"> (we are checking it)</template></p>
                    <p>Please keep {{ formatPrice(order.balance_due) }} in cash ready for the delivery man.</p>
                    <p v-if="order.transaction_id" class="mt-1 text-xs text-amber-700">Transaction ID: {{ order.transaction_id }}</p>
                </div>
            </div>
            <div v-else-if="order.payment_status === 'paid' && order.transaction_id" class="mb-4 flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 p-4">
                <CheckCircle2 class="h-6 w-6 shrink-0 text-green-600" />
                <div>
                    <p class="font-semibold text-green-800">Payment confirmed</p>
                    <p class="text-sm text-green-700">Transaction ID: {{ order.transaction_id }}</p>
                </div>
            </div>
            <div v-else-if="order.payment_method === 'cod' && !cancelled && order.payment_status !== 'paid'" class="mb-4 flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <Banknote class="h-6 w-6 shrink-0 text-amber-600" />
                <p class="text-sm font-medium text-amber-800">Cash on delivery -- please keep {{ formatPrice(order.total_amount) }} ready when your order arrives.</p>
            </div>
            <div v-if="cancelled" class="mb-4 flex items-center gap-3 rounded-2xl border border-gray-200 bg-gray-100 p-4">
                <XCircle class="h-6 w-6 shrink-0 text-gray-500" />
                <p class="text-sm font-medium text-gray-700">This order was cancelled<span v-if="order.cancelled_at"> on {{ formatDate(order.cancelled_at) }}</span>.<span v-if="order.payment_status === 'refunded'"> Your payment is marked as refunded.</span></p>
            </div>

            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="font-mono text-lg font-bold break-all text-gray-900 sm:text-xl">{{ order.order_number }}</h1>
                    <p class="text-sm text-gray-500">Placed {{ formatDate(order.created_at) }} &middot; {{ paymentMethodLabel(order.payment_method) }}</p>
                </div>
                <div class="flex gap-2">
                    <StatusBadge :value="order.status" kind="order" />
                    <StatusBadge :value="order.payment_status" kind="payment" />
                </div>
            </div>

            <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-3 sm:p-5">
                <OrderTracker :steps="order.tracking_steps" />
                <p class="mt-3 text-center text-[11px] text-gray-400">Status updates automatically.</p>
            </div>

            <div v-if="order.tracking_code" class="mb-6 flex items-start gap-3 rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
                <Truck class="mt-0.5 h-5 w-5 shrink-0 text-indigo-600" />
                <div class="min-w-0 text-sm text-indigo-900">
                    <p class="font-semibold">Shipped with {{ courier ?? order.courier }}</p>
                    <p class="break-all">Tracking code: <span class="font-mono font-semibold">{{ order.tracking_code }}</span></p>
                </div>
            </div>

            <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
                <h2 class="mb-3 flex items-center gap-2 font-bold text-gray-900">
                    <Package class="h-4 w-4" /> Items
                </h2>
                <ul class="divide-y divide-gray-100">
                    <li v-for="item in order.items" :key="item.id" class="flex items-center gap-3 py-3 sm:gap-4">
                        <img loading="lazy" decoding="async" :src="item.product_image" :alt="item.product_name" class="h-14 w-14 shrink-0 rounded-lg bg-gray-100 object-cover">
                        <div class="min-w-0 flex-1">
                            <Link
                                v-if="item.product && item.product.is_active"
                                :href="routes.productShow(item.product.slug)"
                                class="line-clamp-2 block text-sm font-semibold break-words text-gray-800 hover:text-green-700"
                            >
                                {{ item.product_name }}
                            </Link>
                            <p v-else class="line-clamp-2 text-sm font-semibold break-words text-gray-800">{{ item.product_name }}</p>
                            <p v-if="item.variant_label" class="text-xs text-gray-500">{{ item.variant_label }}</p>
                            <p class="text-xs text-gray-400">Qty {{ item.quantity }} &times; {{ formatPrice(item.price) }}</p>
                            <Link v-if="order.status === 'completed' && item.product && item.product.is_active" :href="routes.productShow(item.product.slug)" class="text-xs font-medium text-green-700 hover:underline">Write a review</Link>
                        </div>
                        <span class="shrink-0 font-semibold whitespace-nowrap text-gray-900">{{ formatPrice(item.subtotal) }}</span>
                    </li>
                </ul>

                <div class="mt-4 space-y-1 border-t border-gray-100 pt-4 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span>{{ formatPrice(order.subtotal) }}</span>
                    </div>
                    <div v-if="Number(order.discount_amount) > 0" class="flex justify-between text-green-700">
                        <span>Discount ({{ order.coupon_code }})</span>
                        <span>-{{ formatPrice(order.discount_amount) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Shipping</span>
                        <span>{{ Number(order.shipping_fee) > 0 ? formatPrice(order.shipping_fee) : 'Free' }}</span>
                    </div>
                    <div class="flex justify-between pt-1 text-base font-bold text-gray-900">
                        <span>Total</span>
                        <span>{{ formatPrice(order.total_amount) }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
                <h2 class="mb-3 flex items-center gap-2 font-bold text-gray-900">
                    <MapPin class="h-4 w-4" /> Shipping Details
                </h2>
                <p class="text-sm break-words text-gray-700">{{ order.shipping_name }} &middot; {{ order.shipping_phone }}</p>
                <p class="text-sm break-words text-gray-500">{{ order.shipping_address }}<span v-if="order.shipping_city">, {{ order.shipping_city }}</span></p>
                <p v-if="order.notes" class="mt-2 text-sm break-words text-gray-500 italic">"{{ order.notes }}"</p>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <Link :href="routes.ordersIndex()" class="inline-block py-2 text-sm font-medium text-green-700 hover:underline">
                    &larr; Back to Order History
                </Link>
                <div class="flex flex-wrap gap-2">
                    <a :href="routes.orderInvoice(order.id)" target="_blank" class="flex items-center gap-1.5 rounded-full border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 sm:py-2">
                        <Printer class="h-4 w-4" /> Invoice
                    </a>
                    <button v-if="order.can_cancel" type="button" class="rounded-full border border-rose-200 px-4 py-2.5 text-sm font-medium text-rose-600 hover:bg-rose-50 sm:py-2" @click="cancelOrder">
                        Cancel order
                    </button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
