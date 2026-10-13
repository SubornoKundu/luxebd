<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2, Flame, Heart, MessageCircleQuestion, Minus, Plus, RefreshCcw, ShoppingCart, Star, Store, Tag, Truck, Zap,
} from '@lucide/vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import RecentlyViewed from '@/Components/RecentlyViewed.vue';
import BrandIcon from '@/Components/BrandIcon.vue';
import ProductGallery from '@/Components/ProductGallery.vue';
import { formatPrice } from '@/currency';
import { routes } from '@/routes';
import { addToCart as addToCartRequest, openCartDrawer } from '@/Composables/cart';
import { rememberProduct } from '@/Composables/recentlyViewed';
import { useWishlist } from '@/Composables/wishlist';

const props = defineProps({
    product: { type: Object, required: true },
    relatedProducts: { type: Array, default: () => [] },
    bundle: { type: Array, default: () => [] },
    questions: { type: Array, default: () => [] },
    shippingInfo: { type: String, default: '' },
    lowStockThreshold: { type: Number, default: 5 },
    reviews: { type: Array, default: () => [] },
    ratingAvg: { type: Number, default: 0 },
    ratingCount: { type: Number, default: 0 },
    canReview: { type: Boolean, default: false },
    myReview: { type: Object, default: null },
});

const page = usePage();
const isAuthed = computed(() => Boolean(page.props.auth?.user));
const shop = computed(() => page.props.shop ?? {});
const advPercent = computed(() => { const p = Number(shop.value.advancePercent || 0); return p > 0 && p < 100 ? p : 0; });

/* ---------- variants ---------- */
const variants = computed(() => props.product.variants ?? []);
const hasVariants = computed(() => variants.value.length > 0);

const ATTRIBUTES = [
    { key: 'type', label: 'Type' },
    { key: 'size', label: 'Size' },
    { key: 'color', label: 'Color' },
];

// Only the attributes that at least one variant actually uses.
const attributes = computed(() => ATTRIBUTES
    .map((a) => ({ ...a, values: [...new Set(variants.value.map((v) => v[a.key]).filter(Boolean))] }))
    .filter((a) => a.values.length > 0));

const selection = reactive({ type: null, size: null, color: null });

function matches(variant, skipKey = null) {
    return attributes.value.every((a) => a.key === skipKey || !selection[a.key] || variant[a.key] === selection[a.key]);
}

// A value is greyed out when no in-stock variant has it together with the other picks.
function isAvailable(attribute, value) {
    return variants.value.some((v) => v[attribute.key] === value && v.stock > 0 && matches(v, attribute.key));
}

// Price shown on a size tile: base (or sale) price plus the cheapest matching variant's extra.
function optionPrice(attribute, value) {
    const extras = variants.value.filter((v) => v[attribute.key] === value).map((v) => Number(v.extra_price ?? 0));
    if (!extras.length) return null;
    const base = props.product.discount_price && Number(props.product.discount_price) < Number(props.product.price)
        ? Number(props.product.discount_price)
        : Number(props.product.price);
    return base + Math.min(...extras);
}

function pick(attribute, value) {
    selection[attribute.key] = selection[attribute.key] === value ? null : value;
}

const selectedVariant = computed(() => {
    if (!hasVariants.value) return null;
    if (!attributes.value.every((a) => selection[a.key])) return null;
    return variants.value.find((v) => attributes.value.every((a) => v[a.key] === selection[a.key])) ?? null;
});

const missingChoices = computed(() => attributes.value.filter((a) => !selection[a.key]).map((a) => a.label.toLowerCase()));

function resetSelection() {
    selection.type = null;
    selection.size = null;
    selection.color = null;
    quantity.value = 1;
    tab.value = 'overview';

    // A product with a single option needs no choosing.
    if (variants.value.length === 1) {
        ATTRIBUTES.forEach((a) => (selection[a.key] = variants.value[0][a.key] ?? null));
    }
}

function isCssColor(name) {
    return typeof CSS !== 'undefined' && CSS.supports('color', String(name).toLowerCase());
}

