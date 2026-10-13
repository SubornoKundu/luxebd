<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { srcset } from '@/thumb';

// The brand mark used in the header, footer, login card and admin sidebar.
// Uses the uploaded logo when there is one, otherwise an icon + the site name.
const props = defineProps({
    size: { type: String, default: 'md' },        // sm | md | lg
    dark: { type: Boolean, default: false },       // on a dark background (admin sidebar)
    hideNameBelow: { type: Boolean, default: false }, // hide the text name on very narrow phones
    suffix: { type: String, default: '' },         // e.g. "Admin"
});

const site = computed(() => usePage().props.site ?? {});
const name = computed(() => site.value.name || 'Vireluxe');

// A wide logo is a full wordmark (name already inside it) so it stands alone.
// A square / icon-only logo gets the site name written next to it.
const iconOnly = ref(false);
function logoLoaded(e) {
    const { naturalWidth: w, naturalHeight: h } = e.target;
    iconOnly.value = h > 0 && w / h < 1.8;
}

// "ShopEase" -> Shop + Ease, "My Shop" -> My + Shop, "Acme" -> Acme
const parts = computed(() => {
    const n = name.value.trim();
    const camel = n.match(/^([A-Z][a-z0-9]+)([A-Z][A-Za-z0-9]*)$/);
    if (camel) return [camel[1], camel[2]];
    const i = n.indexOf(' ');
    if (i > 0) return [n.slice(0, i + 1), n.slice(i + 1)];
    return [n, ''];
});

const box = computed(() => ({ sm: 'h-8 w-8 rounded-lg', md: 'h-9 w-9 rounded-xl', lg: 'h-10 w-10 rounded-xl' }[props.size]));
const icon = computed(() => (props.size === 'sm' ? 'h-4 w-4' : 'h-5 w-5'));
const logoH = computed(() => ({ sm: 'h-8 max-w-[9rem]', md: 'h-9 max-w-[min(13rem,42vw)] sm:h-10 sm:max-w-[15rem]', lg: 'h-11 max-w-[16rem]' }[props.size]));
const text = computed(() => (props.size === 'lg' ? 'text-xl' : 'text-lg'));
const wordSize = computed(() => ({ sm: 'text-base', md: 'text-lg sm:text-xl', lg: 'text-2xl' }[props.size]));
</script>

<template>
    <span class="flex min-w-0 items-center gap-2">
        <template v-if="site.logo">
            <img :key="site.logo" :src="site.logo" :srcset="srcset(site.logo, [360])" sizes="160px" :alt="name" class="w-auto shrink-0 object-contain" :class="logoH" @load="logoLoaded">
            <span v-if="iconOnly" class="min-w-0 truncate font-bold tracking-tight" :class="[text, dark ? 'text-white' : 'text-gray-900', hideNameBelow ? 'hidden min-[375px]:inline' : '']">
                {{ parts[0] }}<span v-if="parts[1]" :class="dark ? 'text-green-400' : 'text-green-600'">{{ parts[1] }}</span>
            </span>
            <span v-if="suffix" class="text-xs font-normal" :class="dark ? 'text-gray-400' : 'text-gray-500'">{{ suffix }}</span>
        </template>
        <template v-else>
            <!-- Default mark: the Vireluxe logo on its own black tile, so it reads the same on light and dark backgrounds. -->
            <img src="/images/vireluxe/logo.png" width="640" height="155" srcset="/thumbs/360/images/vireluxe/logo.png 360w, /images/vireluxe/logo.png 640w" sizes="160px" :alt="name" class="w-auto min-w-0 shrink rounded-[3px] object-contain" :class="logoH">
            <span v-if="suffix" class="text-xs font-normal" :class="dark ? 'text-gray-400' : 'text-gray-500'">{{ suffix }}</span>
        </template>
    </span>
</template>
