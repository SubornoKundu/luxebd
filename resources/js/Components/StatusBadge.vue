<script setup>
/**
 * One coloured pill. `kind` says what the status is about, so two pills side
 * by side are never confusing ("Order: Pending" vs "Payment: Pending").
 * Without `kind` only the status word is shown (used in table columns that
 * already have a heading).
 */
defineProps({
    value: { type: String, required: true },
    kind: { type: String, default: null }, // 'order' | 'payment' | null
});

const styles = {
    pending: 'bg-amber-100 text-amber-700',
    processing: 'bg-blue-100 text-blue-700',
    shipped: 'bg-indigo-100 text-indigo-700',
    completed: 'bg-green-100 text-green-700',
    paid: 'bg-green-100 text-green-700',
    cancelled: 'bg-gray-200 text-gray-600',
    failed: 'bg-rose-100 text-rose-700',
    refunded: 'bg-purple-100 text-purple-700',
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-gray-200 text-gray-600',
};

const prefixes = { order: 'Order', payment: 'Payment' };
</script>

<template>
    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap" :class="styles[value] ?? 'bg-gray-100 text-gray-600'">
        <span v-if="kind && prefixes[kind]" class="mr-1 font-normal">{{ prefixes[kind] }}:</span><span class="capitalize">{{ value === 'completed' ? 'delivered' : value }}</span>
    </span>
</template>
