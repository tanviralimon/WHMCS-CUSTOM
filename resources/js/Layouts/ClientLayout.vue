<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Toast from '@/Components/Toast.vue';

const page = usePage();
const showSidebar = ref(false);
const showUserMenu = ref(false);
const searchQuery = ref('');
const showSearch = ref(false);

const features = computed(() => page.props.features || {});

const navigation = computed(() => {
    const items = [
        {
            name: 'Dashboard',
            href: 'client.dashboard',
            icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />`,
        },
        {
            name: 'Services',
            href: 'client.services.index',
            icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0h.375a2.625 2.625 0 010 5.25H3.375a2.625 2.625 0 010-5.25H3.75" />`,
        },
    ];

    if (features.value.domains) {
        items.push({
            name: 'Domains',
            href: 'client.domains.index',
            icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5a17.92 17.92 0 01-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />`,
            children: [
                { name: 'My Domains', href: 'client.domains.index' },
                { name: 'Search Domains', href: 'client.domains.search' },
                { name: 'Domain Pricing', href: 'client.domains.pricing' },
            ],
        });
    }

    items.push({
        name: 'Billing',
        href: 'client.invoices.index',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />`,
        children: [
            { name: 'Invoices', href: 'client.invoices.index' },
            { name: 'Transactions', href: 'client.billing.transactions' },
            { name: 'Credit / Add Funds', href: 'client.billing.credit' },
            ...(features.value.quotes ? [{ name: 'Quotes', href: 'client.billing.quotes' }] : []),
        ],
    });

    items.push({
        name: 'Support',
        href: 'client.tickets.index',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />`,
        children: [
            { name: 'Tickets', href: 'client.tickets.index' },
            { name: 'Open Ticket', href: 'client.tickets.create' },
            ...(features.value.knowledgebase ? [{ name: 'Knowledge Base', href: 'client.kb.index' }] : []),
            ...(features.value.announcements ? [{ name: 'Announcements', href: 'client.announcements.index' }] : []),
            ...(features.value.downloads ? [{ name: 'Downloads', href: 'client.downloads.index' }] : []),
        ],
    });

    if (features.value.orders) {
        items.push({
            name: 'Order',
            href: 'client.orders.products',
            icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />`,
        });
    }

    items.push({
        name: 'Account',
        href: 'client.account.profile',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />`,
        children: [
            { name: 'Profile', href: 'client.account.profile' },
            { name: 'Contacts', href: 'client.account.contacts' },
            { name: 'Security', href: 'client.account.security' },
        ],
    });

    if (features.value.affiliates) {
        items.push({
            name: 'Affiliates',
            href: 'client.affiliates.dashboard',
            icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />`,
        });
    }

    return items;
});

const expandedMenus = ref({});

function toggleSubmenu(name) {
    expandedMenus.value[name] = !expandedMenus.value[name];
}

function isActive(href) {
    try {
        const base = href.split('.').slice(0, 2).join('.');
        return route().current(href) || route().current(base + '.*');
    } catch {
        return false;
    }
}

function isChildActive(href) {
    try {
        return route().current(href);
    } catch {
        return false;
    }
}

function isGroupActive(item) {
    if (isActive(item.href)) return true;
    if (item.children) {
        return item.children.some(c => isActive(c.href));
    }
    return false;
}

