<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ImagePlus, Trash2, Check } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    settings: Object,
});

const s = props.settings;
const form = useForm({
    site_name: s.site_name ?? '',
    tagline: s.tagline ?? '',
    meta_description: s.meta_description ?? '',
    announcement_text: s.announcement_text ?? '',
    announcement_link: s.announcement_link ?? '',
    footer_text: s.footer_text ?? '',
    phone: s.phone ?? '',
    email: s.email ?? '',
    address: s.address ?? '',
    hours: s.hours ?? '',
    whatsapp: s.whatsapp ?? '',
    facebook: s.facebook ?? '',
    instagram: s.instagram ?? '',
    tiktok: s.tiktok ?? '',
    shipping_fee: s.shipping_fee ?? 0,
    free_shipping_over: s.free_shipping_over ?? 0,
    low_stock_threshold: s.low_stock_threshold ?? 5,
    bkash_number: s.bkash_number ?? '',
    bkash_type: s.bkash_type ?? 'Personal',
    advance_percent: s.advance_percent ?? 0,
    premium_from: s.premium_from ?? 4000,
    map_query: s.map_query ?? '',
    logo: null,
    favicon: null,
    remove_logo: false,
    remove_favicon: false,
});

// Image previews (existing file, or the newly picked one).
const previews = ref({ logo: null, favicon: null });

function pick(field, event) {
    const file = event.target.files?.[0];
    if (!file) return;
    form[field] = file;
    form[`remove_${field}`] = false;
    if (previews.value[field]) URL.revokeObjectURL(previews.value[field]);
    previews.value[field] = URL.createObjectURL(file);
    event.target.value = '';
}

function remove(field) {
    form[field] = null;
    form[`remove_${field}`] = true;
    if (previews.value[field]) URL.revokeObjectURL(previews.value[field]);
    previews.value[field] = null;
}

const shown = (field) => {
    if (form[`remove_${field}`]) return null;
    return previews.value[field] || s[field] || null;
};

onBeforeUnmount(() => Object.values(previews.value).forEach((u) => u && URL.revokeObjectURL(u)));

const saved = ref(false);

function submit() {
    const needsReload = form.isDirty && (
        form.site_name !== s.site_name
        || form.logo || form.favicon || form.remove_logo || form.remove_favicon
        || form.meta_description !== (s.meta_description ?? '')
    );

    form.post('/admin/settings', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            saved.value = true;
            // Title and favicon come from the page shell, so reload once.
            if (needsReload) setTimeout(() => window.location.reload(), 600);
            else setTimeout(() => (saved.value = false), 2500);
        },
    });
}

const input = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 focus:outline-none';
const label = 'mb-1 block text-sm font-medium text-gray-700';
const card = 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6';
const hint = 'mt-1 text-xs text-gray-400';
</script>

