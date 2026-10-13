<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, EyeOff, Search, Send, Trash2 } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { routes } from '@/routes';

const props = defineProps({ questions: Object, filters: Object, counts: Object });

const f = reactive({ ...props.filters });
const drafts = reactive({});

function apply(extra = {}) {
    Object.assign(f, extra);
    router.get('/admin/questions', Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '')), { preserveState: true });
}

function answer(q) {
    const text = (drafts[q.id] ?? q.answer ?? '').trim();
    if (text.length < 2) return;
    router.patch(`/admin/questions/${q.id}`, { answer: text }, { preserveScroll: true, onSuccess: () => { delete drafts[q.id]; } });
}

function toggle(q) {
    router.patch(`/admin/questions/${q.id}/toggle`, {}, { preserveScroll: true });
}

function destroy(q) {
    if (window.confirm('Delete this question?')) router.delete(`/admin/questions/${q.id}`, { preserveScroll: true });
}

const tabs = [
    { key: '', label: 'All' },
    { key: 'open', label: 'Unanswered' },
    { key: 'answered', label: 'Answered' },
    { key: 'hidden', label: 'Hidden' },
];

const when = (v) => new Date(v).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head title="Product Q&A" />

    <AdminLayout title="Product Q&A">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <div class="flex max-w-full flex-wrap gap-1.5">
                <button v-for="t in tabs" :key="t.key" type="button" class="min-h-9 shrink-0 rounded-full border px-3.5 text-sm font-medium" :class="f.status === t.key ? 'border-green-600 bg-green-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'" @click="apply({ status: t.key })">
                    {{ t.label }}<span v-if="t.key === 'open' && counts.open" class="ml-1.5 rounded-full bg-rose-500 px-1.5 text-[10px] font-bold text-white">{{ counts.open }}</span>
                </button>
            </div>
            <form class="relative w-full sm:ml-auto sm:w-64" @submit.prevent="apply()">
                <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input v-model="f.q" type="search" placeholder="Search questions..." class="w-full rounded-lg border border-gray-200 bg-white py-2.5 pr-3 pl-9 text-sm focus:border-green-500 focus:outline-none sm:py-2">
            </form>
        </div>

        <ul class="space-y-3">
            <li v-for="q in questions.data" :key="q.id" class="rounded-2xl bg-white p-4 shadow-sm" :class="q.is_published ? '' : 'opacity-70'">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <img loading="lazy" decoding="async" v-if="q.product" :src="q.product.image" :alt="q.product.name" class="h-10 w-10 shrink-0 rounded-lg bg-gray-100 object-cover">
                        <div class="min-w-0">
                            <Link v-if="q.product" :href="routes.productShow(q.product.slug)" class="line-clamp-1 text-sm font-semibold text-green-700 hover:underline">{{ q.product.name }}</Link>
                            <p class="truncate text-xs text-gray-400">{{ q.user?.name ?? 'Customer' }} &middot; {{ when(q.created_at) }}<span v-if="!q.is_published"> &middot; hidden</span></p>
                        </div>
                    </div>
                    <div class="flex shrink-0">
                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" :title="q.is_published ? 'Hide' : 'Show'" :aria-label="q.is_published ? 'Hide question' : 'Show question'" @click="toggle(q)"><component :is="q.is_published ? EyeOff : Eye" class="h-4 w-4" /></button>
                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-lg text-rose-500 hover:bg-rose-50" title="Delete" aria-label="Delete question" @click="destroy(q)"><Trash2 class="h-4 w-4" /></button>
                    </div>
                </div>
                <p class="mt-3 text-sm font-medium break-words text-gray-900">Q: {{ q.question }}</p>
                <form class="mt-2 flex flex-col gap-2 sm:flex-row" @submit.prevent="answer(q)">
                    <textarea :value="drafts[q.id] ?? q.answer ?? ''" rows="2" :placeholder="q.answer ? '' : 'Write an answer...'" aria-label="Answer" class="min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:outline-none" @input="drafts[q.id] = $event.target.value" />
                    <button type="submit" class="flex min-h-10 items-center justify-center gap-1.5 self-start rounded-full bg-green-600 px-4 text-sm font-semibold text-white hover:bg-green-700 sm:self-end"><Send class="h-3.5 w-3.5" /> {{ q.answer ? 'Update' : 'Answer' }}</button>
                </form>
            </li>
            <li v-if="!questions.data.length" class="rounded-2xl bg-white p-8 text-center text-gray-400 shadow-sm">No questions here.</li>
        </ul>
        <Pagination :links="questions.links" />
    </AdminLayout>
</template>
