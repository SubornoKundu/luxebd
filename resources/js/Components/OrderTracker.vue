<script setup>
import { Check, X } from '@lucide/vue';

defineProps({
    steps: { type: Array, required: true },
    compact: { type: Boolean, default: false },
});

function when(value) {
    if (!value) return '';
    return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}
</script>

<template>
    <ol class="flex items-start" :aria-label="'Order progress'">
        <li v-for="(step, i) in steps" :key="step.key" class="relative min-w-0 flex-1 text-center">
            <div v-if="i > 0" class="absolute top-3.5 right-1/2 h-0.5 w-full" :class="step.done ? (step.key === 'cancelled' ? 'bg-rose-300' : 'bg-gold-500') : 'bg-gray-200'" />
            <span
                class="relative z-10 mx-auto flex items-center justify-center rounded-full text-xs font-bold"
                :class="[compact ? 'h-7 w-7' : 'h-8 w-8', step.done ? (step.key === 'cancelled' ? 'bg-rose-500 text-white' : 'bg-black text-gold-300') : 'bg-gray-200 text-gray-500']"
            >
                <X v-if="step.key === 'cancelled'" class="h-4 w-4" />
                <Check v-else-if="step.done" class="h-4 w-4" />
                <template v-else>{{ i + 1 }}</template>
            </span>
            <p class="mt-1.5 px-0.5 text-[11px] leading-tight font-medium sm:text-xs" :class="[compact ? 'hidden sm:block' : '', step.done ? 'text-gray-900' : 'text-gray-400']">{{ step.label }}</p>
            <p v-if="!compact && step.at" class="mt-0.5 hidden px-0.5 text-[10px] leading-tight text-gray-400 sm:block">{{ when(step.at) }}</p>
        </li>
    </ol>
</template>
