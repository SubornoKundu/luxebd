<script setup>
import PasswordInput from '@/Components/PasswordInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthCard from '@/Components/AuthCard.vue';
import { routes } from '@/routes';

const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '' });

function submit() {
    form.post(routes.register(), { onFinish: () => form.reset('password', 'password_confirmation') });
}

const input = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 focus:outline-none';
</script>

<template>
    <Head title="Create account" />

    <AuthCard title="Create your account" subtitle="It only takes a minute -- no email verification needed.">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="register-23" class="mb-1 block text-sm font-medium text-gray-700">Full name</label>
                <input id="register-23" v-model="form.name" placeholder="Rakib Hossain" type="text" autocomplete="name" autofocus required :class="input">
                <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label for="register-28" class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                <input id="register-28" v-model="form.email" placeholder="rakibhossain@gmail.com" type="email" autocomplete="email" required :class="input">
                <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
            </div>
            <div>
                <label for="register-33" class="mb-1 block text-sm font-medium text-gray-700">Phone <span class="font-normal text-gray-400">(optional)</span></label>
                <input id="register-33" v-model="form.phone" placeholder="01712345678" type="tel" autocomplete="tel" :class="input">
                <p v-if="form.errors.phone" class="mt-1 text-xs text-rose-600">{{ form.errors.phone }}</p>
            </div>
            <div>
                <label for="register-38" class="mb-1 block text-sm font-medium text-gray-700">Password</label>
                <PasswordInput id="register-38" v-model="form.password" placeholder="Minimum 8 characters" autocomplete="new-password" required :class="input" />
                <p class="mt-1 text-xs text-gray-400">At least 8 characters.</p>
                <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p>
            </div>
            <div>
                <label for="register-44" class="mb-1 block text-sm font-medium text-gray-700">Confirm password</label>
                <PasswordInput id="register-44" v-model="form.password_confirmation" placeholder="Type the password again" autocomplete="new-password" required :class="input" />
            </div>
            <button type="submit" :disabled="form.processing" class="w-full rounded-full bg-green-600 py-2.5 font-semibold text-white hover:bg-green-700 disabled:opacity-60">
                {{ form.processing ? 'Creating account...' : 'Create account' }}
            </button>
        </form>

        <template #footer>
            Already have an account?
            <Link :href="routes.login()" class="font-semibold text-green-700 hover:underline">Sign in</Link>
        </template>
    </AuthCard>
</template>