<template>
    <Head title="Site settings" />
    <AdminLayout title="Site settings">
        <form class="mx-auto max-w-4xl space-y-5 pb-24" @submit.prevent="submit">
            <!-- General -->
            <section :class="card">
                <h2 class="text-base font-bold text-gray-900">General</h2>
                <p class="mt-0.5 text-sm text-gray-500">Name, logo and icon shown across the store, emails and invoices.</p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label :class="label" for="site_name">Site name</label>
                        <input id="site_name" v-model="form.site_name" placeholder="e.g. Dhaka Bazar" type="text" maxlength="60" required :class="input">
                        <p :class="hint">Shown in the header, footer, browser tab title, emails, invoices and packing slips.</p>
                        <p v-if="form.errors.site_name" class="mt-1 text-xs text-rose-600">{{ form.errors.site_name }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="label" for="tagline">Tagline (footer)</label>
                        <textarea id="tagline" v-model="form.tagline" placeholder="One line about your shop, shown in the footer" rows="2" maxlength="300" :class="input" />
                        <p v-if="form.errors.tagline" class="mt-1 text-xs text-rose-600">{{ form.errors.tagline }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="label" for="meta_description">Search-engine description</label>
                        <textarea id="meta_description" v-model="form.meta_description" placeholder="Short description shown on Google search results" rows="2" maxlength="300" :class="input" />
                        <p v-if="form.errors.meta_description" class="mt-1 text-xs text-rose-600">{{ form.errors.meta_description }}</p>
                    </div>

                    <!-- Logo -->
                    <div>
                        <span :class="label">Logo</span>
                        <div class="flex items-center gap-3">
                            <div class="flex h-16 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-dashed border-gray-300 bg-gray-50 p-1.5">
                                <img v-if="shown('logo')" :src="shown('logo')" alt="Logo preview" class="max-h-full max-w-full object-contain">
                                <span v-else class="text-xs text-gray-400">Default</span>
                            </div>
                            <div class="flex min-w-0 flex-col gap-2">
                                <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <ImagePlus class="h-4 w-4" /> Upload
                                    <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="pick('logo', $event)">
                                </label>
                                <button v-if="shown('logo')" type="button" class="inline-flex min-h-10 items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50" @click="remove('logo')"><Trash2 class="h-4 w-4" /> Remove</button>
                            </div>
                        </div>
                        <p :class="hint">PNG, JPG or WebP, max 2 MB. Any size works (large ones are shrunk). A wide logo with transparent background works best.</p>
                        <p v-if="form.errors.logo" class="mt-1 text-xs text-rose-600">{{ form.errors.logo }}</p>
                    </div>

                    <!-- Favicon -->
                    <div>
                        <span :class="label">Site icon (favicon)</span>
                        <div class="flex items-center gap-3">
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-dashed border-gray-300 bg-gray-50 p-2">
                                <img v-if="shown('favicon')" :src="shown('favicon')" alt="Icon preview" class="max-h-full max-w-full object-contain">
                                <span v-else class="text-xs text-gray-400">None</span>
                            </div>
                            <div class="flex min-w-0 flex-col gap-2">
                                <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <ImagePlus class="h-4 w-4" /> Upload
                                    <input type="file" accept="image/png,image/jpeg,image/webp,.ico" class="sr-only" @change="pick('favicon', $event)">
                                </label>
                                <button v-if="shown('favicon')" type="button" class="inline-flex min-h-10 items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50" @click="remove('favicon')"><Trash2 class="h-4 w-4" /> Remove</button>
                            </div>
                        </div>
                        <p :class="hint">Square PNG (at least 64x64), ICO or WebP, max 1 MB. Shown in the browser tab.</p>
                        <p v-if="form.errors.favicon" class="mt-1 text-xs text-rose-600">{{ form.errors.favicon }}</p>
                    </div>
                </div>
            </section>

            <!-- Appearance -->
            <section :class="card">
                <h2 class="text-base font-bold text-gray-900">Announcement &amp; footer</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="label" for="announcement_text">Announcement bar text</label>
                        <input id="announcement_text" v-model="form.announcement_text" type="text" maxlength="200" placeholder="Eid sale: 20% off everything!" :class="input">
                        <p :class="hint">Leave empty to hide the bar.</p>
                        <p v-if="form.errors.announcement_text" class="mt-1 text-xs text-rose-600">{{ form.errors.announcement_text }}</p>
                    </div>
                    <div>
                        <label :class="label" for="announcement_link">Announcement link (optional)</label>
                        <input id="announcement_link" v-model="form.announcement_link" type="url" placeholder="https://vireluxe.com/products" :class="input">
                        <p v-if="form.errors.announcement_link" class="mt-1 text-xs text-rose-600">{{ form.errors.announcement_link }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="label" for="footer_text">Footer copyright line (optional)</label>
                        <input id="footer_text" v-model="form.footer_text" type="text" maxlength="300" placeholder="© 2026 Vireluxe. All rights reserved." :class="input">
                        <p :class="hint">Leave empty to use "© year Site name. All rights reserved."</p>
                        <p v-if="form.errors.footer_text" class="mt-1 text-xs text-rose-600">{{ form.errors.footer_text }}</p>
                    </div>
                </div>
            </section>

            <!-- Contact -->
            <section :class="card">
                <h2 class="text-base font-bold text-gray-900">Contact &amp; social links</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="label" for="phone">Phone</label>
                        <input id="phone" v-model="form.phone" placeholder="+880 1712-345678" type="tel" :class="input">
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-rose-600">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <label :class="label" for="email">Email</label>
                        <input id="email" v-model="form.email" placeholder="support@yourshop.com" type="email" :class="input">
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="label" for="address">Address</label>
                        <input id="address" v-model="form.address" placeholder="House 12, Road 5, Dhanmondi, Dhaka 1209" type="text" :class="input">
                        <p v-if="form.errors.address" class="mt-1 text-xs text-rose-600">{{ form.errors.address }}</p>
                    </div>
                    <div>
                        <label :class="label" for="hours">Opening hours</label>
                        <input id="hours" v-model="form.hours" placeholder="Sat - Thu, 10:00 AM - 8:00 PM" type="text" :class="input">
                        <p v-if="form.errors.hours" class="mt-1 text-xs text-rose-600">{{ form.errors.hours }}</p>
                    </div>
                    <div>
                        <label :class="label" for="whatsapp">WhatsApp number</label>
                        <input id="whatsapp" v-model="form.whatsapp" placeholder="8801712345678" type="text" inputmode="numeric" :class="input">
                        <p :class="hint">Digits only with country code. Empty hides the WhatsApp buttons.</p>
                        <p v-if="form.errors.whatsapp" class="mt-1 text-xs text-rose-600">{{ form.errors.whatsapp }}</p>
                    </div>
                    <div v-for="n in ['facebook', 'instagram', 'tiktok']" :key="n">
                        <label :class="label" :for="n" >{{ { facebook: 'Facebook', instagram: 'Instagram', tiktok: 'TikTok' }[n] }} page link</label>
                        <input :id="n" v-model="form[n]" type="url" :placeholder="`https://${n}.com/vireluxe`" :class="input">
                        <p v-if="form.errors[n]" class="mt-1 text-xs text-rose-600">{{ form.errors[n] }}</p>
                    </div>
                </div>
            </section>

            <!-- Store -->
            <section :class="card">
                <h2 class="text-base font-bold text-gray-900">Shipping, stock &amp; payments</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label :class="label" for="shipping_fee">Shipping fee</label>
                        <input id="shipping_fee" v-model="form.shipping_fee" placeholder="e.g. 120" type="number" min="0" step="1" inputmode="decimal" required :class="input">
                        <p v-if="form.errors.shipping_fee" class="mt-1 text-xs text-rose-600">{{ form.errors.shipping_fee }}</p>
                    </div>
                    <div>
                        <label :class="label" for="free_shipping_over">Free shipping over</label>
                        <input id="free_shipping_over" v-model="form.free_shipping_over" placeholder="e.g. 5000" type="number" min="0" step="1" inputmode="decimal" required :class="input">
                        <p :class="hint">0 = never free.</p>
                        <p v-if="form.errors.free_shipping_over" class="mt-1 text-xs text-rose-600">{{ form.errors.free_shipping_over }}</p>
                    </div>
                    <div>
                        <label :class="label" for="low_stock_threshold">Low-stock alert at</label>
                        <input id="low_stock_threshold" v-model="form.low_stock_threshold" placeholder="e.g. 5" type="number" min="0" step="1" inputmode="numeric" required :class="input">
                        <p v-if="form.errors.low_stock_threshold" class="mt-1 text-xs text-rose-600">{{ form.errors.low_stock_threshold }}</p>
                    </div>
                    <div>
                        <label :class="label" for="bkash_number">bKash number</label>
                        <input id="bkash_number" v-model="form.bkash_number" placeholder="01712345678" type="text" inputmode="tel" :class="input">
                        <p v-if="form.errors.bkash_number" class="mt-1 text-xs text-rose-600">{{ form.errors.bkash_number }}</p>
                    </div>
                    <div>
                        <label :class="label" for="bkash_type">bKash account type</label>
                        <select id="bkash_type" v-model="form.bkash_type" :class="input">
                            <option value="Personal">Personal (Send Money)</option>
                            <option value="Agent">Agent (Cash Out)</option>
                            <option value="Merchant">Merchant (Payment)</option>
                        </select>
                        <p :class="hint">Shown at checkout so customers send money the right way.</p>
                        <p v-if="form.errors.bkash_type" class="mt-1 text-xs text-rose-600">{{ form.errors.bkash_type }}</p>
                    </div>
                    <div>
                        <label :class="label" for="advance_percent">bKash advance (%)</label>
                        <input id="advance_percent" v-model="form.advance_percent" placeholder="50" type="number" min="0" max="100" step="1" inputmode="numeric" :class="input">
                        <p :class="hint">The customer pays this share by bKash when ordering and the rest in cash to the delivery man. 0 = pay everything by bKash.</p>
                        <p v-if="form.errors.advance_percent" class="mt-1 text-xs text-rose-600">{{ form.errors.advance_percent }}</p>
                    </div>
                </div>
            </section>

            <!-- Home page & map -->
            <section :class="card">
                <h2 class="text-base font-bold text-gray-900">Home page &amp; map</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="label" for="premium_from">Premium perfumes start at (৳)</label>
                        <input id="premium_from" v-model="form.premium_from" placeholder="e.g. 4000" type="number" min="1" step="1" inputmode="numeric" :class="input">
                        <p :class="hint">Perfumes at or above this price show under "Premium", cheaper ones under "Everyday".</p>
                        <p v-if="form.errors.premium_from" class="mt-1 text-xs text-rose-600">{{ form.errors.premium_from }}</p>
                    </div>
                    <div>
                        <label :class="label" for="map_query">Shop location for the map</label>
                        <input id="map_query" v-model="form.map_query" type="text" maxlength="200" placeholder="22.935250,91.336250" :class="input">
                        <p :class="hint">A Google Plus Code plus the area, or an address. Open the "Visit us" map on the site once and check the pin is right.</p>
                        <p v-if="form.errors.map_query" class="mt-1 text-xs text-rose-600">{{ form.errors.map_query }}</p>
                    </div>
                </div>
            </section>

            <!-- Sticky save bar -->
            <div class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur lg:left-64">
                <div class="mx-auto flex max-w-4xl items-center justify-between gap-3">
                    <p class="min-w-0 truncate text-sm" :class="saved ? 'text-green-700' : 'text-gray-400'">
                        <span v-if="saved" class="inline-flex items-center gap-1.5"><Check class="h-4 w-4" /> Saved</span>
                        <span v-else-if="form.hasErrors" class="text-rose-600">Please fix the highlighted fields.</span>
                        <span v-else-if="form.isDirty">Unsaved changes</span>
                    </p>
                    <button type="submit" :disabled="form.processing" class="min-h-11 shrink-0 rounded-lg bg-green-600 px-6 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60">{{ form.processing ? 'Saving...' : 'Save settings' }}</button>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
