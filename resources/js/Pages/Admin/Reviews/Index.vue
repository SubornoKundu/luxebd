<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Eye, EyeOff, Star, Trash2 } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({ reviews: Object, filters: Object });

function filter(event) {
    router.get('/admin/reviews', event.target.value ? { status: event.target.value } : {}, { preserveState: true });
}

function toggle(review) {
    router.patch(`/admin/reviews/${review.id}`, {}, { preserveScroll: true });
}

function destroy(review) {
    if (window.confirm('Delete this review permanently?')) router.delete(`/admin/reviews/${review.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Reviews" />

    <AdminLayout title="Reviews">
        <select :value="filters.status" class="mb-4 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-green-500 focus:outline-none sm:w-auto sm:py-2" aria-label="Filter reviews" @change="filter">
            <option value="">All reviews</option><option value="visible">Visible</option><option value="hidden">Hidden</option>
        </select>

        <div class="space-y-3">
            <div v-for="r in reviews.data" :key="r.id" class="flex items-start justify-between gap-3 rounded-2xl bg-white p-4 shadow-sm sm:gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="flex"><Star v-for="n in 5" :key="n" class="h-4 w-4" :class="n <= r.rating ? 'fill-amber-400 text-amber-400' : 'text-gray-200'" /></span>
                        <a :href="`/products/${r.product?.slug}`" target="_blank" class="min-w-0 text-sm font-semibold break-words text-gray-800 hover:text-green-700">{{ r.product?.name }}</a>
                        <StatusBadge :value="r.is_approved ? 'active' : 'inactive'" />
                    </div>
                    <p class="mt-1 text-sm break-words text-gray-600">{{ r.comment || 'No comment.' }}</p>
                    <p class="mt-1 text-xs break-all text-gray-400">{{ r.user?.name }} &middot; {{ r.user?.email }} &middot; {{ new Date(r.created_at).toLocaleDateString() }}</p>
                </div>
                <div class="flex shrink-0 flex-col gap-1 sm:flex-row">
                    <button type="button" class="rounded-lg p-2.5 text-gray-500 hover:bg-gray-100 sm:p-2" :aria-label="r.is_approved ? 'Hide' : 'Show'" :title="r.is_approved ? 'Hide' : 'Show'" @click="toggle(r)"><component :is="r.is_approved ? EyeOff : Eye" class="h-4 w-4" /></button>
                    <button type="button" class="rounded-lg p-2.5 text-rose-500 hover:bg-rose-50 sm:p-2" aria-label="Delete" title="Delete" @click="destroy(r)"><Trash2 class="h-4 w-4" /></button>
                </div>
            </div>
            <p v-if="!reviews.data.length" class="rounded-2xl bg-white p-8 text-center text-gray-400 shadow-sm">No reviews yet.</p>
        </div>
        <Pagination :links="reviews.links" />
    </AdminLayout>
</template>
