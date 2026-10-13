<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import AuthCard from '@/Components/AuthCard.vue';
import { routes } from '@/routes';

const props = defineProps({
    email: { type: String, default: '' },
    from: { type: String, default: '' },
});

// The email was typed on the previous page, so it is shown, not asked again.
const hasEmail = computed(() => props.email !== '');
const backHref = computed(() => (props.from === 'admin' ? '/admin/login' : routes.login()));
const changeEmailHref = computed(() => `${routes.forgotPassword()}?${new URLSearchParams({ email: props.email, ...(props.from ? { from: props.from } : {}) })}`);

// Send the code again to the same email (the server allows one every 45 seconds).
const wait = ref(45);
let timer = window.setInterval(() => { if (wait.value > 0) wait.value -= 1; }, 1000);
onBeforeUnmount(() => window.clearInterval(timer));

function resend() {
    if (wait.value > 0) return;
    router.post(routes.forgotPassword(), { email: props.email, from: props.from }, {
        preserveScroll: true,
        onSuccess: () => { wait.value = 45; },
    });
}

const form = useForm({ email: props.email, code: '', password: '', password_confirmation: '' });

// Step 1: the emailed code. Step 2 (only after the code is right): the new password.
const step = ref(1);

function checkCode() {
    form.clearErrors();
    form.post(routes.checkResetCode(), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => { step.value = 2; },
    });
}

function resetPassword() {
    form.post(routes.resetPassword(), {
        preserveScroll: true,
        // The code ran out or was refused: back to the code step.
        onError: (errors) => { if (errors.code || errors.email) step.value = 1; },
    });
}

const input = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 focus:outline-none';
</script>

<template>
    <Head title="Reset password" />

    <AuthCard
        :title="step === 1 ? 'Enter your security code' : 'Choose a new password'"
        :subtitle="step === 1 ? 'We emailed you a 6-digit code. Type it below.' : 'The code is correct. Now type the new password you want.'"
    >
        <!-- Step 1 -->
        <form v-if="step === 1" class="space-y-4" @submit.prevent="checkCode">
            <p v-if="hasEmail" class="rounded-lg bg-gray-50 px-3 py-2.5 text-sm text-gray-600">
                Code sent to <strong class="break-all text-gray-900">{{ form.email }}</strong>.
                <Link :href="changeEmailHref" class="font-medium text-green-700 hover:underline">Wrong email?</Link>
                <span class="mt-1 block text-xs text-gray-500">It can take a minute. Check your Spam folder too.</span>
            </p>
            <div v-else>
                <label for="resetpassword-23" class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                <input id="resetpassword-23" v-model="form.email" placeholder="rakibhossain@gmail.com" type="email" required :class="input">
                <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
            </div>
            <p v-if="hasEmail && form.errors.email" class="text-xs text-rose-600">{{ form.errors.email }}</p>
            <div>
                <label for="resetpassword-code" class="mb-1 block text-sm font-medium text-gray-700">Security code</label>
                <input id="resetpassword-code" v-model="form.code" placeholder="6-digit code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus :class="[input, 'tracking-widest']">
                <p v-if="form.errors.code" class="mt-1 text-xs text-rose-600">{{ form.errors.code }}</p>
            </div>
            <button type="submit" :disabled="form.processing" class="w-full rounded-full bg-green-600 py-2.5 font-semibold text-white hover:bg-green-700 disabled:opacity-60">
                {{ form.processing ? 'Checking...' : 'Continue' }}
            </button>
        </form>

        <!-- Step 2 -->
        <form v-else class="space-y-4" @submit.prevent="resetPassword">
            <p class="flex items-start gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-800">
                <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" />
                <span class="min-w-0 break-words">Code accepted for <strong>{{ form.email }}</strong>.</span>
            </p>
            <div>
                <label for="resetpassword-28" class="mb-1 block text-sm font-medium text-gray-700">New password</label>
                <PasswordInput id="resetpassword-28" v-model="form.password" placeholder="Minimum 8 characters" autocomplete="new-password" required autofocus :class="input" />
                <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p>
            </div>
            <div>
                <label for="resetpassword-33" class="mb-1 block text-sm font-medium text-gray-700">Confirm new password</label>
                <PasswordInput id="resetpassword-33" v-model="form.password_confirmation" placeholder="Type the password again" autocomplete="new-password" required :class="input" />
            </div>
            <button type="submit" :disabled="form.processing" class="w-full rounded-full bg-green-600 py-2.5 font-semibold text-white hover:bg-green-700 disabled:opacity-60">
                {{ form.processing ? 'Saving...' : 'Reset password' }}
            </button>
            <button type="button" class="w-full py-1 text-xs font-medium text-gray-500 hover:underline" @click="step = 1">Use a different code</button>
        </form>

        <template #footer>
            <p v-if="step === 1">
                Did not get the code?
                <button v-if="hasEmail" type="button" class="font-semibold text-green-700 hover:underline disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline" :disabled="wait > 0" @click="resend">
                    {{ wait > 0 ? `Send again in ${wait}s` : 'Send it again' }}
                </button>
                <Link v-else :href="routes.forgotPassword()" class="font-semibold text-green-700 hover:underline">Ask for a code</Link>
            </p>
            <p class="mt-2">
                Remembered your password?
                <Link :href="backHref" class="font-semibold text-green-700 hover:underline">Back to sign in</Link>
            </p>
        </template>
    </AuthCard>
</template>
