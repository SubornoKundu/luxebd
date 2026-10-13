<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Banknote, Package, ShoppingBag, TrendingDown, TrendingUp, Users } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatPrice } from '@/currency';

const props = defineProps({
    stats: Object,
    statusCounts: Object,
    chart: Array,
    daily: Object,
    series: Array,
    categorySales: { type: Array, default: () => [] },
    recentStatus: { type: String, default: 'all' },
    topProducts: Array,
    lowStock: Array,
    recentOrders: Array,
});

// Interactive chart: range (7/14/30/90 days) + metric (sales / orders).
const range = ref(14);
const metric = ref('sales');
const picked = ref(null);

const visible = computed(() => props.series.slice(-range.value));
const values = computed(() => visible.value.map((d) => d[metric.value]));
const maxValue = computed(() => Math.max(1, ...values.value));
const chartTotal = computed(() => values.value.reduce((sum, v) => sum + v, 0));
const fmt = (v) => (metric.value === 'sales' ? formatPrice(v) : `${v} order${v === 1 ? '' : 's'}`);
const shown = computed(() => (picked.value !== null ? visible.value[picked.value] : null));

function setRange(n) { range.value = n; picked.value = null; }

const maxCategory = computed(() => Math.max(1, ...props.categorySales.map((c) => c.revenue)));

const filters = [
    { key: 'all', label: 'All' },
    { key: 'pending', label: 'Pending' },
    { key: 'processing', label: 'Processing' },
    { key: 'shipped', label: 'Shipped' },
];

function filterOrders(key) {
    router.get('/admin', key === 'all' ? {} : { status: key }, {
        only: ['recentOrders', 'recentStatus'], preserveState: true, preserveScroll: true, replace: true,
    });
}

const changeClass = (v) => (v === null || v === undefined ? 'text-gray-400' : v >= 0 ? 'text-green-600' : 'text-rose-600');

const cards = computed(() => [
    { label: 'Total revenue', value: formatPrice(props.stats.revenue), sub: `${formatPrice(props.stats.revenue_today)} today`, icon: Banknote, color: 'bg-green-100 text-green-700' },
    { label: 'Orders', value: props.stats.orders, sub: `${props.stats.pending_orders} pending`, icon: ShoppingBag, color: 'bg-blue-100 text-blue-700' },
    { label: 'Customers', value: props.stats.customers, sub: 'registered', icon: Users, color: 'bg-purple-100 text-purple-700' },
    { label: 'Products', value: props.stats.products, sub: `${props.stats.low_stock} low / out of stock`, icon: Package, color: 'bg-amber-100 text-amber-700' },
]);

const statuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];