// Close user menu on outside click
function handleClickOutside(e) {
    if (!e.target.closest('.user-menu-area')) {
        showUserMenu.value = false;
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Toast notifications -->
        <Toast />

        <!-- Mobile sidebar overlay -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showSidebar" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden" @click="showSidebar = false" />
        </Transition>

        <!-- Sidebar -->
        <aside :class="[
            'fixed inset-y-0 left-0 z-50 w-[272px] bg-white border-r border-gray-200 transform transition-transform duration-300 ease-in-out lg:translate-x-0 flex flex-col',
            showSidebar ? 'translate-x-0' : '-translate-x-full'
        ]">
            <!-- Logo -->
            <div class="flex items-center h-16 px-5 border-b border-gray-100 flex-shrink-0">
                <Link :href="route('client.dashboard')" class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-200">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <span class="text-[17px] font-bold text-gray-900 tracking-tight">OrcusTech</span>
                </Link>
                <button @click="showSidebar = false" class="lg:hidden ml-auto p-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto scrollbar-thin">
                <template v-for="item in navigation" :key="item.name">
                    <!-- Simple link (no children) -->
                    <Link
                        v-if="!item.children"
                        :href="route(item.href)"
                        :class="[
                            'flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-all duration-150',
                            isActive(item.href)
                                ? 'bg-indigo-50 text-indigo-700'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                        ]"
                        @click="showSidebar = false"
                    >
                        <svg class="w-[18px] h-[18px] flex-shrink-0" :class="isActive(item.href) ? 'text-indigo-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="item.icon" />
                        {{ item.name }}
                    </Link>

                    <!-- Menu with children -->
                    <div v-else>
                        <button
                            @click="toggleSubmenu(item.name)"
                            :class="[
                                'flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-all duration-150 w-full text-left',
                                isGroupActive(item)
                                    ? 'bg-indigo-50 text-indigo-700'
                                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                            ]"
                        >
                            <svg class="w-[18px] h-[18px] flex-shrink-0" :class="isGroupActive(item) ? 'text-indigo-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="item.icon" />
                            <span class="flex-1">{{ item.name }}</span>
                            <svg :class="['w-4 h-4 text-gray-400 transition-transform duration-200', expandedMenus[item.name] || isGroupActive(item) ? 'rotate-90' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <Transition
                            enter-active-class="transition-all duration-200"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-40"
                            leave-active-class="transition-all duration-200"
                            leave-from-class="opacity-100 max-h-40"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <div v-show="expandedMenus[item.name] || isGroupActive(item)" class="overflow-hidden">
                                <div class="ml-[30px] pl-3 border-l border-gray-200 mt-1 space-y-0.5">
                                    <Link
                                        v-for="child in item.children"
                                        :key="child.href"
                                        :href="route(child.href)"
                                        :class="[
                                            'block px-3 py-1.5 rounded-md text-[13px] transition-colors duration-150',
                                            isChildActive(child.href)
                                                ? 'text-indigo-700 font-medium bg-indigo-50/50'
                                                : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50'
                                        ]"
                                        @click="showSidebar = false"
                                    >
                                        {{ child.name }}
                                    </Link>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </template>
            </nav>

            <!-- User section at bottom -->
            <div class="p-3 border-t border-gray-100 flex-shrink-0">
                <div class="user-menu-area relative">
                    <button @click="showUserMenu = !showUserMenu" class="flex items-center gap-3 w-full px-3 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-white text-xs font-bold shadow-sm">
                            {{ page.props.auth.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                        </div>
                        <div class="flex-1 min-w-0 text-left">
                            <p class="text-[13px] font-medium text-gray-900 truncate">{{ page.props.auth.user?.name }}</p>
                            <p class="text-[11px] text-gray-500 truncate">{{ page.props.auth.user?.email }}</p>
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                        </svg>
                    </button>

                    <!-- User dropdown -->
                    <Transition
                        enter-active-class="transition duration-150"
                        enter-from-class="opacity-0 scale-95 -translate-y-1"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition duration-100"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div v-show="showUserMenu" class="absolute bottom-full left-0 right-0 mb-2 bg-white rounded-xl shadow-lg ring-1 ring-gray-200 py-1.5 z-50">
                            <Link :href="route('client.account.profile')" class="flex items-center gap-2 px-4 py-2 text-[13px] text-gray-700 hover:bg-gray-50">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0" /></svg>
                                Profile
                            </Link>
                            <Link :href="route('client.account.security')" class="flex items-center gap-2 px-4 py-2 text-[13px] text-gray-700 hover:bg-gray-50">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                                Security
                            </Link>
                            <hr class="my-1.5 border-gray-100" />
                            <a v-if="page.props.features?.sso" :href="route('client.sso', { destination: 'clientarea:services' })" class="flex items-center gap-2 px-4 py-2 text-[13px] text-amber-700 hover:bg-amber-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                                Switch to Old Panel
                            </a>
                            <hr class="my-1.5 border-gray-100" />
                            <Link :href="route('logout')" method="post" as="button" class="flex items-center gap-2 w-full px-4 py-2 text-[13px] text-red-600 hover:bg-red-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                                Sign out
                            </Link>
                        </div>
                    </Transition>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <div class="lg:pl-[272px]">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 h-16 bg-white/80 backdrop-blur-xl border-b border-gray-200 flex items-center px-4 lg:px-8 gap-4">
                <button @click="showSidebar = true" class="p-2 -ml-2 text-gray-500 hover:text-gray-700 lg:hidden rounded-lg hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <!-- Page title -->
                <div class="flex-1">
                    <slot name="header" />
                </div>

                <!-- Header actions -->
                <div class="flex items-center gap-2">
                    <slot name="actions" />
                </div>
            </header>

            <!-- Page content -->
            <main class="p-4 lg:p-8 max-w-7xl">
                <slot />
            </main>
        </div>
    </div>
</template>
