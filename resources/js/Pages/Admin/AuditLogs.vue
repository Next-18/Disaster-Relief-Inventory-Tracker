<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import InertiaPagination from '../../Components/InertiaPagination.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    auditLogs: { type: Object, required: true },
    modules: { type: Array, required: true },
    actions: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const filters = reactive({
    module: props.filters.module ?? 'all',
    action: props.filters.action ?? 'all',
    start_date: props.filters.start_date ?? '',
    end_date: props.filters.end_date ?? '',
});

watch(() => props.filters, (next) => {
    Object.assign(filters, {
        module: next.module ?? 'all',
        action: next.action ?? 'all',
        start_date: next.start_date ?? '',
        end_date: next.end_date ?? '',
    });
}, { deep: true });

function applyFilters() {
    router.get(routes.value.auditLogs, {
        module: filters.module === 'all' ? undefined : filters.module,
        action: filters.action === 'all' ? undefined : filters.action,
        start_date: filters.start_date || undefined,
        end_date: filters.end_date || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    Object.assign(filters, { module: 'all', action: 'all', start_date: '', end_date: '' });
    applyFilters();
}
</script>

<template>
    <section class="module-heading"><div><h2>Audit Logs</h2><p>View activity recorded across the admin portal.</p></div></section>

    <section class="panel record-panel">
        <div class="panel-heading"><div><h3>Filter Logs</h3><p>Filter audit logs by module, action, or date range</p></div></div>
        <div class="audit-filter-body">
            <form class="audit-filter-form" @submit.prevent="applyFilters">
                <label>Module<select v-model="filters.module"><option value="all">All Modules</option><option v-for="module in modules" :key="module" :value="module">{{ String(module).replaceAll('-', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()) }}</option></select></label>
                <label>Action<select v-model="filters.action"><option value="all">All Actions</option><option v-for="action in actions" :key="action" :value="action">{{ String(action).charAt(0).toUpperCase() + String(action).slice(1) }}</option></select></label>
                <label>Start Date<input v-model="filters.start_date" type="date"></label>
                <label>End Date<input v-model="filters.end_date" type="date"></label>
                <div class="audit-filter-actions"><button class="primary-action" type="submit">Filter</button><button class="cancel-button" type="button" @click="clearFilters">Clear</button></div>
            </form>
        </div>
    </section>

    <section class="panel record-panel">
        <div class="panel-heading"><div><h3>Activity Log</h3><p>{{ auditLogs.total }} total records</p></div></div>
        <div class="table-wrap"><table class="record-table">
            <thead><tr><th>USER</th><th>ACTION</th><th>MODULE</th><th>DESCRIPTION</th><th>IP ADDRESS</th><th>DATE/TIME</th></tr></thead>
            <tbody>
                <tr v-for="log in auditLogs.data" :key="log.id">
                    <td><b>{{ log.user?.name || 'Unknown User' }}</b><small>{{ log.user?.email || 'N/A' }}</small></td>
                    <td><span class="tag neutral">{{ String(log.action).charAt(0).toUpperCase() + String(log.action).slice(1) }}</span></td>
                    <td><span class="tag" :class="log.module === 'delete' ? 'warning' : 'success'">{{ String(log.module).replaceAll('-', ' ') }}</span></td>
                    <td>{{ log.description || '—' }}</td><td><small>{{ log.ip_address || '—' }}</small></td>
                    <td><b>{{ new Date(log.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' }) }}</b><small>{{ new Date(log.created_at).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) }}</small></td>
                </tr>
                <tr v-if="!auditLogs.data.length"><td colspan="6" class="empty-cell">No audit logs found.</td></tr>
            </tbody>
        </table></div>
        <InertiaPagination :links="auditLogs.links" :show="auditLogs.last_page > 1" />
    </section>
</template>

<style scoped>
.audit-filter-body { padding: 20px; }
.audit-filter-form { display: grid; gap: 15px; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); align-items: end; }
.audit-filter-form label { display: grid; gap: 6px; color: #5b6d84; font-size: 11px; font-weight: 700; }
.audit-filter-form input, .audit-filter-form select { width: 100%; height: 38px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; background: #fff; color: #29496f; font: inherit; }
.audit-filter-actions { display: flex; align-items: center; gap: 10px; }
</style>
