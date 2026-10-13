<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ShieldCheck, ShieldOff, UserPlus, UserCheck } from '@lucide/vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ admins: Array });

const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
const promoteForm = useForm({ promote_email: '' });

function addAdmin() {
    form.post('/admin/admins', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function promote() {
    promoteForm.post('/admin/admins/promote', {
        preserveScroll: true,
        onSuccess: () => promoteForm.reset(),
    });
}

const removing = ref(null);

function askRemove(admin) {
    removing.value = admin;
}

function remove() {
    const a = removing.value;
    router.delete(`/admin/admins/${a.id}`, {
        preserveScroll: true,
        onFinish: () => { removing.value = null; },
    });
}

const shortDate = (v) => new Date(v).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
const input = 'w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 focus:outline-none';
const label = 'mb-1 block text-sm font-medium text-gray-700';
const card = 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6';
</script>

<template>
    <Head title="Admins" />
    <AdminLayout title="Admins">
        <div class="mx-auto max-w-4xl space-y-5">
            <!-- Current admins -->
            <section :class="card">
                <h2 class="flex items-center gap-2 text-base font-bold text-gray-900"><ShieldCheck class="h-5 w-5 text-green-600" /> Admins ({{ admins.length }})</h2>
                <p class="mt-0.5 text-sm text-gray-500">Everyone here can sign in to this admin panel.</p>

                <ul class="mt-4 divide-y divide-gray-100">
                    <li v-for="a in admins" :key="a.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0 flex-1 basis-48">
                            <p class="flex flex-wrap items-center gap-2 font-medium break-words text-gray-900">
                                {{ a.name }}
                                <span v-if="a.is_me" class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-700">You</span>
                            </p>
                            <p class="text-xs break-all text-gray-500">{{ a.email }}<span v-if="a.phone"> &middot; {{ a.phone }}</span></p>
                            <p class="text-xs text-gray-400">Joined {{ shortDate(a.created_at) }}</p>
                        </div>
                        <button
                            v-if="!a.is_me"
                            type="button"
                            class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-rose-200 px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50"
                            @click="askRemove(a)"
                        >
                            <ShieldOff class="h-4 w-4" /> Remove admin access
                        </button>
                    </li>
                </ul>
            </section>

            <!-- New admin -->
            <section :class="card">
                <h2 class="flex items-center gap-2 text-base font-bold text-gray-900"><UserPlus class="h-5 w-5 text-green-600" /> Add a new admin</h2>
                <p class="mt-0.5 text-sm text-gray-500">Creates a new account that is an admin from the start.</p>

                <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="addAdmin">
                    <div>
                        <label :class="label" for="a_name">Full name</label>
                        <input id="a_name" v-model="form.name" placeholder="Rakib Hossain" type="text" required maxlength="255" autocomplete="off" :class="input">
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label :class="label" for="a_email">Email</label>
                        <input id="a_email" v-model="form.email" placeholder="rakibhossain@gmail.com" type="email" required maxlength="255" autocomplete="off" :class="input">
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="label" for="a_phone">Phone (optional)</label>
                        <input id="a_phone" v-model="form.phone" placeholder="01712345678" type="tel" maxlength="20" autocomplete="off" :class="input">
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-rose-600">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <label :class="label" for="a_pass">Password</label>
                        <PasswordInput id="a_pass" v-model="form.password" placeholder="Minimum 8 characters" required minlength="8" autocomplete="new-password" :class="input" />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p>
                        <p v-else class="mt-1 text-xs text-gray-400">At least 8 characters.</p>
                    </div>
                    <div>
                        <label :class="label" for="a_pass2">Confirm password</label>
                        <PasswordInput id="a_pass2" v-model="form.password_confirmation" placeholder="Type the password again" required minlength="8" autocomplete="new-password" :class="input" />
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" :disabled="form.processing" class="min-h-11 w-full rounded-lg bg-green-600 px-6 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60 sm:w-auto">{{ form.processing ? 'Creating...' : 'Create admin' }}</button>
                    </div>
                </form>
            </section>

            <!-- Promote -->
            <section :class="card">
                <h2 class="flex items-center gap-2 text-base font-bold text-gray-900"><UserCheck class="h-5 w-5 text-green-600" /> Make an existing customer an admin</h2>
                <p class="mt-0.5 text-sm text-gray-500">Type the email of someone who already has an account.</p>

                <form class="mt-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="promote">
                    <div class="min-w-0 flex-1">
                        <label class="sr-only" for="promote_email">Customer email</label>
                        <input id="promote_email" v-model="promoteForm.promote_email" placeholder="karim.uddin@gmail.com" type="email" required autocomplete="off" :class="input">
                        <p v-if="promoteForm.errors.promote_email" class="mt-1 text-xs text-rose-600">{{ promoteForm.errors.promote_email }}</p>
                    </div>
                    <button type="submit" :disabled="promoteForm.processing" class="min-h-11 shrink-0 rounded-lg bg-gray-900 px-6 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60">Make admin</button>
                </form>
            </section>
        </div>

        <!-- Remove confirmation -->
        <div v-if="removing" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center" role="dialog" aria-modal="true" @click.self="removing = null">
            <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
                <h3 class="text-lg font-bold text-gray-900">Remove admin access?</h3>
                <p class="mt-2 text-sm break-words text-gray-600"><strong>{{ removing.name }}</strong> will no longer be able to open the admin panel. Their account stays as a normal customer.</p>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button type="button" class="min-h-11 rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="removing = null">No, keep</button>
                    <button type="button" class="min-h-11 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700" @click="remove">Yes, remove</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
