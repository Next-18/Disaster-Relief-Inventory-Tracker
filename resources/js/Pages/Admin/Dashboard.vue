<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminModal from '../../Components/AdminModal.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    location: { type: String, required: true },
    today: { type: String, required: true },
    totalBeneficiaries: { type: Number, required: true },
    activeBeneficiaries: { type: Number, required: true },
    newThisMonth: { type: Number, required: true },
    availablePackages: { type: Number, required: true },
    totalPackages: { type: Number, required: true },
    distributedThisMonth: { type: Number, required: true },
    totalDistributions: { type: Number, required: true },
    lowStockItems: { type: Array, required: true },
    stockAlertsCount: { type: Number, required: true },
    recentDistributions: { type: Array, required: true },
    beneficiaries: { type: Array, required: true },
    packages: { type: Array, required: true },
});

const modal = ref(null);
const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const form = useForm({
    beneficiary_id: '',
    package_id: '',
    date_released: props.today,
    status: 'Released',
    notes: '',
});

function openDistributionModal() {
    form.clearErrors();
    modal.value?.showModal();
}

function closeDistributionModal() {
    if (!form.processing) modal.value?.close();
}

function recordDistribution() {
    form.post(routes.value.distributionStore, {
        preserveScroll: true,
        onSuccess: () => {
            modal.value?.close();
            form.reset();
            form.date_released = props.today;
            form.status = 'Released';
        },
    });
}

function initials(name) {
    return (name || '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0] || '').join('').toUpperCase();
}
</script>

<template>
    <section class="welcome">
        <div>
            <h2>Welcome, {{ $page.props.auth?.user?.name?.split(' ')[0] || 'Administrator' }}</h2>
            <p>Here’s the latest relief operation overview for <strong>{{ location }}</strong>.</p>
        </div>
        <button class="add-button" type="button" @click="openDistributionModal"><span>+</span> Record Distribution</button>
    </section>

    <section class="metrics">
        <article class="metric-card blue">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M17 11a3 3 0 1 0-1.3-5.7M17 14c2.8 0 5 2.3 5 5"/></svg></span>
            <div><p>Total Beneficiaries</p><strong>{{ totalBeneficiaries }}</strong><small>+{{ newThisMonth }} registered this month</small></div>
        </article>
        <article class="metric-card green">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><path d="M4 10h16v10H4zM3 6h18v4H3zM12 6v14M9 3h6l-3 3-3-3Z"/></svg></span>
            <div><p>Available Relief Packs</p><strong>{{ availablePackages }}</strong><small>Across {{ totalPackages }} total packages</small></div>
        </article>
        <article class="metric-card orange">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><path d="M4 11h16v8H4zM7 11V7h10v4M8 15h8"/></svg></span>
            <div><p>Distributed This Month</p><strong>{{ distributedThisMonth }}</strong><small>{{ totalBeneficiaries > 0 ? Math.round((distributedThisMonth / totalBeneficiaries) * 1000) / 10 : 0 }}% of registered families</small></div>
        </article>
        <article class="metric-card red">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><path d="M12 3 2.8 20h18.4L12 3ZM12 9v5M12 17h.01"/></svg></span>
            <div><p>Stock Alerts</p><strong>{{ stockAlertsCount }}</strong><small>Items need attention</small></div>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="panel distribution" id="distribution">
            <div class="panel-heading">
                <div><h3>Recent Distribution Records</h3><p>Latest completed relief releases</p></div>
                <Link :href="routes.distribution">View all →</Link>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>BENEFICIARY</th><th>RELIEF PACKAGE</th><th>DATE RELEASED</th><th>STATUS</th></tr></thead>
                    <tbody>
                        <tr v-for="distribution in recentDistributions" :key="distribution.id">
                            <td><i class="avatar" :class="`a${(distribution.id % 4) + 1}`">{{ initials(distribution.beneficiary_name) }}</i><b>{{ distribution.beneficiary_name }}</b><small>{{ distribution.beneficiary_no }}</small></td>
                            <td>{{ distribution.package_name }}</td>
                            <td>{{ distribution.date_released }}</td>
                            <td><em class="status" :class="distribution.status === 'Released' ? 'success' : 'pending'">{{ distribution.status }}</em></td>
                        </tr>
                        <tr v-if="!recentDistributions.length"><td colspan="4" class="empty-cell">No distribution records yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </article>

        <aside class="side-panels">
            <article class="panel stock" id="inventory">
                <div class="panel-heading"><div><h3>Stock Alerts</h3><p>Items at or below minimum stock</p></div><b class="alert-count">{{ stockAlertsCount }}</b></div>
                <div v-for="item in lowStockItems" :key="item.id" class="stock-item">
                    📦
                    <div><b>{{ item.item_name }}</b><small>{{ item.quantity }} {{ item.unit }} remaining</small></div>
                    <em>{{ item.status }}</em>
                </div>
                <div v-if="!lowStockItems.length" class="stock-item"><div><b>No stock alerts</b><small>All items are adequately stocked</small></div></div>
                <Link class="stock-link" :href="routes.inventory">Manage inventory →</Link>
            </article>
            <article class="panel quick">
                <h3>Quick Actions</h3>
                <Link :href="`${routes.beneficiaries}?action=add`">♟ <span>Add beneficiary<small>Create a beneficiary account</small></span>›</Link>
                <Link :href="routes.qrCodes">▦ <span>Scan QR code<small>Verify a relief recipient</small></span>›</Link>
                <Link :href="routes.reports">📊 <span>View reports<small>Distribution and stock reports</small></span>›</Link>
            </article>
        </aside>
    </section>

    <AdminModal ref="modal" class="form-modal" aria-labelledby="distribution-modal-title" @click.self="closeDistributionModal">
        <div class="modal-title">
            <div><h3 id="distribution-modal-title">Record Distribution</h3><p>Record a relief package distribution to a beneficiary.</p></div>
            <button type="button" class="modal-close" aria-label="Close" @click="closeDistributionModal">×</button>
        </div>
        <form @submit.prevent="recordDistribution">
            <label>Beneficiary
                <select v-model="form.beneficiary_id" required :aria-invalid="Boolean(form.errors.beneficiary_id)">
                    <option value="">Select beneficiary</option>
                    <option v-for="beneficiary in beneficiaries" :key="beneficiary.id" :value="beneficiary.id">{{ beneficiary.full_name }} ({{ beneficiary.beneficiary_no }})</option>
                </select>
                <small v-if="form.errors.beneficiary_id" class="field-error">{{ form.errors.beneficiary_id }}</small>
            </label>
            <label>Relief Package
                <select v-model="form.package_id" required :aria-invalid="Boolean(form.errors.package_id)">
                    <option value="">Select package</option>
                    <option v-for="item in packages" :key="item.id" :value="item.id">{{ item.package_name }}</option>
                </select>
                <small v-if="form.errors.package_id" class="field-error">{{ form.errors.package_id }}</small>
            </label>
            <label>Date Released
                <input v-model="form.date_released" type="date" required :aria-invalid="Boolean(form.errors.date_released)">
                <small v-if="form.errors.date_released" class="field-error">{{ form.errors.date_released }}</small>
            </label>
            <label>Status
                <select v-model="form.status" required><option value="Released">Released</option><option value="Pending">Pending</option></select>
            </label>
            <label>Notes<textarea v-model="form.notes" rows="3"/></label>
            <div class="modal-actions">
                <button type="button" class="cancel-button" :disabled="form.processing" @click="closeDistributionModal">Cancel</button>
                <button class="primary-action" type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Record Distribution' }}</button>
            </div>
        </form>
    </AdminModal>
</template>
