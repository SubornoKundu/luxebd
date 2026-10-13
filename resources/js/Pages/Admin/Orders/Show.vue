<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, MessageCircle, PackageCheck, Phone, Printer, Truck } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatPrice, paymentMethodLabel } from '@/currency';

const props = defineProps({ order: Object, courierLabel: { type: String, default: null } });

const hasAdvance = computed(() => Number(props.order.advance_amount) > 0);
const manualPay = computed(() => ['bkash', 'nagad'].includes(props.order.payment_method));

// One tap to call or WhatsApp the customer. A Bangladeshi number gets 880 in front for WhatsApp.
const waLink = (phone) => {
    const d = String(phone ?? '').replace(/\D/g, '');
    return `https://wa.me/${/^0?1\d{9}$/.test(d) ? '880' + d.replace(/^0/, '') : d}`;
};
const telLink = (phone) => `tel:${String(phone ?? '').replace(/[^\d+]/g, '')}`;

const courierName = ref(props.order.courier ?? '');
const courierCode = ref(props.order.tracking_code ?? '');
const courierSaving = ref(false);
function saveCourier() {
    router.patch(`/admin/orders/${props.order.id}/courier`, { courier: courierName.value, tracking_code: courierCode.value }, {
        preserveScroll: true,
        onStart: () => { courierSaving.value = true; },
        onFinish: () => { courierSaving.value = false; },
    });
}

const labels = { processing: 'Start processing', shipped: 'Mark as shipped', completed: 'Mark as delivered', cancelled: 'Cancel order' };

function setStatus(status) {
    if (status === 'cancelled' && !window.confirm('Cancel this order? Stock will be returned to inventory.')) return;
    router.patch(`/admin/orders/${props.order.id}/status`, { status }, { preserveScroll: true });
}

function setPayment(event) {
    const select = event.target;

    router.patch(`/admin/orders/${props.order.id}/payment`, { payment_status: select.value }, {
        preserveScroll: true,
        // If the server refused the change, snap the dropdown back to the real value.
        onFinish: () => { select.value = props.order.payment_status; },
    });
}

