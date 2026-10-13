<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Clock, Mail, MapPin, Phone, Truck } from '@lucide/vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import BrandIcon from '@/Components/BrandIcon.vue';
import LocationMap from '@/Components/LocationMap.vue';
import { routes } from '@/routes';

const props = defineProps({
    slug: { type: String, required: true },
    title: { type: String, required: true },
    intro: { type: String, default: '' },
    sections: { type: Array, default: () => [] },
});

const page = usePage();
const contact = computed(() => page.props.shop?.contact ?? {});
const whatsappUrl = computed(() => {
    const n = String(page.props.shop?.whatsapp ?? '').replace(/\D/g, '');
    return n ? `https://wa.me/${n}` : '';
});
const facebookUrl = computed(() => page.props.shop?.social?.facebook || '');
const instagramUrl = computed(() => page.props.shop?.social?.instagram || '');
const telHref = computed(() => 'tel:' + String(contact.value.phone ?? '').replace(/[^\d+]/g, ''));

const others = [
    ['about', 'About us'], ['contact', 'Contact us'], ['returns', 'Returns & refunds'],
    ['privacy', 'Privacy policy'], ['terms', 'Terms & conditions'],
];
</script>

<template>
    <Head :title="title" />

    <MainLayout>
        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 sm:py-10 lg:px-8">
            <h1 class="text-2xl font-bold break-words text-gray-900 sm:text-3xl">{{ title }}</h1>
            <p v-if="intro" class="mt-3 text-base leading-relaxed text-gray-600">{{ intro }}</p>

            <!-- Contact details come straight from Admin > Settings -->
            <div v-if="slug === 'contact'" class="mt-6 grid gap-3 sm:grid-cols-2">
                <div v-if="contact.phone" class="flex gap-3 rounded-2xl border border-gray-200 bg-white p-4">
                    <Phone class="mt-0.5 h-5 w-5 shrink-0 text-green-600" />
                    <div class="min-w-0"><p class="text-sm font-semibold text-gray-900">Hotline</p><a :href="telHref" class="inline-block py-2.5 text-sm break-words text-green-700 hover:underline">{{ contact.phone }}</a></div>
                </div>
                <div v-if="contact.email" class="flex gap-3 rounded-2xl border border-gray-200 bg-white p-4">
                    <Mail class="mt-0.5 h-5 w-5 shrink-0 text-green-600" />
                    <div class="min-w-0"><p class="text-sm font-semibold text-gray-900">Email</p><a :href="`mailto:${contact.email}`" class="inline-block py-2.5 text-sm break-all text-green-700 hover:underline">{{ contact.email }}</a></div>
                </div>
                <div v-if="contact.address" class="flex gap-3 rounded-2xl border border-gray-200 bg-white p-4">
                    <MapPin class="mt-0.5 h-5 w-5 shrink-0 text-green-600" />
                    <div class="min-w-0"><p class="text-sm font-semibold text-gray-900">Address</p><p class="py-1 text-sm break-words text-gray-600">{{ contact.address }}</p></div>
                </div>
                <div v-if="contact.hours" class="flex gap-3 rounded-2xl border border-gray-200 bg-white p-4">
                    <Clock class="mt-0.5 h-5 w-5 shrink-0 text-green-600" />
                    <div class="min-w-0"><p class="text-sm font-semibold text-gray-900">Open</p><p class="py-1 text-sm break-words text-gray-600">{{ contact.hours }}</p></div>
                </div>
                <a v-if="whatsappUrl" :href="whatsappUrl" target="_blank" rel="noopener" class="flex min-h-12 items-center justify-center gap-2 rounded-full bg-[#0b7a63] px-5 py-3 text-sm font-semibold text-white hover:bg-[#096450] sm:col-span-2">
                    <BrandIcon name="whatsapp" class="h-5 w-5" /> Chat on WhatsApp
                </a>
                <a v-if="facebookUrl" :href="facebookUrl" target="_blank" rel="noopener" class="flex min-h-12 items-center justify-center gap-2 rounded-full bg-[#1877F2] px-5 py-3 text-sm font-semibold text-white hover:bg-[#1468d6]" :class="instagramUrl ? '' : 'sm:col-span-2'">
                    <BrandIcon name="facebook" class="h-5 w-5" /> Follow us on Facebook
                </a>
                <a v-if="instagramUrl" :href="instagramUrl" target="_blank" rel="noopener" class="flex min-h-12 items-center justify-center gap-2 rounded-full bg-gradient-to-r from-[#833ab4] via-[#c13584] to-[#c8325f] px-5 py-3 text-sm font-semibold text-white hover:opacity-90" :class="facebookUrl ? '' : 'sm:col-span-2'">
                    <BrandIcon name="instagram" class="h-5 w-5" /> Follow us on Instagram
                </a>
                <Link :href="routes.trackOrder()" class="flex min-h-12 items-center justify-center gap-2 rounded-full border-2 border-green-600 px-5 py-3 text-sm font-semibold text-green-700 hover:bg-green-50 sm:col-span-2">
                    <Truck class="h-5 w-5" /> Track my order
                </Link>
            </div>

            <div v-if="slug === 'contact'" class="mt-8"><LocationMap heading="Find our shop" /></div>

            <section v-for="s in sections" :key="s.heading" class="mt-8">
                <h2 class="text-lg font-bold text-gray-900">{{ s.heading }}</h2>
                <p v-for="(p, i) in s.paragraphs ?? []" :key="i" class="mt-2 text-sm leading-relaxed text-gray-600 sm:text-base">{{ p }}</p>
                <ul v-if="s.items?.length" class="mt-2 list-disc space-y-1.5 pl-5 text-sm leading-relaxed text-gray-600 sm:text-base">
                    <li v-for="(it, i) in s.items" :key="i">{{ it }}</li>
                </ul>
            </section>

            <nav class="mt-10 flex flex-wrap gap-2 border-t border-gray-200 pt-6" aria-label="More information">
                <Link
                    v-for="[key, label] in others.filter(([k]) => k !== slug)"
                    :key="key"
                    :href="`/${key}`"
                    class="inline-flex min-h-10 items-center rounded-full border border-gray-200 px-4 text-sm font-medium text-gray-600 hover:border-green-300 hover:text-green-700"
                >{{ label }}</Link>
            </nav>
        </div>
    </MainLayout>
</template>
