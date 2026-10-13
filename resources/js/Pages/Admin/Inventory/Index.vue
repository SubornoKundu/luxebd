<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Boxes, Banknote, PackageX, Search } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatPrice } from '@/currency';

const props = defineProps({
    products: Object,
    filters: Object,
    threshold: Number,
    summary: Object,
});

const f = reactive({ ...props.filters });
const qty = reactive({});

function apply(extra = {}) {
    Object.assign(f, extra);
    router.get('/admin/inventory', Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null)), { preserveState: true });
}

function restock(p) {
    const quantity = parseInt(qty[p.id], 10);
    if (!quantity || quantity < 1) return;
    router.post(`/admin/inventory/${p.id}/restock`, { quantity }, { preserveScroll: true, onSuccess: () => { qty[p.id] = ''; } });
}

const cards = [
    { key: 'units', label: 'Units in stock', icon: Boxes, color: 'bg-blue-100 text-blue-700', fmt: (v) => v.toLocaleString('en-US') },
    { key: 'retail_value', label: 'Stock value (retail)', icon: Banknote, color: 'bg-green-100 text-green-700', fmt: formatPrice },
    { key: 'low', label: 'Low stock', icon: AlertTriangle, color: 'bg-amber-100 text-amber-700', fmt: (v) => v },
    { key: 'out', label: 'Out of stock', icon: PackageX, color: 'bg-rose-100 text-rose-700', fmt: (v) => v },
];

const badge = { out: 'bg-rose-100 text-rose-700', low: 'bg-amber-100 text-amber-700', ok: 'bg-green-100 text-green-700' };
const badgeText = { out: 'Out of stock', low: 'Low', ok: 'In stock' };
const hasVariants = (p) => (p.variants?.length ?? 0) > 0;
const daysText = (p) => (p.days_left === null ? 'No recent sales' : p.days_left <= 0 ? 'Out' : `~${p.days_left} days left`);
</script>

<template>
    <Head title="Inventory" />

    <AdminLayout title="Inventory">
        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <div v-for="c in cards" :key="c.key" class="flex min-w-0 items-center gap-3 rounded-2xl bg-white p-4 shadow-sm">
                <span class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-xl sm:flex" :class="c.color"><component :is="c.icon" class="h-5 w-5" /></span>
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 sm:text-sm">{{ c.label }}</p>
                    <p class="truncate text-xl font-bold text-gray-900">{{ c.fmt(summary[c.key]) }}</p>
                </div>
            </div>
        </div>

        <div class="mt-4 mb-4 flex flex-wrap items-center gap-2">
            <div class="flex rounded-full bg-gray-200 p-0.5 text-sm font-medium">
                <button type="button" class="min-h-9 rounded-full px-4" :class="f.view === 'attention' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'" @click="apply({ view: 'attention' })">Needs attention</button>
                <button type="button" class="min-h-9 rounded-full px-4" :class="f.view === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'" @click="apply({ view: 'all' })">All products</button>
            </div>
            <form class="relative w-full sm:ml-auto sm:w-64" @submit.prevent="apply()">
                <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input v-model="f.q" type="search" placeholder="Search products..." class="w-full rounded-lg border border-gray-200 bg-white py-2.5 pr-3 pl-9 text-sm focus:border-green-500 focus:outline-none sm:py-2">
            </form>
        </div>
        <p class="mb-3 text-xs text-gray-500">Alert level: {{ threshold }} units or fewer. Admins get an email when a product crosses it. Suggested restock targets ~30 days of sales.</p>

        <ul class="space-y-3">
            <li v-for="p in products.data" :key="p.id" class="rounded-2xl bg-white p-3 shadow-sm sm:p-4">
                <div class="flex gap-3">
                    <img loading="lazy" decoding="async" :src="p.image" :alt="p.name" class="h-14 w-14 shrink-0 rounded-lg bg-gray-100 object-cover sm:h-16 sm:w-16">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                            <Link :href="`/admin/products/${p.id}/edit`" class="line-clamp-2 min-w-0 text-sm font-semibold break-words text-gray-900 hover:text-green-700">{{ p.name }}</Link>
                            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="badge[p.level]">{{ badgeText[p.level] }}</span>
                        </div>
                        <p class="truncate text-xs text-gray-400">{{ p.category?.name }} &middot; value {{ formatPrice(p.stock_value) }}</p>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-600">
                            <span><strong class="text-sm text-gray-900">{{ p.stock }}</strong> in stock</span>
                            <span>{{ p.sold_30d }} sold / 30d</span>
                            <span :class="p.days_left !== null && p.days_left <= 7 ? 'font-semibold text-rose-600' : ''">{{ daysText(p) }}</span>
                            <span v-if="p.suggested_restock > 0" class="font-semibold text-amber-700">Suggest +{{ p.suggested_restock }}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 border-t border-gray-100 pt-3">
                    <p v-if="hasVariants(p)" class="text-xs text-gray-500">
                        <span v-for="(v, i) in p.variants" :key="v.id" :class="v.stock <= 0 ? 'text-rose-600' : v.stock <= threshold ? 'text-amber-600' : ''">{{ v.label }}: {{ v.stock }}<template v-if="i < p.variants.length - 1"> &middot; </template></span>
                        <Link :href="`/admin/products/${p.id}/edit`" class="ml-2 font-medium text-green-700 hover:underline">Edit variants</Link>
                    </p>
                    <form v-else class="flex items-center gap-2" @submit.prevent="restock(p)">
                        <input v-model="qty[p.id]" type="number" min="1" inputmode="numeric" :placeholder="p.suggested_restock ? String(p.suggested_restock) : 'Qty'" :aria-label="`Restock quantity for ${p.name}`" class="w-24 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:outline-none">
                        <button type="submit" class="min-h-10 rounded-full bg-gray-900 px-4 text-sm font-semibold text-white hover:bg-gray-700">Restock</button>
                        <button v-if="p.suggested_restock > 0" type="button" class="min-h-10 rounded-full border border-gray-300 px-3 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="qty[p.id] = p.suggested_restock">Use +{{ p.suggested_restock }}</button>
                    </form>
                </div>
            </li>
            <li v-if="!products.data.length" class="rounded-2xl bg-white p-8 text-center text-gray-400 shadow-sm">{{ f.view === 'attention' ? 'Nothing needs attention. 🎉' : 'No products found.' }}</li>
        </ul>
        <Pagination :links="products.links" />
    </AdminLayout>
</template>
