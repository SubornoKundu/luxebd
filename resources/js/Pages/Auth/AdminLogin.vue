<script setup>
import PasswordInput from '@/Components/PasswordInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SiteLogo from '@/Components/SiteLogo.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { ShieldCheck } from '@lucide/vue';
import { routes } from '@/routes';

const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post('/admin/login', { onFinish: () => form.reset('password') });
}

const input = 'w-full rounded-lg border border-white/15 bg-white/5 px-3.5 py-3 text-sm text-white placeholder:text-white/40 focus:border-gold-400 focus:ring-2 focus:ring-gold-400/25 focus:outline-none [&:-webkit-autofill]:shadow-[inset_0_0_0_100px_#1b1812] [&:-webkit-autofill]:[-webkit-text-fill-color:#fff]';
</script>

<template>
    <Head title="Admin sign in">
        <meta name="robots" content="noindex, nofollow">
    </Head>

    <main class="relative flex min-h-dvh items-center justify-center overflow-hidden bg-black px-4 py-10">
        <FlashMessages />
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(226,185,63,0.18),transparent_60%),radial-gradient(ellipse_at_bottom,rgba(201,154,31,0.10),transparent_55%)]" aria-hidden="true" />

        <div class="relative w-full max-w-md">
            <Link :href="routes.home()" class="mb-8 flex items-center justify-center" aria-label="Back to the shop">
                <SiteLogo size="lg" />
            </Link>

            <div class="rounded-2xl border border-gold-500/40 bg-[#0f0d0a]/90 p-6 shadow-[0_0_60px_-15px_rgba(226,185,63,0.35)] backdrop-blur sm:p-8">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gold-400/15 text-gold-300"><ShieldCheck class="h-5 w-5" aria-hidden="true" /></span>
                    <div>
                        <h1 class="text-xl font-bold text-white">Admin sign in</h1>
                        <p class="text-sm text-white/60">Store team only. Customers sign in from the shop.</p>
                    </div>
                </div>

                <form class="mt-7 space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="admin-email" class="mb-1.5 block text-sm font-medium text-white/85">Email</label>
                        <input id="admin-email" v-model="form.email" type="email" autocomplete="username" autofocus required placeholder="Your email" :class="input">
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-rose-400" role="alert">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label for="admin-password" class="block text-sm font-medium text-white/85">Password</label>
                            <Link :href="`${routes.forgotPassword()}?from=admin`" class="inline-block py-1 text-xs font-medium text-gold-300 hover:underline">Forgot password?</Link>
                        </div>
                        <PasswordInput id="admin-password" v-model="form.password" placeholder="Your password" autocomplete="current-password" required :class="input" />
                        <p v-if="form.errors.password" class="mt-1.5 text-xs text-rose-400" role="alert">{{ form.errors.password }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-white/70">
                        <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-white/30 bg-transparent accent-[#c99a1f]"> Keep me signed in
                    </label>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-full bg-gradient-to-b from-gold-300 to-gold-500 py-3 font-semibold text-black shadow-lg shadow-gold-500/20 transition hover:from-gold-300 hover:to-gold-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-300 disabled:opacity-60">
                        {{ form.processing ? 'Signing in...' : 'Sign in' }}
                    </button>
                </form>
            </div>

            <div class="mt-5 text-center text-sm">
                <Link :href="routes.home()" class="inline-block py-2 font-medium text-white/70 hover:text-gold-300">Back to the shop</Link>
            </div>
        </div>
    </main>
</template>
