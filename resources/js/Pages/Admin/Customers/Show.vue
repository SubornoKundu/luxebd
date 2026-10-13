<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatPrice } from '@/currency';

const props = defineProps({ customer: Object, orders: Array, stats: Object });

function toggle() {
    const msg = props.customer.is_blocked ? 'Allow this customer to sign in again?' : 'Suspend this customer? They will be signed out and cannot log in.';
    if (window.confirm(msg)) router.patch(`/admin/customers/${props.customer.id}/block`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="customer.name" />

    <AdminLayout :title="customer.name">
        <Link href="/admin/customers" class="mb-4 inline-flex min-h-10 items-center gap-1 text-sm font-medium text-green-700 hover:underline"><ArrowLeft class="h-4 w-4" /> All customers</Link>

        <div class="grid gap-4 sm:gap-6 lg:grid-cols-3">
            <div class="h-fit min-w-0 space-y-3 rounded-2xl bg-white p-4 text-sm shadow-sm sm:p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="min-w-0 text-lg font-bold break-words text-gray-900">{{ customer.name }}</h2>
                    <StatusBadge :value="customer.is_blocked ? 'cancelled' : 'active'" />
                </div>
                <p class="break-all text-gray-500">{{ customer.email }}</p>
                <p class="text-gray-500">{{ customer.phone ?? 'No phone' }}</p>
                <p class="break-words text-gray-500">{{ [customer.address, customer.city].filter(Boolean).join(', ') || 'No address saved' }}</p>
                <p class="text-xs text-gray-400">Joined {{ new Date(customer.created_at).toLocaleDateString() }}</p>
                <div class="grid grid-cols-2 gap-3 border-t border-gray-100 pt-3">
                    <div><p class="text-xs text-gray-400">Orders</p><p class="text-xl font-bold">{{ stats.orders }}</p></div>
                    <div class="min-w-0"><p class="text-xs text-gray-400">Total spent</p><p class="truncate text-xl font-bold">{{ formatPrice(stats.spent) }}</p></div>
                </div>
                <button type="button" class="w-full rounded-full py-2.5 text-sm font-semibold" :class="customer.is_blocked ? 'bg-green-600 text-white hover:bg-green-700' : 'border border-rose-200 text-rose-600 hover:bg-rose-50'" @click="toggle">
                    {{ customer.is_blocked ? 'Re-activate account' : 'Suspend account' }}
                </button>
            </div>

            <div class="min-w-0 rounded-2xl bg-white p-4 shadow-sm sm:p-5 lg:col-span-2">
                <h2 class="mb-3 font-bold text-gray-900">Recent orders</h2>
                <ul class="divide-y divide-gray-100 sm:hidden">
                    <li v-for="o in orders" :key="o.id" class="py-3">
                        <Link :href="`/admin/orders/${o.id}`" class="block">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-sm font-semibold break-all text-green-700">{{ o.order_number }}</p>
                                    <p class="text-xs text-gray-400">{{ new Date(o.created_at).toLocaleDateString() }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold whitespace-nowrap">{{ formatPrice(o.total_amount) }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2"><StatusBadge :value="o.status" kind="order" /><StatusBadge :value="o.payment_status" kind="payment" /></div>
                        </Link>
                    </li>
                    <li v-if="!orders.length" class="py-6 text-center text-sm text-gray-400">No orders yet.</li>
                </ul>
                <div class="hidden overflow-x-auto sm:block">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs text-gray-400 uppercase"><tr><th class="py-2 pr-4">Order</th><th class="pr-4">Date</th><th class="pr-4">Status</th><th class="pr-4">Payment</th><th class="text-right">Total</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="o in orders" :key="o.id">
                                <td class="py-2 pr-4 whitespace-nowrap"><Link :href="`/admin/orders/${o.id}`" class="font-mono font-semibold text-green-700 hover:underline">{{ o.order_number }}</Link></td>
                                <td class="pr-4 whitespace-nowrap text-gray-500">{{ new Date(o.created_at).toLocaleDateString() }}</td>
                                <td class="pr-4"><StatusBadge :value="o.status" /></td>
                                <td class="pr-4"><StatusBadge :value="o.payment_status" /></td>
                                <td class="text-right font-semibold whitespace-nowrap">{{ formatPrice(o.total_amount) }}</td>
                            </tr>
                            <tr v-if="!orders.length"><td colspan="5" class="py-6 text-center text-gray-400">No orders yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