</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout title="Dashboard">
        <section class="mb-4 rounded-2xl bg-gray-900 p-4 text-white shadow-sm sm:mb-6 sm:p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-bold">Today's sales summary</h2>
                <span class="text-xs text-gray-400">{{ daily.date }}</span>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="min-w-0">
                    <p class="text-xs text-gray-400">Sales</p>
                    <p class="truncate text-lg font-bold sm:text-xl">{{ formatPrice(daily.today.sales) }}</p>
                    <p class="flex items-center gap-1 text-xs" :class="changeClass(daily.change.sales)">
                        <component :is="(daily.change.sales ?? 0) >= 0 ? TrendingUp : TrendingDown" class="h-3 w-3" />
                        {{ daily.change.sales === null ? 'no data yesterday' : `${daily.change.sales > 0 ? '+' : ''}${daily.change.sales}% vs yesterday` }}
                    </p>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-400">Orders</p>
                    <p class="text-lg font-bold sm:text-xl">{{ daily.today.orders }}</p>
                    <p class="text-xs" :class="changeClass(daily.change.orders)">{{ daily.change.orders === null ? `yesterday ${daily.yesterday.orders}` : `${daily.change.orders > 0 ? '+' : ''}${daily.change.orders}% vs yesterday` }}</p>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-400">Paid / collected</p>
                    <p class="truncate text-lg font-bold sm:text-xl">{{ formatPrice(daily.today.paid) }}</p>
                    <p class="text-xs text-gray-400">{{ daily.today.units }} units sold</p>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-400">Avg. order value</p>
                    <p class="truncate text-lg font-bold sm:text-xl">{{ formatPrice(daily.today.average) }}</p>
                    <p class="text-xs text-gray-400">{{ daily.today.cancelled }} cancelled</p>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-2 2xl:grid-cols-4">
            <div v-for="card in cards" :key="card.label" class="flex min-w-0 items-center gap-3 rounded-2xl bg-white p-4 shadow-sm sm:gap-4 sm:p-5">
                <span class="hidden h-12 w-12 shrink-0 items-center justify-center rounded-xl sm:flex" :class="card.color"><component :is="card.icon" class="h-6 w-6" /></span>
                <div class="min-w-0">
                    <p class="text-sm text-gray-500">{{ card.label }}</p>
                    <p class="truncate text-xl font-bold text-gray-900 sm:text-2xl" :title="String(card.value)">{{ card.value }}</p>
                    <p class="truncate text-xs text-gray-400">{{ card.sub }}</p>
                </div>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:mt-6 sm:gap-6 lg:grid-cols-3">
            <div class="min-w-0 rounded-2xl bg-white p-4 shadow-sm sm:p-5 lg:col-span-2">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-bold text-gray-900">{{ metric === 'sales' ? 'Sales' : 'Orders' }}, last {{ range }} days</h2>
                    <div class="flex flex-wrap gap-2">
                        <div class="flex rounded-full bg-gray-100 p-0.5 text-xs font-medium">
                            <button v-for="m in ['sales', 'orders']" :key="m" type="button" class="min-h-8 rounded-full px-3 capitalize" :class="metric === m ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'" @click="metric = m; picked = null">{{ m }}</button>
                        </div>
                        <div class="flex rounded-full bg-gray-100 p-0.5 text-xs font-medium">
                            <button v-for="n in [7, 14, 30, 90]" :key="n" type="button" class="min-h-8 rounded-full px-2.5" :class="range === n ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'" @click="setRange(n)">{{ n }}d</button>
                        </div>
                    </div>
                </div>
                <p class="mb-2 min-h-5 text-sm text-gray-500">
                    <template v-if="shown"><strong class="text-gray-900">{{ shown.label }}</strong>: {{ fmt(shown[metric]) }} <span class="text-xs">({{ shown.orders }} orders)</span></template>
                    <template v-else>Total: <strong class="text-gray-900">{{ fmt(chartTotal) }}</strong> &middot; tap a bar for details</template>
                </p>
                <div class="flex h-44 items-end gap-px sm:gap-1" @mouseleave="picked = null">
                    <button v-for="(day, index) in visible" :key="day.date" type="button" class="group flex h-full min-w-0 flex-1 flex-col justify-end outline-none" :aria-label="`${day.label}: ${fmt(day[metric])}`" @mouseenter="picked = index" @focus="picked = index" @click="picked = index">
                        <div class="w-full rounded-t transition" :class="picked === index ? 'bg-green-700' : 'bg-green-500 group-hover:bg-green-600'" :style="{ height: Math.max(2, (day[metric] / maxValue) * 100) + '%' }" />
                    </button>
                </div>
                <div class="mt-2 flex justify-between text-[10px] text-gray-400"><span>{{ visible[0]?.label }}</span><span>{{ visible[visible.length - 1]?.label }}</span></div>
            </div>

            <div class="min-w-0 rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                <h2 class="mb-4 font-bold text-gray-900">Orders by status</h2>
                <ul class="space-y-2">
                    <li v-for="s in statuses" :key="s" class="flex items-center justify-between text-sm">
                        <StatusBadge :value="s" /> <span class="font-semibold text-gray-800">{{ statusCounts[s] ?? 0 }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:mt-6 sm:gap-6 lg:grid-cols-3">
            <div class="min-w-0 rounded-2xl bg-white p-4 shadow-sm sm:p-5 lg:col-span-2">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="font-bold text-gray-900">Recent orders</h2>
                    <Link :href="recentStatus === 'all' ? '/admin/orders' : `/admin/orders?status=${recentStatus}`" class="shrink-0 text-sm font-medium text-green-700 hover:underline">View all</Link>
                </div>
                <div class="mb-3 flex flex-wrap gap-1.5">
                    <button v-for="f in filters" :key="f.key" type="button" class="min-h-9 shrink-0 rounded-full border px-3.5 text-xs font-semibold" :class="recentStatus === f.key ? 'border-green-600 bg-green-600 text-white' : 'border-gray-200 text-gray-600 hover:bg-gray-50'" @click="filterOrders(f.key)">{{ f.label }}</button>
                </div>
                <ul class="divide-y divide-gray-100 sm:hidden">
                    <li v-for="order in recentOrders" :key="order.id" class="py-3">
                        <Link :href="`/admin/orders/${order.id}`" class="block">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-sm font-semibold break-all text-green-700">{{ order.order_number }}</p>
                                    <p class="truncate text-sm text-gray-600">{{ order.user?.name ?? order.shipping_name }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold whitespace-nowrap">{{ formatPrice(order.total_amount) }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2"><StatusBadge :value="order.status" kind="order" /><StatusBadge :value="order.payment_status" kind="payment" /></div>
                        </Link>
                    </li>
                    <li v-if="!recentOrders.length" class="py-6 text-center text-sm text-gray-400">No orders found.</li>
                </ul>
                <div class="hidden overflow-x-auto sm:block">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs text-gray-400 uppercase"><tr><th class="py-2 pr-4">Order</th><th class="pr-4">Customer</th><th class="pr-4">Status</th><th class="pr-4">Payment</th><th class="text-right">Total</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="order in recentOrders" :key="order.id">
                                <td class="py-2 pr-4 whitespace-nowrap"><Link :href="`/admin/orders/${order.id}`" class="font-mono font-semibold text-green-700 hover:underline">{{ order.order_number }}</Link></td>
                                <td class="max-w-[10rem] truncate pr-4">{{ order.user?.name ?? order.shipping_name }}</td>
                                <td class="pr-4"><StatusBadge :value="order.status" /></td>
                                <td class="pr-4"><StatusBadge :value="order.payment_status" /></td>
                                <td class="text-right font-semibold whitespace-nowrap">{{ formatPrice(order.total_amount) }}</td>
                            </tr>
                            <tr v-if="!recentOrders.length"><td colspan="5" class="py-6 text-center text-gray-400">No orders found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="min-w-0 space-y-4 sm:space-y-6">
                <div class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="mb-3 font-bold text-gray-900">Sales by category</h2>
                    <ul class="space-y-3 text-sm">
                        <li v-for="c in categorySales" :key="c.name">
                            <div class="flex justify-between gap-3"><span class="truncate text-gray-700">{{ c.name }}</span><span class="shrink-0 font-semibold text-gray-900">{{ formatPrice(c.revenue) }}</span></div>
                            <div class="mt-1 h-1.5 rounded-full bg-gray-100"><div class="h-full rounded-full bg-green-500" :style="{ width: Math.max(3, (c.revenue / maxCategory) * 100) + '%' }" /></div>
                        </li>
                        <li v-if="!categorySales.length" class="text-gray-400">No sales yet.</li>
                    </ul>
                </div>
                <div class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="mb-3 font-bold text-gray-900">Top selling</h2>
                    <ul class="space-y-2 text-sm">
                        <li v-for="p in topProducts" :key="p.product_name" class="flex justify-between gap-3">
                            <span class="truncate text-gray-700">{{ p.product_name }}</span>
                            <span class="shrink-0 font-semibold text-gray-900">{{ p.qty }} sold</span>
                        </li>
                        <li v-if="!topProducts.length" class="text-gray-400">No sales yet.</li>
                    </ul>
                </div>
                <div class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="mb-3 flex items-center gap-2 font-bold text-gray-900"><AlertTriangle class="h-4 w-4 text-amber-500" /> Low stock <Link href="/admin/inventory" class="ml-auto text-sm font-medium text-green-700 hover:underline">Inventory</Link></h2>
                    <ul class="space-y-2 text-sm">
                        <li v-for="p in lowStock" :key="p.id" class="flex justify-between gap-3">
                            <Link :href="`/admin/products/${p.id}/edit`" class="truncate text-gray-700 hover:text-green-700">{{ p.name }}</Link>
                            <span class="shrink-0 font-semibold" :class="p.stock <= 0 ? 'text-rose-600' : 'text-amber-600'">{{ p.stock <= 0 ? 'Out' : p.stock + ' left' }}</span>
                        </li>
                        <li v-if="!lowStock.length" class="text-gray-400">Everything is well stocked.</li>
                    </ul>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
