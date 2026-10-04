<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

defineProps({ title: { type: String, required: true }, subtitle: { type: String, required: true } });
const emit = defineEmits(['toggle-sidebar']);
const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const routes = computed(() => page.props.routeUrls ?? {});
const notifications = computed(() => page.props.notifications ?? []);
const notificationOpen = ref(false);
const quickActionsOpen = ref(false);
const searchTerm = ref(new URLSearchParams(page.url.split('?')[1] ?? '').get('q') ?? '');
const readIds = ref(new Set());
const unreadCount = computed(() => notifications.value.filter((item) => !readIds.value.has(item.id)).length);
const storageKey = computed(() => `relief-tracker:notifications-read:${user.value.id ?? 'guest'}`);

watch(() => page.url, (url) => {
    searchTerm.value = new URLSearchParams(url.split('?')[1] ?? '').get('q') ?? '';
});

function loadReadIds() {
    try {
        const stored = JSON.parse(localStorage.getItem(storageKey.value) || '[]');
        readIds.value = new Set(Array.isArray(stored) ? stored.filter((id) => typeof id === 'string') : []);
    } catch {
        readIds.value = new Set();
    }
}

function saveReadIds() {
    try {
        localStorage.setItem(storageKey.value, JSON.stringify([...readIds.value].slice(-200)));
    } catch {
        // The panel remains usable when browser storage is unavailable.
    }
}

function markRead(id) {
    readIds.value.add(id);
    readIds.value = new Set(readIds.value);
    saveReadIds();
}

function markAllRead() {
    readIds.value = new Set([...readIds.value, ...notifications.value.map((item) => item.id)]);
    saveReadIds();
}

function submitSearch() {
    const term = searchTerm.value.trim();
    if (!term) return;
    router.get(routes.value.search, { q: term }, { preserveState: true, preserveScroll: true });
}

function closeMenus(event) {
    if (!event || !event.target.closest?.('.notification-btn, .notifications-panel')) notificationOpen.value = false;
    if (!event || !event.target.closest?.('.quick-actions-dropdown')) quickActionsOpen.value = false;
}

function onKeydown(event) {
    if (event.key === 'Escape') closeMenus();
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        document.getElementById('global-search')?.focus();
    }
}

async function signOut() {
    const result = window.Swal
        ? await window.Swal.fire({
            title: 'Sign out?',
            text: 'You will need to sign in again to access the admin portal.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sign out',
            cancelButtonText: 'Stay signed in',
            confirmButtonColor: '#2563eb',
        })
        : { isConfirmed: window.confirm('Are you sure you want to sign out?') };

    if (result.isConfirmed) {
        router.post(routes.value.logout);
    }
}

function isSpaUrl(url) {
    const targets = ['dashboard', 'beneficiaries', 'inventory', 'distribution', 'packages', 'qrCodes', 'lostQr', 'reports', 'auditLogs', 'settings', 'search']
        .map((key) => routes.value[key])
        .filter(Boolean)
        .map((target) => new URL(target, window.location.origin).pathname);

    return targets.includes(new URL(url, window.location.origin).pathname);
}

function actionUrl(key) {
    const url = new URL(routes.value[key], window.location.origin);
    url.searchParams.set('action', 'add');
    return url.toString();
}

