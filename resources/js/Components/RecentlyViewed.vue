<script setup>
import { computed, onMounted } from 'vue';
import ProductCard from '@/Components/ProductCard.vue';
import { loadRecentlyViewed, recentlyViewed } from '@/Composables/recentlyViewed';

const props = defineProps({
    excludeId: { type: Number, default: null },
    title: { type: String, default: 'Recently viewed' },
});

onMounted(loadRecentlyViewed);

const items = computed(() => recentlyViewed.value.filter((p) => p.id !== props.excludeId).slice(0, 10));
</script>

<template>
    <section v-if="items.length">
        <h2 class="mb-2.5 text-base font-bold text-gray-900 sm:mb-3 sm:text-lg">{{ title }}</h2>
        <div class="-mx-4 flex snap-x gap-2.5 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0 sm:gap-3">
            <div v-for="product in items" :key="product.id" class="w-40 shrink-0 snap-start sm:w-44 lg:w-48">
                <ProductCard :product="product" class="h-full" />
            </div>
        </div>
    </section>
</template>
