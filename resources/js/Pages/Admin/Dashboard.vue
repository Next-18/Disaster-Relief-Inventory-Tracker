<script setup>
import { Link, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminModal from '../../Components/AdminModal.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    location: { type: String, required: true },
    today: { type: String, required: true },
    totalInventoryItems: { type: Number, required: true },
    pendingDistributions: { type: Number, required: true },
    fulfilledDistributions: { type: Number, required: true },
    lowStockItems: { type: Array, required: true },
    inventoryByCategory: { type: Array, required: true },
    stockAlertsCount: { type: Number, required: true },
    recentDistributions: { type: Array, required: true },
    beneficiaries: { type: Array, required: true },
    packages: { type: Array, required: true },
});

const modal = ref(null);
const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
usePoll(30000, {
    only: ['totalInventoryItems', 'stockAlertsCount', 'pendingDistributions', 'fulfilledDistributions', 'lowStockItems', 'inventoryByCategory', 'recentDistributions'],
    preserveScroll: true,
});
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
            <p>Inventory and distribution overview for <strong>{{ location }}</strong>.</p>
        </div>
        <button class="add-button" type="button" @click="openDistributionModal"><span>+</span> Record Distribution</button>
    </section>

    <section class="metrics dashboard-metrics" aria-label="Relief operations summary" aria-live="polite">
        <Link class="metric-card dashboard-metric blue" :href="routes.inventory" aria-label="View inventory records">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><path d="m3 7 9-4 9 4-9 4-9-4Z"/><path d="M3 7v10l9 4 9-4V7M12 11v10M7.5 5 16.5 9"/></svg></span>
            <div class="dashboard-metric-copy"><p>Inventory items</p><strong>{{ Number(totalInventoryItems).toLocaleString() }}</strong><small>Supply types tracked</small></div>
        </Link>
        <Link class="metric-card dashboard-metric red" :href="`${routes.inventory}?stock_status=low`" aria-label="Review low stock items">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><path d="m12 3 9 17H3L12 3Z"/><path d="M12 9v5m0 3h.01"/></svg></span>
            <div class="dashboard-metric-copy"><p>Low stock</p><strong>{{ Number(stockAlertsCount).toLocaleString() }}</strong><small>At or below minimum</small></div>
        </Link>
        <Link class="metric-card dashboard-metric orange" :href="`${routes.distribution}?status=Pending`" aria-label="Review pending distributions">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
            <div class="dashboard-metric-copy"><p>Pending distributions</p><strong>{{ Number(pendingDistributions).toLocaleString() }}</strong><small>Awaiting completion</small></div>
        </Link>
        <Link class="metric-card dashboard-metric green" :href="`${routes.distribution}?status=Released`" aria-label="Review released packages">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg></span>
            <div class="dashboard-metric-copy"><p>Fulfilled</p><strong>{{ Number(fulfilledDistributions).toLocaleString() }}</strong><small>Packages released</small></div>
        </Link>
    </section>

    <section class="dashboard-grid">
        <article class="panel distribution" id="distribution">
            <div class="panel-heading">
                <div><h3>Recent Updates</h3><p>Distribution activity · refreshes every 30 seconds</p></div>
                <Link :href="routes.distribution">View all →</Link>
            </div>
            <div class="table-wrap">
                <table class="dashboard-recent-table">
                    <thead><tr><th>BENEFICIARY</th><th>RELIEF PACKAGE</th><th>DATE RELEASED</th><th>RECORDED BY</th><th>STATUS</th></tr></thead>
                    <tbody>
                        <tr v-for="distribution in recentDistributions" :key="distribution.id">
                            <td data-label="Beneficiary"><i class="avatar" :class="`a${(distribution.id % 4) + 1}`">{{ initials(distribution.beneficiary_name) }}</i><b>{{ distribution.beneficiary_name }}</b><small>{{ distribution.beneficiary_no }}</small></td>
                            <td data-label="Package">{{ distribution.package_name }}</td>
                            <td data-label="Date">{{ distribution.date_released }}</td>
                            <td data-label="Recorded by"><b>{{ distribution.recorded_by }}</b><small>{{ distribution.recorded_at }}</small></td>
                            <td data-label="Status"><em class="status" :class="distribution.status === 'Released' ? 'success' : 'pending'">{{ distribution.status }}</em></td>
                        </tr>
                        <tr v-if="!recentDistributions.length"><td colspan="5" class="empty-cell">No distribution records yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </article>

        <aside class="side-panels">
            <article class="panel stock" id="inventory">
                <div class="panel-heading"><div><h3>Stock Alerts</h3><p>Items at or below minimum stock</p></div><b class="alert-count">{{ stockAlertsCount }}</b></div>
                <div v-for="item in lowStockItems" :key="item.id" class="stock-item">
                    <span class="stock-item-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3 7 9-4 9 4-9 4-9-4Z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/></svg></span>
                    <div><b>{{ item.item_name }}</b><small>{{ item.quantity }} {{ item.unit }} remaining</small></div>
                    <em>{{ item.status }}</em>
                </div>
                <div v-if="!lowStockItems.length" class="stock-item"><div><b>No stock alerts</b><small>All items are adequately stocked</small></div></div>
                <p v-if="stockAlertsCount > lowStockItems.length" class="stock-more">Showing {{ lowStockItems.length }} of {{ stockAlertsCount }} items needing attention.</p>
                <Link class="stock-link" :href="routes.inventory + '?stock_status=low'">Review low-stock items →</Link>
            </article>
            <article class="panel category-stock">
                <div class="panel-heading"><div><h3>Stock by category</h3><p>On hand, grouped by unit</p></div></div>
                <div v-for="stock in inventoryByCategory" :key="`${stock.category}-${stock.unit}`" class="category-stock-row">
                    <div><b>{{ stock.category }}</b><small>{{ stock.item_count }} {{ stock.item_count === 1 ? 'item type' : 'item types' }}</small></div>
                    <strong>{{ Number(stock.quantity).toLocaleString() }} {{ stock.unit }}</strong>
                </div>
                <div v-if="!inventoryByCategory.length" class="category-stock-empty">No inventory recorded yet.</div>
            </article>
            <article class="panel quick">
                <h3>Quick Actions</h3>
                <Link :href="`${routes.beneficiaries}?action=add`"><span class="quick-action-icon" aria-hidden="true">+</span><span>Add beneficiary<small>Create a beneficiary record</small></span>›</Link>
                <Link :href="`${routes.inventory}?action=add`"><span class="quick-action-icon" aria-hidden="true">+</span><span>Add inventory item<small>Track a new relief supply</small></span>›</Link>
                <Link :href="routes.deliveries">↓ <span>Record delivery<small>Add received supplies to stock</small></span>›</Link>
                <Link :href="routes.distribution">→ <span>Manage distributions<small>Review pending and completed</small></span>›</Link>
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
                    <option v-for="item in packages" :key="item.id" :value="item.id">{{ item.package_name }} — {{ item.available_quantity }} packs ready</option>
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
