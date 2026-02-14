<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const showSidebar = ref(false);
const page = usePage();

const navigation = [
    { name: 'Dashboard', href: 'dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: 'Services', href: 'services.index', icon: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01' },
    { name: 'Invoices', href: 'invoices.index', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { name: 'Tickets', href: 'tickets.index', icon: 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z' },
    { name: 'Profile', href: 'profile.edit', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
];

function isActive(href) {
    const base = href.split('.')[0];
    return route().current(href) || route().current(base + '.*');
}
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Mobile sidebar overlay -->
        <div v-if="showSidebar" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="showSidebar = false" />

        <!-- Sidebar -->
        <aside :class="[
            'fixed inset-y-0 left-0 z-50 w-64 bg-gradient-to-b from-slate-900 to-slate-800 transform transition-transform duration-300 lg:translate-x-0',
            showSidebar ? 'translate-x-0' : '-translate-x-full'
        ]">
            <div class="flex flex-col h-full">
                <!-- Logo -->
                <div class="flex items-center h-16 px-6 border-b border-slate-700">
                    <Link :href="route('dashboard')" class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <span class="text-lg font-bold text-white tracking-tight">Client Portal</span>
                    </Link>
                    <button @click="showSidebar = false" class="lg:hidden ml-auto text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Nav -->
                <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                    <Link
                        v-for="item in navigation"
                        :key="item.name"
                        :href="route(item.href)"
                        :class="[
                            'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200',
                            isActive(item.href)
                                ? 'bg-white/10 text-white shadow-lg shadow-black/10'
                                : 'text-slate-400 hover:bg-white/5 hover:text-slate-200'
                        ]"
                        @click="showSidebar = false"
                    >
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="item.icon" />
                        </svg>
                        {{ item.name }}
                    </Link>
                </nav>

                <!-- User / Logout -->
                <div class="p-3 border-t border-slate-700">
                    <div class="flex items-center gap-3 px-3 py-2 mb-2">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold">
                            {{ page.props.auth.user.name?.charAt(0)?.toUpperCase() || 'U' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ page.props.auth.user.email }}</p>
                        </div>
                    </div>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-sm font-medium text-slate-400 hover:bg-red-500/10 hover:text-red-400 transition-all"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Logout
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <div class="lg:pl-64">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 flex items-center h-16 px-4 bg-white/80 backdrop-blur-lg border-b border-slate-200 lg:px-8">
                <button @click="showSidebar = true" class="p-2 -ml-2 text-slate-500 hover:text-slate-700 lg:hidden">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="flex-1" />
                <slot name="header" />
            </header>

            <!-- Page -->
            <main class="p-4 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
