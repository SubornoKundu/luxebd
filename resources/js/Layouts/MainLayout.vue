<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ChevronDown, Clock, Heart, LayoutDashboard, LogOut, MapPin, Menu, Package, Phone, Search, ShoppingBag, User, X, Mail,
} from '@lucide/vue';
import { routes } from '@/routes';
import FlashMessages from '@/Components/FlashMessages.vue';
import CartDrawer from '@/Components/CartDrawer.vue';
import LiveSearch from '@/Components/LiveSearch.vue';
import SiteLogo from '@/Components/SiteLogo.vue';
import BrandIcon from '@/Components/BrandIcon.vue';
import { openCartDrawer } from '@/Composables/cart';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const cartCount = computed(() => page.props.cartCount ?? 0);
const wishlistCount = computed(() => page.props.wishlistCount ?? 0);
const shop = computed(() => page.props.shop ?? {});
const categories = computed(() => page.props.navCategories ?? []);
const site = computed(() => page.props.site ?? {});
const siteName = computed(() => site.value.name || 'Vireluxe');

const contact = computed(() => shop.value.contact ?? {});
const social = computed(() => Object.entries(shop.value.social ?? {}).filter(([, url]) => url));
// Each network keeps its own brand colour (like the WhatsApp button): white icon on the official colour.
const socialLook = {
    facebook: 'bg-[#1877F2]',
    instagram: 'bg-[radial-gradient(circle_at_30%_107%,#fdf497_0%,#fdf497_5%,#fd5949_45%,#d6249f_60%,#285AEB_90%)]',
    youtube: 'bg-[#FF0000]',
    tiktok: 'bg-black ring-1 ring-white/30',
    x: 'bg-black ring-1 ring-white/30',
};
const socialLabel = { facebook: 'Facebook', instagram: 'Instagram', youtube: 'YouTube', tiktok: 'TikTok', x: 'X' };
const whatsappUrl = computed(() => {
    const number = String(shop.value.whatsapp ?? '').replace(/\D/g, '');
    return number ? `https://wa.me/${number}?text=${encodeURIComponent(`Hi ${siteName.value}, I need some help with an order / product.`)}` : null;
});
const telHref = computed(() => `tel:${String(contact.value.phone ?? '').replace(/[^+\d]/g, '')}`);

const mobileMenuOpen = ref(false);
// The search bar is tucked away behind the magnifier on every screen, so the page starts higher.
const searchOpen = ref(false);
const searchRoot = ref(null);
function toggleSearch() {
    searchOpen.value = !searchOpen.value;
    if (searchOpen.value) nextTick(() => searchRoot.value?.querySelector('input')?.focus());
}
const openCategory = ref(null);      // desktop dropdown
const mobileCategory = ref(null);    // mobile accordion
const accountOpen = ref(false);
const navRoot = ref(null);
const accountRoot = ref(null);

// Close every popup when the page changes.
watch(() => page.url, () => {
    mobileMenuOpen.value = false;
    openCategory.value = null;
    accountOpen.value = false;
    searchOpen.value = false;
});

function toggleCategory(id) {
    openCategory.value = openCategory.value === id ? null : id;
}

// Hover opens the dropdown on mouse devices; touch devices use tap.
function hoverCategory(id) {
    if (window.matchMedia('(hover: hover)').matches) openCategory.value = id;
}

function onOutside(event) {
    if (navRoot.value && !navRoot.value.contains(event.target)) openCategory.value = null;
    if (accountRoot.value && !accountRoot.value.contains(event.target)) accountOpen.value = false;
}

function onKey(event) {
    if (event.key === 'Escape') {
        openCategory.value = null;
        accountOpen.value = false;
    }
}

// A soft shadow under the header once the page has scrolled, so it reads as floating above the content.
const scrolled = ref(false);
function onScroll() {
    scrolled.value = window.scrollY > 8;
}

onMounted(() => {
    document.addEventListener('pointerdown', onOutside);
    window.addEventListener('keydown', onKey);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onOutside);
    window.removeEventListener('keydown', onKey);
    window.removeEventListener('scroll', onScroll);
});

function logout() {
    router.post(routes.logout());
}

const year = new Date().getFullYear();

