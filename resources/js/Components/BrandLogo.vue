<script>
// Shared by every BrandLogo on the page: logo address -> the same logo with its
// empty edges cut away (or null when there was nothing to cut / it cannot be read).
const cache = new Map();

/** "Empty" = see-through or (nearly) white, so a logo saved with a big blank margin can fill its box. */
function trimmed(src) {
    if (cache.has(src)) return cache.get(src);

    const job = new Promise((resolve) => {
        const img = new Image();
        img.decoding = 'async';
        img.onerror = () => resolve(null);
        img.onload = () => {
            try {
                const w = img.naturalWidth;
                const h = img.naturalHeight;
                if (!w || !h || w * h > 4_000_000) return resolve(null);

                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(img, 0, 0);
                const d = ctx.getImageData(0, 0, w, h).data;

                let x0 = w, y0 = h, x1 = -1, y1 = -1;
                for (let y = 0; y < h; y++) {
                    for (let x = 0; x < w; x++) {
                        const i = (y * w + x) * 4;
                        if (d[i + 3] < 40 || (d[i] >= 232 && d[i + 1] >= 232 && d[i + 2] >= 232)) continue;
                        if (x < x0) x0 = x;
                        if (x > x1) x1 = x;
                        if (y < y0) y0 = y;
                        if (y > y1) y1 = y;
                    }
                }

                if (x1 < 0) return resolve(null); // nothing but blank: leave it as it is
                x0 = Math.max(0, x0 - 1);
                y0 = Math.max(0, y0 - 1);
                x1 = Math.min(w - 1, x1 + 1);
                y1 = Math.min(h - 1, y1 + 1);
                const cw = x1 - x0 + 1;
                const ch = y1 - y0 + 1;
                if (cw >= w * 0.96 && ch >= h * 0.96) return resolve(null); // already fills the picture

                const out = document.createElement('canvas');
                out.width = cw;
                out.height = ch;
                out.getContext('2d').drawImage(canvas, x0, y0, cw, ch, 0, 0, cw, ch);
                resolve(out.toDataURL('image/png'));
            } catch {
                resolve(null); // e.g. picture hosted elsewhere and not readable: show it untouched
            }
        };
        img.src = src;
    });

    cache.set(src, job);
    return job;
}
</script>

<script setup>
import { ref, watch } from 'vue';

// A brand logo that fills its box. The brand name is inside the logo itself, so no text is shown.
const props = defineProps({
    src: { type: String, required: true },
    alt: { type: String, default: '' },
});

const shown = ref(props.src);
const ready = ref(false);

watch(
    () => props.src,
    async (src) => {
        ready.value = false;
        shown.value = src;
        const fitted = await trimmed(src);
        if (src === props.src) {
            shown.value = fitted || src;
            ready.value = true;
        }
    },
    { immediate: true },
);
</script>

<template>
    <img :src="shown" :alt="alt" decoding="async" class="h-full w-full object-contain transition-opacity duration-150" :class="ready ? 'opacity-100' : 'opacity-0'" @error="ready = true">
</template>
