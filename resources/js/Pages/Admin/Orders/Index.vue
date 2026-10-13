<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { PackageCheck, Search } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatPrice, paymentMethodLabel } from '@/currency';

const props = defineProps({ orders: Object, filters: Object });
const f = reactive({ ...props.filters });

// Only the payment methods this shop accepts.
const methods = computed(() => usePage().props.paymentMethods ?? ['cod', 'bkash']);

const chips = [
    { label: 'All orders', set: { status: '', payment: '', method: '', check: '' } },
    { label: 'New (pending)', set: { status: 'pending', payment: '', method: '', check: '' } },
    { label: 'Check payment', set: { status: '', payment: '', method: '', check: '1' } },
    { label: 'To ship', set: { status: 'processing', payment: '', method: '', check: '' } },
    { label: 'On the way', set: { status: 'shipped', payment: '', method: '', check: '' } },
];
const chipOn = (c) => Object.keys(c.set).every((k) => (f[k] ?? '') === c.set[k]) && !f.q;
function pick(c) {
    Object.assign(f, c.set, { q: '' });
    apply();
}

function apply() {
    router.get('/admin/orders', Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '')), { preserveState: true });
}

const selected = ref([]);
const allOnPage = computed(() => props.orders.data.length > 0 && props.orders.data.every((o) => selected.value.includes(o.id)));

function toggleAll() {
    selected.value = allOnPage.value ? [] : props.orders.data.map((o) => o.id);
}

function printSlips() {
    window.open(`/admin/orders/print?ids=${selected.value.join(',')}`, '_blank');
}

