<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, X } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatPrice } from '@/currency';
import { uploadProblem } from '@/uploadGuard';

const props = defineProps({
    title: String,
    base: String,
    columns: Array,
    fields: Array,
    items: Object,
    filters: Object,
    searchable: Boolean,
});

const search = ref(props.filters.q ?? '');
const editingId = ref(null);
const showForm = ref(false);
const preview = ref(null);

function blank() {
    const data = { _method: 'post' };
    props.fields.forEach((f) => {
        data[f.key] = f.type === 'image' ? null : (f.default ?? (f.type === 'checkbox' ? false : ''));
    });
    return data;
}

const form = useForm(blank());
const imageField = computed(() => props.fields.find((f) => f.type === 'image'));

function openCreate() {
    editingId.value = null;
    form.defaults(blank()).reset();
    form.clearErrors();
    preview.value = null;
    showForm.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    const data = { _method: 'put' };
    props.fields.forEach((f) => {
        if (f.type === 'image') data[f.key] = null;
        else if (f.type === 'date') data[f.key] = item[f.key] ? String(item[f.key]).slice(0, 10) : '';
        else data[f.key] = item[f.key] ?? (f.type === 'checkbox' ? false : '');
    });
    form.defaults(data).reset();
    form.clearErrors();
    preview.value = imageField.value ? item[imageField.value.key] : null;
    showForm.value = true;
}

function pickImage(key, event) {
    const file = event.target.files[0] ?? null;
    const problem = uploadProblem(file);
    if (problem) {
        event.target.value = '';
        form.setError(key, problem);
        return;
    }
    form.clearErrors(key);
    form[key] = file;
    if (file) preview.value = URL.createObjectURL(file);
}

// The box never closes by accident: a click next to it is ignored while something is typed or
// uploaded, and the Cancel / X buttons ask before throwing the entered data away.
let pressedOnBackdrop = false;
function closeForm() {
    if (form.processing) return;
    if (form.isDirty && !window.confirm('Close without saving? What you entered will be lost.')) return;
    showForm.value = false;
}
function onBackdropClick() {
    const real = pressedOnBackdrop;
    pressedOnBackdrop = false;
    if (real && !form.isDirty && !form.processing) showForm.value = false;
}

function submit() {
    const url = editingId.value ? `${props.base}/${editingId.value}` : props.base;
    form.post(url, { forceFormData: true, preserveScroll: true, onSuccess: () => (showForm.value = false) });
}

function destroy(item) {
    if (window.confirm('Delete this item? This cannot be undone.')) {
        router.delete(`${props.base}/${item.id}`, { preserveScroll: true });
    }
}

function runSearch() {
    router.get(props.base, search.value ? { q: search.value } : {}, { preserveState: true });
}

// Phone/tablet cards: the image column (if any) and the first text column form the card header.
const imageColumn = computed(() => props.columns.find((c) => c.format === 'image'));
const titleColumn = computed(() => props.columns.find((c) => c.format !== 'image'));
const detailColumns = computed(() => props.columns.filter((c) => c !== imageColumn.value && c !== titleColumn.value));

const singular = computed(() => props.title.replace(/ies$/, 'y').replace(/s$/, ''));
const input = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 focus:outline-none';
</script>

