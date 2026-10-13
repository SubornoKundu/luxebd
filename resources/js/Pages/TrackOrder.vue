<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { PackageSearch, SearchX } from '@lucide/vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import OrderTracker from '@/Components/OrderTracker.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatPrice, paymentMethodLabel } from '@/currency';
import { routes } from '@/routes';

const props = defineProps({
    query: { type: Object, default: () => ({ order: '', phone: '' }) },
    searched: { type: Boolean, default: false },
    result: { type: Object, default: null },
});

const order = ref(props.query.order ?? '');
const phone = ref(props.query.phone ?? '');
const busy = ref(false);

function submit() {
    if (!order.value.trim() || !phone.value.trim()) return;
    router.get(routes.trackOrder({ order: order.value.trim(), phone: phone.value.trim() }), {}, {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
    });
}

const input = 'w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 focus:outline-none';
const fmt = (v) => new Date(v).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <Head title="Track your order" />

    <MainLayout>
        <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6 sm:py-10">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-green-50 text-green-600"><PackageSearch class="h-6 w-6" /></span>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">Track your order</h1>
                    <p class="text-sm text-gray-500">Enter your order number and the phone number you used.</p>
                </div>
            </div>

            <form class="mt-6 space-y-4 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6" @submit.prevent="submit">
                <div>
                    <label for="trackorder-49" class="mb-1 block text-sm font-medium text-gray-700">Order number</label>
                    <input id="trackorder-49" v-model="order" placeholder="ORD-20261003-AB12CD" type="text" autocomplete="off" autocapitalize="characters" required maxlength="40" :class="input">
                </div>
                <div>
                    <label for="trackorder-53" class="mb-1 block text-sm font-medium text-gray-700">Phone number</label>
                    <input id="trackorder-53" v-model="phone" placeholder="01712345678" type="tel" inputmode="tel" autocomplete="tel" required maxlength="20" :class="input">
                </div>
                <button type="submit" :disabled="busy" class="w-full rounded-full bg-green-600 py-2.5 font-semibold text-white hover:bg-green-700 disabled:opacity-60">
                    {{ busy ? 'Checking...' : 'Track order' }}
                </button>
            </form>

            <div v-if="searched && !result" class="mt-6 flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800" role="alert">
                <SearchX class="mt-0.5 h-5 w-5 shrink-0" />
                <p>We could not find an order with that number and phone. Check both and try again. The phone is the one you gave at checkout.</p>
            </div>

            <div v-if="result" class="mt-6 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-bold break-all text-gray-900">{{ result.order_number }}</p>
                        <p class="text-xs text-gray-500">Placed {{ fmt(result.placed_at) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2"><StatusBadge :value="result.status" kind="order" /><StatusBadge :value="result.payment_status" kind="payment" /></div>
                </div>

                <div class="mt-5"><OrderTracker :steps="result.steps" /></div>

                <p v-if="result.courier" class="mt-4 rounded-lg bg-indigo-50 px-3 py-2 text-sm break-words text-indigo-800">
                    With {{ result.courier }}<span v-if="result.tracking_code"> &middot; tracking code <strong>{{ result.tracking_code }}</strong></span>
                </p>

                <ul class="mt-5 divide-y divide-gray-100 border-t border-gray-100 text-sm">
                    <li v-for="(item, i) in result.items" :key="i" class="flex justify-between gap-3 py-2.5">
                        <span class="min-w-0 break-words text-gray-700">{{ item.name }}<span v-if="item.variant" class="text-gray-400"> ({{ item.variant }})</span></span>
                        <span class="shrink-0 text-gray-500">&times; {{ item.quantity }}</span>
                    </li>
                </ul>
                <div class="mt-2 flex justify-between border-t border-gray-100 pt-3 text-sm">
                    <span class="text-gray-500">Total &middot; {{ paymentMethodLabel(result.payment_method) }}</span>
                    <span class="font-bold text-gray-900">{{ formatPrice(result.total_amount) }}</span>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