const quickLinks = computed(() => [
    { label: 'All perfumes', href: routes.productsIndex() },
    ...(shop.value.premiumFrom > 0 ? [
        { label: 'Premium perfumes', href: routes.productsIndex({ min_price: shop.value.premiumFrom }) },
        { label: 'Everyday perfumes', href: routes.productsIndex({ max_price: shop.value.premiumFrom - 1 }) },
    ] : []),
    { label: 'Best sellers', href: routes.productsIndex({ sort: 'popular' }) },
    { label: 'New arrivals', href: routes.productsIndex({ sort: 'latest' }) },
    { label: 'My orders', href: routes.ordersIndex() },
    { label: 'Track an order', href: routes.trackOrder() },
    { label: 'Wishlist', href: routes.wishlistIndex() },
]);

// Help & policy pages (footer).
const helpLinks = [
    { label: 'About us', href: routes.page('about') },
    { label: 'Contact us', href: routes.page('contact') },
    { label: 'Returns & refunds', href: routes.page('returns') },
    { label: 'Privacy policy', href: routes.page('privacy') },
    { label: 'Terms & conditions', href: routes.page('terms') },
];
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-white">
        <a href="#main" class="sr-only z-[200] bg-gold-500 px-4 py-3 text-sm font-semibold text-black focus:not-sr-only focus:fixed focus:top-2 focus:left-2">Skip to content</a>
        <FlashMessages />
        <CartDrawer />

        <div v-if="site.announcement" role="region" aria-label="Announcement" class="bg-green-950 px-4 py-2 text-center text-xs leading-snug font-medium text-gold-300 sm:text-sm">
            <a v-if="site.announcement.link" :href="site.announcement.link" class="block py-1 break-words underline-offset-2 hover:underline">{{ site.announcement.text }}</a>
            <span v-else class="break-words">{{ site.announcement.text }}</span>
        </div>

        <header class="sticky top-0 z-40 border-b border-gold-500/25 bg-black backdrop-blur transition-shadow duration-300" :class="scrolled ? 'shadow-[0_10px_30px_-14px_rgba(0,0,0,0.7)]' : ''">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between gap-3 lg:h-16 lg:gap-10">
                    <Link :href="routes.home()" class="flex min-w-0 shrink items-center" :aria-label="siteName">
                        <SiteLogo size="md" />
                    </Link>

                    <!-- Desktop menu: the shop's categories, straight from the admin panel. -->
                    <nav ref="navRoot" class="hidden min-w-0 flex-1 items-center justify-center gap-x-0.5 text-sm font-medium whitespace-nowrap text-gray-200 xl:flex" aria-label="Categories">
                        <Link :href="routes.productsIndex()" class="nav-link px-2.5 py-2 hover:text-gold-300">All perfumes</Link>
                        <Link :href="routes.home() + '#brands'" class="nav-link px-2.5 py-2 hover:text-gold-300">Brands</Link>
                        <div
                            v-for="category in categories"
                            :key="category.id"
                            class="relative"
                            @mouseenter="category.children?.length && hoverCategory(category.id)"
                            @mouseleave="openCategory = openCategory === category.id ? null : openCategory"
                        >
                            <template v-if="category.children?.length">
                                <button type="button" class="nav-link flex items-center gap-1 px-2.5 py-2 hover:text-gold-300" :class="openCategory === category.id ? 'text-gold-300' : ''" :aria-expanded="openCategory === category.id" @click="toggleCategory(category.id)">
                                    {{ category.name }} <ChevronDown class="h-3.5 w-3.5 transition" :class="openCategory === category.id ? 'rotate-180' : ''" />
                                </button>
                                <div v-if="openCategory === category.id" class="absolute top-full left-0 z-50 min-w-52 border border-gray-200 bg-white p-1.5 shadow-xl">
                                    <Link :href="routes.productsIndex({ category: category.slug })" class="block px-3 py-2 font-semibold text-gray-900 hover:bg-green-50 hover:text-green-700">All {{ category.name }}</Link>
                                    <Link v-for="child in category.children" :key="child.id" :href="routes.productsIndex({ category: child.slug })" class="block px-3 py-2 hover:bg-green-50 hover:text-green-700">{{ child.name }}</Link>
                                </div>
                            </template>
                            <Link v-else :href="routes.productsIndex({ category: category.slug })" class="nav-link block px-2.5 py-2 hover:text-gold-300">{{ category.name }}</Link>
                        </div>
                        <Link v-if="shop.premiumFrom > 0" :href="routes.productsIndex({ min_price: shop.premiumFrom })" class="nav-link px-2.5 py-2 hover:text-gold-300">Premium</Link>
                        <Link v-if="shop.premiumFrom > 0" :href="routes.productsIndex({ max_price: shop.premiumFrom - 1 })" class="nav-link px-2.5 py-2 hover:text-gold-300">Everyday</Link>
                    </nav>

                    <div class="flex items-center gap-0.5 sm:gap-1">
                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full text-gray-200 hover:bg-white/10" :class="searchOpen ? 'bg-white/10 text-gold-300' : ''" :aria-expanded="searchOpen" title="Search" aria-label="Search" @click="toggleSearch"><Search class="h-5 w-5" /></button>

                        <Link :href="routes.wishlistIndex()" class="relative flex h-10 w-10 items-center justify-center rounded-full text-gray-200 hover:bg-white/10" title="Wishlist" aria-label="Wishlist">
                            <Heart class="h-5 w-5" />
                            <span v-if="wishlistCount > 0" class="absolute top-0 right-0 flex h-4 min-w-4 items-center justify-center rounded-full bg-gold-500 px-1 text-[10px] leading-none font-bold text-white">
                                {{ wishlistCount > 99 ? '99+' : wishlistCount }}
                            </span>
                        </Link>

                        <button type="button" class="relative flex h-10 w-10 items-center justify-center rounded-full text-gray-200 hover:bg-white/10" title="Cart" :aria-label="`Open cart, ${cartCount} items`" @click="openCartDrawer">
                            <ShoppingBag class="h-5 w-5" />
                            <span v-if="cartCount > 0" :key="cartCount" class="absolute top-0 right-0 flex h-4 min-w-4 animate-[pop_0.3s_ease-out] items-center justify-center rounded-full bg-gold-500 px-1 text-[10px] leading-none font-bold text-white">
                                {{ cartCount > 99 ? '99+' : cartCount }}
                            </span>
                        </button>

                        <!-- Desktop account -->
                        <div v-if="user" ref="accountRoot" class="relative ml-1 hidden xl:block">
                            <button type="button" class="flex h-10 items-center gap-2 rounded-full border border-white/25 py-1 pr-3 pl-1 hover:bg-white/10" :aria-expanded="accountOpen" @click="accountOpen = !accountOpen">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-green-600 text-sm font-bold text-white uppercase">{{ user.name?.charAt(0) }}</span>
                                <span class="max-w-24 truncate text-sm font-medium text-gray-200">{{ user.name?.split(' ')[0] }}</span>
                                <ChevronDown class="h-4 w-4 text-gray-400" />
                            </button>
                            <div v-if="accountOpen" class="absolute top-full right-0 z-50 mt-2 w-56 border border-gray-200 bg-white p-1.5 shadow-xl">
                                <Link v-if="user.role === 'admin'" :href="routes.admin()" class="flex items-center gap-2.5 px-3 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50"><LayoutDashboard class="h-4 w-4" /> Admin panel</Link>
                                <Link :href="routes.ordersIndex()" class="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><Package class="h-4 w-4" /> My orders</Link>
                                <Link :href="routes.addressesIndex()" class="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><MapPin class="h-4 w-4" /> Address book</Link>
                                <Link :href="routes.wishlistIndex()" class="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><Heart class="h-4 w-4" /> Wishlist</Link>
                                <Link :href="routes.profileEdit()" class="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><User class="h-4 w-4" /> Profile</Link>
                                <button type="button" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50" @click="logout"><LogOut class="h-4 w-4" /> Log out</button>
                            </div>
                        </div>
                        <template v-else>
                            <Link :href="routes.login()" class="hidden shrink-0 px-3 py-2 text-sm font-semibold text-gray-200 hover:text-gold-300 xl:block">Log in</Link>
                            <Link :href="routes.register()" class="hidden shrink-0 rounded-full bg-gold-500 px-4 py-2 text-sm font-semibold text-green-950 hover:bg-gold-400 xl:block">Sign up</Link>
                        </template>

                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full text-gray-200 hover:bg-white/10 xl:hidden" :aria-expanded="mobileMenuOpen" aria-label="Menu" @click="mobileMenuOpen = !mobileMenuOpen">
                            <component :is="mobileMenuOpen ? X : Menu" class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- Search opens under the logo row when the magnifier is tapped (same on phones and computers) -->
                <div ref="searchRoot" class="pb-3" :class="searchOpen ? '' : 'hidden'">
                    <LiveSearch />
                </div>
            </div>

            <!-- Mobile / tablet menu -->
            <div v-if="mobileMenuOpen" class="max-h-[calc(100dvh-4.5rem)] overflow-y-auto overscroll-contain border-t border-gray-100 bg-white xl:hidden">
                <nav class="mx-auto max-w-7xl space-y-1 px-4 py-3 text-sm font-medium text-gray-700 sm:px-6">
                    <Link :href="routes.home()" class="block px-3 py-2.5 hover:bg-gray-100">Home</Link>
                    <Link :href="routes.productsIndex()" class="block px-3 py-2.5 hover:bg-gray-100">All perfumes</Link>
                    <Link :href="routes.home() + '#brands'" class="block px-3 py-2.5 hover:bg-gray-100">Brands</Link>
                    <template v-if="shop.premiumFrom > 0">
                        <Link :href="routes.productsIndex({ min_price: shop.premiumFrom })" class="block px-3 py-2.5 hover:bg-gray-100">Premium perfumes</Link>
                        <Link :href="routes.productsIndex({ max_price: shop.premiumFrom - 1 })" class="block px-3 py-2.5 hover:bg-gray-100">Everyday perfumes</Link>
                    </template>

                    <p v-if="categories.length" class="px-3 pt-3 pb-1 text-xs font-semibold tracking-wide text-gray-400">Shop by collection</p>
                    <div v-for="category in categories" :key="category.id">
                        <div class="flex items-stretch">
                            <Link :href="routes.productsIndex({ category: category.slug })" class="min-w-0 flex-1 px-3 py-2.5 hover:bg-gray-100">{{ category.name }}</Link>
                            <button v-if="category.children?.length" type="button" class="flex w-11 items-center justify-center text-gray-400 hover:bg-gray-100" :aria-expanded="mobileCategory === category.id" :aria-label="`Show ${category.name} sub-categories`" @click="mobileCategory = mobileCategory === category.id ? null : category.id">
                                <ChevronDown class="h-4 w-4 transition" :class="mobileCategory === category.id ? 'rotate-180' : ''" />
                            </button>
                        </div>
                        <div v-if="mobileCategory === category.id" class="mb-1 ml-3 border-l border-gray-200 pl-2">
                            <Link v-for="child in category.children" :key="child.id" :href="routes.productsIndex({ category: child.slug })" class="block px-3 py-2 text-gray-600 hover:bg-gray-100">{{ child.name }}</Link>
                        </div>
                    </div>

                    <p class="px-3 pt-3 pb-1 text-xs font-semibold tracking-wide text-gray-400">Account</p>
                    <template v-if="user">
                        <Link v-if="user.role === 'admin'" :href="routes.admin()" class="block bg-green-900 px-3 py-2.5 text-white">Admin panel</Link>
                        <Link :href="routes.ordersIndex()" class="block px-3 py-2.5 hover:bg-gray-100">My orders</Link>
                        <Link :href="routes.addressesIndex()" class="block px-3 py-2.5 hover:bg-gray-100">Address book</Link>
                        <Link :href="routes.wishlistIndex()" class="block px-3 py-2.5 hover:bg-gray-100">Wishlist</Link>
                        <Link :href="routes.profileEdit()" class="block px-3 py-2.5 hover:bg-gray-100">Profile</Link>
                        <button type="button" class="block w-full px-3 py-2.5 text-left text-rose-600 hover:bg-rose-50" @click="logout">Log out</button>
                    </template>
                    <template v-else>
                        <Link :href="routes.login()" class="block px-3 py-2.5 hover:bg-gray-100">Log in</Link>
                        <Link :href="routes.register()" class="block bg-green-600 px-3 py-2.5 text-center font-semibold text-white">Sign up</Link>
                    </template>
                </nav>
            </div>
        </header>

        <main id="main" tabindex="-1" class="page-in flex-1 focus:outline-none">
            <slot />
        </main>

        <footer class="mt-6 bg-green-950 text-green-100/80 sm:mt-8">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-9 lg:px-8">
                <div class="grid grid-cols-2 gap-x-6 gap-y-7 min-[560px]:gap-8 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr] lg:gap-12">
                    <div class="col-span-2 min-w-0 min-[560px]:col-span-1">
                        <SiteLogo size="lg" dark />
                        <p v-if="site.tagline" class="mt-4 max-w-sm text-sm leading-relaxed break-words text-green-100/70">{{ site.tagline }}</p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <a v-for="[name, url] in social" :key="name" :href="url" target="_blank" rel="noopener" class="flex h-10 w-10 items-center justify-center rounded-full text-white transition hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" :class="socialLook[name] ?? 'bg-green-900'" :aria-label="`${siteName} on ${socialLabel[name] ?? name}`">
                                <BrandIcon :name="name" class="h-5 w-5" />
                            </a>
                        </div>
                    </div>

                    <div class="min-w-0">
                        <h2 class="font-display text-lg text-white">Shop</h2>
                        <ul class="mt-3 space-y-0.5 text-sm">
                            <li v-for="link in quickLinks" :key="link.label"><Link :href="link.href" class="inline-block py-1 hover:text-gold-300">{{ link.label }}</Link></li>
                        </ul>
                    </div>

                    <div class="min-w-0">
                        <h2 class="font-display text-lg text-white">Collections</h2>
                        <ul class="mt-3 space-y-0.5 text-sm">
                            <li v-for="category in categories.slice(0, 7)" :key="category.id"><Link :href="routes.productsIndex({ category: category.slug })" class="inline-block py-1 hover:text-gold-300">{{ category.name }}</Link></li>
                            <li v-if="!categories.length"><Link :href="routes.productsIndex()" class="inline-block py-1 hover:text-gold-300">All perfumes</Link></li>
                        </ul>
                    </div>

                    <div class="col-span-2 min-w-0 min-[560px]:col-span-1">
                        <h2 class="font-display text-lg text-white">Find us</h2>
                        <ul class="mt-3 space-y-3 text-sm">
                            <li v-if="contact.address" class="flex gap-2.5"><MapPin class="mt-0.5 h-4 w-4 shrink-0 text-gold-400" /> <span class="min-w-0 break-words">{{ contact.address }}</span></li>
                            <li v-if="contact.phone" class="flex gap-2.5"><Phone class="mt-0.5 h-4 w-4 shrink-0 text-gold-400" /> <a :href="telHref" class="inline-block py-1.5 hover:text-gold-300">{{ contact.phone }}</a></li>
                            <li v-if="contact.email" class="flex gap-2.5"><Mail class="mt-0.5 h-4 w-4 shrink-0 text-gold-400" /> <a :href="`mailto:${contact.email}`" class="inline-block min-w-0 py-1.5 break-all hover:text-gold-300">{{ contact.email }}</a></li>
                            <li v-if="contact.hours" class="flex gap-2.5"><Clock class="mt-0.5 h-4 w-4 shrink-0 text-gold-400" /> <span>{{ contact.hours }}</span></li>
                            <li v-if="whatsappUrl"><a :href="whatsappUrl" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-[#0b7a63] px-4 py-2 text-sm font-semibold text-white hover:bg-[#096450]"><BrandIcon name="whatsapp" class="h-4 w-4" /> Chat on WhatsApp</a></li>
                        </ul>
                    </div>
                </div>

                <div class="mt-8 flex flex-col flex-wrap items-center justify-between gap-3 border-t border-green-100/15 pt-6 text-center text-xs text-green-100/60 sm:flex-row sm:text-left">
                    <p class="break-words">{{ site.footerText || `© ${year} ${siteName}. All rights reserved.` }}</p>
                    <nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1" aria-label="Help and policies">
                        <Link v-for="link in helpLinks" :key="link.label" :href="link.href" class="inline-block py-2.5 hover:text-gold-300">{{ link.label }}</Link>
                    </nav>
                    <p class="flex items-center gap-2"><span class="rounded-sm bg-[#e2136e] px-1.5 py-0.5 text-[11px] font-bold text-white">bKash</span> We accept bKash payment</p>
                </div>
            </div>
        </footer>

        <!-- Floating WhatsApp button: opens a chat with the shop number (Settings > WhatsApp, or SHOP_WHATSAPP). Hidden when no number is set. -->
        <aside v-if="whatsappUrl" aria-label="Chat">
        <a
            :href="whatsappUrl"
            target="_blank"
            rel="noopener"
            class="fixed right-4 z-40 flex h-12 w-12 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg ring-1 ring-black/10 transition hover:scale-105 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-700 sm:right-6 sm:h-14 sm:w-14"
            :class="page.component === 'Products/Show' ? 'bottom-24 lg:bottom-6' : 'bottom-4 sm:bottom-6'"
            aria-label="Chat with us on WhatsApp"
        >
            <BrandIcon name="whatsapp" class="h-6 w-6 sm:h-7 sm:w-7" />
        </a>
        </aside>
    </div>
</template>