<template>
    <Head :title="title" />

    <AdminLayout :title="title">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <form v-if="searchable" class="relative w-full sm:w-64" @submit.prevent="runSearch">
                <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input v-model="search" type="search" :placeholder="`Search ${title.toLowerCase()}...`" class="w-full rounded-lg border border-gray-200 bg-white py-2.5 pr-3 pl-9 text-sm focus:border-green-500 focus:outline-none sm:py-2">
            </form>
            <button type="button" class="flex w-full items-center justify-center gap-1.5 rounded-full bg-green-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-700 sm:ml-auto sm:w-auto sm:py-2" @click="openCreate">
                <Plus class="h-4 w-4" /> Add {{ singular.toLowerCase() }}
            </button>
        </div>

        <!-- Phones & tablets: cards -->
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:hidden">
            <div v-for="item in items.data" :key="item.id" class="rounded-2xl bg-white p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <img loading="lazy" decoding="async" v-if="imageColumn" :src="item[imageColumn.key]" :alt="String(item[titleColumn?.key] ?? '')" class="h-12 w-20 shrink-0 rounded-lg bg-gray-100 object-cover">
                    <div class="min-w-0 flex-1">
                        <p v-if="titleColumn" class="font-semibold break-words text-gray-800" :class="titleColumn.format === 'mono' ? 'font-mono' : ''">{{ item[titleColumn.key] ?? '-' }}</p>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <button type="button" class="inline-flex rounded-lg p-2.5 text-gray-500 hover:bg-gray-100" title="Edit" aria-label="Edit" @click="openEdit(item)"><Pencil class="h-4 w-4" /></button>
                        <button type="button" class="inline-flex rounded-lg p-2.5 text-rose-500 hover:bg-rose-50" title="Delete" aria-label="Delete" @click="destroy(item)"><Trash2 class="h-4 w-4" /></button>
                    </div>
                </div>
                <dl v-if="detailColumns.length" class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                    <div v-for="c in detailColumns" :key="c.key" class="min-w-0">
                        <dt class="text-xs text-gray-400">{{ c.label }}</dt>
                        <dd class="break-words">
                            <StatusBadge v-if="c.format === 'active'" :value="item[c.key] ? 'active' : 'inactive'" />
                            <span v-else-if="c.format === 'money'">{{ formatPrice(item[c.key]) }}</span>
                            <span v-else-if="c.format === 'mono'" class="font-mono font-semibold">{{ item[c.key] }}</span>
                            <span v-else :class="c.format === 'muted' ? 'text-gray-500' : 'font-medium text-gray-800'">{{ item[c.key] ?? '-' }}</span>
                        </dd>
                    </div>
                </dl>
            </div>
            <p v-if="!items.data.length" class="rounded-2xl bg-white p-8 text-center text-gray-400 shadow-sm md:col-span-2">Nothing here yet.</p>
        </div>

        <!-- Desktops: table -->
        <div class="hidden overflow-x-auto rounded-2xl bg-white shadow-sm xl:block">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs text-gray-400 uppercase">
                    <tr><th v-for="c in columns" :key="c.key" class="p-3">{{ c.label }}</th><th class="pr-3 text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="item in items.data" :key="item.id">
                        <td v-for="c in columns" :key="c.key" class="p-3">
                            <img loading="lazy" decoding="async" v-if="c.format === 'image'" :src="item[c.key]" class="h-10 w-16 rounded-lg bg-gray-100 object-cover">
                            <StatusBadge v-else-if="c.format === 'active'" :value="item[c.key] ? 'active' : 'inactive'" />
                            <span v-else-if="c.format === 'money'">{{ formatPrice(item[c.key]) }}</span>
                            <span v-else-if="c.format === 'mono'" class="font-mono font-semibold">{{ item[c.key] }}</span>
                            <span v-else :class="c.format === 'muted' ? 'text-gray-500' : 'font-medium text-gray-800'">{{ item[c.key] ?? '-' }}</span>
                        </td>
                        <td class="pr-3 text-right whitespace-nowrap">
                            <button type="button" class="inline-flex rounded-lg p-2 text-gray-500 hover:bg-gray-100" title="Edit" aria-label="Edit" @click="openEdit(item)"><Pencil class="h-4 w-4" /></button>
                            <button type="button" class="inline-flex rounded-lg p-2 text-rose-500 hover:bg-rose-50" title="Delete" aria-label="Delete" @click="destroy(item)"><Trash2 class="h-4 w-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!items.data.length"><td :colspan="columns.length + 1" class="p-8 text-center text-gray-400">Nothing here yet.</td></tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="items.links" />

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" @mousedown="pressedOnBackdrop = $event.target === $event.currentTarget" @click.self="onBackdropClick">
            <form class="max-h-[92dvh] w-full max-w-lg space-y-4 overflow-y-auto overscroll-contain rounded-t-2xl bg-white p-4 shadow-xl sm:max-h-[90dvh] sm:rounded-2xl sm:p-6" @submit.prevent="submit">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-gray-900">{{ editingId ? 'Edit' : 'Add' }} {{ singular.toLowerCase() }}</h2>
                    <button type="button" class="-m-2 p-2" aria-label="Close" @click="closeForm"><X class="h-5 w-5 text-gray-400" /></button>
                </div>

                <div v-for="f in fields" :key="f.key">
                    <label v-if="f.type !== 'checkbox'" class="mb-1 block text-sm font-medium text-gray-700">{{ f.label }}<span v-if="f.required || (f.requiredOnCreate && !editingId)" class="text-rose-500"> *</span></label>

                    <select v-if="f.type === 'select'" v-model="form[f.key]" :class="input"><option v-for="o in f.options" :key="o.value" :value="o.value">{{ o.label }}</option></select>
                    <template v-else-if="f.type === 'image'">
                        <img v-if="preview" :src="preview" class="mb-2 w-full rounded-lg bg-gray-100 object-cover" :class="f.previewWide ? 'aspect-[3/1]' : 'h-28'" alt="Picture preview">
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-full file:border-0 file:bg-green-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-green-700" @change="pickImage(f.key, $event)">
                        <p v-if="f.hint" class="mt-1.5 rounded-lg bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-900">{{ f.hint }}</p>
                    </template>
                    <label v-else-if="f.type === 'checkbox'" class="flex items-center gap-2 py-1 text-sm text-gray-700"><input v-model="form[f.key]" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-green-600"> {{ f.label }}</label>
                    <input v-else v-model="form[f.key]" :type="f.type" :step="f.type === 'number' ? '0.01' : undefined" :placeholder="f.placeholder" :class="input">

                    <p v-if="form.errors[f.key]" class="mt-1 text-xs text-rose-600">{{ form.errors[f.key] }}</p>
                </div>

                <div class="flex flex-col-reverse gap-2 pt-2 min-[480px]:flex-row min-[480px]:justify-end">
                    <button type="button" class="rounded-full border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeForm">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="rounded-full bg-green-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60">{{ form.processing ? 'Saving...' : 'Save' }}</button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
