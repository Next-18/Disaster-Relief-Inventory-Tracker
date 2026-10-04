<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ open: { type: Boolean, default: false } });
const emit = defineEmits(['close']);
const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const currentPath = computed(() => new URL(page.url, window.location.origin).pathname);
const user = computed(() => page.props.auth?.user ?? {});

const links = computed(() => [
    { label: 'Dashboard', href: routes.value.dashboard, icon: 'dashboard' },
    { label: 'Beneficiaries', href: routes.value.beneficiaries, icon: 'people' },
    { label: 'Inventory', href: routes.value.inventory, icon: 'box' },
    { label: 'Packages', href: routes.value.packages, icon: 'package' },
    { label: 'QR Codes', href: routes.value.qrCodes, icon: 'qr' },
    { label: 'Lost QR', href: routes.value.lostQr, icon: 'lost' },
    { label: 'Distribution', href: routes.value.distribution, icon: 'distribution' },
    { label: 'Reports', href: routes.value.reports, icon: 'reports' },
    { label: 'Audit Logs', href: routes.value.auditLogs, icon: 'audit' },
    { label: 'Settings', href: routes.value.settings, icon: 'settings' },
]);

const isActive = (href) => Boolean(href) && currentPath.value === new URL(href, window.location.origin).pathname;
</script>

<template>
    <aside class="sidebar" :class="{ 'sidebar-open': open }" aria-label="Admin navigation">
        <Link :href="routes.dashboard" class="brand" @click="emit('close')">
            <img :src="routes.logo" alt="" class="brand-logo">
            <span>Disaster Relief<br>Inventory Tracker</span>
        </Link>

        <nav class="nav-links" aria-label="Main navigation">
            <Link
                v-for="link in links"
                :key="link.href"
                :href="link.href"
                :class="{ active: isActive(link.href) }"
                :aria-current="isActive(link.href) ? 'page' : undefined"
                @click="emit('close')"
            >
                <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <template v-if="link.icon === 'dashboard'"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></template>
                    <template v-else-if="link.icon === 'people'"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M17 11a3 3 0 1 0-1.3-5.7M17 14c2.8 0 5 2.3 5 5"/></template>
                    <template v-else-if="link.icon === 'box'"><path d="m4 7 8-4 8 4-8 4-8-4ZM4 7v10l8 4 8-4V7M12 11v10"/></template>
                    <template v-else-if="link.icon === 'package'"><path d="M4 10h16v10H4zM3 6h18v4H3zM12 6v14M9 3h6l-3 3-3-3Z"/></template>
                    <template v-else-if="link.icon === 'qr'"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h3v3h-3zM19 14h1v6h-4M14 19h3"/></template>
                    <template v-else-if="link.icon === 'lost'"><circle cx="12" cy="12" r="8"/><path d="m8 8 8 8M9 12h6"/></template>
                    <template v-else-if="link.icon === 'distribution'"><path d="M4 11h16v8H4zM7 11V7h10v4M8 15h8"/></template>
                    <template v-else-if="link.icon === 'reports'"><path d="M4 20V4M4 20h16M8 17v-5M12 17V7M16 17v-8"/></template>
                    <template v-else-if="link.icon === 'audit'"><path d="M7 3h10v18H7zM10 7h4M10 11h4M10 15h4M4 7h3M4 11h3M4 15h3"/></template>
                    <template v-else><circle cx="12" cy="12" r="3"/><path d="M19 12h2M3 12h2M12 3v2M12 19v2M17 7l1-1M6 18l1-1M17 17l1 1M6 6l1 1"/></template>
                </svg>
                <span>{{ link.label }}</span>
            </Link>
        </nav>

        <div class="profile-box">
            <div class="profile-initial">{{ (user.name || 'A').slice(0, 1).toUpperCase() }}</div>
            <div><strong>{{ user.name || 'Administrator' }}</strong><small>{{ user.role || 'Administrator' }}</small></div>
        </div>
    </aside>
</template>
