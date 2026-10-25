<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import InertiaPagination from '../../Components/InertiaPagination.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    tickets: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const reportForm = useForm({ title: '', type: 'technical', priority: 'normal', description: '' });
const ticketForms = reactive({});
const rows = computed(() => props.tickets.data ?? []);

watch(rows, (tickets) => {
    for (const ticket of tickets) {
        if (!ticketForms[ticket.id]) {
            ticketForms[ticket.id] = useForm({ status: ticket.status, admin_response: ticket.admin_response ?? '' });
        }
    }
}, { immediate: true });

function submitReport() {
    reportForm.post(routes.value.supportStore, {
        preserveScroll: true,
        onSuccess: () => reportForm.reset('title', 'description'),
    });
}

function updateTicket(ticket) {
    ticketForms[ticket.id].patch(routes.value.supportUpdate.replace('__ID__', encodeURIComponent(ticket.id)), {
        preserveScroll: true,
    });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

function statusLabel(status) {
    return ({ open: 'Open', in_progress: 'In progress', resolved: 'Resolved' })[status] ?? status;
}
</script>

<template>
    <section class="module-heading">
        <div><h2>{{ isAdmin ? 'Support inbox' : 'Report a concern' }}</h2><p>{{ isAdmin ? 'Review reports from staff and volunteers and send them an update.' : 'Send a problem or concern to the administrator and follow its progress.' }}</p></div>
    </section>

    <section v-if="!isAdmin" class="panel support-form-panel">
        <div class="support-panel-heading"><div><h3>Submit a report</h3><p>Include what happened and the steps to reproduce it, if it is a system problem.</p></div></div>
        <form class="support-form" @submit.prevent="submitReport">
            <label>Short title<input v-model="reportForm.title" maxlength="160" placeholder="e.g. Unable to record a delivery" required><small v-if="reportForm.errors.title" class="field-error">{{ reportForm.errors.title }}</small></label>
            <div class="form-row">
                <label>Report type<select v-model="reportForm.type" required><option value="technical">Technical help</option><option value="system-bug">System bug</option><option value="operational">Work process concern</option><option value="other">Other</option></select><small v-if="reportForm.errors.type" class="field-error">{{ reportForm.errors.type }}</small></label>
                <label>Urgency<select v-model="reportForm.priority" required><option value="low">Low</option><option value="normal">Normal</option><option value="urgent">Urgent</option></select><small v-if="reportForm.errors.priority" class="field-error">{{ reportForm.errors.priority }}</small></label>
            </div>
            <label>Describe the problem<textarea v-model="reportForm.description" rows="5" minlength="10" maxlength="5000" placeholder="What happened? Which page were you using? What did you expect?" required/><small v-if="reportForm.errors.description" class="field-error">{{ reportForm.errors.description }}</small></label>
            <p class="support-privacy-note">Please don’t include beneficiary passwords, full personal records, or other sensitive information in a report.</p>
            <div class="support-form-actions"><button class="primary-action" type="submit" :disabled="reportForm.processing">{{ reportForm.processing ? 'Sending…' : 'Send to admin' }}</button></div>
        </form>
    </section>

    <section class="panel support-list-panel">
        <div class="support-panel-heading"><div><h3>{{ isAdmin ? 'All reports' : 'My reports' }}</h3><p>{{ tickets.total }} {{ tickets.total === 1 ? 'report' : 'reports' }}</p></div></div>
        <div class="support-ticket-list">
            <article v-for="ticket in rows" :key="ticket.id" class="support-ticket">
                <header class="support-ticket-header">
                    <div><div class="support-title-row"><h4>#{{ ticket.id }} · {{ ticket.title }}</h4><span class="support-status" :class="`status-${ticket.status}`">{{ statusLabel(ticket.status) }}</span></div><p class="support-meta">{{ ticket.reporter?.name ?? ticket.reporter_name }} · {{ ticket.reporter?.role ?? ticket.reporter_role }} · {{ formatDate(ticket.created_at) }}</p></div>
                    <span class="support-priority" :class="`priority-${ticket.priority}`">{{ ticket.priority }} priority</span>
                </header>
                <div class="support-tags"><span>{{ ticket.type.replaceAll('-', ' ') }}</span><span v-if="ticket.handler">Handled by {{ ticket.handler.name }}</span></div>
                <p class="support-description">{{ ticket.description }}</p>
                <div v-if="ticket.admin_response" class="support-response"><strong>Admin response</strong><p>{{ ticket.admin_response }}</p></div>

                <form v-if="isAdmin" class="support-admin-form" @submit.prevent="updateTicket(ticket)">
                    <label>Status<select v-model="ticketForms[ticket.id].status"><option value="open">Open</option><option value="in_progress">In progress</option><option value="resolved">Resolved</option></select><small v-if="ticketForms[ticket.id].errors.status" class="field-error">{{ ticketForms[ticket.id].errors.status }}</small></label>
                    <label>Reply to reporter<textarea v-model="ticketForms[ticket.id].admin_response" rows="3" maxlength="5000" placeholder="Add an update or explain how this was resolved"/><small v-if="ticketForms[ticket.id].errors.admin_response" class="field-error">{{ ticketForms[ticket.id].errors.admin_response }}</small></label>
                    <div class="support-form-actions"><button class="primary-action" type="submit" :disabled="ticketForms[ticket.id].processing">{{ ticketForms[ticket.id].processing ? 'Saving…' : 'Save update' }}</button></div>
                </form>
            </article>
            <div v-if="!rows.length" class="support-empty">{{ isAdmin ? 'No reports have been submitted.' : 'You haven’t submitted any reports yet.' }}</div>
        </div>
        <InertiaPagination :links="tickets.links" :show="tickets.last_page > 1" />
    </section>
</template>

<style scoped>
.support-form-panel,.support-list-panel{padding:22px;margin-bottom:18px}.support-panel-heading{padding-bottom:16px;border-bottom:1px solid #e8eeea}.support-panel-heading h3{margin:0;color:#20362d}.support-panel-heading p{margin:5px 0 0;color:#75817b;font-size:13px}.support-form{display:grid;gap:15px;padding-top:17px}.support-form label,.support-admin-form label{display:grid;gap:6px;color:#53665c;font-size:13px;font-weight:600}.support-form input,.support-form select,.support-form textarea,.support-admin-form select,.support-admin-form textarea{width:100%;padding:10px 12px;border:1px solid #d8e1db;border-radius:8px;background:#fff;color:#24382e;font:inherit;font-weight:400}.support-form textarea,.support-admin-form textarea{resize:vertical}.support-privacy-note{margin:0;color:#75817b;font-size:12px}.support-form-actions{display:flex;justify-content:flex-end}.support-ticket-list{display:grid;gap:12px;padding-top:16px}.support-ticket{padding:16px;border:1px solid #e4ebe6;border-radius:11px}.support-ticket-header,.support-title-row{display:flex;align-items:center;justify-content:space-between;gap:12px}.support-title-row{justify-content:flex-start;flex-wrap:wrap}.support-ticket h4{margin:0;color:#20362d;font-size:15px}.support-meta{margin:5px 0 0;color:#7b8780;font-size:12px}.support-status,.support-priority{padding:5px 9px;border-radius:999px;background:#edf2ef;color:#526159;font-size:11px;font-weight:700;text-transform:capitalize;white-space:nowrap}.status-open{background:#fff3d8;color:#79570b}.status-in_progress{background:#e8f1ff;color:#315b8e}.status-resolved{background:#e7f5ec;color:#26704c}.priority-urgent{background:#ffebe8;color:#a83d2f}.priority-low{background:#f0f3f1;color:#66736b}.support-tags{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.support-tags span{padding:4px 8px;border-radius:6px;background:#f2f5f3;color:#69766f;font-size:11px;text-transform:capitalize}.support-description{margin:13px 0;color:#405149;font-size:14px;line-height:1.55;white-space:pre-wrap}.support-response{padding:12px;border-left:3px solid #347354;border-radius:4px;background:#f1f7f3}.support-response strong{color:#285c43;font-size:12px}.support-response p{margin:5px 0 0;color:#405149;font-size:13px;white-space:pre-wrap}.support-admin-form{display:grid;gap:12px;margin-top:14px;padding-top:14px;border-top:1px solid #e8eeea}.support-empty{padding:34px 15px;text-align:center;color:#7a8780}
@media(max-width:650px){.support-form-panel,.support-list-panel{padding:15px}.support-ticket-header{align-items:flex-start;flex-direction:column}.support-title-row{align-items:flex-start}}
</style>