/* ---------- price & stock ---------- */
const extra = computed(() => Number(selectedVariant.value?.extra_price ?? 0));
const hasDiscount = computed(
    () => props.product.discount_price && Number(props.product.discount_price) < Number(props.product.price),
);
const originalPrice = computed(() => Number(props.product.price) + extra.value);
const finalPrice = computed(() => (hasDiscount.value ? Number(props.product.discount_price) : Number(props.product.price)) + extra.value);
const percentOff = computed(() => (hasDiscount.value ? Math.round((1 - finalPrice.value / originalPrice.value) * 100) : 0));

// null while a size/colour still has to be picked.
const stockNow = computed(() => {
    if (!hasVariants.value) return Number(props.product.stock);
    return selectedVariant.value ? Number(selectedVariant.value.stock) : null;
});
const soldOut = computed(() => (hasVariants.value ? Number(props.product.stock) <= 0 : Number(props.product.stock) <= 0));
const selectedSoldOut = computed(() => stockNow.value !== null && stockNow.value <= 0);
const lowStock = computed(() => stockNow.value !== null && stockNow.value > 0 && stockNow.value <= props.lowStockThreshold);
const canBuy = computed(() => !soldOut.value && stockNow.value !== null && stockNow.value > 0);
const maxQty = computed(() => Math.min(50, stockNow.value ?? 1));

const quantity = ref(1);
watch(maxQty, (max) => {
    if (quantity.value > max) quantity.value = Math.max(1, max);
});

function inc() {
    if (quantity.value < maxQty.value) quantity.value += 1;
}
function dec() {
    if (quantity.value > 1) quantity.value -= 1;
}

/* ---------- gallery ---------- */
const gallery = computed(() => [...new Set([
    props.product.image,
    ...(props.product.images ?? []).map((i) => i.path),
    ...variants.value.map((v) => v.image).filter(Boolean),
].filter(Boolean))]);
const chosenImage = ref(null);
const mainImage = computed(() => chosenImage.value || selectedVariant.value?.image || props.product.image);

watch(selectedVariant, (variant) => {
    if (variant?.image) chosenImage.value = null;
});

/* ---------- actions ---------- */
const needsChoice = computed(() => hasVariants.value && !selectedVariant.value);

const choiceHint = ref(false);
watch(selectedVariant, () => (choiceHint.value = false));

const payload = () => ({
    product_id: props.product.id,
    variant_id: selectedVariant.value?.id,
    quantity: quantity.value,
});

function addToCart() {
    if (needsChoice.value) {
        choiceHint.value = true;
        return;
    }
    addToCartRequest(payload());
}

function orderNow() {
    if (needsChoice.value) {
        choiceHint.value = true;
        return;
    }
    addToCartRequest(payload(), { buyNow: true });
}

const { liked: inWishlist, toggle: toggleWishlist } = useWishlist(() => props.product.id);

/* ---------- bundle ("frequently bought together") ---------- */
const bundleOn = reactive({});
const bundleItems = computed(() => props.bundle.filter((p) => bundleOn[p.id] !== false));
const bundleTotal = computed(() => finalPrice.value
    + bundleItems.value.reduce((sum, p) => sum + Number(p.discount_price && Number(p.discount_price) < Number(p.price) ? p.discount_price : p.price), 0));
const bundleBusy = ref(false);

