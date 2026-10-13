<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, RefreshCcw, Send, ShieldCheck, Smartphone, SprayCan, Truck } from '@lucide/vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import RecentlyViewed from '@/Components/RecentlyViewed.vue';
import BrandLogo from '@/Components/BrandLogo.vue';
import LocationMap from '@/Components/LocationMap.vue';
import { formatPrice } from '@/currency';
import { routes } from '@/routes';
import { srcset } from '@/thumb';

const doors = [
    { slug: 'for-her', label: 'Her' },
    { slug: 'for-him', label: 'Him' },
    { slug: 'unisex', label: 'Everyone' },
];

const props = defineProps({
    sliders: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    latestProducts: { type: Array, default: () => [] },
    popularProducts: { type: Array, default: () => [] },
    featuredProducts: { type: Array, default: () => [] },
    genderSections: { type: Array, default: () => [] },
    premiumFrom: { type: Number, default: 0 },
    premiumProducts: { type: Array, default: () => [] },
    affordableProducts: { type: Array, default: () => [] },
});

const SLIDE_MS = 6500;
const activeSlide = ref(0);

// Only the first banner picture loads at once; the others wait until the page has finished loading,
// so they never compete with it for the connection (this is what keeps phones fast).
const restReady = ref(false);
onMounted(() => {
    const go = () => window.setTimeout(() => { restReady.value = true; }, 300);
    if (document.readyState === 'complete') go();
    else window.addEventListener('load', go, { once: true });
});

// On computers the banner box takes the shape of the picture showing, so the whole picture is always
// visible (wide 3:1 banners, or an older 16:9 one). Phones keep the fixed 16:9 box.
const ratios = ref({});
const heroRatio = computed(() => {
    const r = ratios.value[props.sliders[activeSlide.value]?.id];
    return r ? Math.min(3.2, Math.max(1.6, r)) : 3;
});

function noteRatio(slide, event) {
    const img = event.target;
    if (img.naturalWidth && img.naturalHeight) {
        ratios.value = { ...ratios.value, [slide.id]: img.naturalWidth / img.naturalHeight };
    }
}
let timer = null;

function goTo(index) {
    activeSlide.value = (index + props.sliders.length) % props.sliders.length;
}

function next() {
    goTo(activeSlide.value + 1);
}

function prev() {
    goTo(activeSlide.value - 1);
}

function resetTimer() {
    window.clearInterval(timer);
    if (props.sliders.length > 1) {
        timer = window.setInterval(next, SLIDE_MS);
    }
}

onMounted(resetTimer);

// Brands: one swipeable row with arrows, so 5 brands or 50 brands look the same.
const brandRail = ref(null);
const railStart = ref(true);
const railEnd = ref(true);

function updateRail() {
    const el = brandRail.value;
    if (!el) return;
    railStart.value = el.scrollLeft <= 4;
    railEnd.value = el.scrollLeft + el.clientWidth >= el.scrollWidth - 4;
}

function scrollRail(direction) {
    const el = brandRail.value;
    if (el) el.scrollBy({ left: direction * Math.max(200, el.clientWidth * 0.8), behavior: 'smooth' });
}

onMounted(() => {
    updateRail();
    window.addEventListener('resize', updateRail);
});
onBeforeUnmount(() => window.removeEventListener('resize', updateRail));
onBeforeUnmount(() => window.clearInterval(timer));

function onNavClick(fn) {
    fn();
    resetTimer();
}

const shopName = computed(() => usePage().props.site?.name || 'Vireluxe');
const adv = computed(() => { const p = Number(usePage().props.shop?.advancePercent || 0); return p > 0 && p < 100 ? p : 0; });
const bkashNumber = computed(() => usePage().props.shop?.bkash || '');

const hasSliders = computed(() => props.sliders.length > 0);

// Swipe left/right on touch screens to change slides.
let touchX = null;
let touchY = null;

function onTouchStart(event) {
    const touch = event.touches[0];
    touchX = touch.clientX;
    touchY = touch.clientY;
}