const fmt = (v) => new Date(v).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <Head :title="`Order ${order.order_number}`" />

    <AdminLayout :title="`Order ${order.order_number}`">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/admin/orders" class="flex min-h-10 items-center gap-1 text-sm font-medium text-green-700 hover:underline"><ArrowLeft class="h-4 w-4" /> All orders</Link>
            <div class="flex flex-wrap gap-2">
                <a :href="`/admin/orders/print?ids=${order.id}`" target="_blank" class="flex min-h-10 items-center gap-1.5 rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"><PackageCheck class="h-4 w-4" /> Packing slip</a>
                <a :href="`/orders/${order.id}/invoice`" target="_blank" class="flex min-h-10 items-center gap-1.5 rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"><Printer class="h-4 w-4" /> Invoice</a>
            </div>
        </div>

        <div class="grid gap-4 sm:gap-6 lg:grid-cols-3">
            <div class="min-w-0 space-y-4 sm:space-y-6 lg:col-span-2">
                <div class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="mb-3 font-bold text-gray-900">Items</h2>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="item in order.items" :key="item.id" class="flex items-center gap-3 py-3 sm:gap-4">
                            <img loading="lazy" decoding="async" :src="item.product_image" :alt="item.product_name" class="h-12 w-12 shrink-0 rounded-lg bg-gray-100 object-cover">
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2 text-sm font-semibold break-words text-gray-800">{{ item.product_name }}</p>
                                <p v-if="item.variant_label" class="text-xs text-gray-500">{{ item.variant_label }}</p>
                                <p class="text-xs text-gray-400">{{ item.quantity }} &times; {{ formatPrice(item.price) }}</p>
                            </div>
                            <span class="shrink-0 font-semibold whitespace-nowrap">{{ formatPrice(item.subtotal) }}</span>
                        </li>
                    </ul>
                    <div class="mt-3 space-y-1 border-t border-gray-100 pt-3 text-sm">
                        <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ formatPrice(order.subtotal) }}</span></div>
                        <div v-if="Number(order.discount_amount) > 0" class="flex justify-between text-green-700"><span>Discount ({{ order.coupon_code }})</span><span>-{{ formatPrice(order.discount_amount) }}</span></div>
                        <div class="flex justify-between text-gray-600"><span>Shipping</span><span>{{ formatPrice(order.shipping_fee) }}</span></div>
                        <div class="flex justify-between pt-1 text-base font-bold text-gray-900"><span>Total</span><span>{{ formatPrice(order.total_amount) }}</span></div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="mb-3 font-bold text-gray-900">Shipping</h2>
                    <p class="text-sm break-words text-gray-700">{{ order.shipping_name }} &middot; {{ order.shipping_phone }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <a :href="telLink(order.shipping_phone)" class="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"><Phone class="h-4 w-4" /> Call</a>
                        <a :href="waLink(order.shipping_phone)" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-green-300 px-4 py-2 text-sm font-medium text-green-800 hover:bg-green-50"><MessageCircle class="h-4 w-4" /> WhatsApp</a>
                    </div>
                    <p class="text-sm break-words text-gray-500">{{ order.shipping_address }}<span v-if="order.shipping_city">, {{ order.shipping_city }}</span></p>
                    <p v-if="order.notes" class="mt-2 text-sm break-words text-gray-500 italic">"{{ order.notes }}"</p>
                </div>
            </div>

            <div class="min-w-0 space-y-4 sm:space-y-6">
                <div class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="mb-3 font-bold text-gray-900">Status</h2>
                    <div class="mb-4 flex flex-wrap gap-2"><StatusBadge :value="order.status" kind="order" /><StatusBadge :value="order.payment_status" kind="payment" /></div>
                    <div v-if="order.next_statuses.length" class="space-y-2">
                        <button v-for="s in order.next_statuses" :key="s" type="button" class="w-full rounded-full py-2.5 text-sm font-semibold" :class="s === 'cancelled' ? 'border border-rose-200 text-rose-600 hover:bg-rose-50' : 'bg-green-600 text-white hover:bg-green-700'" @click="setStatus(s)">{{ labels[s] }}</button>
                    </div>
                    <p v-else class="text-sm text-gray-400">This order is {{ order.status === 'completed' ? 'delivered' : order.status }} and can't be changed.</p>
                </div>

                <div v-if="order.status !== 'cancelled'" class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="mb-1 flex items-center gap-2 font-bold text-gray-900"><Truck class="h-4 w-4" /> Courier</h2>
                    <p class="mb-3 text-xs text-gray-500">Type the courier and tracking code after you hand the parcel over. The customer sees it on their order page.</p>
                    <form class="space-y-2" @submit.prevent="saveCourier">
                        <label class="sr-only" for="courier_name">Courier name</label>
                        <input id="courier_name" v-model="courierName" list="couriers" maxlength="60" placeholder="Courier name, e.g. Pathao" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-green-500 focus:outline-none">
                        <datalist id="couriers"><option value="Pathao" /><option value="Steadfast" /><option value="RedX" /><option value="Paperfly" /><option value="eCourier" /><option value="Sundarban" /><option value="SA Paribahan" /></datalist>
                        <label class="sr-only" for="courier_code">Tracking code</label>
                        <input id="courier_code" v-model="courierCode" maxlength="100" placeholder="Tracking code (optional)" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 font-mono text-sm focus:border-green-500 focus:outline-none">
                        <button type="submit" :disabled="courierSaving" class="min-h-10 w-full rounded-full bg-gray-900 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60">{{ courierSaving ? 'Saving...' : 'Save courier' }}</button>
                    </form>
                </div>

                <div class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="mb-3 font-bold text-gray-900">Payment</h2>
                    <p class="text-sm text-gray-600">{{ paymentMethodLabel(order.payment_method) }}</p>
                    <dl v-if="manualPay && (order.transaction_id || order.payer_number || hasAdvance)" class="mt-2 space-y-1.5 rounded-lg bg-gray-50 p-3 text-sm">
                        <div v-if="hasAdvance" class="flex justify-between gap-3"><dt class="text-gray-500">Advance sent by bKash</dt><dd class="font-semibold text-gray-900">{{ formatPrice(order.advance_amount) }}</dd></div>
                        <div v-if="order.payer_number" class="flex justify-between gap-3"><dt class="text-gray-500">Sent from bKash number</dt><dd class="font-mono text-gray-900">{{ order.payer_number }}</dd></div>
                        <div v-if="order.transaction_id" class="flex justify-between gap-3"><dt class="text-gray-500">Transaction ID</dt><dd class="font-mono break-all text-gray-900">{{ order.transaction_id }}</dd></div>
                    </dl>
                    <div v-if="hasAdvance || order.payment_method === 'cod'" class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3">
                        <p class="text-xs font-medium text-amber-800">Delivery man collects in cash</p>
                        <p class="text-xl font-bold text-amber-900">{{ formatPrice(order.balance_due) }}</p>
                        <p v-if="hasAdvance" class="text-xs text-amber-700">Total {{ formatPrice(order.total_amount) }} minus {{ formatPrice(order.advance_amount) }} advance.</p>
                    </div>
                    <p v-if="manualPay && order.payment_status === 'pending'" class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-700">The customer says they sent <template v-if="hasAdvance">{{ formatPrice(order.advance_amount) }}</template><template v-else>the money</template>. Look for this Transaction ID in your {{ paymentMethodLabel(order.payment_method) }} account<template v-if="order.payer_number"> (from {{ order.payer_number }})</template>, then set the status to Paid.</p>
                    <p v-if="hasAdvance && order.payment_status === 'paid' && order.status !== 'completed'" class="mt-2 rounded-lg bg-green-50 p-2 text-xs text-green-700">Advance received. The balance is paid to the delivery man; the order becomes fully paid when you mark it Completed.</p>
                    <label class="mt-2 mb-1 block text-xs font-medium text-gray-500">Payment status</label>
                    <select :value="order.payment_status" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-green-500 focus:outline-none" @change="setPayment">
                        <option value="pending">{{ hasAdvance ? 'Pending (advance not checked)' : 'Pending' }}</option><option value="paid">{{ hasAdvance ? 'Paid (advance received)' : 'Paid' }}</option><option value="failed">Failed</option><option value="refunded">Refunded</option>
                    </select>
                </div>

                <div class="rounded-2xl bg-white p-4 text-sm shadow-sm sm:p-5">
                    <h2 class="mb-3 font-bold text-gray-900">Customer</h2>
                    <Link v-if="order.user" :href="`/admin/customers/${order.user.id}`" class="font-medium text-green-700 hover:underline">{{ order.user.name }}</Link>
                    <p class="break-all text-gray-500">{{ order.user?.email }}</p>
                    <p class="mt-3 text-xs text-gray-400">Placed {{ fmt(order.created_at) }}</p>
                    <p v-if="order.delivered_at" class="text-xs text-gray-400">Delivered {{ fmt(order.delivered_at) }}</p>
                    <p v-if="order.cancelled_at" class="text-xs text-gray-400">Cancelled {{ fmt(order.cancelled_at) }}</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
