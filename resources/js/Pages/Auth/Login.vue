<script setup>
import PasswordInput from '@/Components/PasswordInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthCard from '@/Components/AuthCard.vue';
import { routes } from '@/routes';

defineProps({
    notice: { type: String, default: null },
});

const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post(routes.login(), { onFinish: () => form.reset('password') });
}

const input = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 focus:outline-none';
</script>

<template>
    <Head title="Sign in" />

    <AuthCard title="Welcome back" subtitle="Sign in to your account to continue.">
        <p v-if="notice" class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2.5 text-sm text-blue-800" role="status">{{ notice }}</p>
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="login-28" class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                <input id="login-28" v-model="form.email" placeholder="rakibhossain@gmail.com" type="email" autocomplete="email" autofocus required :class="input">
                <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
            </div>
            <div>
                <div class="mb-1 flex items-center justify-between">
                    <label for="login-34" class="block text-sm font-medium text-gray-700">Password</label>
                    <Link :href="routes.forgotPassword()" class="inline-block py-2 text-xs font-medium text-green-700 hover:underline">Forgot password?</Link>
                </div>
                <PasswordInput id="login-34" v-model="form.password" placeholder="Your password" autocomplete="current-password" required :class="input" />
                <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input v-model="form.remember" type="checkbox" class="rounded border-gray-300 text-green-600"> Remember me
            </label>
            <button type="submit" :disabled="form.processing" class="w-full rounded-full bg-green-600 py-2.5 font-semibold text-white hover:bg-green-700 disabled:opacity-60">
                {{ form.processing ? 'Signing in...' : 'Sign in' }}
            </button>
        </form>

        <template #footer>
            New to {{ $page.props.site?.name || 'Vireluxe' }}?
            <Link :href="routes.register()" class="font-semibold text-green-700 hover:underline">Create an account</Link>
        </template>
    </AuthCard>
</template>
