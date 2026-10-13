<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, XCircle, X } from '@lucide/vue';

const page = usePage();
const visible = ref(false);

const message = computed(() => page.props.flash?.success || page.props.flash?.error || null);
const isError = computed(() => Boolean(page.props.flash?.error));

let hideTimer = null;

// Watch the flash object itself (a fresh one arrives with every response), so the
// same text twice in a row - e.g. two failed attempts - shows the toast again.
watch(
    () => page.props.flash,
    () => {
        if (message.value) {
            visible.value = true;
            window.clearTimeout(hideTimer);
            hideTimer = window.setTimeout(() => (visible.value = false), 4000);
        }
    },
    { immediate: true },
);
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
    >
        <div
            v-if="visible && message"
            class="fixed top-4 left-1/2 z-[80] w-[92%] max-w-md -translate-x-1/2"
        >
            <div
                class="flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg backdrop-blur"
                :class="isError
                    ? 'border-red-200 bg-red-50/95 text-red-800'
                    : 'border-green-200 bg-green-50/95 text-green-800'"
            >
                <CheckCircle2 v-if="!isError" class="mt-0.5 h-5 w-5 shrink-0" />
                <XCircle v-else class="mt-0.5 h-5 w-5 shrink-0" />
                <p class="flex-1 text-sm font-medium">{{ message }}</p>
                <button type="button" class="shrink-0 opacity-60 hover:opacity-100" @click="visible = false">
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>
    </Transition>
</template>
