<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminSidebar from '../Components/AdminSidebar.vue';
import AdminTopbar from '../Components/AdminTopbar.vue';
import { appDialog, resolveAppDialog } from '../composables/useAppDialog';

const page = usePage();
const sidebarOpen = ref(false);
const headings = {
    'Admin/Dashboard': ['Dashboard', 'ADMIN PORTAL / OVERVIEW'],
    'Admin/Beneficiaries': ['Beneficiaries', 'ADMIN PORTAL / RECORDS'],
    'Admin/Inventory': ['Inventory', 'ADMIN PORTAL / STOCK CONTROL'],
    'Admin/Deliveries': ['Deliveries', 'ADMIN PORTAL / DELIVERIES'],
    'Admin/Support': ['Support', 'ADMIN PORTAL / SUPPORT'],
    'Admin/Announcements': ['Announcements', 'ADMIN PORTAL / STAFF UPDATES'],
    'Admin/Distribution': ['Distribution', 'ADMIN PORTAL / RELIEF DISTRIBUTION'],
    'Admin/Search': ['Search results', 'ADMIN PORTAL / SEARCH'],
    'Admin/Packages': ['Relief Packages', 'ADMIN PORTAL / PACKAGES'],
    'Admin/QrCodes': ['QR Codes', 'ADMIN PORTAL / QR CODES'],
    'Admin/LostQr': ['QR Codes', 'ADMIN PORTAL / QR CODES'],
    'Admin/Reports': ['Reports', 'ADMIN PORTAL / REPORTS'],
    'Admin/AuditLogs': ['Audit Logs', 'ADMIN PORTAL / AUDIT LOGS'],
    'Admin/Settings': ['Settings', 'ADMIN PORTAL / SETTINGS'],
    'Admin/Users': ['Users', 'ADMIN PORTAL / USERS'],
    'Admin/Module': ['Admin Portal', 'ADMIN PORTAL'],
};
const heading = computed(() => headings[page.component] ?? ['Admin Portal', 'ADMIN PORTAL']);
const success = computed(() => page.props.flash?.success);
const error = computed(() => page.props.flash?.error);
const staffAnnouncements = computed(() => page.props.staffAnnouncements ?? []);

watch(() => page.url, () => { sidebarOpen.value = false; });
</script>

<template>
    <div class="app-shell">
        <Head :title="`${heading[0]} | Relief Inventory Tracker`" />
        <AdminSidebar :open="sidebarOpen" @close="sidebarOpen = false" />
        <button v-if="sidebarOpen" class="sidebar-backdrop" type="button" aria-label="Close navigation" @click="sidebarOpen = false" />
        <main class="content">
            <AdminTopbar :title="heading[0]" :subtitle="heading[1]" @toggle-sidebar="sidebarOpen = !sidebarOpen" />
            <div v-if="success" class="flash-success" role="status">{{ success }}</div>
            <div v-if="error" class="flash-error" role="alert">{{ error }}</div>
            <section v-if="staffAnnouncements.length" class="staff-announcements" aria-label="Staff announcements">
                <article v-for="announcement in staffAnnouncements" :key="announcement.id" class="staff-announcement">
                    <span class="staff-announcement-icon" aria-hidden="true">!</span>
                    <div><strong>{{ announcement.title }}</strong><p>{{ announcement.body }}</p><small>{{ new Date(announcement.created_at).toLocaleString() }}</small></div>
                </article>
            </section>
            <slot />
        </main>
        <Teleport to="body">
            <div v-if="appDialog.visible" class="app-dialog-backdrop" @click.self="resolveAppDialog(false)" @keydown.esc.window="resolveAppDialog(false)">
                <section class="app-dialog" :class="{ 'app-dialog-danger': appDialog.danger }" role="alertdialog" aria-modal="true" aria-labelledby="app-dialog-title" aria-describedby="app-dialog-message" tabindex="-1">
                    <div class="app-dialog-heading">
                        <span class="app-dialog-icon" :class="appDialog.danger ? 'danger' : appDialog.type" aria-hidden="true">{{ appDialog.danger ? '!' : appDialog.type === 'confirm' ? '?' : 'i' }}</span>
                        <button v-if="appDialog.type === 'confirm'" type="button" class="app-dialog-close" aria-label="Cancel" @click="resolveAppDialog(false)">×</button>
                    </div>
                    <h2 id="app-dialog-title">{{ appDialog.title }}</h2>
                    <p id="app-dialog-message">{{ appDialog.message }}</p>
                    <div class="app-dialog-actions">
                        <button v-if="appDialog.type === 'confirm'" type="button" class="app-dialog-cancel" @click="resolveAppDialog(false)">{{ appDialog.cancelLabel }}</button>
                        <button type="button" class="app-dialog-confirm" :class="{ danger: appDialog.danger }" autofocus @click="resolveAppDialog(true)">{{ appDialog.confirmLabel }}</button>
                    </div>
                </section>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.staff-announcements { display: grid; gap: 10px; margin-bottom: 18px; }
.staff-announcement { display: flex; gap: 12px; padding: 14px 16px; border: 1px solid #f1d48a; border-radius: 12px; background: #fff9e8; color: #604c1f; }
.staff-announcement-icon { display: grid; width: 24px; height: 24px; flex: 0 0 24px; place-items: center; border-radius: 50%; background: #f5e6b6; color: #765500; font-size: 13px; font-weight: 800; }
.staff-announcement strong { display: block; font-size: 14px; }
.staff-announcement p { margin: 4px 0; color: #665a3d; font-size: 13px; line-height: 1.5; white-space: pre-line; }
.staff-announcement small { color: #8a7a56; font-size: 11px; }
</style>
