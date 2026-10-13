<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Loader2, Search, X } from '@lucide/vue';
import { formatPrice } from '@/currency';
import { routes } from '@/routes';

const props = defineProps({
    inputClass: { type: String, default: '' },
});

const emit = defineEmits(['navigate']);

const query = ref(new URLSearchParams(window.location.search).get('q') ?? '');
const open = ref(false);
const loading = ref(false);
const results = ref({ products: [], categories: [], total: 0 });
const active = ref(-1);
const root = ref(null);
const input = ref(null);

let timer = null;
let controller = null;

const term = computed(() => query.value.trim());
const ready = computed(() => term.value.length >= 2);

// One flat list so arrow keys can walk categories, then products, then "see all".
const options = computed(() => {
    const list = [
        ...results.value.categories.map((c) => ({ type: 'category', key: `c${c.id}`, href: routes.productsIndex({ category: c.slug }), data: c })),
        ...results.value.products.map((p) => ({ type: 'product', key: `p${p.id}`, href: routes.productShow(p.slug), data: p })),
    ];

    if (results.value.total > results.value.products.length) {
        list.push({ type: 'all', key: 'all', href: routes.productsIndex({ q: term.value }) });
    }

    return list;
});

async function fetchResults() {
    if (!ready.value) {
        results.value = { products: [], categories: [], total: 0 };
        loading.value = false;
        return;
    }

    controller?.abort();
    controller = new AbortController();
    loading.value = true;

    try {
        const response = await fetch(routes.searchSuggest(term.value), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        });
        if (response.ok) {
            results.value = await response.json();
            active.value = -1;
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            results.value = { products: [], categories: [], total: 0 };
        }
    } finally {
        loading.value = false;
    }
}

watch(query, () => {
    window.clearTimeout(timer);
    if (!ready.value) {
        controller?.abort();
        results.value = { products: [], categories: [], total: 0 };
        loading.value = false;
        return;
    }
    loading.value = true;
    timer = window.setTimeout(fetchResults, 250);
});

function go(href) {
    open.value = false;
    emit('navigate');
    router.visit(href);
}

function submit() {
    const choice = options.value[active.value];
    if (choice) {
        go(choice.href);
        return;
    }
    open.value = false;
    emit('navigate');
    router.get(routes.productsIndex({ q: term.value || undefined }));
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        open.value = false;
        input.value?.blur();
        return;
    }
    if (!open.value || !options.value.length) return;
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        active.value = (active.value + 1) % options.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active.value = (active.value - 1 + options.value.length) % options.value.length;
    }
}

function clear() {
    query.value = '';
    open.value = false;
    nextTick(() => input.value?.focus());
}

function onOutside(event) {
    if (root.value && !root.value.contains(event.target)) open.value = false;
}

onMounted(() => document.addEventListener('pointerdown', onOutside));
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onOutside);
    window.clearTimeout(timer);
    controller?.abort();
});

const showPanel = computed(() => open.value && ready.value && !(loading.value && !options.value.length));
</script>

<template>
    <form ref="root" class="relative w-full" role="search" @submit.prevent="submit">
        <Search class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input
            ref="input"
            v-model="query"
            type="search"
            autocomplete="off"
            enterkeyhint="search"
            placeholder="Search products, brands, categories..."
            role="combobox"
            aria-label="Search products"
            :aria-expanded="showPanel"
            aria-autocomplete="list"
            :class="['w-full rounded-full border border-gray-200 bg-gray-50 py-2.5 pr-10 pl-10 text-sm focus:border-green-500 focus:bg-white focus:ring-2 focus:ring-green-500/20 focus:outline-none [&::-webkit-search-cancel-button]:hidden', props.inputClass]"
            @focus="open = true"
            @input="open = true"
            @keydown="onKeydown"
        >
        <Loader2 v-if="loading" class="absolute top-1/2 right-3.5 h-4 w-4 -translate-y-1/2 animate-spin text-green-600" />
        <button v-else-if="query" type="button" class="absolute top-1/2 right-1.5 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 hover:text-gray-700" aria-label="Clear search" @click="clear">
            <X class="h-4 w-4" />
        </button>

        <div
            v-if="showPanel"
            class="absolute top-full right-0 left-0 z-50 mt-2 max-h-[min(70dvh,32rem)] overflow-y-auto overscroll-contain rounded-2xl border border-gray-200 bg-white p-2 shadow-xl"
            role="listbox"
        >
            <template v-if="options.length">
                <div v-if="results.categories.length" class="px-2 pt-1 pb-2">
                    <p class="mb-1.5 text-[11px] font-semibold tracking-wide text-gray-400 uppercase">Categories</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="option in options.filter((o) => o.type === 'category')"
                            :key="option.key"
                            type="button"
                            class="rounded-full border px-3 py-1 text-xs font-medium"
                            :class="options[active]?.key === option.key ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-200 text-gray-600 hover:border-green-300'"
                            @click="go(option.href)"
                        >
                            {{ option.data.name }}
                        </button>
                    </div>
                </div>

                <ul>
                    <li v-for="option in options.filter((o) => o.type === 'product')" :key="option.key" role="option" :aria-selected="options[active]?.key === option.key">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-xl p-2 text-left"
                            :class="options[active]?.key === option.key ? 'bg-green-50' : 'hover:bg-gray-50'"
                            @click="go(option.href)"
                        >
                            <img loading="lazy" decoding="async" :src="option.data.image" :alt="option.data.name" class="h-12 w-12 shrink-0 rounded-lg bg-gray-100 object-cover">
                            <span class="min-w-0 flex-1">
                                <span class="line-clamp-1 block text-sm font-medium break-words text-gray-900">{{ option.data.name }}</span>
                                <span class="block truncate text-xs text-gray-500">{{ option.data.category }}</span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="block text-sm font-bold text-gray-900">{{ formatPrice(option.data.final_price) }}</span>
                                <span v-if="option.data.has_discount" class="block text-xs text-gray-400 line-through">{{ formatPrice(option.data.price) }}</span>
                                <span v-if="!option.data.in_stock" class="block text-[11px] font-medium text-rose-600">Out of stock</span>
                            </span>
                        </button>
                    </li>
                </ul>

                <button
                    v-if="options.some((o) => o.type === 'all')"
                    type="button"
                    class="mt-1 w-full rounded-xl px-3 py-2.5 text-center text-sm font-semibold text-green-700"
                    :class="options[active]?.type === 'all' ? 'bg-green-50' : 'hover:bg-gray-50'"
                    @click="go(options.find((o) => o.type === 'all').href)"
                >
                    See all {{ results.total }} results for "{{ term }}"
                </button>
            </template>
            <p v-else class="px-3 py-6 text-center text-sm text-gray-500">No products found for "{{ term }}".</p>
        </div>
    </form>
</template>
