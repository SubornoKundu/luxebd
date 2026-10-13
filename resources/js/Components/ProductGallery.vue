<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { ChevronLeft, ChevronRight, X } from '@lucide/vue';

/**
 * Photo gallery for the product page: big picture with arrows, a strip of
 * thumbnails, and a full-screen viewer (tap the picture) with arrows,
 * keyboard keys, swipe and an "n of N" counter.
 *
 * `modelValue` is the picture that is currently shown (so the page can also
 * switch it, e.g. when a colour option has its own photo).
 */
const props = defineProps({
    images: { type: Array, required: true },
    modelValue: { type: String, default: null },
    title: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const index = computed(() => Math.max(0, props.images.indexOf(props.modelValue)));
const current = computed(() => props.images[index.value] ?? props.modelValue);
const many = computed(() => props.images.length > 1);

function go(step) {
    if (!many.value) return;
    const n = props.images.length;
    emit('update:modelValue', props.images[(index.value + step + n) % n]);
}

/* thumbnails: keep the active one in view */
const strip = ref(null);
watch(index, async () => {
    await nextTick();
    strip.value?.querySelector('[data-active="true"]')?.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
});

/* swipe (used on both the page picture and the viewer) */
let sx = null;
let sy = null;
function swipeStart(e) {
    sx = e.touches[0].clientX;
    sy = e.touches[0].clientY;
}
function swipeEnd(e) {
    if (sx === null) return;
    const dx = e.changedTouches[0].clientX - sx;
    const dy = e.changedTouches[0].clientY - sy;
    sx = null;
    if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.5) go(dx < 0 ? 1 : -1);
}

/* the picture zooms towards the mouse pointer (desktop only, see .gallery-zoom) */
function onZoomMove(e) {
    const box = e.currentTarget.getBoundingClientRect();
    e.currentTarget.style.setProperty('--zx', `${((e.clientX - box.left) / box.width) * 100}%`);
    e.currentTarget.style.setProperty('--zy', `${((e.clientY - box.top) / box.height) * 100}%`);
}

/* full-screen viewer */
const open = ref(false);
const closeButton = ref(null);
let opener = null;

function show() {
    opener = document.activeElement;
    open.value = true;
}
function hide() {
    open.value = false;
}

function onKey(e) {
    if (e.key === 'Escape') hide();
    else if (e.key === 'ArrowRight') go(1);
    else if (e.key === 'ArrowLeft') go(-1);
}

watch(open, async (isOpen) => {
    try {
        document.body.style.overflow = isOpen ? 'hidden' : '';
    } catch { /* ignore */ }
    if (isOpen) {
        window.addEventListener('keydown', onKey);
        await nextTick();
        closeButton.value?.focus();
    } else {
        window.removeEventListener('keydown', onKey);
        opener?.focus?.();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});
</script>

<template>
    <div>
        <!-- Picture on the page -->
        <div class="stage-in relative overflow-hidden bg-black" @touchstart.passive="swipeStart" @touchend.passive="swipeEnd">
            <button type="button" class="gallery-zoom block w-full cursor-zoom-in overflow-hidden" aria-label="Open photo full screen" @click="show" @mousemove="onZoomMove">
                <img :key="current" :src="current" :alt="title" class="gallery-fade aspect-square w-full object-cover">
            </button>
            <template v-if="many">
                <button type="button" class="absolute top-1/2 left-2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/85 text-gray-800 shadow hover:bg-white" aria-label="Previous photo" @click="go(-1)">
                    <ChevronLeft class="h-5 w-5" />
                </button>
                <button type="button" class="absolute top-1/2 right-2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/85 text-gray-800 shadow hover:bg-white" aria-label="Next photo" @click="go(1)">
                    <ChevronRight class="h-5 w-5" />
                </button>
                <span class="pointer-events-none absolute right-3 bottom-3 rounded-full bg-black/60 px-2.5 py-1 text-xs font-medium text-white">{{ index + 1 }} / {{ images.length }}</span>
            </template>
        </div>

        <!-- Thumbnails -->
        <div v-if="many" ref="strip" class="mt-3 flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="(image, i) in images"
                :key="image"
                type="button"
                class="h-16 w-16 shrink-0 overflow-hidden border-2 bg-black sm:h-20 sm:w-20"
                :class="i === index ? 'border-gold-500' : 'border-transparent hover:border-gold-500/60'"
                :data-active="i === index"
                :aria-label="`Show photo ${i + 1} of ${images.length}`"
                :aria-current="i === index"
                @click="emit('update:modelValue', image)"
            >
                <img loading="lazy" decoding="async" :src="image" alt="" class="h-full w-full object-cover">
            </button>
        </div>

        <!-- Full-screen viewer -->
        <Teleport to="body">
            <div
                v-if="open"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/75 p-3 sm:p-6"
                role="dialog"
                aria-modal="true"
                :aria-label="`${title} photos`"
                @click.self="hide"
                @touchstart.passive="swipeStart"
                @touchend.passive="swipeEnd"
            >
                <div class="relative overflow-hidden bg-black shadow-2xl" :style="{ width: 'min(100%, 640px, calc(100dvh - 7rem))' }">
                    <button ref="closeButton" type="button" class="absolute top-1 right-1 z-10 flex h-11 w-11 items-center justify-center rounded-full text-gray-700 hover:bg-gray-100" aria-label="Close" @click="hide">
                        <X class="h-6 w-6" />
                    </button>
                    <img :src="current" :alt="title" class="aspect-square w-full bg-black object-contain">
                    <div class="flex items-center justify-between gap-3 bg-neutral-900 px-4 py-2.5 text-sm text-white">
                        <span class="min-w-0 truncate">{{ title }}</span>
                        <span class="shrink-0 text-gray-300">{{ index + 1 }} of {{ images.length }}</span>
                    </div>
                </div>

                <template v-if="many">
                    <button type="button" class="absolute top-1/2 left-2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-neutral-900/85 text-white hover:bg-neutral-900 sm:left-5" aria-label="Previous photo" @click.stop="go(-1)">
                        <ChevronLeft class="h-6 w-6" />
                    </button>
                    <button type="button" class="absolute top-1/2 right-2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-neutral-900/85 text-white hover:bg-neutral-900 sm:right-5" aria-label="Next photo" @click.stop="go(1)">
                        <ChevronRight class="h-6 w-6" />
                    </button>
                </template>
            </div>
        </Teleport>
    </div>
</template>
