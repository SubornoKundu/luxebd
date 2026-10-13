<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CheckCircle2, MessageCircle, Package, ShoppingBag } from '@lucide/vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import OrderTracker from '@/Components/OrderTracker.vue';
import { formatPrice, paymentMethodLabel } from '@/currency';
import { routes } from '@/routes';

defineProps({
    order: { type: Object, required: true },
});

// Questions about the order go straight to the shop's WhatsApp.
const whatsapp = computed(() => usePage().props.shop?.whatsapp || '');
</script>

<template>
    <Head title="Order placed" />

    <MainLayout>
        <div class="mx-auto max-w-2xl px-4 py-5 sm:px-6 sm:py-8">
            <div class="rounded-3xl border border-green-200 bg-white p-5 text-center shadow-sm sm:p-8">
                <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                    <CheckCircle2 class="h-9 w-9 text-green-600" />
                </span>
                <h1 class="font-display mt-4 text-3xl font-medium text-green-900 sm:text-4xl">Thank you. Your order is placed.</h1>
                <p class="mt-1 text-sm text-gray-500">Your order number is</p>
                <p class="mt-1 font-mono text-base font-bold break-all text-green-700 sm:text-lg">{{ order.order_number }}</p>

                <div class="mt-6 text-left">
                    <OrderTracker :steps="order.tracking_steps" />
                </div>

                <dl class="mt-6 space-y-2 rounded-2xl bg-gray-50 p-4 text-left text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Payment</dt><dd class="font-medium text-gray-800">{{ paymentMethodLabel(order.payment_method) }}</dd></div>
                    <div v-if="order.payer_number" class="flex justify-between gap-3"><dt class="text-gray-500">Sent from bKash number</dt><dd class="font-mono text-gray-800">{{ order.payer_number }}</dd></div>
                    <div v-if="order.transaction_id" class="flex justify-between gap-3"><dt class="text-gray-500">Transaction ID</dt><dd class="font-mono break-all text-gray-800">{{ order.transaction_id }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Deliver to</dt><dd class="min-w-0 text-right break-words text-gray-800">{{ order.shipping_name }}, {{ order.shipping_address }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-gray-200 pt-2 text-base font-bold text-gray-900"><dt>Total</dt><dd>{{ formatPrice(order.total_amount) }}</dd></div>
                    <template v-if="Number(order.advance_amount) > 0">
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Advance by bKash</dt><dd class="font-medium text-gray-800">{{ formatPrice(order.advance_amount) }}</dd></div>
                        <div class="flex justify-between gap-3 font-semibold text-amber-800"><dt>Pay in cash on delivery</dt><dd>{{ formatPrice(order.balance_due) }}</dd></div>
                    </template>
                </dl>
                <p v-if="order.payment_method === 'cod'" class="mt-3 text-xs text-amber-700">Cash on delivery: please keep {{ formatPrice(order.total_amount) }} ready.</p>
                <p v-else-if="Number(order.advance_amount) > 0" class="mt-3 text-xs text-gray-600">We are checking your {{ formatPrice(order.advance_amount) }} bKash advance. Please keep {{ formatPrice(order.balance_due) }} in cash ready for the delivery man.</p>
                <p v-else-if="order.payment_status !== 'paid'" class="mt-3 text-xs text-gray-600">We are checking your bKash payment. We will update this order and email you as soon as it is confirmed.</p>

                <ul class="mt-5 divide-y divide-gray-100 text-left">
                    <li v-for="item in order.items" :key="item.id" class="flex items-center gap-3 py-2.5">
                        <img loading="lazy" decoding="async" :src="item.product_image" :alt="item.product_name" class="h-12 w-12 shrink-0 rounded-lg bg-gray-100 object-cover">
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 text-sm font-medium break-words text-gray-800">{{ item.product_name }}</p>
                            <p class="text-xs text-gray-400"><span v-if="item.variant_label">{{ item.variant_label }} &middot; </span>Qty {{ item.quantity }}</p>
                        </div>
                        <span class="shrink-0 text-sm font-semibold whitespace-nowrap">{{ formatPrice(item.subtotal) }}</span>
                    </li>
                </ul>

                <a v-if="whatsapp" :href="`https://wa.me/${whatsapp}?text=${encodeURIComponent('Hello Vireluxe, my order number is ' + order.order_number)}`" target="_blank" rel="noopener" class="mt-5 flex min-h-11 items-center justify-center gap-2 rounded-full border border-green-300 px-5 py-2.5 text-sm font-semibold text-green-800 hover:bg-green-50">
                    <MessageCircle class="h-4 w-4" /> Questions? Message us on WhatsApp
                </a>

                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <Link :href="routes.orderShow(order.id)" class="flex min-h-11 items-center justify-center gap-2 rounded-full bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700">
                        <Package class="h-4 w-4" /> Track my order
                    </Link>
                    <Link :href="routes.productsIndex()" class="flex min-h-11 items-center justify-center gap-2 rounded-full border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        <ShoppingBag class="h-4 w-4" /> Continue shopping
                    </Link>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
