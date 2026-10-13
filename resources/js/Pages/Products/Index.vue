<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { SlidersHorizontal, X } from '@lucide/vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import Pagination from '@/Components/Pagination.vue';
import { routes } from '@/routes';

const props = defineProps({
    products: { type: Object, required: true },
    brands: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const activeCategory = computed(() => props.categories.find((c) => c.slug === props.filters.category));

// Two-level tree: top-level categories with their sub-categories.
const tree = computed(() => props.categories
    .filter((c) => !c.parent_id)
    .map((parent) => ({ ...parent, children: props.categories.filter((c) => c.parent_id === parent.id) })));

// A parent stays expanded while it (or one of its sub-categories) is selected.
function isExpanded(parent) {
    const slug = props.filters.category;
    return Boolean(slug) && (parent.slug === slug || parent.children.some((child) => child.slug === slug));
}
const activeBrand = computed(() => props.brands.find((b) => b.slug === props.filters.brand));

const hasActiveFilters = computed(
    () => Boolean(props.filters.brand || props.filters.category || props.filters.q
        || props.filters.min_price || props.filters.max_price || props.filters.in_stock),
);

// Number shown on the mobile "Filters" button.
const activeFilterCount = computed(
    () => [props.filters.brand, props.filters.category, props.filters.min_price || props.filters.max_price, props.filters.in_stock]
        .filter(Boolean).length,
);

const minPrice = ref(props.filters.min_price ?? '');
const maxPrice = ref(props.filters.max_price ?? '');

// Keep the Min / Max boxes in step when a quick filter (Premium / Everyday) changes the address.
watch(() => [props.filters.min_price, props.filters.max_price], ([min, max]) => {
    minPrice.value = min ?? '';
    maxPrice.value = max ?? '';
});

// Premium = at or above this price, Everyday = below it (SHOP_PREMIUM_FROM).
const premiumFrom = computed(() => Number(usePage().props.shop?.premiumFrom ?? 0));
const tier = computed(() => {
    if (!premiumFrom.value) return null;
    const min = Number(props.filters.min_price || 0);
    const max = Number(props.filters.max_price || 0);
    if (min === premiumFrom.value && !max) return 'premium';
    if (max === premiumFrom.value - 1 && !min) return 'everyday';
    return null;
});

function setTier(name) {
    if (tier.value === name) return applyFilter({ min_price: undefined, max_price: undefined });
    applyFilter(name === 'premium'
        ? { min_price: premiumFrom.value, max_price: undefined }
        : { min_price: undefined, max_price: premiumFrom.value - 1 });
}

// On phones/tablets the filters live in a slide-in drawer.
const filtersOpen = ref(false);

function onKeydown(event) {
    if (event.key === 'Escape') filtersOpen.value = false;
}

watch(filtersOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});

function applyPrice() {
    applyFilter({ min_price: minPrice.value || undefined, max_price: maxPrice.value || undefined });
}

function toggleInStock() {
    applyFilter({ in_stock: props.filters.in_stock ? undefined : 1 });
}

function applyFilter(patch) {
    router.get(routes.productsIndex({ ...props.filters, ...patch }), {}, { preserveState: true, preserveScroll: true });
}

function toggleCategory(slug) {
    applyFilter({ category: props.filters.category === slug ? undefined : slug });
}

function toggleBrand(slug) {
    applyFilter({ brand: props.filters.brand === slug ? undefined : slug });
}

function setSort(event) {
    applyFilter({ sort: event.target.value });
}

// Quick chips above the grid: who it is for, then the price tier.
const quickCategories = computed(() => props.categories.filter((c) => !c.parent_id));

const pageTitle = computed(() => {
    if (props.filters.q) return `Search: "${props.filters.q}"`;
    if (activeCategory.value) return activeCategory.value.name;
    if (activeBrand.value) return activeBrand.value.name;
    return 'All perfumes';
});
</script>

<template>
    <Head :title="pageTitle" />

    <MainLayout>
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3 sm:mb-4">
                <div class="min-w-0">
                    <h1 class="font-display text-3xl font-medium break-words text-green-900 sm:text-4xl">{{ pageTitle }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ products.total }} perfume{{ products.total === 1 ? '' : 's' }} found</p>
                </div>
                <div class="flex w-full items-center gap-2 sm:w-auto">
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 sm:flex-none lg:hidden"
                        @click="filtersOpen = true"
                    >
                        <SlidersHorizontal class="h-4 w-4" />
                        Filters
                        <span v-if="activeFilterCount" class="flex h-5 min-w-5 items-center justify-center rounded-full bg-green-600 px-1 text-[11px] font-bold text-white">{{ activeFilterCount }}</span>
                    </button>
                    <select
                        class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-green-500 focus:outline-none sm:flex-none lg:py-2"
                        :value="filters.sort || 'latest'"
                        aria-label="Sort products"
                        @change="setSort"
                    >
                        <option value="latest">Newest first</option>
                        <option value="popular">Most popular</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="name">Name: A-Z</option>
                    </select>
                </div>
            </div>

            <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:mb-5 sm:flex-wrap sm:overflow-visible sm:px-0 [&::-webkit-scrollbar]:hidden" role="group" aria-label="Quick filters">
                <button
                    type="button"
                    class="shrink-0 rounded-full border px-4 py-2 text-sm font-medium whitespace-nowrap transition duration-300 active:scale-95"
                    :class="!filters.category && !tier ? 'border-black bg-black text-gold-300' : 'border-gray-300 bg-white text-gray-700 hover:border-gold-500'"
                    @click="applyFilter({ category: undefined, min_price: undefined, max_price: undefined })"
                >All</button>
                <button
                    v-for="c in quickCategories"
                    :key="c.id"
                    type="button"
                    class="shrink-0 rounded-full border px-4 py-2 text-sm font-medium whitespace-nowrap transition duration-300 active:scale-95"
                    :class="filters.category === c.slug ? 'border-black bg-black text-gold-300' : 'border-gray-300 bg-white text-gray-700 hover:border-gold-500'"
                    @click="toggleCategory(c.slug)"
                >{{ c.name }}</button>
                <template v-if="premiumFrom">
                    <span class="mx-1 hidden w-px self-stretch bg-gray-300 sm:block" aria-hidden="true" />
                    <button
                        type="button"
                        class="shrink-0 rounded-full border px-4 py-2 text-sm font-medium whitespace-nowrap transition duration-300 active:scale-95"
                        :class="tier === 'premium' ? 'border-gold-500 bg-gold-500 text-black' : 'border-gold-500/60 bg-white text-gray-800 hover:bg-gold-500/10'"
                        @click="setTier('premium')"
                    >Premium</button>
                    <button
                        type="button"
                        class="shrink-0 rounded-full border px-4 py-2 text-sm font-medium whitespace-nowrap transition duration-300 active:scale-95"
                        :class="tier === 'everyday' ? 'border-gold-500 bg-gold-500 text-black' : 'border-gold-500/60 bg-white text-gray-800 hover:bg-gold-500/10'"
                        @click="setTier('everyday')"
                    >Everyday</button>
                </template>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-[240px_minmax(0,1fr)]">
                <!-- Dark backdrop behind the drawer (phones / tablets only) -->
                <div v-if="filtersOpen" class="fixed inset-0 z-50 bg-black/40 lg:hidden" @click="filtersOpen = false" />

                <!-- Filters: slide-in drawer below lg, normal sidebar from lg up -->
                <aside
                    class="fixed inset-y-0 left-0 z-[60] flex w-[88%] max-w-sm flex-col bg-white shadow-xl transition-[transform,visibility] duration-200 lg:static lg:z-auto lg:block lg:w-auto lg:max-w-none lg:translate-x-0 lg:bg-transparent lg:shadow-none"
                    :class="filtersOpen ? 'visible translate-x-0' : 'invisible -translate-x-full lg:visible'"
                >
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 lg:hidden">
                        <h2 class="flex items-center gap-2 font-bold text-gray-900"><SlidersHorizontal class="h-4 w-4" /> Filters</h2>
                        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100" aria-label="Close filters" @click="filtersOpen = false">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="flex-1 space-y-6 overflow-y-auto overscroll-contain p-5 lg:overflow-visible lg:p-0">
                        <div v-if="hasActiveFilters" class="flex flex-wrap gap-2">
                            <Link
                                :href="routes.productsIndex()"
                                class="inline-flex items-center gap-1 rounded-full bg-gray-900 px-3 py-1.5 text-xs font-medium text-white lg:py-1"
                            >
                                Clear all <X class="h-3 w-3" />
                            </Link>
                        </div>

                        <div>
                            <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-gray-900">
                                <SlidersHorizontal class="hidden h-4 w-4 lg:block" /> Shop for
                            </h2>
                            <ul class="space-y-1">
                                <li v-for="category in tree" :key="category.id">
                                    <button
                                        type="button"
                                        class="w-full rounded-lg px-3 py-2 text-left text-sm transition lg:py-1.5"
                                        :class="filters.category === category.slug
                                            ? 'bg-green-50 font-semibold text-green-700'
                                            : 'text-gray-600 hover:bg-gray-100'"
                                        @click="toggleCategory(category.slug)"
                                    >
                                        {{ category.name }}
                                    </button>
                                    <ul v-if="category.children.length && isExpanded(category)" class="mt-1 mb-1 ml-3 space-y-1 border-l border-gray-200 pl-2">
                                        <li v-for="child in category.children" :key="child.id">
                                            <button
                                                type="button"
                                                class="w-full rounded-lg px-3 py-2 text-left text-sm transition lg:py-1.5"
                                                :class="filters.category === child.slug
                                                    ? 'bg-green-50 font-semibold text-green-700'
                                                    : 'text-gray-500 hover:bg-gray-100'"
                                                @click="toggleCategory(child.slug)"
                                            >
                                                {{ child.name }}
                                            </button>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <h2 class="mb-3 text-sm font-bold text-gray-900">Price (৳)</h2>
                            <div v-if="premiumFrom" class="mb-3 grid grid-cols-2 gap-2">
                                <button type="button" class="min-h-10 border px-2 py-2 text-sm transition" :class="tier === 'premium' ? 'border-green-600 bg-green-50 font-semibold text-green-700' : 'border-gray-200 text-gray-600 hover:border-gray-300'" @click="setTier('premium')">Premium</button>
                                <button type="button" class="min-h-10 border px-2 py-2 text-sm transition" :class="tier === 'everyday' ? 'border-green-600 bg-green-50 font-semibold text-green-700' : 'border-gray-200 text-gray-600 hover:border-gray-300'" @click="setTier('everyday')">Everyday</button>
                            </div>
                            <form class="flex items-center gap-2" @submit.prevent="applyPrice">
                                <input v-model="minPrice" type="number" inputmode="numeric" min="0" placeholder="Min" class="w-full min-w-0 rounded-lg border border-gray-200 px-2 py-2 text-sm focus:border-green-500 focus:outline-none lg:py-1.5">
                                <span class="text-gray-300">-</span>
                                <input v-model="maxPrice" type="number" inputmode="numeric" min="0" placeholder="Max" class="w-full min-w-0 rounded-lg border border-gray-200 px-2 py-2 text-sm focus:border-green-500 focus:outline-none lg:py-1.5">
                                <button type="submit" class="shrink-0 rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white lg:py-1.5">Go</button>
                            </form>
                            <label class="mt-3 flex items-center gap-2 py-1 text-sm text-gray-600">
                                <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-green-600" :checked="Boolean(filters.in_stock)" @change="toggleInStock">
                                In stock only
                            </label>
                        </div>

                        <div>
                            <h2 class="mb-3 text-sm font-bold text-gray-900">Brands</h2>
                            <ul class="space-y-1">
                                <li v-for="brand in brands" :key="brand.id">
                                    <button
                                        type="button"
                                        class="w-full rounded-lg px-3 py-2 text-left text-sm transition lg:py-1.5"
                                        :class="filters.brand === brand.slug
                                            ? 'bg-green-50 font-semibold text-green-700'
                                            : 'text-gray-600 hover:bg-gray-100'"
                                        @click="toggleBrand(brand.slug)"
                                    >
                                        {{ brand.name }} <span class="text-xs font-normal text-gray-400">({{ brand.products_count ?? 0 }})</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 p-4 lg:hidden">
                        <button type="button" class="w-full rounded-full bg-green-600 py-3 text-sm font-semibold text-white hover:bg-green-700" @click="filtersOpen = false">
                            Show {{ products.total }} perfume{{ products.total === 1 ? '' : 's' }}
                        </button>
                    </div>
                </aside>

                <!-- Grid -->
                <div class="min-w-0">
                    <div v-if="products.data.length" class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 xl:grid-cols-4">
                        <ProductCard v-for="product in products.data" :key="product.id" :product="product" />
                    </div>
                    <div v-else class="rounded-2xl border border-dashed border-gray-300 px-4 py-16 text-center sm:py-20">
                        <p class="text-gray-500">No perfumes match these filters.</p>
                        <Link :href="routes.productsIndex()" class="mt-2 inline-block py-2 text-sm font-medium text-green-700 hover:underline">
                            Clear filters
                        </Link>
                    </div>

                    <Pagination :links="products.links" />
                </div>
            </div>
        </div>
    </MainLayout>
</template>
