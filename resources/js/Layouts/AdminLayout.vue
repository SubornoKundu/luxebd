<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ExternalLink, HelpCircle, Image, LayoutDashboard, LogOut, Menu, MessageSquare, Package, ShoppingBag, Settings, ShieldCheck, Star, Tag, Ticket, Users, X, Layers, Warehouse,
} from '@lucide/vue';
import SiteLogo from '@/Components/SiteLogo.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { routes } from '@/routes';

defineProps({ title: { type: String, default: '' } });

const page = usePage();
const user = computed(() => page.props.auth?.user);
const open = ref(false);

// Lock page scroll while the mobile drawer is open; Esc closes it.
watch(open, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
});

function onKeydown(event) {
    if (event.key === 'Escape') { open.value = false; if (!resetting.value) resetOpen.value = false; if (!saving.value) saveOpen.value = false; }
}

onMounted(() => window.addEventListener('keydown', onKeydown));

// After deleting the last item on a page the list would show "nothing found" with no pager.
// Any paginated list that comes back empty on page 2+ jumps to its real last page instead.
function jumpToLastPageIfEmpty() {
    for (const value of Object.values(page.props)) {
        if (value && typeof value === 'object' && Array.isArray(value.data) && value.data.length === 0 && value.current_page > 1) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', String(Math.max(1, value.last_page || 1)));
            router.visit(url.pathname + url.search, { replace: true, preserveScroll: true });
            return;
        }
    }
}

watch(() => page.props, jumpToLastPageIfEmpty, { immediate: true });
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});

const alerts = computed(() => page.props.adminAlerts ?? {});

const links = computed(() => [
    { href: '/admin', label: 'Dashboard', icon: LayoutDashboard, exact: true },
    { href: '/admin/orders', label: 'Orders', icon: ShoppingBag, badge: alerts.value.pending_orders },
    { href: '/admin/products', label: 'Products', icon: Package },
    { href: '/admin/inventory', label: 'Inventory', icon: Warehouse, badge: alerts.value.low_stock, warn: true },
    { href: '/admin/categories', label: 'Categories', icon: Layers },
    { href: '/admin/brands', label: 'Brands', icon: Tag },
    { href: '/admin/customers', label: 'Customers', icon: Users },
    { href: '/admin/coupons', label: 'Coupons', icon: Ticket },
    { href: '/admin/sliders', label: 'Banners', icon: Image },
    { href: '/admin/reviews', label: 'Reviews', icon: Star },
    { href: '/admin/questions', label: 'Q&A', icon: HelpCircle, badge: alerts.value.open_questions },
    { href: '/admin/admins', label: 'Admins', icon: ShieldCheck },
    { href: '/admin/settings', label: 'Settings', icon: Settings, hidden: !page.props.shop?.settingsAccess },
].filter((link) => !link.hidden));

function active(link) {
    const path = page.url.split('?')[0];
    return link.exact ? path === link.href : path === link.href || path.startsWith(link.href + '/');
}

function logout() {
    router.post(routes.logout());
}
</script>

<template>
    <div class="min-h-dvh bg-gray-100">
        <FlashMessages />

        <div v-if="open" class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="open = false" />

        <aside class="fixed inset-y-0 left-0 z-40 flex w-64 max-w-[85vw] flex-col bg-gray-900 text-gray-300 transition-[transform,visibility] duration-200 lg:visible lg:translate-x-0" :class="open ? 'visible translate-x-0' : 'invisible -translate-x-full'">
            <div class="flex h-16 items-center justify-between px-5">
                <Link href="/admin" class="flex min-w-0 items-center text-white">
                    <SiteLogo size="sm" dark suffix="Admin" />
                </Link>
                <button type="button" class="flex h-9 w-9 items-center justify-center rounded-lg hover:bg-gray-800 lg:hidden" aria-label="Close menu" @click="open = false"><X class="h-5 w-5" /></button>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <Link v-for="link in links" :key="link.href" :href="link.href" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition lg:py-2" :class="active(link) ? 'bg-green-600 text-white' : 'hover:bg-gray-800 hover:text-white'" @click="open = false">
                    <component :is="link.icon" class="h-4 w-4" /> <span class="min-w-0 flex-1 truncate">{{ link.label }}</span>
                    <span v-if="link.badge" class="rounded-full px-1.5 py-0.5 text-[10px] leading-none font-bold" :class="link.warn ? 'bg-amber-400 text-gray-900' : 'bg-rose-500 text-white'">{{ link.badge }}</span>
                </Link>
            </nav>
            <div class="border-t border-gray-800 p-4 text-sm">
                <a href="/" target="_blank" class="mb-3 flex items-center gap-2 text-gray-400 hover:text-white"><ExternalLink class="h-4 w-4" /> View store</a>
                <p class="truncate font-medium text-white">{{ user?.name }}</p>
                <p class="truncate text-xs text-gray-500">{{ user?.email }}</p>
                <button type="button" class="mt-3 flex items-center gap-2 text-gray-400 hover:text-white" @click="logout"><LogOut class="h-4 w-4" /> Log out</button>
            </div>
        </aside>

        <div class="min-w-0 lg:pl-64">
            <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-gray-200 bg-white px-4 sm:px-6">
                <button type="button" class="-ml-2 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg hover:bg-gray-100 lg:hidden" aria-label="Open menu" @click="open = true"><Menu class="h-5 w-5" /></button>
                <h1 class="min-w-0 truncate text-base font-bold text-gray-900 sm:text-lg">{{ title }}</h1>
            </header>
            <main class="p-3 sm:p-6"><slot /></main>
        </div>
    </div>
</template>
