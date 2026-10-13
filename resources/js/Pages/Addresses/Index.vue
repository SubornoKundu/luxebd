<script setup>
import { nextTick, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Star, Trash2 } from '@lucide/vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import { routes } from '@/routes';

const props = defineProps({
    addresses: { type: Array, required: true },
    max: { type: Number, default: 10 },
});

const editingId = ref(null);
const open = ref(false);

const form = useForm({ label: 'Home', name: '', phone: '', address: '', city: '', is_default: false });

function startNew() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
    nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
}

function startEdit(a) {
    editingId.value = a.id;
    form.clearErrors();
    Object.assign(form, { label: a.label, name: a.name, phone: a.phone, address: a.address, city: a.city ?? '', is_default: !!a.is_default });
    open.value = true;
    // The form sits above the list - bring it into view on phones.
    nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
}

function submit() {
    const opts = { preserveScroll: true, onSuccess: (page) => { if (page.props.flash?.error) return; open.value = false; form.reset(); } };
    if (editingId.value) form.put(routes.addressUpdate(editingId.value), opts);
    else form.post(routes.addressStore(), opts);
}

function makeDefault(a) {
    router.patch(routes.addressDefault(a.id), {}, { preserveScroll: true });
}

function remove(a) {
    if (window.confirm('Delete this address?')) router.delete(routes.addressDestroy(a.id), { preserveScroll: true });
}

const input = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 focus:outline-none';
</script>

<template>
    <Head title="Address Book" />

    <MainLayout>
        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3 sm:mb-6">
                <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">Address Book</h1>
                <button v-if="addresses.length < max" type="button" class="flex min-h-10 items-center gap-1.5 rounded-full bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700" @click="startNew">
                    <Plus class="h-4 w-4" /> Add address
                </button>
            </div>

            <form v-if="open" class="mb-6 space-y-4 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6" @submit.prevent="submit">
                <h2 class="font-bold text-gray-900">{{ editingId ? 'Edit address' : 'New address' }}</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="index-68" class="mb-1 block text-sm font-medium text-gray-700">Label</label>
                        <input id="index-68" v-model="form.label" placeholder="Home, Office, Mother's house" type="text" :class="input">
                        <p v-if="form.errors.label" class="mt-1 text-xs text-rose-600">{{ form.errors.label }}</p>
                    </div>
                    <div>
                        <label for="index-73" class="mb-1 block text-sm font-medium text-gray-700">Full name</label>
                        <input id="index-73" v-model="form.name" placeholder="Rakib Hossain" type="text" autocomplete="name" :class="input">
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label for="index-78" class="mb-1 block text-sm font-medium text-gray-700">Phone</label>
                        <input id="index-78" v-model="form.phone" placeholder="01712345678" type="tel" inputmode="tel" :class="input">
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-rose-600">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <label for="index-83" class="mb-1 block text-sm font-medium text-gray-700">City (optional)</label>
                        <input id="index-83" v-model="form.city" placeholder="Dhaka" type="text" :class="input">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="index-87" class="mb-1 block text-sm font-medium text-gray-700">Address</label>
                        <input id="index-87" v-model="form.address" placeholder="House 12, Road 5, Dhanmondi" type="text" :class="input">
                        <p v-if="form.errors.address" class="mt-1 text-xs text-rose-600">{{ form.errors.address }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-600 sm:col-span-2">
                        <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300 text-green-600"> Make this my default address
                    </label>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" :disabled="form.processing" class="rounded-full bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60">{{ form.processing ? 'Saving...' : 'Save address' }}</button>
                    <button type="button" class="rounded-full border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="open = false">Cancel</button>
                </div>
            </form>

            <div v-if="!addresses.length && !open" class="rounded-2xl border border-dashed border-gray-300 py-16 text-center">
                <MapPin class="mx-auto h-10 w-10 text-gray-300" />
                <p class="mt-3 text-gray-500">No saved addresses yet.</p>
            </div>

            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <li v-for="a in addresses" :key="a.id" class="flex min-w-0 flex-col justify-between gap-3 rounded-2xl border bg-white p-4" :class="a.is_default ? 'border-green-400' : 'border-gray-200'">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-900">{{ a.label }}</span>
                            <span v-if="a.is_default" class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-700">Default</span>
                        </div>
                        <p class="mt-1 text-sm break-words text-gray-700">{{ a.name }} &middot; {{ a.phone }}</p>
                        <p class="text-sm break-words text-gray-500">{{ a.address }}<span v-if="a.city">, {{ a.city }}</span></p>
                    </div>
                    <div class="flex flex-wrap gap-1.5 text-xs">
                        <button v-if="!a.is_default" type="button" class="flex min-h-9 items-center gap-1 rounded-full border border-gray-200 px-3 py-1.5 font-medium text-gray-600 hover:border-green-500 hover:text-green-700" @click="makeDefault(a)"><Star class="h-3.5 w-3.5" /> Set default</button>
                        <button type="button" class="flex min-h-9 items-center gap-1 rounded-full border border-gray-200 px-3 py-1.5 font-medium text-gray-600 hover:bg-gray-50" @click="startEdit(a)"><Pencil class="h-3.5 w-3.5" /> Edit</button>
                        <button type="button" class="flex min-h-9 items-center gap-1 rounded-full border border-rose-200 px-3 py-1.5 font-medium text-rose-600 hover:bg-rose-50" @click="remove(a)"><Trash2 class="h-3.5 w-3.5" /> Delete</button>
                    </div>
                </li>
            </ul>
        </div>
    </MainLayout>
</template>
