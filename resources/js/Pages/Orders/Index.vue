<script setup>
import { Head, Link } from '@inertiajs/vue3';
import OrderTracker from '@/Components/OrderTracker.vue';
import { useLivePoll } from '@/Composables/livePoll';
import { ChevronRight, PackageOpen } from '@lucide/vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatPrice } from '@/currency';
import { routes } from '@/routes';

const props = defineProps({
    orders: { type: Object, required: true },
});

useLivePoll(() => props.orders.data.some((o) => ['pending', 'processing', 'shipped'].includes(o.status)), ['orders']);

</script>

<template>
    <Head title="Order History" />

    <MainLayout>
        <div class="mx-auto max-w-4xl px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
            <h1 class="mb-5 text-xl font-bold text-gray-900 sm:mb-6 sm:text-2xl">Order History</h1>

            <div v-if="!orders.data.length" class="rounded-2xl border border-dashed border-gray-300 py-20 text-center">
                <PackageOpen class="mx-auto h-10 w-10 text-gray-300" />
                <p class="mt-3 text-gray-500">You haven't placed any orders yet.</p>
                <Link :href="routes.productsIndex()" class="mt-3 inline-block rounded-full bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-700">
                    Start Shopping
                </Link>
            </div>

            <ul v-else class="space-y-3">
                <li v-for="order in orders.data" :key="order.id">
                    <Link
                        :href="routes.orderShow(order.id)"
                        class="block rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-green-300 hover:shadow-md sm:p-5"
                    >
                        <div class="flex items-start justify-between gap-3 sm:gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="font-mono text-sm font-semibold break-all text-gray-800">{{ order.order_number }}</p>
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ new Date(order.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) }}
                                    &middot; {{ order.items_count }} item{{ order.items_count === 1 ? '' : 's' }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                                <span class="font-bold whitespace-nowrap text-gray-900">{{ formatPrice(order.total_amount) }}</span>
                                <ChevronRight class="h-5 w-5 text-gray-300" />
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <StatusBadge :value="order.status" kind="order" />
                            <StatusBadge :value="order.payment_status" kind="payment" />
                        </div>
                        <OrderTracker v-if="order.status !== 'cancelled'" :steps="order.tracking_steps" compact class="mt-3 max-w-md" />
                        <p v-if="order.status !== 'cancelled'" class="mt-1.5 text-xs text-gray-500 sm:hidden">{{ [...order.tracking_steps].reverse().find((t) => t.done)?.label }}</p>
                    </Link>
                </li>
            </ul>

            <Pagination :links="orders.links" />
        </div>
    </MainLayout>
</template>