const select = 'min-w-0 flex-1 basis-[calc(50%-0.25rem)] sm:basis-auto rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-green-500 focus:outline-none sm:flex-none sm:py-2';
const shortDate = (v) => new Date(v).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head title="Orders" />

    <AdminLayout title="Orders">
        <div class="mb-3 flex flex-wrap gap-2" role="group" aria-label="Quick views">
            <button v-for="c in chips" :key="c.label" type="button" class="min-h-9 rounded-full border px-3.5 py-1.5 text-sm font-medium" :class="chipOn(c) ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'" @click="pick(c)">{{ c.label }}</button>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <form class="relative w-full sm:w-64" @submit.prevent="apply">
                <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input v-model="f.q" type="search" placeholder="Order #, name, phone, email" class="w-full rounded-lg border border-gray-200 bg-white py-2.5 pr-3 pl-9 text-sm focus:border-green-500 focus:outline-none sm:py-2">
            </form>
            <select v-model="f.status" :class="select" aria-label="Order status" @change="apply">
                <option value="">All statuses</option><option value="pending">Pending</option><option value="processing">Processing</option>
                <option value="shipped">Shipped</option><option value="completed">Delivered</option><option value="cancelled">Cancelled</option>
            </select>
            <select v-model="f.payment" :class="select" aria-label="Payment status" @change="apply">
                <option value="">Any payment</option><option value="pending">Pending</option><option value="paid">Paid</option><option value="failed">Failed</option><option value="refunded">Refunded</option>
            </select>
            <select v-model="f.method" :class="select" aria-label="Payment method" @change="apply">
                <option value="">Any method</option><option v-for="m in methods" :key="m" :value="m">{{ paymentMethodLabel(m) }}</option>
            </select>
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-3 text-sm">
            <label class="flex min-h-9 items-center gap-2 text-gray-600"><input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-green-600" :checked="allOnPage" @change="toggleAll"> Select all on page</label>
            <button v-if="selected.length" type="button" class="flex min-h-9 items-center gap-1.5 rounded-full bg-gray-900 px-4 py-1.5 font-semibold text-white hover:bg-gray-700" @click="printSlips"><PackageCheck class="h-4 w-4" /> Print {{ selected.length }} packing slip{{ selected.length === 1 ? '' : 's' }}</button>
        </div>

        <!-- Phones & tablets: cards -->
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 2xl:hidden">
            <div v-for="o in orders.data" :key="o.id" class="relative rounded-2xl bg-white shadow-sm transition hover:shadow-md" :class="selected.includes(o.id) ? 'ring-2 ring-green-500' : ''">
            <label class="absolute top-2 right-2 z-10 flex h-9 w-9 items-center justify-center" :aria-label="`Select ${o.order_number}`"><input v-model="selected" type="checkbox" :value="o.id" class="h-4 w-4 rounded border-gray-300 text-green-600"></label>
            <Link :href="`/admin/orders/${o.id}`" class="block p-4 pr-11">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono text-sm font-semibold break-all text-green-700">{{ o.order_number }}</p>
                        <p class="mt-0.5 text-xs text-gray-400">{{ shortDate(o.created_at) }} &middot; {{ o.items_count }} item{{ o.items_count === 1 ? '' : 's' }} &middot; {{ paymentMethodLabel(o.payment_method) }}</p>
                    </div>
                    <span class="shrink-0 font-bold whitespace-nowrap text-gray-900">{{ formatPrice(o.total_amount) }}</span>
                </div>
                <p class="mt-2 truncate text-sm font-medium text-gray-800">{{ o.shipping_name }}</p>
                <p class="truncate text-xs text-gray-400">{{ o.user?.email }}</p>
                <p v-if="Number(o.advance_amount) > 0 && o.balance_due > 0" class="mt-1 text-xs font-medium text-amber-700">Cash due on delivery {{ formatPrice(o.balance_due) }}</p>
                <div class="mt-3 flex flex-wrap gap-2"><StatusBadge :value="o.status" kind="order" /><StatusBadge :value="o.payment_status" kind="payment" /></div>
            </Link>
            </div>
            <p v-if="!orders.data.length" class="rounded-2xl bg-white p-8 text-center text-gray-400 shadow-sm md:col-span-2">No orders match.</p>
        </div>

        <!-- Large desktops: table -->
        <div class="hidden overflow-x-auto rounded-2xl bg-white shadow-sm 2xl:block">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs text-gray-400 uppercase">
                    <tr><th class="w-10 px-3 py-3"><input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-green-600" :checked="allOnPage" aria-label="Select all" @change="toggleAll"></th><th class="px-3">Order</th><th class="px-3">Date</th><th class="px-3">Customer</th><th class="px-3">Items</th><th class="px-3">Method</th><th class="px-3">Status</th><th class="px-3">Payment</th><th class="px-3 text-right">Total</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="o in orders.data" :key="o.id" class="hover:bg-gray-50">
                        <td class="px-3"><input v-model="selected" type="checkbox" :value="o.id" class="h-4 w-4 rounded border-gray-300 text-green-600" :aria-label="`Select ${o.order_number}`"></td>
                        <td class="px-3 py-3 whitespace-nowrap"><Link :href="`/admin/orders/${o.id}`" class="font-mono font-semibold text-green-700 hover:underline">{{ o.order_number }}</Link></td>
                        <td class="px-3 whitespace-nowrap text-gray-500">{{ shortDate(o.created_at) }}</td>
                        <td class="px-3"><p class="font-medium text-gray-800">{{ o.shipping_name }}</p><p class="text-xs text-gray-400">{{ o.user?.email }}</p></td>
                        <td class="px-3 text-gray-500">{{ o.items_count }}</td>
                        <td class="px-3 whitespace-nowrap text-gray-500">{{ paymentMethodLabel(o.payment_method) }}</td>
                        <td class="px-3"><StatusBadge :value="o.status" /></td>
                        <td class="px-3"><StatusBadge :value="o.payment_status" /></td>
                        <td class="px-3 text-right font-semibold whitespace-nowrap">{{ formatPrice(o.total_amount) }}<p v-if="Number(o.advance_amount) > 0 && o.balance_due > 0" class="text-xs font-medium text-amber-700">Cash due {{ formatPrice(o.balance_due) }}</p></td>
                    </tr>
                    <tr v-if="!orders.data.length"><td colspan="9" class="p-8 text-center text-gray-400">No orders match.</td></tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="orders.links" />
    </AdminLayout>
</template>