function addBundle() {
    if (needsChoice.value) {
        choiceHint.value = true;
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    bundleBusy.value = true;
    router.post(
        routes.cartBundle(),
        {
            items: [
                { product_id: props.product.id, variant_id: selectedVariant.value?.id },
                ...bundleItems.value.map((p) => ({ product_id: p.id })),
            ],
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (p) => {
                if (!p.props.flash?.error && p.props.auth?.user) openCartDrawer();
            },
            onFinish: () => (bundleBusy.value = false),
        },
    );
}

/* ---------- tabs ---------- */
const tab = ref('overview');
const tabsEl = ref(null);

const tabs = computed(() => [
    { key: 'overview', label: 'Overview' },
    { key: 'specs', label: 'Specifications' },
    { key: 'shipping', label: 'Shipping Terms' },
    { key: 'reviews', label: `Reviews (${props.ratingCount})` },
    { key: 'qa', label: `Q&A (${props.questions.length})` },
]);

async function openTab(key) {
    tab.value = key;
    await nextTick();
    tabsEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const specs = computed(() => props.product.specifications ?? []);
const shippingLines = computed(() => String(props.shippingInfo || '').split('\n').map((l) => l.trim()).filter(Boolean));

/* ---------- reviews & questions ---------- */
const reviewForm = useForm({
    rating: props.myReview?.rating ?? 5,
    comment: props.myReview?.comment ?? '',
});

function submitReview() {
    reviewForm.post(routes.reviewStore(props.product.slug), { preserveScroll: true });
}

const questionForm = useForm({ question: '' });

function submitQuestion() {
    questionForm.post(routes.questionStore(props.product.slug), {
        preserveScroll: true,
        onSuccess: () => questionForm.reset(),
    });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
}

const whatsappAsk = computed(() => {
    const number = String(shop.value.whatsapp ?? '').replace(/\D/g, '');
    if (!number) return null;
    const text = `Hi, I have a question about "${props.product.name}" (${window.location.origin}${routes.productShow(props.product.slug)})`;
    return `https://wa.me/${number}?text=${encodeURIComponent(text)}`;
});

/* ---------- lifecycle ---------- */
onMounted(() => rememberProduct(props.product));

watch(() => props.product.id, () => {
    chosenImage.value = null;
    choiceHint.value = false;
    resetSelection();
    rememberProduct(props.product);
});

resetSelection();
</script>

<template>
    <Head :title="product.name" />

    <MainLayout>
        <div class="mx-auto max-w-7xl px-4 py-3 pb-28 sm:px-6 sm:py-5 lg:px-8 lg:pb-6">
            <nav class="mb-2 flex flex-wrap items-center gap-x-1 text-sm text-gray-500 sm:mb-3 [&_a]:inline-block [&_a]:py-2" aria-label="Breadcrumb">
                <Link :href="routes.home()" class="hover:text-green-700">Home</Link>
                <template v-if="product.category?.parent">
                    <span>/</span>
                    <Link :href="routes.productsIndex({ category: product.category.parent.slug })" class="hover:text-green-700">{{ product.category.parent.name }}</Link>
                </template>
                <span>/</span>
                <Link :href="routes.productsIndex({ category: product.category?.slug })" class="hover:text-green-700">
                    {{ product.category?.name }}
                </Link>
                <span>/</span>
                <span class="max-w-full truncate text-gray-700">{{ product.name }}</span>
            </nav>

            <div class="grid grid-cols-1 gap-6 sm:gap-10 lg:grid-cols-2">
                <!-- Gallery -->
                <div class="min-w-0 lg:sticky lg:top-40 lg:self-start">
                    <ProductGallery :images="gallery" :model-value="mainImage" :title="product.name" @update:model-value="chosenImage = $event" />
                </div>

                <!-- Details -->
                <div class="enter-stagger min-w-0">
                    <Link
                        v-if="product.brand"
                        :href="routes.productsIndex({ brand: product.brand.slug })"
                        class="inline-flex min-h-9 items-center gap-1 text-xs font-semibold tracking-wide text-green-700 uppercase hover:underline"
                    >
                        <Store class="h-3.5 w-3.5" /> {{ product.brand.name }}
                    </Link>

                    <h1 class="font-display mt-2 text-2xl font-bold break-words text-gray-900 sm:text-4xl">{{ product.name }}</h1>

                    <button type="button" class="mt-1 flex min-h-9 flex-wrap items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700" @click="openTab('reviews')">
                        <span class="flex"><Star v-for="n in 5" :key="n" class="h-4 w-4" :class="n <= Math.round(ratingAvg) ? 'fill-amber-400 text-amber-400' : 'text-gray-300'" /></span>
                        <span v-if="ratingCount"><strong class="text-gray-800">{{ ratingAvg }}</strong> ({{ ratingCount }} review{{ ratingCount === 1 ? '' : 's' }})</span>
                        <span v-else>No reviews yet</span>
                    </button>

                    <!-- Price: sale price with the original struck through -->
                    <div class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span class="font-display text-3xl font-bold text-gray-900 sm:text-4xl">{{ formatPrice(finalPrice) }}</span>
                        <span v-if="hasDiscount" class="text-lg text-gray-400 line-through decoration-rose-500/70 decoration-2">{{ formatPrice(originalPrice) }}</span>
                        <span v-if="hasDiscount" class="inline-flex items-center gap-0.5 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">
                            <Tag class="h-3 w-3" /> Save {{ percentOff }}%
                        </span>
                    </div>
                    <p v-if="hasDiscount" class="mt-1 text-sm text-green-700">You save {{ formatPrice(originalPrice - finalPrice) }}</p>

                    <!-- Stock / urgency -->
                    <div class="mt-4" aria-live="polite">
                        <p v-if="soldOut" class="text-sm font-semibold text-rose-600">Out of stock</p>
                        <p v-else-if="stockNow === null" class="text-sm text-gray-500">Choose {{ missingChoices.join(' & ') }} to see availability.</p>
                        <p v-else-if="selectedSoldOut" class="text-sm font-semibold text-rose-600">This option is sold out - try another one.</p>
                        <div v-else-if="lowStock" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5">
                            <p class="flex items-center gap-1.5 text-sm font-bold text-amber-700">
                                <Flame class="h-4 w-4 animate-pulse" /> Only {{ stockNow }} left in stock!
                            </p>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-amber-100">
                                <div class="h-full rounded-full bg-amber-500" :style="{ width: Math.max(8, (stockNow / (lowStockThreshold + 1)) * 100) + '%' }" />
                            </div>
                        </div>
                        <p v-else class="flex items-center gap-1.5 text-sm font-medium text-green-700"><CheckCircle2 class="h-4 w-4" /> In stock</p>
                    </div>

                    <!-- Variant selector -->
                    <div v-if="hasVariants" class="mt-5 space-y-4">
                        <div v-for="attribute in attributes" :key="attribute.key">
                            <p class="mb-2 text-sm font-semibold text-gray-800">
                                {{ attribute.label }}<span v-if="selection[attribute.key]" class="ml-1 font-normal text-gray-500">: {{ selection[attribute.key] }}</span>
                            </p>
                            <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="attribute.label">
                                <button
                                    v-for="value in attribute.values"
                                    :key="value"
                                    type="button"
                                    role="radio"
                                    :aria-checked="selection[attribute.key] === value"
                                    class="relative flex min-h-10 min-w-11 items-center justify-center gap-1.5 rounded-[3px] border px-3.5 py-2 text-sm font-medium transition duration-300 active:scale-95"
                                    :class="[
                                        attribute.key === 'size' ? 'min-w-[5.5rem] flex-col gap-0 px-4 py-2.5' : '',
                                        selection[attribute.key] === value
                                            ? 'border-gold-500 bg-black text-gold-300 ring-1 ring-gold-500'
                                            : 'border-gray-300 text-gray-700 hover:border-gold-500',
                                        !isAvailable(attribute, value) ? 'text-gray-500' : '',
                                    ]"
                                    @click="pick(attribute, value)"
                                >
                                    <span v-if="attribute.key === 'color' && isCssColor(value)" class="h-3.5 w-3.5 rounded-full border border-gray-300" :style="{ backgroundColor: value.toLowerCase() }" />
                                    <span :class="!isAvailable(attribute, value) ? 'line-through' : ''">{{ value }}</span>
                                    <span v-if="attribute.key === 'size' && optionPrice(attribute, value) !== null" class="text-[11px] font-normal opacity-80">{{ formatPrice(optionPrice(attribute, value)) }}</span>
                                </button>
                            </div>
                        </div>
                        <p v-if="choiceHint && needsChoice" class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700" role="alert">
                            Please select {{ missingChoices.join(' and ') }} first.
                        </p>
                        <p v-if="selectedVariant && Number(selectedVariant.extra_price) > 0" class="text-xs text-gray-500">This option adds {{ formatPrice(selectedVariant.extra_price) }} to the price.</p>
                    </div>

                    <!-- Quantity & buttons -->
                    <div class="mt-6 flex flex-wrap items-center gap-3 sm:mt-7">
                        <div class="flex items-center rounded-full border border-gray-200">
                            <button type="button" class="flex h-11 w-11 items-center justify-center text-gray-600 disabled:opacity-30" aria-label="Decrease quantity" :disabled="quantity <= 1" @click="dec">
                                <Minus class="h-4 w-4" />
                            </button>
                            <span class="w-9 text-center font-semibold tabular-nums">{{ quantity }}</span>
                            <button type="button" class="flex h-11 w-11 items-center justify-center text-gray-600 disabled:opacity-30" aria-label="Increase quantity" :disabled="!canBuy || quantity >= maxQty" @click="inc">
                                <Plus class="h-4 w-4" />
                            </button>
                        </div>

                        <button
                            type="button"
                            :disabled="soldOut || selectedSoldOut"
                            class="flex min-h-12 min-w-[9.5rem] flex-1 items-center justify-center gap-2 rounded-full border-2 border-black px-5 whitespace-nowrap py-3 font-semibold text-black transition duration-300 hover:bg-black hover:text-gold-300 active:scale-[.97] disabled:cursor-not-allowed disabled:border-gray-300 disabled:text-gray-400 disabled:hover:bg-transparent sm:flex-none sm:px-7"
                            @click="addToCart"
                        >
                            <ShoppingCart class="h-4 w-4" /> Add to Cart
                        </button>

                        <button
                            type="button"
                            :disabled="soldOut || selectedSoldOut"
                            class="hidden min-h-12 flex-1 items-center justify-center gap-2 rounded-full bg-gold-500 px-5 py-3 font-semibold text-black transition duration-300 hover:bg-gold-400 active:scale-[.97] disabled:cursor-not-allowed disabled:bg-gray-300 sm:flex sm:flex-none sm:px-7"
                            @click="orderNow"
                        >
                            <Zap class="h-4 w-4" /> Order Now
                        </button>

                        <button
                            type="button"
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border hover:border-rose-300 hover:text-rose-600"
                            :class="inWishlist ? 'border-rose-300 bg-rose-50 text-rose-600' : 'border-gray-200 text-gray-600'"
                            :title="inWishlist ? 'Remove from wishlist' : 'Add to wishlist'"
                            :aria-label="inWishlist ? 'Remove from wishlist' : 'Add to wishlist'"
                            :aria-pressed="inWishlist"
                            @click="toggleWishlist"
                        >
                            <Heart class="h-5 w-5" :fill="inWishlist ? 'currentColor' : 'none'" />
                        </button>
                    </div>

                    <ul class="mt-6 space-y-2.5 rounded-2xl border border-gray-200 bg-white p-4 text-sm text-gray-600">
                        <li class="flex items-start gap-2.5"><Truck class="mt-0.5 h-4 w-4 shrink-0 text-green-600" /> Free delivery on orders over {{ formatPrice(shop.freeShippingOver) }} &middot; {{ advPercent ? `${advPercent}% bKash, ${100 - advPercent}% on delivery` : 'Pay by bKash' }}</li>
                        <li class="flex items-start gap-2.5"><RefreshCcw class="mt-0.5 h-4 w-4 shrink-0 text-green-600" /> 100% original perfume &middot; 7-day replacement if damaged or not as described</li>
                        <li v-if="whatsappAsk" class="flex items-start gap-2.5"><BrandIcon name="whatsapp" class="mt-0.5 h-4 w-4 shrink-0 text-[#25D366]" /> <a :href="whatsappAsk" target="_blank" rel="noopener" class="inline-block py-1.5 font-medium text-green-700 hover:underline">Ask us about this product on WhatsApp</a></li>
                    </ul>
                </div>
            </div>

            <!-- Frequently bought together -->
            <section v-if="bundle.length" class="mt-8 rounded-2xl border border-gray-200 bg-white p-4 sm:mt-10 sm:p-6">
                <h2 class="text-lg font-bold text-gray-900">Frequently bought together</h2>
                <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,1fr)_260px] lg:items-center">
                    <ul class="grid grid-cols-1 gap-3 min-[560px]:grid-cols-3">
                        <li class="flex min-w-0 items-center gap-3 rounded-xl border border-green-200 bg-green-50/50 p-3">
                            <img loading="lazy" decoding="async" :src="mainImage" :alt="product.name" class="h-14 w-14 shrink-0 rounded-lg bg-gray-100 object-cover">
                            <div class="min-w-0">
                                <p class="line-clamp-2 text-sm font-medium break-words text-gray-900">{{ product.name }}</p>
                                <p class="text-sm font-bold text-gray-900">{{ formatPrice(finalPrice) }}</p>
                                <p class="text-[11px] text-green-700">This item</p>
                            </div>
                        </li>
                        <li v-for="item in bundle" :key="item.id" class="min-w-0">
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3" :class="bundleOn[item.id] === false ? 'border-gray-200 opacity-60' : 'border-gray-300'">
                                <input type="checkbox" class="h-4 w-4 shrink-0 rounded border-gray-300 text-green-600" :checked="bundleOn[item.id] !== false" @change="bundleOn[item.id] = $event.target.checked">
                                <img loading="lazy" decoding="async" :src="item.image" :alt="item.name" class="h-14 w-14 shrink-0 rounded-lg bg-gray-100 object-cover">
                                <span class="min-w-0">
                                    <Link :href="routes.productShow(item.slug)" class="line-clamp-2 text-sm font-medium break-words text-gray-900 hover:text-green-700" @click.stop>{{ item.name }}</Link>
                                    <span class="block text-sm font-bold text-gray-900">{{ formatPrice(item.final_price) }}</span>
                                </span>
                            </label>
                        </li>
                    </ul>
                    <div class="rounded-xl bg-gray-50 p-4 text-center lg:text-left">
                        <p class="text-sm text-gray-500">Total for {{ bundleItems.length + 1 }} item{{ bundleItems.length ? 's' : '' }}</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatPrice(bundleTotal) }}</p>
                        <button type="button" :disabled="bundleBusy || soldOut" class="mt-3 w-full rounded-full bg-green-600 px-4 py-3 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60" @click="addBundle">
                            {{ bundleBusy ? 'Adding...' : `Add ${bundleItems.length + 1} item${bundleItems.length ? 's' : ''} to cart` }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- Tabs -->
            <section ref="tabsEl" class="mt-8 scroll-mt-40 sm:mt-10">
                <div class="border-gray-200 pb-1 sm:border-b sm:pb-0" role="tablist">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="t in tabs"
                            :key="t.key"
                            type="button"
                            role="tab"
                            :aria-selected="tab === t.key"
                            class="rounded-full border px-3.5 py-2 text-sm font-semibold whitespace-nowrap transition sm:-mb-px sm:rounded-none sm:border-0 sm:border-b-2 sm:px-5 sm:py-3"
                            :class="tab === t.key ? 'border-black bg-black text-gold-300 sm:border-gold-500 sm:bg-transparent sm:text-black' : 'border-gray-200 text-gray-600 hover:text-gray-900 sm:border-transparent sm:text-gray-500 sm:hover:text-gray-800'"
                            @click="tab = t.key"
                        >
                            {{ t.label }}
                        </button>
                    </div>
                </div>

                <div class="mt-3 rounded-2xl bg-white p-4 sm:mt-0 sm:rounded-t-none sm:rounded-b-2xl sm:p-6" role="tabpanel">
                    <!-- Overview -->
                    <div v-if="tab === 'overview'">
                        <p v-if="product.description" class="leading-relaxed break-words whitespace-pre-line text-gray-600">{{ product.description }}</p>
                        <p v-else class="text-gray-400">No description yet.</p>
                        <dl class="mt-5 grid grid-cols-1 gap-x-8 gap-y-2 text-sm min-[560px]:grid-cols-2">
                            <div v-if="product.brand" class="flex justify-between gap-3 border-b border-gray-100 py-2"><dt class="text-gray-500">Brand</dt><dd class="font-medium text-gray-900">{{ product.brand.name }}</dd></div>
                            <div class="flex justify-between gap-3 border-b border-gray-100 py-2"><dt class="text-gray-500">Category</dt><dd class="font-medium text-gray-900">{{ product.category?.name }}</dd></div>
                            <div class="flex justify-between gap-3 border-b border-gray-100 py-2"><dt class="text-gray-500">Availability</dt><dd class="font-medium" :class="soldOut ? 'text-rose-600' : 'text-green-700'">{{ soldOut ? 'Out of stock' : 'In stock' }}</dd></div>
                            <div v-if="hasVariants" class="flex justify-between gap-3 border-b border-gray-100 py-2"><dt class="text-gray-500">Options</dt><dd class="font-medium text-gray-900">{{ variants.length }} available</dd></div>
                        </dl>
                    </div>

                    <!-- Specifications -->
                    <div v-else-if="tab === 'specs'">
                        <table v-if="specs.length" class="w-full text-left text-sm">
                            <tbody>
                                <tr v-for="(row, i) in specs" :key="i" class="border-b border-gray-100 last:border-0" :class="i % 2 ? '' : 'bg-gray-50/70'">
                                    <th class="w-2/5 px-3 py-2.5 align-top font-medium text-gray-500 sm:w-1/3">{{ row.label }}</th>
                                    <td class="px-3 py-2.5 break-words text-gray-900">{{ row.value }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="text-gray-400">No specifications have been added for this product.</p>
                    </div>

                    <!-- Shipping -->
                    <div v-else-if="tab === 'shipping'">
                        <ul class="space-y-3">
                            <li v-for="(line, i) in shippingLines" :key="i" class="flex items-start gap-2.5 text-sm text-gray-700"><CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-green-600" /> <span class="min-w-0 break-words">{{ line }}</span></li>
                        </ul>
                    </div>

                    <!-- Reviews -->
                    <div v-else-if="tab === 'reviews'">
                        <p class="mb-5 flex flex-wrap items-center gap-1 text-sm text-gray-500">
                            <template v-if="ratingCount">
                                <Star class="h-4 w-4 fill-amber-400 text-amber-400" />
                                <strong class="text-gray-800">{{ ratingAvg }}</strong> out of 5 &middot; {{ ratingCount }} review{{ ratingCount === 1 ? '' : 's' }}
                            </template>
                            <template v-else>No reviews yet.</template>
                        </p>

                        <form v-if="canReview" class="mb-6 space-y-3 rounded-2xl border border-gray-200 p-4 sm:p-5" @submit.prevent="submitReview">
                            <p class="font-semibold text-gray-900">{{ myReview ? 'Update your review' : 'Write a review' }}</p>
                            <div class="flex gap-1">
                                <button v-for="n in 5" :key="n" type="button" class="p-0.5" :aria-label="`${n} star${n === 1 ? '' : 's'}`" @click="reviewForm.rating = n">
                                    <Star class="h-6 w-6" :class="n <= reviewForm.rating ? 'fill-amber-400 text-amber-400' : 'text-gray-300'" />
                                </button>
                            </div>
                            <textarea v-model="reviewForm.comment" rows="3" placeholder="Share your experience (optional)" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:outline-none" />
                            <p v-if="reviewForm.errors.rating || reviewForm.errors.comment" class="text-xs text-rose-600">{{ reviewForm.errors.rating || reviewForm.errors.comment }}</p>
                            <button type="submit" :disabled="reviewForm.processing" class="rounded-full bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60">Submit review</button>
                        </form>
                        <p v-else-if="isAuthed" class="mb-4 text-xs text-gray-400">Only customers who received this product can review it.</p>

                        <ul class="space-y-3">
                            <li v-for="review in reviews" :key="review.id" class="rounded-2xl border border-gray-200 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                    <p class="min-w-0 text-sm font-semibold break-words text-gray-800">{{ review.user?.name }} <span class="ml-1 rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-medium text-green-700">Verified purchase</span></p>
                                    <span class="flex">
                                        <Star v-for="n in 5" :key="n" class="h-4 w-4" :class="n <= review.rating ? 'fill-amber-400 text-amber-400' : 'text-gray-200'" />
                                    </span>
                                </div>
                                <p v-if="review.comment" class="mt-2 text-sm break-words text-gray-600">{{ review.comment }}</p>
                            </li>
                        </ul>
                    </div>

                    <!-- Q&A -->
                    <div v-else-if="tab === 'qa'">
                        <form v-if="isAuthed" class="mb-6 space-y-3 rounded-2xl border border-gray-200 p-4 sm:p-5" @submit.prevent="submitQuestion">
                            <p class="flex items-center gap-2 font-semibold text-gray-900"><MessageCircleQuestion class="h-4 w-4 text-green-600" /> Ask a question about this product</p>
                            <textarea v-model="questionForm.question" rows="2" maxlength="500" placeholder="e.g. Is this the original with warranty?" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:outline-none" />
                            <p v-if="questionForm.errors.question" class="text-xs text-rose-600">{{ questionForm.errors.question }}</p>
                            <button type="submit" :disabled="questionForm.processing || questionForm.question.trim().length < 5" class="rounded-full bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60">Post question</button>
                        </form>
                        <p v-else class="mb-5 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600">
                            <Link :href="routes.login()" class="font-semibold text-green-700 hover:underline">Log in</Link> to ask a question.
                        </p>

                        <ul v-if="questions.length" class="space-y-4">
                            <li v-for="q in questions" :key="q.id" class="rounded-2xl border border-gray-200 p-4">
                                <p class="flex gap-2 text-sm font-semibold break-words text-gray-900"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs">Q</span> <span class="min-w-0">{{ q.question }}</span></p>
                                <p class="mt-1 ml-8 text-xs text-gray-400">{{ q.user?.name }} &middot; {{ formatDate(q.created_at) }}</p>
                                <p v-if="q.answer" class="mt-3 flex gap-2 text-sm break-words text-gray-700"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-100 text-xs font-bold text-green-700">A</span> <span class="min-w-0">{{ q.answer }} <span class="block text-xs text-gray-400">{{ page.props.site?.name || 'Vireluxe' }} &middot; {{ formatDate(q.answered_at) }}</span></span></p>
                                <p v-else class="mt-3 ml-8 text-xs text-gray-400">Waiting for an answer...</p>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-gray-400">No questions yet. Be the first to ask!</p>
                    </div>
                </div>
            </section>

            <section v-if="relatedProducts.length" class="mt-8 sm:mt-10">
                <h2 class="font-display mb-4 text-2xl font-bold text-gray-900">You might also like</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                    <ProductCard v-for="related in relatedProducts" :key="related.id" :product="related" />
                </div>
            </section>

            <RecentlyViewed class="mt-8 sm:mt-10" :exclude-id="product.id" />
        </div>

        <!-- Sticky bottom bar on phones & tablets -->
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-4px_16px_rgba(0,0,0,0.06)] backdrop-blur sm:px-6 lg:hidden">
            <div class="mx-auto flex max-w-7xl items-center gap-2 min-[375px]:gap-3">
                <div class="min-w-0 shrink-0">
                    <p class="text-base min-[375px]:text-lg leading-tight font-bold text-gray-900">{{ formatPrice(finalPrice) }}</p>
                    <p v-if="hasDiscount" class="text-xs leading-tight text-gray-400 line-through">{{ formatPrice(originalPrice) }}</p>
                </div>
                <button type="button" :disabled="soldOut || selectedSoldOut" class="flex min-h-11 min-w-0 flex-1 items-center justify-center gap-1.5 rounded-full border-2 border-black px-2 text-sm font-semibold whitespace-nowrap text-black active:scale-95 disabled:border-gray-300 disabled:text-gray-400" @click="addToCart">
                    <ShoppingCart class="hidden h-4 w-4 shrink-0 min-[375px]:block" /> <span class="truncate">Add to Cart</span>
                </button>
                <button type="button" :disabled="soldOut || selectedSoldOut" class="flex min-h-11 min-w-0 flex-1 items-center justify-center gap-1.5 rounded-full bg-gold-500 px-2 text-sm font-semibold whitespace-nowrap text-black active:scale-95 disabled:bg-gray-300" @click="orderNow">
                    <Zap class="hidden h-4 w-4 shrink-0 min-[375px]:block" /> <span class="truncate">Order Now</span>
                </button>
            </div>
        </div>
    </MainLayout>
</template>
