<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminSidebar from '../Components/AdminSidebar.vue';
import AdminTopbar from '../Components/AdminTopbar.vue';

const page = usePage();
const sidebarOpen = ref(false);
const headings = {
    'Admin/Dashboard': ['Dashboard', 'ADMIN PORTAL / OVERVIEW'],
    'Admin/Beneficiaries': ['Beneficiaries', 'ADMIN PORTAL / RECORDS'],
    'Admin/Inventory': ['Inventory', 'ADMIN PORTAL / STOCK CONTROL'],
    'Admin/Distribution': ['Distribution', 'ADMIN PORTAL / RELIEF DISTRIBUTION'],
    'Admin/Search': ['Search results', 'ADMIN PORTAL / SEARCH'],
    'Admin/Packages': ['Relief Packages', 'ADMIN PORTAL / PACKAGES'],
    'Admin/QrCodes': ['QR Codes', 'ADMIN PORTAL / QR CODES'],
    'Admin/LostQr': ['Lost QR', 'ADMIN PORTAL / LOST QR'],
    'Admin/Reports': ['Reports', 'ADMIN PORTAL / REPORTS'],
    'Admin/AuditLogs': ['Audit Logs', 'ADMIN PORTAL / AUDIT LOGS'],
    'Admin/Settings': ['Settings', 'ADMIN PORTAL / SETTINGS'],
    'Admin/Module': ['Admin Portal', 'ADMIN PORTAL'],
};
const heading = computed(() => headings[page.component] ?? ['Admin Portal', 'ADMIN PORTAL']);
const success = computed(() => page.props.flash?.success);
const error = computed(() => page.props.flash?.error);

watch(() => page.url, () => { sidebarOpen.value = false; });
</script>

<template>
    <div class="app-shell">
        <Head :title="`${heading[0]} | Relief Tracker`" />
        <AdminSidebar :open="sidebarOpen" @close="sidebarOpen = false" />
        <button v-if="sidebarOpen" class="sidebar-backdrop" type="button" aria-label="Close navigation" @click="sidebarOpen = false" />
        <main class="content">
            <AdminTopbar :title="heading[0]" :subtitle="heading[1]" @toggle-sidebar="sidebarOpen = !sidebarOpen" />
            <div v-if="success" class="flash-success" role="status">{{ success }}</div>
            <div v-if="error" class="flash-error" role="alert">{{ error }}</div>
            <slot />
        </main>
    </div>
</template>
