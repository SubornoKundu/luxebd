<script setup>
import { computed, ref } from 'vue';
import { useForm, Head, router, usePage } from '@inertiajs/vue3';
import { Copy, MapPin, Tag, X } from '@lucide/vue';
import { formatPrice } from '@/currency';
import { routes } from '@/routes';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    cartItems: { type: Array, required: true },
    totals: { type: Object, required: true },
    coupon: { type: Object, default: null },
    defaults: { type: Object, default: () => ({}) },
    addresses: { type: Array, default: () => [] },
    advance: { type: Object, default: () => ({ percent: 0, amount: 0 }) },
    manualPayments: { type: Object, default: () => ({}) },
    paymentMethods: { type: Array, default: () => ['bkash'] },
});

const page = usePage();
const freeOver = page.props.shop?.freeShippingOver ?? 0;

const form = useForm({
    shipping_name: props.defaults.shipping_name ?? '',
    shipping_phone: props.defaults.shipping_phone ?? '',
    shipping_address: props.defaults.shipping_address ?? '',
    shipping_city: props.defaults.shipping_city ?? '',
    notes: '',
    payment_method: props.paymentMethods[0] ?? 'bkash',
    payer_number: '',
    transaction_id: '',
    save_address: false,
});

const isManual = computed(() => ['bkash', 'nagad'].includes(form.payment_method));
// Part-payment: only bKash orders. payNow is what to send by bKash now, dueLater is paid in cash on delivery.
const hasAdvance = computed(() => form.payment_method === 'bkash' && props.advance.percent > 0 && props.advance.amount > 0);
const payNow = computed(() => (hasAdvance.value ? props.advance.amount : props.totals.total));
const dueLater = computed(() => (hasAdvance.value ? Math.max(0, props.totals.total - props.advance.amount) : 0));
const manual = computed(() => props.manualPayments[form.payment_method] ?? null);

function useAddress(a) {
    form.shipping_name = a.name;
    form.shipping_phone = a.phone;
    form.shipping_address = a.address;
    form.shipping_city = a.city ?? '';
}

// Vireluxe takes manual bKash only. The server decides which methods are open (SHOP_PAYMENT_METHODS).
const methods = [
    { value: 'cod', label: 'Cash on Delivery', hint: 'Pay in cash when your order arrives.' },
    { value: 'bkash', label: 'bKash', hint: props.advance.percent > 0 ? `Pay ${props.advance.percent}% now by bKash, the rest in cash on delivery.` : 'Send money, then enter the Transaction ID.' },
    { value: 'nagad', label: 'Nagad', hint: 'Send money, then enter the Transaction ID.' },
].filter((m) => props.paymentMethods.includes(m.value));

const copied = ref(false);
async function copyNumber() {
    try {
        await navigator.clipboard.writeText(String(manual.value?.number ?? '').replace(/\D/g, ''));
        copied.value = true;
        window.setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard blocked: the number is on screen, the customer can type it.
    }
}

const copiedAmount = ref(false);
async function copyAmount() {
    try {
        await navigator.clipboard.writeText(String(Math.round(Number(payNow.value))));
        copiedAmount.value = true;
        window.setTimeout(() => (copiedAmount.value = false), 2000);
    } catch {
        // Clipboard blocked: the amount is on screen.
    }
}

const couponCode = ref('');
const couponBusy = ref(false);

function submit() {
    form.post(routes.checkoutStore());
}

function applyCoupon() {
    if (!couponCode.value.trim()) return;
    router.post(routes.couponApply(), { code: couponCode.value }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (couponBusy.value = true),
        // Keep the typed code when it was rejected so it can be corrected.
        onSuccess: (page) => { if (!page.props.flash?.error) couponCode.value = ''; },
        onFinish: () => { couponBusy.value = false; },
    });
}

function removeCoupon() {
    router.delete(routes.couponRemove(), { preserveScroll: true, preserveState: true });
}

const input = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 focus:outline-none';
</script>

