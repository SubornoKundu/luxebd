<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Clock, MapPin, Navigation, Phone } from '@lucide/vue';

// "Visit us": the shop's live location on Google Maps, with a directions button.
// The search text comes from SHOP_MAP_QUERY (Plus Code + area); the address text from Admin > Settings.
const props = defineProps({
    heading: { type: String, default: 'Visit us' },
});

const shop = computed(() => usePage().props.shop ?? {});
const contact = computed(() => shop.value.contact ?? {});
const query = computed(() => String(shop.value.mapQuery || contact.value.address || '').trim());
const embedUrl = computed(() => `https://www.google.com/maps?q=${encodeURIComponent(query.value)}&z=16&output=embed`);
const openUrl = computed(() => shop.value.mapLink || `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query.value)}`);
// Google's map is heavy (about 450 KB of scripts), so it only starts loading when the visitor
// scrolls close to it. Until then the box is an empty grey space of the same size.
const root = ref(null);
const near = ref(false);
let watcher = null;
onMounted(() => {
    if (!('IntersectionObserver' in window)) {
        near.value = true;
        return;
    }
    watcher = new IntersectionObserver((entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        near.value = true;
        watcher?.disconnect();
    }, { rootMargin: '300px 0px' });
    if (root.value) watcher.observe(root.value);
});
onBeforeUnmount(() => watcher?.disconnect());

const telHref = computed(() => `tel:${String(contact.value.phone ?? '').replace(/[^+\d]/g, '')}`);
</script>

<template>
    <section v-if="query" ref="root" class="grid overflow-hidden border border-gray-200 bg-white lg:grid-cols-[1fr_1.4fr]" aria-label="Shop location">
        <div class="flex min-w-0 flex-col justify-center gap-4 p-5 sm:p-8">
            <h2 class="font-display text-3xl font-medium text-green-900 sm:text-4xl">{{ heading }}</h2>
            <ul class="space-y-3 text-sm text-gray-700">
                <li v-if="contact.address" class="flex gap-3"><MapPin class="mt-0.5 h-4 w-4 shrink-0 text-gold-500" /> <span class="min-w-0 break-words">{{ contact.address }}</span></li>
                <li v-if="contact.phone" class="flex gap-3"><Phone class="mt-0.5 h-4 w-4 shrink-0 text-gold-500" /> <a :href="telHref" class="inline-block py-1 font-semibold text-green-700 hover:underline">{{ contact.phone }}</a></li>
                <li v-if="contact.hours" class="flex gap-3"><Clock class="mt-0.5 h-4 w-4 shrink-0 text-gold-500" /> <span>{{ contact.hours }}</span></li>
            </ul>
            <a :href="openUrl" target="_blank" rel="noopener" class="inline-flex min-h-11 w-fit items-center gap-2 bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700">
                <Navigation class="h-4 w-4" /> Get directions
            </a>
        </div>
        <iframe
            :src="near ? embedUrl : undefined"
            title="Map showing the shop location"
            class="h-72 w-full border-0 bg-gray-100 lg:h-full lg:min-h-80"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allowfullscreen
        />
    </section>
</template>
