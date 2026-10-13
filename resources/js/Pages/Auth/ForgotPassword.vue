<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthCard from '@/Components/AuthCard.vue';
import { routes } from '@/routes';

const props = defineProps({
    from: { type: String, default: '' },
    email: { type: String, default: '' },
});

const form = useForm({ email: props.email, from: props.from });
const backHref = computed(() => (props.from === 'admin' ? '/admin/login' : routes.login()));
</script>

<template>
    <Head title="Forgot password" />

    <AuthCard title="Forgot your password?" subtitle="Type your email. We send a 6-digit code to it, then you choose a new password.">
        <form class="space-y-4" @submit.prevent="form.post(routes.forgotPassword())">
            <div>
                <label for="forgotpassword-15" class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                <input id="forgotpassword-15" v-model="form.email" placeholder="rakibhossain@gmail.com" type="email" autocomplete="email" autofocus required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 focus:outline-none">
                <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
            </div>
            <button type="submit" :disabled="form.processing" class="w-full rounded-full bg-green-600 py-2.5 font-semibold text-white hover:bg-green-700 disabled:opacity-60">
                {{ form.processing ? 'Sending...' : 'Send code' }}
            </button>
        </form>

        <template #footer>
            Remembered it?
            <Link :href="backHref" class="font-semibold text-green-700 hover:underline">Back to sign in</Link>
        </template>
    </AuthCard>
</template>