<template>
    <Head title="Checkout" />

    <MainLayout>
        <div class="mx-auto max-w-5xl px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
            <h1 class="font-display mb-5 text-4xl font-medium text-green-900 sm:mb-6">Checkout</h1>

            <div class="grid grid-cols-1 gap-6 sm:gap-8 lg:grid-cols-3">
                <form class="min-w-0 space-y-4 sm:space-y-5 lg:col-span-2" @submit.prevent="submit">
                    <div class="border border-gray-200 bg-white p-4 sm:p-6">
                        <h2 class="font-display mb-4 text-2xl font-medium text-green-900">Delivery details</h2>

                        <div v-if="addresses.length" class="mb-4 flex flex-wrap gap-2">
                            <button v-for="a in addresses" :key="a.id" type="button" class="flex min-h-10 max-w-full min-w-0 items-center gap-1.5 rounded-full border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-green-500 hover:text-green-700" @click="useAddress(a)">
                                <MapPin class="h-3.5 w-3.5 shrink-0" /> <span class="shrink-0">{{ a.label }} &middot;</span> <span class="min-w-0 truncate">{{ a.address }}</span>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="index-108" class="mb-1 block text-sm font-medium text-gray-700">Full Name</label>
                                <input id="index-108" v-model="form.shipping_name" placeholder="Your full name" type="text" autocomplete="name" :class="input">
                                <p v-if="form.errors.shipping_name" class="mt-1 text-xs text-rose-600">{{ form.errors.shipping_name }}</p>
                            </div>
                            <div>
                                <label for="index-113" class="mb-1 block text-sm font-medium text-gray-700">Phone Number</label>
                                <input id="index-113" v-model="form.shipping_phone" placeholder="01712345678" type="tel" inputmode="tel" autocomplete="tel" :class="input">
                                <p v-if="form.errors.shipping_phone" class="mt-1 text-xs text-rose-600">{{ form.errors.shipping_phone }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="index-118" class="mb-1 block text-sm font-medium text-gray-700">Street Address</label>
                                <input id="index-118" v-model="form.shipping_address" placeholder="House, road, area" type="text" autocomplete="street-address" :class="input">
                                <p v-if="form.errors.shipping_address" class="mt-1 text-xs text-rose-600">{{ form.errors.shipping_address }}</p>
                            </div>
                            <div>
                                <label for="index-123" class="mb-1 block text-sm font-medium text-gray-700">City (optional)</label>
                                <input id="index-123" v-model="form.shipping_city" placeholder="Dhaka" type="text" autocomplete="address-level2" :class="input">
                                <p v-if="form.errors.shipping_city" class="mt-1 text-xs text-rose-600">{{ form.errors.shipping_city }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="index-128" class="mb-1 block text-sm font-medium text-gray-700">Order Notes (optional)</label>
                                <textarea id="index-128" v-model="form.notes" placeholder="Call before delivery, nearest landmark" rows="2" :class="input" />
                                <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-600">{{ form.errors.notes }}</p>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-600 sm:col-span-2">
                                <input v-model="form.save_address" type="checkbox" class="rounded border-gray-300 text-green-600"> Save this address for next time
                            </label>
                        </div>
                    </div>

                    <div class="border border-gray-200 bg-white p-4 sm:p-6">
                        <h2 class="font-display mb-1 text-2xl font-medium text-green-900">Pay by bKash</h2>
                        <p v-if="advance.percent > 0 && advance.percent < 100 && methods.length === 1" class="mb-4 text-sm text-gray-500">Pay {{ advance.percent }}% now by bKash and the other {{ 100 - advance.percent }}% in cash to the delivery man when your order arrives.</p>
                        <p v-else class="mb-4 text-sm text-gray-500">We take payment by bKash only. Your order is held until we confirm it.</p>

                        <div v-if="methods.length > 1" class="mb-4 grid gap-3 sm:grid-cols-2">
                            <label v-for="m in methods" :key="m.value" class="flex min-h-14 cursor-pointer items-start gap-3 border p-3 transition sm:p-4" :class="form.payment_method === m.value ? 'border-green-600 bg-green-50' : 'border-gray-200 hover:border-gray-300'">
                                <input v-model="form.payment_method" type="radio" :value="m.value" class="mt-1 text-green-600">
                                <span class="min-w-0">
                                    <span class="block font-semibold text-gray-900">{{ m.label }}</span>
                                    <span class="block text-xs text-gray-500">{{ m.hint }}</span>
                                </span>
                            </label>
                        </div>

                        <div v-if="isManual && manual" class="space-y-4">
                            <!-- The sums are done for the customer: how much now, how much later. -->
                            <div v-if="hasAdvance" class="grid grid-cols-3 gap-px overflow-hidden border border-gray-200 bg-gray-200 text-center">
                                <div class="bg-white px-2 py-3">
                                    <p class="text-xs text-gray-500">Your total</p>
                                    <p class="mt-0.5 text-sm font-semibold text-gray-900 sm:text-base">{{ formatPrice(totals.total) }}</p>
                                </div>
                                <div class="bg-gold-300/20 px-2 py-3 ring-2 ring-inset ring-gold-400">
                                    <p class="text-xs font-semibold text-green-900">Pay now (bKash)</p>
                                    <p class="mt-0.5 text-lg font-bold text-green-900 sm:text-xl">{{ formatPrice(payNow) }}</p>
                                </div>
                                <div class="bg-white px-2 py-3">
                                    <p class="text-xs text-gray-500">Pay on delivery (cash)</p>
                                    <p class="mt-0.5 text-sm font-semibold text-gray-900 sm:text-base">{{ formatPrice(dueLater) }}</p>
                                </div>
                            </div>

                            <div class="border border-gold-400/60 bg-gold-300/10 p-4 text-sm text-gray-800 sm:p-5">
                                <p class="mb-3 font-semibold text-green-900">Step 1. Send {{ formatPrice(payNow) }} on bKash</p>
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="w-full text-xs text-gray-500 sm:w-24">Send Money to</span>
                                        <span class="bg-white px-3 py-2 font-mono text-lg font-bold tracking-wider break-all text-green-900 ring-1 ring-gray-200">{{ manual.number }}</span>
                                        <button type="button" class="inline-flex min-h-10 items-center gap-1.5 border border-green-600 px-3 text-sm font-semibold text-green-700 hover:bg-green-50" @click="copyNumber">
                                            <Copy class="h-4 w-4" /> {{ copied ? 'Copied' : 'Copy' }}
                                        </button>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="w-full text-xs text-gray-500 sm:w-24">Amount</span>
                                        <span class="bg-white px-3 py-2 font-mono text-lg font-bold text-green-900 ring-1 ring-gray-200">{{ Math.round(Number(payNow)) }}</span>
                                        <button type="button" class="inline-flex min-h-10 items-center gap-1.5 border border-green-600 px-3 text-sm font-semibold text-green-700 hover:bg-green-50" @click="copyAmount">
                                            <Copy class="h-4 w-4" /> {{ copiedAmount ? 'Copied' : 'Copy' }}
                                        </button>
                                    </div>
                                </div>
                                <p v-if="manual.type" class="mt-2 text-xs text-gray-500">This is a bKash {{ manual.type }} number. Use <strong>Send Money</strong>.</p>

                                <p class="mt-5 mb-3 font-semibold text-green-900">Step 2. Tell us about your payment</p>
                                <div class="space-y-3">
                                    <div v-if="form.payment_method === 'bkash'">
                                        <label for="payer_number" class="block text-xs font-medium text-gray-600">The bKash number you sent from</label>
                                        <input id="payer_number" v-model="form.payer_number" placeholder="01XXXXXXXXX" type="tel" inputmode="tel" autocomplete="tel" :class="[input, 'mt-1']">
                                        <p v-if="form.errors.payer_number" class="mt-1 text-xs text-rose-600">{{ form.errors.payer_number }}</p>
                                    </div>
                                    <div>
                                        <label for="transaction_id" class="block text-xs font-medium text-gray-600">Transaction ID (it is in the bKash SMS, for example 8N7A6D5E4C)</label>
                                        <input id="transaction_id" v-model="form.transaction_id" placeholder="Transaction ID" type="text" autocomplete="off" autocapitalize="characters" :class="[input, 'mt-1 uppercase']">
                                        <p v-if="form.errors.transaction_id" class="mt-1 text-xs text-rose-600">{{ form.errors.transaction_id }}</p>
                                    </div>
                                </div>

                                <p class="mt-4 text-xs text-gray-600">Then press <strong>Confirm order</strong>. We check your payment and message you.<template v-if="hasAdvance"> The other {{ formatPrice(dueLater) }} is paid in cash to the delivery man.</template></p>
                            </div>
                        </div>
                        <p v-if="form.errors.payment_method" class="mt-2 text-xs text-rose-600">{{ form.errors.payment_method }}</p>
                    </div>

                    <div class="sticky bottom-0 z-20 -mx-4 border-t border-gray-200 bg-white/95 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-full bg-green-600 py-3 font-semibold text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ form.processing ? 'Placing order...' : (hasAdvance ? 'Confirm order' : `Confirm order - ${formatPrice(totals.total)}`) }}
                    </button>
                    </div>
                </form>

                <div class="h-fit border border-gray-200 bg-white p-4 sm:p-6 lg:sticky lg:top-24">
                    <h2 class="font-display mb-4 text-2xl font-medium text-green-900">Order summary</h2>
                    <ul class="mb-4 space-y-3 lg:max-h-64 lg:overflow-y-auto">
                        <li v-for="item in cartItems" :key="item.id" class="flex items-center gap-3">
                            <img loading="lazy" decoding="async" :src="item.product.image" :alt="item.product.name" class="h-12 w-12 shrink-0 rounded-lg bg-gray-100 object-cover">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-800">{{ item.product.name }}</p>
                                <p class="truncate text-xs text-gray-400"><span v-if="item.variant_label">{{ item.variant_label }} &middot; </span>Qty {{ item.quantity }}</p>
                            </div>
                            <span class="text-sm font-semibold whitespace-nowrap text-gray-800">{{ formatPrice(item.line_total) }}</span>
                        </li>
                    </ul>

                    <div class="mb-4 border-t border-gray-100 pt-4">
                        <div v-if="coupon" class="flex items-center justify-between gap-2 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800">
                            <span class="flex min-w-0 items-center gap-1.5 break-words"><Tag class="h-4 w-4" /> <strong>{{ coupon.code }}</strong> &middot; {{ coupon.label }}</span>
                            <button type="button" class="-m-2 flex h-10 w-10 shrink-0 items-center justify-center text-green-700 hover:text-green-900" aria-label="Remove coupon" title="Remove coupon" @click="removeCoupon"><X class="h-4 w-4" /></button>
                        </div>
                        <form v-else class="flex gap-2" @submit.prevent="applyCoupon">
                            <input v-model="couponCode" placeholder="Coupon code" type="text" class="w-full min-w-0 rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase focus:border-green-500 focus:outline-none">
                            <button type="submit" :disabled="couponBusy" class="shrink-0 bg-green-900 px-4 text-sm font-semibold text-white disabled:opacity-60">Apply</button>
                        </form>
                    </div>

                    <div class="space-y-1 border-t border-gray-100 pt-4 text-sm">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span>{{ formatPrice(totals.subtotal) }}</span>
                        </div>
                        <div v-if="totals.discount > 0" class="flex justify-between text-green-700">
                            <span>Discount</span>
                            <span>-{{ formatPrice(totals.discount) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Shipping</span>
                            <span>{{ totals.shipping > 0 ? formatPrice(totals.shipping) : 'Free' }}</span>
                        </div>
                        <p v-if="totals.shipping > 0 && freeOver > 0" class="text-xs text-gray-400">Free shipping on orders over {{ formatPrice(freeOver) }}.</p>
                        <div class="flex justify-between pt-2 text-base font-bold text-gray-900">
                            <span>Total</span>
                            <span>{{ formatPrice(totals.total) }}</span>
                        </div>
                        <template v-if="hasAdvance">
                            <div class="flex justify-between text-green-800"><span>Pay now by bKash ({{ advance.percent }}%)</span><span class="font-semibold">{{ formatPrice(payNow) }}</span></div>
                            <div class="flex justify-between text-gray-700"><span>Cash to delivery man</span><span class="font-semibold">{{ formatPrice(dueLater) }}</span></div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
