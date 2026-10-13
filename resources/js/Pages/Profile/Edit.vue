<script setup>
import PasswordInput from '@/Components/PasswordInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { KeyRound, Mail, User } from '@lucide/vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import { routes } from '@/routes';

const props = defineProps({
    user: { type: Object, required: true },
    hasPassword: { type: Boolean, default: true },
});

const form = useForm({
    name: props.user.name,
    phone: props.user.phone ?? '',
    address: props.user.address ?? '',
    city: props.user.city ?? '',
});

const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

function submit() {
    form.put(routes.profileUpdate(), { preserveScroll: true });
}

function changePassword() {
    passwordForm.put(routes.profilePassword(), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}

function formatDate(value) {
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
}

const input = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 focus:outline-none';
</script>

<template>
    <Head title="Your Profile" />

    <MainLayout>
        <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
            <h1 class="mb-5 text-xl font-bold text-gray-900 sm:mb-6 sm:text-2xl">Your Profile</h1>

            <div class="mb-6 flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-green-100 text-xl font-bold text-green-700">
                    {{ user.name.charAt(0).toUpperCase() }}
                </span>
                <div class="min-w-0">
                    <p class="font-bold break-words text-gray-900">{{ user.name }}</p>
                    <p class="flex items-start gap-1 text-sm break-all text-gray-500">
                        <Mail class="mt-0.5 h-3.5 w-3.5 shrink-0" /> {{ user.email }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-400">Member since {{ formatDate(user.created_at) }}</p>
                </div>
            </div>

            <form class="mb-6 space-y-4 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6" @submit.prevent="submit">
                <h2 class="flex items-center gap-2 font-bold text-gray-900">
                    <User class="h-4 w-4" /> Details & Default Address
                </h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="edit-67" class="mb-1 block text-sm font-medium text-gray-700">Full Name</label>
                        <input id="edit-67" v-model="form.name" placeholder="Rakib Hossain" type="text" autocomplete="name" :class="input">
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label for="edit-72" class="mb-1 block text-sm font-medium text-gray-700">Phone Number</label>
                        <input id="edit-72" v-model="form.phone" placeholder="01712345678" type="tel" inputmode="tel" autocomplete="tel" :class="input">
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-rose-600">{{ form.errors.phone }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="edit-77" class="mb-1 block text-sm font-medium text-gray-700">Street Address</label>
                        <input id="edit-77" v-model="form.address" placeholder="House 12, Road 5, Dhanmondi" type="text" :class="input">
                        <p v-if="form.errors.address" class="mt-1 text-xs text-rose-600">{{ form.errors.address }}</p>
                    </div>
                    <div>
                        <label for="edit-82" class="mb-1 block text-sm font-medium text-gray-700">City</label>
                        <input id="edit-82" v-model="form.city" placeholder="Dhaka" type="text" :class="input">
                        <p v-if="form.errors.city" class="mt-1 text-xs text-rose-600">{{ form.errors.city }}</p>
                    </div>
                    <div>
                        <label for="edit-87" class="mb-1 block text-sm font-medium text-gray-700">Email Address</label>
                        <input id="edit-87" :value="user.email" type="email" disabled class="w-full cursor-not-allowed rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                    </div>
                </div>
                <p class="text-xs text-gray-400">Your address is pre-filled at checkout.</p>

                <button type="submit" :disabled="form.processing" class="w-full rounded-full bg-green-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60 sm:w-auto">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </button>
            </form>

            <form class="space-y-4 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6" @submit.prevent="changePassword">
                <h2 class="flex items-center gap-2 font-bold text-gray-900">
                    <KeyRound class="h-4 w-4" /> {{ hasPassword ? 'Change Password' : 'Set a Password' }}
                </h2>
                <div v-if="hasPassword">
                    <label for="edit-103" class="mb-1 block text-sm font-medium text-gray-700">Current password</label>
                    <PasswordInput id="edit-103" v-model="passwordForm.current_password" placeholder="Your current password" autocomplete="current-password" :class="input" />
                    <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-rose-600">{{ passwordForm.errors.current_password }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="edit-109" class="mb-1 block text-sm font-medium text-gray-700">New password</label>
                        <PasswordInput id="edit-109" v-model="passwordForm.password" placeholder="Minimum 8 characters" autocomplete="new-password" :class="input" />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-rose-600">{{ passwordForm.errors.password }}</p>
                    </div>
                    <div>
                        <label for="edit-114" class="mb-1 block text-sm font-medium text-gray-700">Confirm new password</label>
                        <PasswordInput id="edit-114" v-model="passwordForm.password_confirmation" placeholder="Type the new password again" autocomplete="new-password" :class="input" />
                    </div>
                </div>
                <button type="submit" :disabled="passwordForm.processing" class="w-full rounded-full bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60 sm:w-auto">
                    Update Password
                </button>
            </form>
        </div>
    </MainLayout>
</template>