function onTouchEnd(event) {
    if (touchX === null) return;
    const touch = event.changedTouches[0];
    const dx = touch.clientX - touchX;
    const dy = touch.clientY - touchY;
    touchX = null;
    if (props.sliders.length > 1 && Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.5) {
        onNavClick(dx < 0 ? next : prev);
    }
}
</script>

<template>
    <Head title="Signature fragrances" />

    <MainLayout>
        <!-- Banners managed in Admin > Sliders. They replace the static hero below; with none active the hero shows. Banners are wide (3:1). On computers the banner sits at the same width as the content below it and the whole picture shows (the box takes the picture's own shape, so nothing is cut). On phones it runs edge to edge in a 7:3 box and the picture is centre-cropped (about 12% off each side). -->
        <section v-if="hasSliders">
            <h1 class="sr-only">Signature Fragrance</h1>
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <!-- The one orchestrated moment of the page: the banner opens from the middle on load, then a gold line counts down to the next picture. -->
            <div class="relative aspect-[7/3] w-full overflow-hidden bg-green-950 md:aspect-(--hero-ratio)" :style="{ '--hero-ratio': heroRatio }" @touchstart.passive="onTouchStart" @touchend.passive="onTouchEnd">
                <template v-for="(slide, index) in sliders" :key="slide.id">
                    <a
                        v-if="/^https?:\/\//i.test(slide.link || '')"
                        :href="slide.link"
                        class="absolute inset-0 transition-opacity duration-[900ms]"
                        :class="index === activeSlide ? 'opacity-100' : 'pointer-events-none opacity-0'"
                    >
                        <img v-if="index === 0 || restReady" :src="slide.image" :srcset="srcset(slide.image, [800, 1200], 1800)" sizes="(min-width: 1280px) 1200px, 100vw" :alt="slide.title" :fetchpriority="index === 0 ? 'high' : 'low'" :decoding="index === 0 ? 'sync' : 'async'" class="relative h-full w-full object-cover" @load="noteRatio(slide, $event)">
                    </a>
                    <Link
                        v-else
                        :href="slide.link || routes.productsIndex()"
                        class="absolute inset-0 transition-opacity duration-[900ms]"
                        :class="index === activeSlide ? 'opacity-100' : 'pointer-events-none opacity-0'"
                    >
                        <img v-if="index === 0 || restReady" :src="slide.image" :srcset="srcset(slide.image, [800, 1200], 1800)" sizes="(min-width: 1280px) 1200px, 100vw" :alt="slide.title" :fetchpriority="index === 0 ? 'high' : 'low'" :decoding="index === 0 ? 'sync' : 'async'" class="relative h-full w-full object-cover" @load="noteRatio(slide, $event)">
                    </Link>
                </template>

                <template v-if="sliders.length > 1">
                    <button type="button" class="absolute top-1/2 left-3 hidden h-10 w-10 -translate-y-1/2 items-center justify-center border border-gold-500/50 bg-black/55 text-gold-300 backdrop-blur-sm transition hover:bg-black hover:text-gold-300 sm:flex" aria-label="Previous slide" @click="onNavClick(prev)"><ChevronLeft class="h-5 w-5" /></button>
                    <button type="button" class="absolute top-1/2 right-3 hidden h-10 w-10 -translate-y-1/2 items-center justify-center border border-gold-500/50 bg-black/55 text-gold-300 backdrop-blur-sm transition hover:bg-black hover:text-gold-300 sm:flex" aria-label="Next slide" @click="onNavClick(next)"><ChevronRight class="h-5 w-5" /></button>
                    <div class="absolute inset-x-0 bottom-0 flex justify-end gap-1 px-3 pb-0 sm:px-6 sm:pb-3">
                        <button v-for="(slide, index) in sliders" :key="slide.id" type="button" class="group flex h-6 w-8 items-center sm:w-12" :aria-label="`Go to slide ${index + 1}`" @click="onNavClick(() => goTo(index))">
                            <span class="relative block h-[3px] w-full overflow-hidden bg-white/35">
                                <!-- Restarts with each slide, so it always shows the time left on the current one. -->
                                <span v-if="index === activeSlide" :key="activeSlide" class="hero-count absolute inset-y-0 left-0 block w-full origin-left bg-gold-400" :style="{ animationDuration: SLIDE_MS + 'ms' }" />
                            </span>
                        </button>
                    </div>
                </template>
            </div>
            </div>
        </section>

        <!-- Hero: a flacon with its liquid, and what a fragrance is made of. -->
        <section v-if="!hasSliders" class="bg-green-950 text-white">
            <div class="mx-auto grid max-w-7xl items-center gap-8 px-4 py-10 sm:px-6 sm:py-14 lg:grid-cols-[1.15fr_1fr] lg:gap-6 lg:px-8 lg:py-16">
                <div class="order-2 lg:order-1">
                    <h1 class="font-display text-5xl leading-[1.02] font-medium tracking-tight text-balance sm:text-6xl lg:text-7xl">Signature Fragrance</h1>
                    <p class="mt-5 max-w-md text-base leading-relaxed text-green-100/80 sm:text-lg">Perfumes from the brands you know, sorted by brand, by who they are for, and by price. Pay by bKash and we deliver across Bangladesh.</p>
                    <div class="mt-7 flex flex-wrap gap-3">
                        <Link :href="routes.productsIndex()" class="inline-flex min-h-12 items-center justify-center bg-gold-500 px-7 py-3 text-sm font-semibold tracking-wide text-green-950 hover:bg-gold-400">Shop perfumes</Link>
                        <Link :href="routes.productsIndex({ sort: 'popular' })" class="inline-flex min-h-12 items-center justify-center border border-green-100/30 px-7 py-3 text-sm font-semibold tracking-wide text-white hover:border-gold-400 hover:text-gold-300">Best sellers</Link>
                    </div>
                </div>

                <div class="order-1 mx-auto flex w-full max-w-md items-center justify-center gap-5 sm:gap-8 lg:order-2">
                    <svg viewBox="0 0 200 300" class="h-72 w-auto shrink-0 overflow-visible sm:h-96" role="img" aria-label="A perfume bottle filled with amber liquid">
                        <defs>
                            <clipPath id="hero-body"><rect x="30" y="92" width="140" height="176" rx="10" /></clipPath>
                            <linearGradient id="hero-liquid" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="#e6bd7e" />
                                <stop offset="1" stop-color="#a96d28" />
                            </linearGradient>
                            <radialGradient id="hero-halo" cx="50%" cy="55%" r="55%">
                                <stop offset="0" stop-color="#9d68b5" stop-opacity="0.55" />
                                <stop offset="1" stop-color="#9d68b5" stop-opacity="0" />
                            </radialGradient>
                        </defs>
                        <ellipse cx="100" cy="180" rx="130" ry="150" fill="url(#hero-halo)" />
                        <rect x="30" y="92" width="140" height="176" rx="10" fill="#ffffff" fill-opacity="0.08" stroke="#ffffff" stroke-opacity="0.35" />
                        <g clip-path="url(#hero-body)">
                            <rect class="hero-liquid" x="34" y="130" width="132" height="134" fill="url(#hero-liquid)" />
                        </g>
                        <rect x="46" y="100" width="7" height="150" rx="3.5" fill="#ffffff" fill-opacity="0.35" />
                        <rect x="62" y="168" width="76" height="52" fill="#f8f3fa" />
                        <text x="100" y="190" text-anchor="middle" font-size="11" letter-spacing="2.6" fill="#240e2f" style="font-family: var(--font-display)">VIRELUXE</text>
                        <line x1="82" y1="199" x2="118" y2="199" stroke="#c8873a" stroke-width="1" />
                        <text x="100" y="212" text-anchor="middle" font-size="6.5" letter-spacing="1.6" fill="#55286b" style="font-family: var(--font-sans)">EAU DE PARFUM</text>
                        <rect x="84" y="74" width="32" height="18" fill="#d8cdb6" />
                        <rect x="72" y="30" width="56" height="46" rx="4" fill="#150a1c" stroke="#c8873a" stroke-opacity="0.7" />
                        <rect x="78" y="36" width="5" height="34" rx="2.5" fill="#ffffff" fill-opacity="0.18" />
                    </svg>

                    <dl class="min-w-0 space-y-6 text-sm">
                        <div class="border-l border-gold-500/60 pl-4">
                            <dt class="font-display text-lg text-gold-300">Top notes</dt>
                            <dd class="mt-0.5 text-green-100/70">The first ten minutes. Light and bright.</dd>
                        </div>
                        <div class="border-l border-gold-500/60 pl-4">
                            <dt class="font-display text-lg text-gold-300">Heart notes</dt>
                            <dd class="mt-0.5 text-green-100/70">The character that shows up after the opening.</dd>
                        </div>
                        <div class="border-l border-gold-500/60 pl-4">
                            <dt class="font-display text-lg text-gold-300">Base notes</dt>
                            <dd class="mt-0.5 text-green-100/70">What is left on your skin hours later.</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <div class="mx-auto max-w-7xl px-4 pt-5 sm:px-6 sm:pt-3 lg:px-8">
            <!-- Who is it for: three doors straight into the shop. -->
            <nav class="mb-5 grid grid-cols-3 gap-2.5 sm:mb-5 sm:gap-3" aria-label="Shop for">
                <Link
                    v-for="door in doors"
                    :key="door.slug"
                    :href="routes.productsIndex({ category: door.slug })"
                    class="group relative flex min-h-[4.5rem] flex-col items-center justify-center overflow-hidden bg-black px-2 py-3 text-center transition duration-500 hover:-translate-y-0.5 sm:min-h-24"
                >
                    <span class="absolute inset-x-0 bottom-0 h-0.5 origin-left scale-x-0 bg-gold-500 transition-transform duration-500 group-hover:scale-x-100" />
                    <span class="text-[10px] text-gray-400 sm:text-xs">Shop for</span>
                    <span class="font-display text-lg font-medium text-gold-300 sm:text-2xl">{{ door.label }}</span>
                </Link>
            </nav>

            <!-- Brands: each tile opens that brand's perfumes, with how many there are. -->
            <section v-if="brands.length" id="brands" class="mb-8 scroll-mt-28 sm:mb-8">
                <div class="mb-3 flex items-end justify-between gap-4">
                    <h2 class="font-display text-3xl font-medium text-green-900 sm:text-4xl">Shop by brand</h2>
                    <Link :href="routes.productsIndex()" class="inline-block shrink-0 py-1.5 text-sm font-semibold text-green-700 underline-offset-4 hover:underline">All perfumes</Link>
                </div>
                <div v-reveal class="relative">
                    <div ref="brandRail" class="no-scrollbar -mx-4 flex snap-x snap-proximity scroll-px-4 gap-2.5 overflow-x-auto scroll-smooth px-4 pb-1 sm:mx-0 sm:scroll-px-0 sm:px-0" role="region" aria-label="Brands" @scroll.passive="updateRail">
                    <Link
                        v-for="brand in brands"
                        :key="brand.id"
                        :href="routes.productsIndex({ brand: brand.slug })"
                                                class="group flex min-h-20 w-36 min-w-0 shrink-0 snap-start flex-col items-center justify-center gap-1 border border-gray-200 border-t-2 border-t-gold-500 bg-white p-3 text-center transition-colors duration-300 hover:border-green-900 hover:bg-green-900"
                    >
                        <span v-if="brand.logo" class="flex h-12 w-full items-center justify-center"><BrandLogo :src="brand.logo" :alt="brand.name" /></span>
                        <span v-else class="font-display max-w-full text-lg leading-tight font-medium break-words text-green-900 transition-colors duration-300 group-hover:text-gold-300">{{ brand.name }}</span>
                        <span class="text-xs text-gray-500 transition-colors duration-300 group-hover:text-gray-300">{{ brand.products_count }} {{ brand.products_count === 1 ? 'perfume' : 'perfumes' }}</span>
                    </Link>
                    </div>
                    <button type="button" class="absolute top-1/2 -left-3 z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center border border-gold-500/60 bg-black text-gold-300 shadow-lg transition hover:bg-gold-500 hover:text-black sm:flex" :class="railStart ? 'pointer-events-none opacity-0' : ''" aria-label="Previous brands" :tabindex="railStart ? -1 : 0" @click="scrollRail(-1)"><ChevronLeft class="h-5 w-5" /></button>
                    <button type="button" class="absolute top-1/2 -right-3 z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center border border-gold-500/60 bg-black text-gold-300 shadow-lg transition hover:bg-gold-500 hover:text-black sm:flex" :class="railEnd ? 'pointer-events-none opacity-0' : ''" aria-label="More brands" :tabindex="railEnd ? -1 : 0" @click="scrollRail(1)"><ChevronRight class="h-5 w-5" /></button>
                </div>
            </section>

            <!-- For her, for him, for everyone: a row each. -->
            <section v-for="section in genderSections" :key="section.slug" class="mb-6 sm:mb-8">
                <div class="mb-4 flex items-end justify-between gap-4">
                    <h2 class="font-display text-3xl font-medium text-green-900 sm:text-4xl">{{ section.name }}</h2>
                    <Link :href="routes.productsIndex({ category: section.slug })" class="inline-block shrink-0 py-1.5 text-sm font-semibold text-green-700 underline-offset-4 hover:underline">View all</Link>
                </div>
                <div v-reveal class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-5 max-xl:[&>*:nth-child(5)]:hidden lg:gap-4">
                    <ProductCard v-for="product in section.products" :key="product.id" :product="product" />
                </div>
            </section>

            <!-- Premium and everyday perfumes, kept apart. -->
            <section v-if="premiumProducts.length" class="relative isolate mb-6 py-6 before:absolute before:inset-y-0 before:left-1/2 before:-z-10 before:w-screen before:-translate-x-1/2 before:bg-black sm:mb-8 sm:py-8">
                <div class="mb-4 flex items-end justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="font-display text-3xl font-medium text-white sm:text-4xl">Premium perfumes</h2>
                        <p v-if="premiumFrom > 0" class="mt-1 text-sm text-gray-400">{{ formatPrice(premiumFrom) }} and above</p>
                    </div>
                    <Link :href="routes.productsIndex({ min_price: premiumFrom || undefined, sort: 'price_desc' })" class="inline-block shrink-0 py-1.5 text-sm font-semibold text-gold-300 underline-offset-4 hover:underline">View all</Link>
                </div>
                <div v-reveal class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-5 max-xl:[&>*:nth-child(5)]:hidden lg:gap-4">
                    <ProductCard v-for="product in premiumProducts" :key="product.id" :product="product" />
                </div>
            </section>

            <section v-if="affordableProducts.length" class="mb-6 sm:mb-8">
                <div class="mb-4 flex items-end justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="font-display text-3xl font-medium text-green-900 sm:text-4xl">Everyday perfumes</h2>
                        <p v-if="premiumFrom > 0" class="mt-1 text-sm text-gray-500">Under {{ formatPrice(premiumFrom) }}</p>
                    </div>
                    <Link :href="routes.productsIndex({ max_price: premiumFrom ? premiumFrom - 1 : undefined, sort: 'price_asc' })" class="inline-block shrink-0 py-1.5 text-sm font-semibold text-green-700 underline-offset-4 hover:underline">View all</Link>
                </div>
                <div v-reveal class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-5 max-xl:[&>*:nth-child(5)]:hidden lg:gap-4">
                    <ProductCard v-for="product in affordableProducts" :key="product.id" :product="product" />
                </div>
            </section>
        </div>

        <!-- How to order: the manual bKash flow is a real sequence, so these steps are numbered. -->
        <section class="border-y border-gray-200 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-7 lg:px-8">
                <h2 class="font-display text-3xl font-medium text-green-900 sm:text-4xl">Ordering takes three steps</h2>
                <ol class="mt-7 grid gap-6 md:grid-cols-3 md:gap-8">
                    <li class="flex gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center bg-green-900 text-gold-300"><SprayCan class="h-5 w-5" /></span>
                        <div class="min-w-0"><p class="font-semibold text-gray-900">1. Choose your perfume</p><p class="mt-1 text-sm leading-relaxed text-gray-600">Pick a bottle and a size, add it to your cart and check out with your delivery address.</p></div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center bg-[#e2136e] text-white"><Smartphone class="h-5 w-5" /></span>
                        <div class="min-w-0"><p class="font-semibold text-gray-900">{{ adv ? `2. Pay ${adv}% by bKash` : '2. Send the total by bKash' }}</p><p class="mt-1 text-sm leading-relaxed text-gray-600">Send <template v-if="adv">{{ adv }}% of the total</template><template v-else>the order total</template> to <strong v-if="bkashNumber" class="whitespace-nowrap text-gray-900">{{ bkashNumber }}</strong><span v-else>our bKash number</span> using Send Money.<template v-if="adv"> The other {{ 100 - adv }}% you pay in cash to the delivery man.</template></p></div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center bg-gold-500 text-green-950"><Send class="h-5 w-5" /></span>
                        <div class="min-w-0"><p class="font-semibold text-gray-900">3. Enter the transaction ID</p><p class="mt-1 text-sm leading-relaxed text-gray-600">Type your bKash number and the transaction ID from the bKash SMS. We check it and send your order out.</p></div>
                    </li>
                </ol>
            </div>
        </section>

        <div class="mx-auto max-w-7xl px-4 pt-5 sm:px-6 sm:pt-6 lg:px-8">
            <!-- Best sellers -->
            <section v-if="popularProducts.length" class="mb-6 sm:mb-8">
                <div class="mb-4 flex items-end justify-between gap-4">
                    <h2 class="font-display text-3xl font-medium text-green-900 sm:text-4xl">Best sellers</h2>
                    <Link :href="routes.productsIndex({ sort: 'popular' })" class="inline-block shrink-0 py-1.5 text-sm font-semibold text-green-700 underline-offset-4 hover:underline">View all</Link>
                </div>
                <div v-reveal class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:gap-4">
                    <ProductCard v-for="product in popularProducts" :key="product.id" :product="product" />
                </div>
            </section>

            <!-- Hidden (no gap) while there is nothing to show -->
            <div class="mb-6 empty:hidden">
                <RecentlyViewed />
            </div>

            <div><LocationMap /></div>

        </div>

        <!-- Promise band: the four things shoppers ask first, just above the footer. -->
        <div class="mx-auto max-w-7xl px-4 pt-6 pb-6 sm:px-6 sm:pb-8 lg:px-8">
            <ul class="grid grid-cols-2 gap-2.5 sm:gap-3 lg:grid-cols-4">
                <li class="flex items-center gap-2.5 border border-gray-200 bg-white p-3 sm:gap-3 sm:p-3.5"><span class="flex h-9 w-9 shrink-0 sm:h-10 sm:w-10 items-center justify-center bg-gold-500 text-green-950"><ShieldCheck class="h-5 w-5" /></span><span class="text-[13px] leading-snug font-semibold text-gray-900 sm:text-sm">100% original perfume</span></li>
                <li class="flex items-center gap-2.5 border border-gray-200 bg-white p-3 sm:gap-3 sm:p-3.5"><span class="flex h-9 w-9 shrink-0 sm:h-10 sm:w-10 items-center justify-center bg-gold-500 text-green-950"><Smartphone class="h-5 w-5" /></span><span class="text-[13px] leading-snug font-semibold text-gray-900 sm:text-sm">{{ adv ? `${adv}% bKash + ${100 - adv}% on delivery` : 'Pay by bKash' }}</span></li>
                <li class="flex items-center gap-2.5 border border-gray-200 bg-white p-3 sm:gap-3 sm:p-3.5"><span class="flex h-9 w-9 shrink-0 sm:h-10 sm:w-10 items-center justify-center bg-gold-500 text-green-950"><Truck class="h-5 w-5" /></span><span class="text-[13px] leading-snug font-semibold text-gray-900 sm:text-sm">Delivery all over Bangladesh</span></li>
                <li class="flex items-center gap-2.5 border border-gray-200 bg-white p-3 sm:gap-3 sm:p-3.5"><span class="flex h-9 w-9 shrink-0 sm:h-10 sm:w-10 items-center justify-center bg-gold-500 text-green-950"><RefreshCcw class="h-5 w-5" /></span><span class="text-[13px] leading-snug font-semibold text-gray-900 sm:text-sm">7-day replacement</span></li>
            </ul>
        </div>
    </MainLayout>
</template>
