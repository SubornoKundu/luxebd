<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    /** The `links` array from a Laravel LengthAwarePaginator (already
     * includes "&laquo; Previous" / "Next &raquo;" entries). */
    links: { type: Array, required: true },
});
</script>

<template>
    <nav v-if="links.length > 3" class="mt-8 flex flex-wrap items-center justify-center gap-1">
        <template v-for="(link, index) in links" :key="index">
            <span
                v-if="link.url === null"
                class="rounded-lg px-3 py-2 text-center text-sm text-gray-300 select-none"
                v-html="link.label"
            />
            <Link
                v-else
                :href="link.url"
                class="min-w-10 rounded-lg px-3 py-2 text-center text-sm font-medium transition"
                :class="link.active
                    ? 'bg-green-600 text-white'
                    : 'text-gray-600 hover:bg-gray-100'"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
