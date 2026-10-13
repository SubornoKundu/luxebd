<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Download, Pencil, Plus, Search, Trash2, Upload, X } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatPrice } from '@/currency';

const props = defineProps({
    products: Object,
    categories: Array,
    brands: Array,
    filters: Object,
    lowStockThreshold: Number,
});

const f = reactive({ ...props.filters });

function apply() {
    router.get('/admin/products', Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null)), { preserveState: true });
}

const importOpen = ref(false);
const importForm = useForm({ file: null });

function submitImport() {
    importForm.post('/admin/products/import', {
        forceFormData: true,
        onSuccess: () => { importOpen.value = false; importForm.reset(); },
    });
}

function destroy(product) {
    if (window.confirm(`Delete "${product.name}"? This cannot be undone.`)) {
        router.delete(`/admin/products/${product.id}`, { preserveScroll: true });
    }
}

function stockClass(p) {
    if (p.stock <= 0) return 'font-semibold text-rose-600';
    if (p.stock <= props.lowStockThreshold) return 'font-semibold text-amber-600';
    return 'text-gray-700';
}

const select = 'min-w-0 flex-1 basis-[calc(50%-0.25rem)] sm:basis-auto rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-green-500 focus:outline-none sm:flex-none sm:py-2';
</script>

<template>
    <Head title="Products" />

    <AdminLayout title="Products">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <form class="relative w-full sm:w-56" @submit.prevent="apply">
                <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input v-model="f.q" type="search" placeholder="Search products..." class="w-full rounded-lg border border-gray-200 bg-white py-2.5 pr-3 pl-9 text-sm focus:border-green-500 focus:outline-none sm:py-2">
            </form>
            <select v-model="f.category" :class="select" aria-label="Category" @change="apply"><option value="">All categories</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select>
            <select v-model="f.brand" :class="select" aria-label="Brand" @change="apply"><option value="">All brands</option><option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option></select>
            <select v-model="f.status" :class="select" aria-label="Status" @change="apply">
                <option value="">Any status</option><option value="active">Active</option><option value="inactive">Hidden</option>
                <option value="low">Low stock (&le;{{ lowStockThreshold }})</option><option value="out">Out of stock</option>
            </select>
            <div class="flex w-full gap-2 sm:ml-auto sm:w-auto">
                <button type="button" class="flex min-h-10 flex-1 items-center justify-center gap-1.5 rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:flex-none" @click="importOpen = true"><Upload class="h-4 w-4" /> Import CSV</button>
                <Link href="/admin/products/create" class="flex min-h-10 flex-1 items-center justify-center gap-1.5 rounded-full bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 sm:flex-none"><Plus class="h-4 w-4" /> Add product</Link>
            </div>
        </div>

        <div v-if="importOpen" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" @click.self="!importForm.file && !importForm.processing && (importOpen = false)">
            <form class="max-h-dvh w-full max-w-md overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl" @submit.prevent="submitImport">
                <div class="mb-3 flex items-center justify-between"><h2 class="font-bold text-gray-900">Bulk upload products (CSV)</h2><button type="button" class="flex h-9 w-9 items-center justify-center rounded-lg hover:bg-gray-100" aria-label="Close" @click="importOpen = false"><X class="h-5 w-5" /></button></div>
                <p class="mb-3 text-sm text-gray-500">Existing products (matched by name) are updated; new ones are created. Missing categories/brands are created automatically.</p>
                <a href="/admin/products/import-template" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-green-700 hover:underline"><Download class="h-4 w-4" /> Download CSV template</a>
                <input type="file" accept=".csv,text/csv,text/plain" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-full file:border-0 file:bg-green-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-green-700" @change="importForm.file = $event.target.files[0] ?? null">
                <p v-if="importForm.errors.file" class="mt-1 text-xs text-rose-600">{{ importForm.errors.file }}</p>
                <button type="submit" :disabled="importForm.processing || !importForm.file" class="mt-4 w-full rounded-full bg-green-600 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60">{{ importForm.processing ? 'Importing...' : 'Upload & import' }}</button>
            </form>
        </div>

        <!-- Phones & tablets: cards -->
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:hidden">
            <div v-for="p in products.data" :key="p.id" class="flex gap-3 rounded-2xl bg-white p-3 shadow-sm">
                <img loading="lazy" decoding="async" :src="p.image" :alt="p.name" class="h-16 w-16 shrink-0 rounded-lg bg-gray-100 object-cover">
                <div class="min-w-0 flex-1">
                    <p class="line-clamp-2 text-sm font-medium break-words text-gray-800">{{ p.name }}</p>
                    <p class="truncate text-xs text-gray-400">{{ [p.category?.name, p.brand?.name].filter(Boolean).join(' · ') }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <span>
                            <span class="font-semibold">{{ formatPrice(p.discount_price ?? p.price) }}</span>
                            <span v-if="p.discount_price" class="ml-1 text-xs text-gray-400 line-through">{{ formatPrice(p.price) }}</span>
                        </span>
                        <span :class="stockClass(p)">Stock: {{ p.stock }}</span>
                        <StatusBadge :value="p.is_active ? 'active' : 'inactive'" />
                    </div>
                </div>
                <div class="flex shrink-0 flex-col justify-between">
                    <Link :href="`/admin/products/${p.id}/edit`" class="inline-flex rounded-lg p-2.5 text-gray-500 hover:bg-gray-100" title="Edit" aria-label="Edit"><Pencil class="h-4 w-4" /></Link>
                    <button type="button" class="inline-flex rounded-lg p-2.5 text-rose-500 hover:bg-rose-50" title="Delete" aria-label="Delete" @click="destroy(p)"><Trash2 class="h-4 w-4" /></button>
                </div>
            </div>
            <p v-if="!products.data.length" class="rounded-2xl bg-white p-8 text-center text-gray-400 shadow-sm md:col-span-2">No products found.</p>
        </div>

        <!-- Desktops: table -->
        <div class="hidden overflow-x-auto rounded-2xl bg-white shadow-sm xl:block">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs text-gray-400 uppercase">
                    <tr><th class="px-3 py-3">Product</th><th class="px-3">Category</th><th class="px-3">Brand</th><th class="px-3">Price</th><th class="px-3">Stock</th><th class="px-3">Status</th><th class="px-3 text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="p in products.data" :key="p.id">
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <img loading="lazy" decoding="async" :src="p.image" :alt="p.name" class="h-10 w-10 shrink-0 rounded-lg bg-gray-100 object-cover">
                                <span class="max-w-[220px] truncate font-medium text-gray-800" :title="p.name">{{ p.name }}</span>
                            </div>
                        </td>
                        <td class="px-3 text-gray-500">{{ p.category?.name }}</td>
                        <td class="px-3 text-gray-500">{{ p.brand?.name }}</td>
                        <td class="px-3 whitespace-nowrap">
                            <span class="font-semibold">{{ formatPrice(p.discount_price ?? p.price) }}</span>
                            <span v-if="p.discount_price" class="ml-1 text-xs text-gray-400 line-through">{{ formatPrice(p.price) }}</span>
                        </td>
                        <td class="px-3" :class="p.stock <= 0 ? 'font-semibold text-rose-600' : p.stock <= lowStockThreshold ? 'font-semibold text-amber-600' : ''">{{ p.stock }}</td>
                        <td class="px-3"><StatusBadge :value="p.is_active ? 'active' : 'inactive'" /></td>
                        <td class="px-3 text-right whitespace-nowrap">
                            <Link :href="`/admin/products/${p.id}/edit`" class="inline-flex rounded-lg p-2 text-gray-500 hover:bg-gray-100" title="Edit" aria-label="Edit"><Pencil class="h-4 w-4" /></Link>
                            <button type="button" class="inline-flex rounded-lg p-2 text-rose-500 hover:bg-rose-50" title="Delete" aria-label="Delete" @click="destroy(p)"><Trash2 class="h-4 w-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!products.data.length"><td colspan="7" class="p-8 text-center text-gray-400">No products found.</td></tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="products.links" />
    </AdminLayout>
</template>
