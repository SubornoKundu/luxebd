<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatPrice } from '@/currency';

const props = defineProps({ customers: Object, filters: Object });
const q = ref(props.filters.q ?? '');

function search() {
    router.get('/admin/customers', q.value ? { q: q.value } : {}, { preserveState: true });
}

const shortDate = (v) => new Date(v).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head title="Customers" />

    <AdminLayout title="Customers">
        <form class="relative mb-4 w-full sm:w-72" @submit.prevent="search">
            <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input v-model="q" type="search" placeholder="Name, email or phone" class="w-full rounded-lg border border-gray-200 bg-white py-2.5 pr-3 pl-9 text-sm focus:border-green-500 focus:outline-none sm:py-2">
        </form>

        <!-- Phones & tablets: cards -->
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:hidden">
            <Link v-for="c in customers.data" :key="c.id" :href="`/admin/customers/${c.id}`" class="block rounded-2xl bg-white p-4 shadow-sm transition hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium break-words text-green-700">{{ c.name }}</p>
                        <p class="text-xs break-all text-gray-400">{{ c.email }}</p>
                    </div>
                    <StatusBadge :value="c.is_blocked ? 'cancelled' : 'active'" class="shrink-0" />
                </div>
                <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                    <div class="min-w-0"><dt class="text-xs text-gray-400">Orders</dt><dd class="font-semibold">{{ c.orders_count }}</dd></div>
                    <div class="min-w-0"><dt class="text-xs text-gray-400">Spent</dt><dd class="truncate font-semibold">{{ formatPrice(c.total_spent ?? 0) }}</dd></div>
                    <div class="min-w-0"><dt class="text-xs text-gray-400">Joined</dt><dd class="truncate text-gray-600">{{ shortDate(c.created_at) }}</dd></div>
                </dl>
                <p class="mt-2 truncate text-xs text-gray-500">{{ c.phone ?? 'No phone' }}</p>
            </Link>
            <p v-if="!customers.data.length" class="rounded-2xl bg-white p-8 text-center text-gray-400 shadow-sm md:col-span-2">No customers found.</p>
        </div>

        <!-- Desktops: table -->
        <div class="hidden overflow-x-auto rounded-2xl bg-white shadow-sm xl:block">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs text-gray-400 uppercase">
                    <tr><th class="px-3 py-3">Customer</th><th class="px-3">Phone</th><th class="px-3">Joined</th><th class="px-3">Orders</th><th class="px-3">Spent</th><th class="px-3">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="c in customers.data" :key="c.id" class="hover:bg-gray-50">
                        <td class="px-3 py-3"><Link :href="`/admin/customers/${c.id}`" class="font-medium text-green-700 hover:underline">{{ c.name }}</Link><p class="text-xs text-gray-400">{{ c.email }}</p></td>
                        <td class="px-3 whitespace-nowrap text-gray-500">{{ c.phone ?? '-' }}</td>
                        <td class="px-3 whitespace-nowrap text-gray-500">{{ shortDate(c.created_at) }}</td>
                        <td class="px-3">{{ c.orders_count }}</td>
                        <td class="px-3 font-semibold whitespace-nowrap">{{ formatPrice(c.total_spent ?? 0) }}</td>
                        <td class="px-3"><StatusBadge :value="c.is_blocked ? 'cancelled' : 'active'" /></td>
                    </tr>
                    <tr v-if="!customers.data.length"><td colspan="6" class="p-8 text-center text-gray-400">No customers found.</td></tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="customers.links" />
    </AdminLayout>
</template>