onMounted(() => {
    loadReadIds();
    document.addEventListener('click', closeMenus);
    document.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', closeMenus);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <header class="topbar">
        <div class="topbar-heading">
            <button class="mobile-menu-toggle" type="button" aria-label="Open navigation" @click="emit('toggle-sidebar')">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div><p>{{ subtitle }}</p><h1>{{ title }}</h1></div>
        </div>

        <div class="top-actions">
            <form class="topbar-search" role="search" @submit.prevent="submitSearch">
                <label class="sr-only" for="global-search">Search all records</label>
                <input id="global-search" v-model="searchTerm" type="search" name="q" placeholder="Search records..." autocomplete="off" required maxlength="100" aria-keyshortcuts="Control+K Meta+K">
                <button class="search-submit" type="submit" aria-label="Search">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </button>
            </form>

            <button class="notification-btn" type="button" aria-label="Notifications" aria-haspopup="true" :aria-expanded="notificationOpen" aria-controls="notifications-panel" @click.stop="notificationOpen = !notificationOpen; quickActionsOpen = false">
                <svg class="notification-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span v-if="unreadCount" class="notification-count">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
            </button>

            <div class="quick-actions-dropdown">
                <button class="quick-actions-btn" type="button" aria-label="Quick actions" aria-haspopup="true" :aria-expanded="quickActionsOpen" aria-controls="quick-actions-menu" @click.stop="quickActionsOpen = !quickActionsOpen; notificationOpen = false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                </button>
                <div v-if="quickActionsOpen" class="quick-actions-menu show" id="quick-actions-menu" aria-label="Quick actions">
                    <Link :href="actionUrl('beneficiaries')" class="quick-action-item" @click="quickActionsOpen = false"><span>Add beneficiary</span></Link>
                    <Link :href="actionUrl('distribution')" class="quick-action-item" @click="quickActionsOpen = false"><span>Record distribution</span></Link>
                    <Link :href="actionUrl('inventory')" class="quick-action-item" @click="quickActionsOpen = false"><span>Add inventory item</span></Link>
                    <div class="quick-action-divider"/>
                    <Link :href="routes.reports" class="quick-action-item" @click="quickActionsOpen = false"><span>View reports</span></Link>
                    <Link :href="routes.settings" class="quick-action-item" @click="quickActionsOpen = false"><span>Settings</span></Link>
                </div>
            </div>

            <div class="user-info" :title="`Signed in as ${user.name || 'Administrator'}`">
                <div class="user-avatar" aria-hidden="true">{{ (user.name || 'A').slice(0, 1).toUpperCase() }}</div>
                <div class="user-details"><span class="user-name">{{ user.name || 'Administrator' }}</span><span class="user-role">{{ user.role || 'Administrator' }}</span></div>
            </div>

            <button class="logout" type="button" aria-label="Sign out" title="Sign out" @click="signOut">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </button>
        </div>

        <section v-if="notificationOpen" class="notifications-panel show" id="notifications-panel" aria-label="Notifications" aria-hidden="false">
            <div class="notifications-header">
                <div><h3>Notifications</h3><p>Recent activity and stock alerts</p></div>
                <button class="mark-read-btn" type="button" :disabled="!unreadCount" @click="markAllRead">Mark all read</button>
            </div>
            <div class="notifications-list">
                <template v-if="notifications.length">
                    <template v-for="item in notifications" :key="item.id">
                        <Link v-if="isSpaUrl(item.url)" class="notification-item" :class="{ unread: !readIds.has(item.id) }" :href="item.url" @click="markRead(item.id); notificationOpen = false">
                            <span class="notification-icon" :class="item.kind" aria-hidden="true">{{ item.kind === 'low-stock' ? '!' : '•' }}</span>
                            <span class="notification-content"><span class="notification-title">{{ item.title }}</span><span class="notification-text">{{ item.message }}</span><time class="notification-time">{{ item.time ? new Date(item.time).toLocaleString() : 'Recently' }}</time></span>
                            <span v-if="!readIds.has(item.id)" class="notification-unread-dot" aria-label="Unread"/>
                        </Link>
                        <a v-else class="notification-item" :class="{ unread: !readIds.has(item.id) }" :href="item.url" @click="markRead(item.id)">
                            <span class="notification-icon" :class="item.kind" aria-hidden="true">{{ item.kind === 'low-stock' ? '!' : '•' }}</span>
                            <span class="notification-content"><span class="notification-title">{{ item.title }}</span><span class="notification-text">{{ item.message }}</span><time class="notification-time">{{ item.time ? new Date(item.time).toLocaleString() : 'Recently' }}</time></span>
                            <span v-if="!readIds.has(item.id)" class="notification-unread-dot" aria-label="Unread"/>
                        </a>
                    </template>
                </template>
                <div v-else class="notifications-empty"><span aria-hidden="true">✓</span><strong>You’re all caught up</strong><p>New stock alerts and activity will appear here.</p></div>
            </div>
        </section>
    </header>
</template>
