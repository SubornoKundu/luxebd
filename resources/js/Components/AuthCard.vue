<script setup>
import { Link } from '@inertiajs/vue3';
import SiteLogo from '@/Components/SiteLogo.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { routes } from '@/routes';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <main class="flex min-h-dvh items-center justify-center bg-white px-4 py-10">
        <FlashMessages />
        <div class="w-full max-w-md">
            <Link :href="routes.home()" class="mb-6 flex items-center justify-center">
                <SiteLogo size="lg" />
            </Link>
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h1 class="text-xl font-bold text-gray-900">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-sm text-gray-500">{{ subtitle }}</p>
                <div class="mt-6"><slot /></div>
            </div>
            <div class="mt-4 text-center text-sm text-gray-500"><slot name="footer" /></div>
        </div>
    </main>
</template>
